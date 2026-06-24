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

  const label = escapeHtml(generateBrandingText(config));
  const url = escapeHtml(config.branding.url);

  return `<a href="${url}" target="_blank" rel="noopener noreferrer" data-geo-seo-branding="codeclinch">${label}</a>`;
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
