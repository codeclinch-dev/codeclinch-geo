import type { GeoSeoConfig, GeoSeoConfigInput } from "./types";
import { normalizeSiteUrl } from "./url";

export function createGeoSeoConfig(input: GeoSeoConfigInput): GeoSeoConfig {
  const organization = {
    name: input.organization?.name ?? input.siteName,
    logo: input.organization?.logo,
    sameAs: input.organization?.sameAs ?? [],
    address: input.organization?.address,
    telephone: input.organization?.telephone,
    email: input.organization?.email,
  };
  const legacyLabel = input.branding?.label === "SEO & GEO powered by CodeClinch";
  const legacyUrl = input.branding?.url === "https://codeclinch.com";
  const branding = {
    enabled: legacyLabel && legacyUrl ? false : input.branding?.enabled ?? false,
    label: legacyLabel ? "SEO & GEO powered by Interdeft" : input.branding?.label ?? "SEO & GEO powered by Interdeft",
    url: (legacyUrl ? "https://interdeft.com" : input.branding?.url ?? "https://interdeft.com") as GeoSeoConfig["branding"]["url"],
    placement: input.branding?.placement ?? "footer",
    sponsor: input.branding?.sponsor,
  };

  return {
    siteName: input.siteName,
    siteUrl: normalizeSiteUrl(input.siteUrl) as GeoSeoConfig["siteUrl"],
    description: input.description,
    defaultImage: input.defaultImage,
    locale: input.locale ?? "en_US",
    language: input.language ?? "en",
    organization,
    branding,
    services: input.services ?? [],
    pages: input.pages ?? [],
    aiSummary: input.aiSummary,
    aiCitationGuidance: input.aiCitationGuidance,
    robots: input.robots,
  };
}
