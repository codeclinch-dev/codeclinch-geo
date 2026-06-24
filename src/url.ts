import type { GeoSeoConfig } from "./types";

export function normalizeSiteUrl(siteUrl: string): string {
  return siteUrl.replace(/\/+$/, "");
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

  return `${normalizeSiteUrl(config.siteUrl)}${normalizePath(pathOrUrl)}`;
}

export function toIsoDate(value?: string | Date): string | undefined {
  if (!value) {
    return undefined;
  }

  return value instanceof Date ? value.toISOString() : new Date(value).toISOString();
}
