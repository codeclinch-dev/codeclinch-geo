import type { GeoSeoConfig, GeoSeoPage } from "./types";

export function normalizeSiteUrl(siteUrl: string): string {
  if (typeof siteUrl !== "string" || !URL.canParse(siteUrl)) {
    throw new Error("Set siteUrl to an absolute HTTP or HTTPS URL.");
  }
  const url = new URL(siteUrl);
  if (!["http:", "https:"].includes(url.protocol) || url.username || url.password || url.search || url.hash) {
    throw new Error("Set siteUrl to an HTTP or HTTPS URL without credentials, a query, or a fragment.");
  }
  return url.href.replace(/\/+$/, "");
}

export function normalizePath(path = "/"): string {
  if (!path) {
    return "/";
  }

  return path.startsWith("/") ? path : `/${path}`;
}

export function absoluteUrl(config: Pick<GeoSeoConfig, "siteUrl">, pathOrUrl = "/"): string {
  if (/^https?:\/\//i.test(pathOrUrl)) {
    return pathOrUrl;
  }

  return `${config.siteUrl.replace(/\/+$/, "")}${normalizePath(pathOrUrl)}`;
}

export function toIsoDate(value?: string | Date): string | undefined {
  if (!value) {
    return undefined;
  }

  const date = value instanceof Date ? value : new Date(value);
  return Number.isFinite(date.getTime()) ? date.toISOString() : undefined;
}

export function getDiscoverablePages(config: GeoSeoConfig, pages: GeoSeoPage[] = config.pages): GeoSeoPage[] {
  const site = new URL(config.siteUrl);
  const urls = new Set<string>();
  return pages.filter((page) => {
    const rawUrl = absoluteUrl(config, page.path);
    if (page.noIndex || !URL.canParse(rawUrl)) return false;
    const url = new URL(rawUrl);
    if (url.origin !== site.origin || url.hash || url.username || url.password || urls.has(url.href)) return false;
    if (page.canonical) {
      const canonical = absoluteUrl(config, page.canonical);
      if (!URL.canParse(canonical) || new URL(canonical).href !== url.href) return false;
    }
    urls.add(url.href);
    return true;
  });
}
