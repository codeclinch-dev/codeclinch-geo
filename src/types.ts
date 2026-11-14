export type AbsoluteUrl = `http://${string}` | `https://${string}`;

export interface RobotsOptions {
  disallow?: string[];
  allow?: string[];
  userAgent?: string;
  crawlerPolicies?: Partial<Record<"OAI-SearchBot" | "GPTBot" | "Claude-SearchBot" | "ClaudeBot" | "Claude-User" | "Google-Extended", "inherit" | "block">>;
}

export interface GeoSeoOrganization {
  name: string;
  logo?: string;
  sameAs?: string[];
  address?: string;
  telephone?: string;
  email?: string;
}

export interface GeoSeoBranding {
  enabled: boolean;
  label: string;
  url: AbsoluteUrl;
  placement: "footer" | "head" | "none";
  sponsor?: string;
}

export interface GeoSeoService {
  name: string;
  description: string;
  path?: string;
  keywords?: string[];
  audience?: string;
  proofPoints?: string[];
  faqs?: GeoSeoFaq[];
}

export interface GeoSeoPage {
  title: string;
  description: string;
  path?: string;
  canonical?: string;
  noIndex?: boolean;
  image?: string;
  keywords?: string[];
  lastModified?: string | Date;
  priority?: number;
  changeFrequency?: "always" | "hourly" | "daily" | "weekly" | "monthly" | "yearly" | "never";
}

export interface GeoSeoFaq {
  question: string;
  answer: string;
}

export interface GeoSeoArticle extends GeoSeoPage {
  author?: string;
  publishedAt?: string | Date;
  updatedAt?: string | Date;
}

export interface GeoSeoConfig {
  siteName: string;
  siteUrl: AbsoluteUrl;
  description: string;
  defaultImage?: string;
  locale: string;
  language: string;
  organization: GeoSeoOrganization;
  branding: GeoSeoBranding;
  services: GeoSeoService[];
  pages: GeoSeoPage[];
  aiSummary?: string;
  aiCitationGuidance?: string;
  robots?: RobotsOptions;
}

export interface GeoSeoConfigInput {
  siteName: string;
  siteUrl: AbsoluteUrl;
  description: string;
  defaultImage?: string;
  locale?: string;
  language?: string;
  organization?: Partial<GeoSeoOrganization>;
  branding?: Partial<GeoSeoBranding>;
  services?: GeoSeoService[];
  pages?: GeoSeoPage[];
  aiSummary?: string;
  aiCitationGuidance?: string;
  robots?: RobotsOptions;
}

export type MetadataInput = GeoSeoPage;

export interface GeneratedMetadata {
  title: string;
  description: string;
  alternates: {
    canonical: string;
  };
  openGraph: {
    title: string;
    description: string;
    url: string;
    siteName: string;
    locale: string;
    type: "website";
    images?: Array<{ url: string; alt: string }>;
  };
  twitter: {
    card: "summary_large_image" | "summary";
    title: string;
    description: string;
    images?: string[];
  };
  robots?: {
    index: boolean;
    follow: boolean;
  };
  keywords?: string[];
}

export interface AiCrawlerMatch {
  isAiCrawler: boolean;
  crawlerName?: string;
}

export interface AiCrawlerVisit {
  type: "ai_crawler_visit";
  crawlerName: string;
  userAgent: string;
  path: string;
  timestamp: string;
}
