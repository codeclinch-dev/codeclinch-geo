#!/usr/bin/env node
import { readFileSync } from "node:fs";
import { join } from "node:path";
import { auditWebsite } from "./audit";
import { createGeoSeoConfig } from "./config";
import { createStarterConfig, installGeoSeo, safeWrite } from "./install";
import { detectProject, recommendedInstallSummary, type ProjectFramework } from "./project";
import { calculateGeoSeoScore } from "./score";
import { generateStaticFiles } from "./adapters/static";
import type { GeoSeoConfigInput } from "./types";

const DEFAULT_CONFIG = "geo-seo.config.json";

async function main(): Promise<void> {
  const [command, ...args] = process.argv.slice(2);

  if (!command || command === "help" || command === "--help" || command === "-h") {
    printHelp();
    return;
  }

  if (command === "init") {
    initCommand(args);
    return;
  }

  if (command === "doctor") {
    doctorCommand(args);
    return;
  }

  if (command === "install") {
    installCommand(args);
    return;
  }

  if (command === "generate") {
    generateCommand(args);
    return;
  }

  if (command === "score") {
    scoreCommand(args);
    return;
  }

  if (command === "audit") {
    await auditCommand(args);
    return;
  }

  throw new Error(`Unknown command: ${command}`);
}

function initCommand(args: string[]): void {
  const flags = parseFlags(args);
  const configPath = flags.config ?? DEFAULT_CONFIG;
  const siteName = flags["site-name"] ?? "Client Website";
  const siteUrl = flags["site-url"] ?? "https://example.com";
  const config = createStarterConfig({ siteName, siteUrl });
  createGeoSeoConfig(config);

  const result = safeWrite(configPath, `${JSON.stringify(config, null, 2)}\n`, writeOptions(flags));
  console.log(`${result.status.toUpperCase()} ${result.path}`);
}

function doctorCommand(args: string[]): void {
  const flags = parseFlags(args);
  const root = flags.cwd ?? process.cwd();
  const detection = detectProject(root);

  console.log(`Project root: ${detection.root}`);
  console.log(`Detected framework: ${detection.framework}`);
  if (detection.packageManager) {
    console.log(`Package manager: ${detection.packageManager}`);
  }
  console.log(`Recommended setup: ${recommendedInstallSummary(detection)}`);

  for (const reason of detection.reasons) {
    console.log(`- ${reason}`);
  }
}

function installCommand(args: string[]): void {
  const flags = parseFlags(args);
  const result = installGeoSeo({
    root: flags.cwd ?? process.cwd(),
    configPath: flags.config ?? DEFAULT_CONFIG,
    framework: flags.framework as ProjectFramework | undefined,
    siteName: flags["site-name"],
    siteUrl: flags["site-url"],
    ...writeOptions(flags),
  });

  console.log(`Detected framework: ${result.detection.framework}`);

  for (const write of result.writes) {
    const suffix = write.backupPath ? ` (backup: ${write.backupPath})` : "";
    console.log(`${write.status.toUpperCase()} ${write.path}${suffix}`);
  }

  console.log("Next steps:");
  for (const step of result.nextSteps) {
    console.log(`- ${step}`);
  }
}

function generateCommand(args: string[]): void {
  const flags = parseFlags(args);
  const config = loadConfig(flags.config ?? DEFAULT_CONFIG);
  const outDir = flags.out ?? "public";
  const files = generateStaticFiles(config);

  for (const [filename, content] of Object.entries(files)) {
    const outputPath = join(outDir, filename);
    const result = safeWrite(outputPath, content, writeOptions(flags));
    console.log(`${result.status.toUpperCase()} ${result.path}`);
  }
}

function scoreCommand(args: string[]): void {
  const flags = parseFlags(args);
  const config = loadConfig(flags.config ?? DEFAULT_CONFIG);
  const score = calculateGeoSeoScore(config);

  console.log(`Setup checklist: ${score.percentage}% (${score.grade})`);

  for (const item of score.items) {
    const status = item.passed ? "PASS" : "FIX";
    console.log(`${status} ${item.label} (${item.points}/${item.maxPoints})`);
    if (!item.passed && item.recommendation) {
      console.log(`  ${item.recommendation}`);
    }
  }
}

async function auditCommand(args: string[]): Promise<void> {
  const [url] = args.filter((arg) => !arg.startsWith("--"));

  if (!url) {
    throw new Error("Missing URL. Example: interdeft-geo audit https://example.com");
  }

  const result = await auditWebsite(url);

  console.log(`Audit: ${result.url}`);
  console.log(`Technical checks passed: ${result.score}%`);

  for (const [name, endpoint] of Object.entries(result.endpoints)) {
    console.log(`- ${name}: ${endpoint.valid ? "valid" : "unavailable or invalid"} (${endpoint.status ?? "no response"}) ${endpoint.url}`);
  }

  if (result.issues.length === 0) {
    console.log("No issues found.");
    return;
  }

  for (const issue of result.issues) {
    console.log(`${issue.severity.toUpperCase()} ${issue.message}`);
    console.log(`  ${issue.recommendation}`);
  }
}

function loadConfig(configPath: string) {
  const raw = readFileSync(configPath, "utf8");
  return createGeoSeoConfig(JSON.parse(raw) as GeoSeoConfigInput);
}

function parseFlags(args: string[]): Record<string, string | undefined> {
  const flags: Record<string, string | undefined> = {};

  for (let index = 0; index < args.length; index += 1) {
    const arg = args[index];
    if (!arg?.startsWith("--")) continue;

    const key = arg.slice(2);
    const next = args[index + 1];
    flags[key] = next && !next.startsWith("--") ? next : "true";
  }

  return flags;
}

function writeOptions(flags: Record<string, string | undefined>) {
  return { force: flags.force === "true", backup: flags.backup === "true" };
}

function printHelp(): void {
  console.log(`interdeft-geo

Commands:
  interdeft-geo init --site-name "Interdeft" --site-url https://interdeft.com
  interdeft-geo doctor
  interdeft-geo install --site-name "Interdeft" --site-url https://interdeft.com
  interdeft-geo generate --config geo-seo.config.json --out public
  interdeft-geo score --config geo-seo.config.json
  interdeft-geo audit https://example.com

Write flags (init, install, generate):
  --framework next-app|next-pages|vite|laravel|static
  --force       overwrite existing files
  --backup      backup existing files before overwrite
`);
}

main().catch((error: unknown) => {
  const message = error instanceof Error ? error.message : String(error);
  console.error(message);
  process.exitCode = 1;
});
