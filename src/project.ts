import { existsSync, readFileSync } from "node:fs";
import { join } from "node:path";

export type ProjectFramework = "next-app" | "next-pages" | "vite" | "laravel" | "static" | "unknown";

export interface ProjectDetection {
  framework: ProjectFramework;
  root: string;
  appDir?: string;
  srcDir?: string;
  packageManager?: "npm" | "pnpm" | "yarn" | "bun";
  reasons: string[];
}

export function detectProject(root = process.cwd()): ProjectDetection {
  const reasons: string[] = [];
  const packageJson = readJson(join(root, "package.json"));
  const dependencies = {
    ...asObject(packageJson?.dependencies),
    ...asObject(packageJson?.devDependencies),
  };

  const appDir = existsSync(join(root, "src/app")) ? "src/app" : existsSync(join(root, "app")) ? "app" : undefined;
  const pagesDir = existsSync(join(root, "src/pages")) ? "src/pages" : existsSync(join(root, "pages")) ? "pages" : undefined;
  const srcDir = existsSync(join(root, "src")) ? "src" : undefined;

  if (existsSync(join(root, "pnpm-lock.yaml"))) reasons.push("Found pnpm-lock.yaml");
  if (existsSync(join(root, "yarn.lock"))) reasons.push("Found yarn.lock");
  if (existsSync(join(root, "bun.lockb"))) reasons.push("Found bun.lockb");
  if (existsSync(join(root, "package-lock.json"))) reasons.push("Found package-lock.json");

  if ("next" in dependencies || existsSync(join(root, "next.config.js")) || existsSync(join(root, "next.config.mjs")) || existsSync(join(root, "next.config.ts"))) {
    reasons.push("Detected Next.js project");
    if (appDir) {
      reasons.push(`Found App Router directory: ${appDir}`);
      return { framework: "next-app", root, appDir, srcDir, packageManager: detectPackageManager(root), reasons };
    }

    if (pagesDir) {
      reasons.push(`Found Pages Router directory: ${pagesDir}`);
      return { framework: "next-pages", root, appDir: pagesDir, srcDir, packageManager: detectPackageManager(root), reasons };
    }

    return { framework: "next-app", root, appDir: "app", srcDir, packageManager: detectPackageManager(root), reasons };
  }

  if (existsSync(join(root, "artisan")) || hasLaravelDependency(root)) {
    reasons.push("Detected Laravel project");
    return { framework: "laravel", root, srcDir, packageManager: detectPackageManager(root), reasons };
  }

  if ("vite" in dependencies || existsSync(join(root, "vite.config.js")) || existsSync(join(root, "vite.config.ts")) || existsSync(join(root, "vite.config.mjs"))) {
    reasons.push("Detected Vite project");
    return { framework: "vite", root, srcDir, packageManager: detectPackageManager(root), reasons };
  }

  if (existsSync(join(root, "index.html")) || existsSync(join(root, "public/index.html"))) {
    reasons.push("Detected static HTML project");
    return { framework: "static", root, srcDir, packageManager: detectPackageManager(root), reasons };
  }

  return { framework: "unknown", root, srcDir, packageManager: detectPackageManager(root), reasons: ["Could not detect framework"] };
}

export function recommendedInstallSummary(detection: ProjectDetection): string {
  switch (detection.framework) {
    case "next-app":
      return "Create App Router route handlers for llms.txt, ai-visibility.json, .well-known/agent.json, robots.txt, and sitemap.xml.";
    case "next-pages":
      return "Generate public discovery files. App Router route handlers are not available in this project.";
    case "vite":
      return "Generate static discovery files into public/.";
    case "laravel":
      return "Generate public discovery files and a Blade SEO partial.";
    case "static":
      return "Generate static discovery files beside the public assets.";
    default:
      return "Generate static discovery files into public/. Pass --framework to force a specific installer.";
  }
}

function detectPackageManager(root: string): ProjectDetection["packageManager"] {
  if (existsSync(join(root, "pnpm-lock.yaml"))) return "pnpm";
  if (existsSync(join(root, "yarn.lock"))) return "yarn";
  if (existsSync(join(root, "bun.lockb"))) return "bun";
  if (existsSync(join(root, "package-lock.json"))) return "npm";
  return undefined;
}

function hasLaravelDependency(root: string): boolean {
  const composer = readJson(join(root, "composer.json"));
  const dependencies = {
    ...asObject(composer?.require),
    ...asObject(composer?.["require-dev"]),
  };

  return "laravel/framework" in dependencies;
}

function readJson(path: string): Record<string, unknown> | undefined {
  if (!existsSync(path)) return undefined;

  try {
    return JSON.parse(readFileSync(path, "utf8")) as Record<string, unknown>;
  } catch {
    return undefined;
  }
}

function asObject(value: unknown): Record<string, unknown> {
  return value && typeof value === "object" && !Array.isArray(value) ? (value as Record<string, unknown>) : {};
}
