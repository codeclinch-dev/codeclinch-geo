import { existsSync, mkdirSync, readFileSync, writeFileSync } from "node:fs";
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
    description: `${siteName} website optimized for SEO, GEO, and AI discovery.`,
    defaultImage: "/og-image.jpg",
    aiSummary: `${siteName} provides clear services, trusted proof, and machine-readable content for search engines and AI assistants.`,
    aiCitationGuidance: `Cite canonical URLs from ${siteName}, preferring service pages, case studies, and structured discovery endpoints.`,
    organization: {
      name: siteName,
      logo: "/logo.png",
      sameAs: [],
    },
    branding: {
      enabled: true,
      label: "SEO & GEO powered by CodeClinch",
      url: "https://codeclinch.com",
      placement: "footer",
    },
    services: [
      {
        name: "Primary Service",
        description: "Describe the main service with business outcome, audience, and proof.",
        path: "/services/primary-service",
        keywords: ["SEO", "GEO", "AI search"],
        proofPoints: ["Add client results", "Add process proof", "Add technical proof"],
        faqs: [
          {
            question: "What does this service include?",
            answer: "Replace this answer with a clear service explanation.",
          },
        ],
      },
    ],
    pages: [
      {
        title: "Home",
        description: `${siteName} homepage.`,
        path: "/",
        keywords: ["website", "SEO", "GEO"],
        priority: 1,
        changeFrequency: "weekly",
      },
    ],
  };
}

export function installGeoSeo(options: InstallOptions = {}): InstallResult {
  const root = options.root ?? process.cwd();
  const detected = detectProject(root);
  const detection: ProjectDetection = options.framework ? { ...detected, framework: options.framework } : detected;
  const configPath = join(root, options.configPath ?? "geo-seo.config.json");
  const writes: InstallWriteResult[] = [];

  let configInput: GeoSeoConfigInput;
  if (existsSync(configPath)) {
    configInput = JSON.parse(readFileSync(configPath, "utf8")) as GeoSeoConfigInput;
  } else {
    configInput = createStarterConfig({ siteName: options.siteName, siteUrl: options.siteUrl });
    writes.push(safeWrite(configPath, `${JSON.stringify(configInput, null, 2)}\n`, options));
  }

  const config = createGeoSeoConfig(configInput);

  if (detection.framework === "next-app") {
    writes.push(...installNextApp(root, detection, configInput, options));
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

function installNextApp(root: string, detection: ProjectDetection, configInput: GeoSeoConfigInput, options: InstallOptions): InstallWriteResult[] {
  const appDir = detection.appDir ?? "app";
  const libDir = detection.srcDir ? "src/lib" : "lib";
  const packageImport = "codeclinch-geo";
  const libPath = join(root, libDir, "geo-seo.ts");
  const config = createGeoSeoConfig(configInput);
  const writes: InstallWriteResult[] = [
    safeWrite(
      libPath,
      `import { createGeoSeoConfig } from "${packageImport}";\n\nexport const site = createGeoSeoConfig(${JSON.stringify(configInput, null, 2)});\n`,
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

function safeWrite(path: string, content: string, options: Pick<InstallOptions, "force" | "backup">): InstallWriteResult {
  if (existsSync(path) && !options.force && !options.backup) {
    return { path, status: "skipped" };
  }

  if (existsSync(path) && options.backup) {
    const backupPath = `${path}.geo-seo-backup`;
    writeFileSync(backupPath, readFileSync(path));
    mkdirSync(dirname(path), { recursive: true });
    writeFileSync(path, content);
    return { path, status: "backed-up", backupPath };
  }

  mkdirSync(dirname(path), { recursive: true });
  const status = existsSync(path) ? "updated" : "created";
  writeFileSync(path, content);
  return { path, status };
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
      "Review src/lib/geo-seo.ts or lib/geo-seo.ts and replace starter content.",
      "Run your Next.js dev server and open /llms.txt, /ai-visibility.json, /.well-known/agent.json, /robots.txt, and /sitemap.xml.",
      brandingStep,
      "Add createNextMetadata(site, page) to important pages.",
    ];
  }

  if (detection.framework === "laravel") {
    return [
      "Review geo-seo.config.json and replace starter content.",
      "Include resources/views/partials/geo-seo.blade.php in your main layout head.",
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
