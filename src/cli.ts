#!/usr/bin/env node
import { mkdirSync, readFileSync, writeFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { auditWebsite } from "./audit";
import { createGeoSeoConfig } from "./config";
import { createStarterConfig, installGeoSeo } from "./install";
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

  writeFileSync(configPath, `${JSON.stringify(config, null, 2)}\n`);
  console.log(`Created ${configPath}`);
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
    force: Boolean(flags.force),
    backup: Boolean(flags.backup),
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
    mkdirSync(dirname(outputPath), { recursive: true });
    writeFileSync(outputPath, content);
    console.log(`Generated ${outputPath}`);
  }
}

function scoreCommand(args: string[]): void {
  const flags = parseFlags(args);
  const config = loadConfig(flags.config ?? DEFAULT_CONFIG);
  const score = calculateGeoSeoScore(config);

  console.log(`GEO/SEO score: ${score.percentage}% (${score.grade})`);

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
    throw new Error("Missing URL. Example: codeclinch-geo audit https://example.com");
  }

  const result = await auditWebsite(url);

  console.log(`Audit: ${result.url}`);
  console.log(`Score: ${result.score}%`);

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

function printHelp(): void {
  console.log(`codeclinch-geo

Commands:
  codeclinch-geo init --site-name "CodeClinch" --site-url https://codeclinch.com
  codeclinch-geo doctor
  codeclinch-geo install --site-name "CodeClinch" --site-url https://codeclinch.com
  codeclinch-geo generate --config geo-seo.config.json --out public
  codeclinch-geo score --config geo-seo.config.json
  codeclinch-geo audit https://example.com

Install flags:
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
