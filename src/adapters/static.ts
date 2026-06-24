import type { GeoSeoConfig } from "../types";
import { generateAgentJson } from "../agent";
import { generateAiVisibilityJson } from "../ai-visibility";
import { generateBrandingFooterHtml, isBrandingEnabled } from "../branding";
import { generateLlmsTxt } from "../llms";
import { generateRobotsTxt } from "../robots";
import { generateSitemapXml } from "../sitemap";

export function generateStaticFiles(config: GeoSeoConfig): Record<string, string> {
  const files: Record<string, string> = {
    "llms.txt": generateLlmsTxt(config),
    "ai-visibility.json": `${JSON.stringify(generateAiVisibilityJson(config), null, 2)}\n`,
    ".well-known/agent.json": `${JSON.stringify(generateAgentJson(config), null, 2)}\n`,
    "robots.txt": generateRobotsTxt(config),
    "sitemap.xml": generateSitemapXml(config),
  };

  if (isBrandingEnabled(config) && config.branding.placement === "footer") {
    files["geo-seo-branding.html"] = generateBrandingFooterHtml(config);
  }

  return files;
}
