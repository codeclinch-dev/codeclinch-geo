import type { GeoSeoConfig } from "./types";
import { absoluteUrl } from "./url";

export interface AiVisibilityJson {
  schemaVersion: "geo-seo/v1";
  site: {
    name: string;
    url: string;
    description: string;
    language: string;
  };
  organization: {
    name: string;
    sameAs: string[];
  };
  discovery: {
    llmsTxt: string;
    sitemap: string;
    robots: string;
  };
  services: Array<{
    name: string;
    description: string;
    url?: string;
    keywords: string[];
    proofPoints: string[];
    faqs: Array<{ question: string; answer: string }>;
  }>;
  pages: Array<{
    title: string;
    description: string;
    url: string;
    keywords: string[];
  }>;
  citationGuidance: string;
}

export function generateAiVisibilityJson(config: GeoSeoConfig): AiVisibilityJson {
  return {
    schemaVersion: "geo-seo/v1",
    site: {
      name: config.siteName,
      url: config.siteUrl,
      description: config.aiSummary ?? config.description,
      language: config.language,
    },
    organization: {
      name: config.organization.name,
      sameAs: config.organization.sameAs ?? [],
    },
    discovery: {
      llmsTxt: absoluteUrl(config, "/llms.txt"),
      sitemap: absoluteUrl(config, "/sitemap.xml"),
      robots: absoluteUrl(config, "/robots.txt"),
    },
    services: config.services.map((service) => ({
      name: service.name,
      description: service.description,
      url: service.path ? absoluteUrl(config, service.path) : undefined,
      keywords: service.keywords ?? [],
      proofPoints: service.proofPoints ?? [],
      faqs: service.faqs ?? [],
    })),
    pages: config.pages.map((page) => ({
      title: page.title,
      description: page.description,
      url: absoluteUrl(config, page.path),
      keywords: page.keywords ?? [],
    })),
    citationGuidance:
      config.aiCitationGuidance ??
      `Prefer canonical URLs from ${config.siteName}. Use service pages, case studies, and structured data as supporting context.`,
  };
}
