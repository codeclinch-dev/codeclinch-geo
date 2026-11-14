import { constants, copyFileSync, existsSync, mkdirSync, readdirSync, readFileSync, writeFileSync } from "node:fs";
import { dirname, join, relative } from "node:path";
import { createGeoSeoConfig } from "./config";
import { generateBrandingFooterHtml, isBrandingEnabled } from "./branding";
import { generateLaravelBladeSeo, generateLaravelBrandingBlade } from "./adapters/laravel";
import { generateStaticFiles } from "./adapters/static";
import { detectProject, type ProjectDetection, type ProjectFramework } from "./project";
import type { GeoSeoConfig, GeoSeoConfigInput } from "./types";

export interface InstallOptions {
  root?: string;
  configPath?: string;
  framework?: ProjectFramework;
  siteName?: string;
  siteUrl?: string;
  force?: boolean;
  backup?: boolean;
}

export interface InstallWriteResult {
  path: string;
  status: "created" | "updated" | "skipped" | "backed-up";
  backupPath?: string;
}

export interface InstallResult {
  detection: ProjectDetection;
  config: GeoSeoConfig;
  writes: InstallWriteResult[];
  nextSteps: string[];
}

export function createStarterConfig(input: { siteName?: string; siteUrl?: string } = {}): GeoSeoConfigInput {
  const siteName = input.siteName ?? "Client Website";
  const siteUrl = input.siteUrl ?? "https://example.com";

  return {
    siteName,
    siteUrl: siteUrl as GeoSeoConfigInput["siteUrl"],
    description: "",
    organization: {
      name: siteName,
      sameAs: [],
    },
    branding: {
      enabled: false,
      label: "SEO & GEO powered by Interdeft",
      url: "https://interdeft.com",
      placement: "footer",
    },
    services: [],
    pages: [],
  };
}

export function installGeoSeo(options: InstallOptions = {}): InstallResult {
  const root = options.root ?? process.cwd();
  const detected = detectProject(root);
  const detection: ProjectDetection = options.framework
    ? { ...detected, framework: options.framework, appDir: detected.framework === "next-app" ? detected.appDir : undefined }
    : detected;
  const configPath = join(root, options.configPath ?? "geo-seo.config.json");
  const writes: InstallWriteResult[] = [];

  let configInput: GeoSeoConfigInput;
  if (existsSync(configPath)) {
    configInput = JSON.parse(readFileSync(configPath, "utf8")) as GeoSeoConfigInput;
  } else {
    configInput = createStarterConfig({ siteName: options.siteName, siteUrl: options.siteUrl });
  }
  const config = createGeoSeoConfig(configInput);
  if (!existsSync(configPath)) {
    writes.push(safeWrite(configPath, `${JSON.stringify(configInput, null, 2)}\n`, options));
  }

  if (detection.framework === "next-app") {
    writes.push(...installNextApp(root, detection, configPath, config, options));
  } else if (detection.framework === "laravel") {
    writes.push(...installStaticFiles(root, config, options));
    writes.push(safeWrite(join(root, "resources/views/partials/geo-seo.blade.php"), generateLaravelBladeSeo(config, config.pages[0] ?? { title: config.siteName, description: config.description, path: "/" }), options));
    if (isBrandingEnabled(config) && config.branding.placement === "footer") {
      writes.push(safeWrite(join(root, "resources/views/partials/geo-seo-branding.blade.php"), generateLaravelBrandingBlade(config), options));
    }
  } else {
    writes.push(...installStaticFiles(root, config, options));
  }

  return {
    detection,
    config,
    writes,
    nextSteps: nextStepsFor(detection, config),
  };
}

function installStaticFiles(root: string, config: GeoSeoConfig, options: InstallOptions): InstallWriteResult[] {
  return Object.entries(generateStaticFiles(config)).map(([filename, content]) => safeWrite(join(root, "public", filename), content, options));
}

function installNextApp(root: string, detection: ProjectDetection, configPath: string, config: GeoSeoConfig, options: InstallOptions): InstallWriteResult[] {
  const appDir = detection.appDir ?? (detection.srcDir ? "src/app" : "app");
  const libDir = detection.srcDir ? "src/lib" : "lib";
  const packageImport = "interdeft-geo";
  const libPath = join(root, libDir, "geo-seo.ts");
  const writes: InstallWriteResult[] = [
    safeWrite(
      libPath,
      `import { createGeoSeoConfig, type GeoSeoConfigInput } from "${packageImport}";\nimport config from "${toRelativeImport(dirname(libPath), configPath)}";\n\nexport const site = createGeoSeoConfig(config as GeoSeoConfigInput);\n`,
      options,
    ),
  ];

  if (isBrandingEnabled(config) && config.branding.placement === "footer") {
    writes.push(
      safeWrite(
        join(root, detection.srcDir ? "src/components/GeoSeoBranding.tsx" : "components/GeoSeoBranding.tsx"),
        `import { generateBrandingFooterHtml } from "${packageImport}";\nimport { site } from "../lib/geo-seo";\n\nexport function GeoSeoBranding() {\n  return <div dangerouslySetInnerHTML={{ __html: generateBrandingFooterHtml(site) }} />;\n}\n`,
        options,
      ),
    );
  }

  const routeSpecs = [
    { path: "llms.txt/route.ts", helper: "createNextLlmsTxtRoute" },
    { path: "ai-visibility.json/route.ts", helper: "createNextAiVisibilityRoute" },
    { path: ".well-known/agent.json/route.ts", helper: "createNextAgentRoute" },
    { path: "robots.txt/route.ts", helper: "createNextRobotsRoute" },
    { path: "sitemap.xml/route.ts", helper: "createNextSitemapRoute" },
  ];

  for (const route of routeSpecs) {
    const routePath = join(root, appDir, route.path);
    const owner = findNextRouteOwner(root, appDir, route.path.split("/route.ts")[0]!, routePath);
    if (owner) {
      writes.push({ path: owner, status: "skipped" });
      continue;
    }
    const importPath = toRelativeImport(dirname(routePath), libPath);
    writes.push(
      safeWrite(
        routePath,
        `import { ${route.helper} } from "${packageImport}";\nimport { site } from "${importPath}";\n\nexport const GET = ${route.helper}(site);\n`,
        options,
      ),
    );
  }

  return writes;
}

