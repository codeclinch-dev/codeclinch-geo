import type { GeoSeoConfig } from "./types";
import { absoluteUrl } from "./url";

export interface RobotsOptions {
  disallow?: string[];
  allow?: string[];
  userAgent?: string;
}

export function generateRobotsTxt(config: GeoSeoConfig, options: RobotsOptions = {}): string {
  const lines = [`User-agent: ${options.userAgent ?? "*"}`];

  for (const path of options.allow ?? []) {
    lines.push(`Allow: ${path}`);
  }

  const disallow = options.disallow ?? [];
  if (disallow.length === 0) {
    lines.push("Disallow:");
  } else {
    for (const path of disallow) {
      lines.push(`Disallow: ${path}`);
    }
  }

  lines.push("");
  lines.push(`Sitemap: ${absoluteUrl(config, "/sitemap.xml")}`);

  return `${lines.join("\n")}\n`;
}
