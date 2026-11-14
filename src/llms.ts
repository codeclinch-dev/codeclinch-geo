import type { GeoSeoConfig } from "./types";
import { absoluteUrl, getDiscoverablePages } from "./url";

export function generateLlmsTxt(config: GeoSeoConfig): string {
  const lines = [
    `# ${config.siteName}`,
    "",
    ...(config.aiSummary || config.description ? (config.aiSummary || config.description).split(/\r?\n/).map((line) => `> ${line}`) : []),
    "",
    "## Website",
    link("Sitemap", absoluteUrl(config, "/sitemap.xml")),
    link("AI visibility data", absoluteUrl(config, "/ai-visibility.json")),
    "",
  ];

  const pages = getDiscoverablePages(config);
  const excludedUrls = new Set(config.pages.filter((page) => !pages.includes(page)).map((page) => absoluteUrl(config, page.path)));
  const services = config.services.filter((service) => service.path && !excludedUrls.has(absoluteUrl(config, service.path)));
  if (services.length > 0) {
    lines.push("## Services");
    for (const service of services) {
      lines.push(link(service.name, absoluteUrl(config, service.path), service.description));
    }
    lines.push("");
  }

  if (pages.length > 0) {
    lines.push("## Important Pages");
    for (const page of pages) {
      lines.push(link(page.title, absoluteUrl(config, page.path), page.description));
    }
    lines.push("");
  }

  return `${lines.join("\n").trim()}\n`;
}

function link(name: string, url: string, description?: string): string {
  const label = name.replace(/[\r\n]+/g, " ").replace(/[\\[\]]/g, "\\$&");
  const target = url.replace(/[\s<>]/g, (character) => encodeURIComponent(character));
  return `- [${label}](<${target}>)${description ? `: ${description.replace(/[\r\n]+/g, " ")}` : ""}`;
}
