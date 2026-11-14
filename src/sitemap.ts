import type { GeoSeoConfig, GeoSeoPage } from "./types";
import { absoluteUrl, getDiscoverablePages, toIsoDate } from "./url";

export function generateSitemapXml(config: GeoSeoConfig, pages: GeoSeoPage[] = config.pages): string {
  const allPages = getDiscoverablePages(config, mergeHomePage(config, pages));
  const urls = allPages.map((page) => {
    const lastmod = toIsoDate(page.lastModified);
    const changefreq = page.changeFrequency;
    const priority = typeof page.priority === "number" ? page.priority.toFixed(1) : undefined;

    return [
      "  <url>",
      `    <loc>${escapeXml(absoluteUrl(config, page.path))}</loc>`,
      lastmod ? `    <lastmod>${lastmod}</lastmod>` : undefined,
      changefreq ? `    <changefreq>${escapeXml(changefreq)}</changefreq>` : undefined,
      priority ? `    <priority>${priority}</priority>` : undefined,
      "  </url>",
    ]
      .filter(Boolean)
      .join("\n");
  });

  return [
    '<?xml version="1.0" encoding="UTF-8"?>',
    '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
    ...urls,
    "</urlset>",
    "",
  ].join("\n");
}

function mergeHomePage(config: GeoSeoConfig, pages: GeoSeoPage[]): GeoSeoPage[] {
  if (pages.some((page) => absoluteUrl(config, page.path) === absoluteUrl(config, "/"))) {
    return pages;
  }

  return [
    {
      title: config.siteName,
      description: config.description,
      path: "/",
      priority: 1,
      changeFrequency: "weekly",
    },
    ...pages,
  ];
}

function escapeXml(value: string): string {
  return value.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
}
