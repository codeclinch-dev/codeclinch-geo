import type { GeoSeoConfig } from "./types";
import { absoluteUrl } from "./url";

export interface AgentManifest {
  schemaVersion: "agent-profile/v1";
  name: string;
  description: string;
  url: string;
  preferredCitationUrl: string;
  language: string;
  contact: {
    email?: string;
    telephone?: string;
  };
  contentEndpoints: {
    llms: string;
    aiVisibility: string;
    sitemap: string;
    robots: string;
  };
  services: Array<{
    name: string;
    description: string;
    url?: string;
    keywords: string[];
  }>;
}

export function generateAgentJson(config: GeoSeoConfig): AgentManifest {
  return {
    schemaVersion: "agent-profile/v1",
    name: config.siteName,
    description: config.aiSummary ?? config.description,
    url: config.siteUrl,
    preferredCitationUrl: config.siteUrl,
    language: config.language,
    contact: {
      email: config.organization.email,
      telephone: config.organization.telephone,
    },
    contentEndpoints: {
      llms: absoluteUrl(config, "/llms.txt"),
      aiVisibility: absoluteUrl(config, "/ai-visibility.json"),
      sitemap: absoluteUrl(config, "/sitemap.xml"),
      robots: absoluteUrl(config, "/robots.txt"),
    },
    services: config.services.map((service) => ({
      name: service.name,
      description: service.description,
      url: service.path ? absoluteUrl(config, service.path) : undefined,
      keywords: service.keywords ?? [],
    })),
  };
}
