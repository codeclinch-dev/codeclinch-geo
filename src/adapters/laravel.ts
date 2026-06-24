import type { GeoSeoConfig, MetadataInput } from "../types";
import { generateBrandingFooterHtml } from "../branding";
import { generateMetadata } from "../metadata";
import { generateOrganizationSchema, stringifyJsonLd } from "../schema";

export function generateLaravelBladeSeo(config: GeoSeoConfig, page: MetadataInput): string {
  const metadata = generateMetadata(config, page);
  const schema = stringifyJsonLd(generateOrganizationSchema(config));
  const image = metadata.openGraph.images?.[0]?.url;

  return [
    `<title>${escapeHtml(metadata.title)}</title>`,
    `<meta name="description" content="${escapeHtml(metadata.description)}">`,
    `<link rel="canonical" href="${escapeHtml(metadata.alternates.canonical)}">`,
    `<meta property="og:title" content="${escapeHtml(metadata.openGraph.title)}">`,
    `<meta property="og:description" content="${escapeHtml(metadata.openGraph.description)}">`,
    `<meta property="og:url" content="${escapeHtml(metadata.openGraph.url)}">`,
    `<meta property="og:site_name" content="${escapeHtml(metadata.openGraph.siteName)}">`,
    image ? `<meta property="og:image" content="${escapeHtml(image)}">` : undefined,
    `<meta name="twitter:card" content="${metadata.twitter.card}">`,
    `<meta name="twitter:title" content="${escapeHtml(metadata.twitter.title)}">`,
    `<meta name="twitter:description" content="${escapeHtml(metadata.twitter.description)}">`,
    `<script type="application/ld+json">${schema.replace(/</g, "\\u003c")}</script>`,
  ]
    .filter(Boolean)
    .join("\n");
}

export function generateLaravelBrandingBlade(config: GeoSeoConfig): string {
  return generateBrandingFooterHtml(config);
}

function escapeHtml(value: string): string {
  return value
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}