function findNextRouteOwner(root: string, appDir: string, endpoint: string, target: string): string | undefined {
  const publicFile = join(root, "public", endpoint);
  if (existsSync(publicFile)) return publicFile;

  function findInApp(directory: string, segments: string[]): string | undefined {
    if (!existsSync(directory)) return;
    for (const entry of readdirSync(directory, { withFileTypes: true })) {
      const candidate = join(directory, entry.name);
      if (entry.isDirectory() && /^\(.+\)$/.test(entry.name)) {
        const owner = findInApp(candidate, segments);
        if (owner) return owner;
      } else if (entry.isDirectory() && entry.name === segments[0]) {
        const owner = findInApp(candidate, segments.slice(1));
        if (owner) return owner;
      } else if (!entry.isDirectory() && candidate !== target) {
        if (segments.length === 0 && /^(?:route|page)\.(?:ts|tsx|js|jsx|mdx)$/.test(entry.name)) return candidate;
        const metadataName = { "robots.txt": "robots", "sitemap.xml": "sitemap" }[endpoint];
        if (segments.length === 1 && metadataName && (
          entry.name === endpoint || /\.(?:ts|tsx|js|jsx)$/.test(entry.name) && entry.name.replace(/\.[^.]+$/, "") === metadataName
        )) return candidate;
      }
    }
  }

  return findInApp(join(root, appDir), endpoint.split("/"));
}

export function safeWrite(path: string, content: string, options: Pick<InstallOptions, "force" | "backup">): InstallWriteResult {
  if (existsSync(path) && !options.force && !options.backup) {
    return { path, status: "skipped" };
  }

  if (existsSync(path) && options.backup) {
    let backupPath = `${path}.geo-seo-backup`;
    for (let index = 1; ; index++) {
      try {
        copyFileSync(path, backupPath, constants.COPYFILE_EXCL);
        break;
      } catch (error) {
        if ((error as NodeJS.ErrnoException).code !== "EEXIST") throw error;
        backupPath = `${path}.geo-seo-backup.${index}`;
      }
    }
    writeFileSync(path, content);
    return { path, status: "backed-up", backupPath };
  }

  mkdirSync(dirname(path), { recursive: true });
  if (options.force || options.backup) {
    const status = existsSync(path) ? "updated" : "created";
    writeFileSync(path, content);
    return { path, status };
  }
  try {
    writeFileSync(path, content, { flag: "wx" });
    return { path, status: "created" };
  } catch (error) {
    if ((error as NodeJS.ErrnoException).code !== "EEXIST") throw error;
    return { path, status: "skipped" };
  }
}

function toRelativeImport(fromDir: string, toFile: string): string {
  const withoutExtension = relative(fromDir, toFile).replace(/\\/g, "/").replace(/\.ts$/, "");
  return withoutExtension.startsWith(".") ? withoutExtension : `./${withoutExtension}`;
}

function nextStepsFor(detection: ProjectDetection, config: GeoSeoConfig): string[] {
  const brandingStep =
    isBrandingEnabled(config) && config.branding.placement === "footer"
      ? "Place the generated branding snippet/component in your footer, or set branding.enabled to false for white-label installs."
      : "Branding is disabled for this install.";

  if (detection.framework === "next-app") {
    return [
      "Add your site details and canonical URLs to the JSON config.",
      "Run your Next.js dev server and open /llms.txt, /ai-visibility.json, /.well-known/agent.json, /robots.txt, and /sitemap.xml.",
      brandingStep,
      "Add createNextMetadata(site, page) to important pages.",
    ];
  }

  if (detection.framework === "laravel") {
    return [
      "Review geo-seo.config.json and replace starter content.",
      "Include resources/views/partials/geo-seo.blade.php in the matching page's head.",
      brandingStep,
      "Open /llms.txt, /ai-visibility.json, /.well-known/agent.json, /robots.txt, and /sitemap.xml.",
    ];
  }

  return [
    "Review geo-seo.config.json and replace starter content.",
    "Upload or serve the generated public discovery files.",
    brandingStep,
    "Add generated SEO metadata helpers to your page templates when needed.",
  ];
}
