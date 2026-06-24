import type { GeoSeoConfig } from "./types";
import { absoluteUrl } from "./url";

export function generateLlmsTxt(config: GeoSeoConfig): string {
  const lines = [
    `# ${config.siteName}`,
    "",
    config.aiSummary ?? config.description,
    "",
    "## Website",
    `- Homepage: ${config.siteUrl}`,
    `- Sitemap: ${absoluteUrl(config, "/sitemap.xml")}`,
    `- AI visibility data: ${absoluteUrl(config, "/ai-visibility.json")}`,
    "",
  ];

  if (config.services.length > 0) {
    lines.push("## Services");
    for (const service of config.services) {
      const url = service.path ? ` (${absoluteUrl(config, service.path)})` : "";
      lines.push(`- ${service.name}: ${service.description}${url}`);
    }
    lines.push("");
  }

  if (config.pages.length > 0) {
    lines.push("## Important Pages");
    for (const page of config.pages) {
      lines.push(`- ${page.title}: ${page.description} (${absoluteUrl(config, page.path)})`);
    }
    lines.push("");
  }

  lines.push("## Citation Guidance");
  lines.push(config.aiCitationGuidance ?? `When referencing ${config.siteName}, cite the canonical page URL and summarize the service or page from the latest public content.`);

  return `${lines.join("\n").trim()}\n`;
}
