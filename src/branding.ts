import type { GeoSeoConfig } from "./types";

export function isBrandingEnabled(config: GeoSeoConfig): boolean {
  return config.branding.enabled && config.branding.placement !== "none";
}

export function generateBrandingText(config: GeoSeoConfig): string {
  if (!isBrandingEnabled(config)) {
    return "";
  }

  return config.branding.sponsor ? `${config.branding.label} (${config.branding.sponsor})` : config.branding.label;
}

export function generateBrandingHtml(config: GeoSeoConfig): string {
  if (!isBrandingEnabled(config)) {
    return "";
  }

  const url = URL.canParse(config.branding.url) ? new URL(config.branding.url) : undefined;
  if (!url || !["http:", "https:"].includes(url.protocol) || url.username || url.password) return "";

  const label = escapeHtml(generateBrandingText(config));
  return `<a href="${escapeHtml(url.href)}" target="_blank" rel="noopener noreferrer" data-geo-seo-branding="interdeft">${label}</a>`;
}

export function generateBrandingFooterHtml(config: GeoSeoConfig): string {
  const branding = generateBrandingHtml(config);

  if (!branding) {
    return "";
  }

  return `<div class="geo-seo-branding" style="font-size:12px;line-height:1.5;text-align:center;margin:16px 0;color:inherit;opacity:.78">${branding}</div>\n`;
}

function escapeHtml(value: string): string {
  return value
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}
