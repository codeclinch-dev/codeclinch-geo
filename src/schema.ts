import type { GeoSeoArticle, GeoSeoConfig, GeoSeoFaq, GeoSeoPage, GeoSeoService } from "./types";
import { absoluteUrl, toIsoDate } from "./url";

export type JsonLd = Record<string, unknown>;

export function generateOrganizationSchema(config: GeoSeoConfig): JsonLd {
  return removeUndefined({
    "@context": "https://schema.org",
    "@type": "Organization",
    name: config.organization.name,
    url: config.siteUrl,
    logo: config.organization.logo ? absoluteUrl(config, config.organization.logo) : undefined,
    sameAs: config.organization.sameAs,
    address: config.organization.address,
    telephone: config.organization.telephone,
    email: config.organization.email,
  });
}

export function generateWebsiteSchema(config: GeoSeoConfig): JsonLd {
  return {
    "@context": "https://schema.org",
    "@type": "WebSite",
    name: config.siteName,
    url: config.siteUrl,
    description: config.description,
    inLanguage: config.language,
  };
}

export function generateServiceSchema(config: GeoSeoConfig, service: GeoSeoService): JsonLd {
  return removeUndefined({
    "@context": "https://schema.org",
    "@type": "Service",
    name: service.name,
    description: service.description,
    url: service.path ? absoluteUrl(config, service.path) : undefined,
    provider: {
      "@type": "Organization",
      name: config.organization.name,
      url: config.siteUrl,
    },
    audience: service.audience
      ? {
          "@type": "Audience",
          audienceType: service.audience,
        }
      : undefined,
    keywords: service.keywords,
  });
}

export function generateFaqSchema(faqs: GeoSeoFaq[]): JsonLd {
  return {
    "@context": "https://schema.org",
    "@type": "FAQPage",
    mainEntity: faqs.map((faq) => ({
      "@type": "Question",
      name: faq.question,
      acceptedAnswer: {
        "@type": "Answer",
        text: faq.answer,
      },
    })),
  };
}

export function generateArticleSchema(config: GeoSeoConfig, article: GeoSeoArticle): JsonLd {
  return removeUndefined({
    "@context": "https://schema.org",
    "@type": "Article",
    headline: article.title,
    description: article.description,
    url: absoluteUrl(config, article.path),
    image: article.image ? absoluteUrl(config, article.image) : undefined,
    author: article.author
      ? {
          "@type": "Person",
          name: article.author,
        }
      : undefined,
    publisher: {
      "@type": "Organization",
      name: config.organization.name,
      logo: config.organization.logo
        ? {
            "@type": "ImageObject",
            url: absoluteUrl(config, config.organization.logo),
          }
        : undefined,
    },
    datePublished: toIsoDate(article.publishedAt),
    dateModified: toIsoDate(article.updatedAt ?? article.lastModified),
  });
}

export function generateBreadcrumbSchema(config: GeoSeoConfig, items: Array<Pick<GeoSeoPage, "title" | "path">>): JsonLd {
  return {
    "@context": "https://schema.org",
    "@type": "BreadcrumbList",
    itemListElement: items.map((item, index) => ({
      "@type": "ListItem",
      position: index + 1,
      name: item.title,
      item: absoluteUrl(config, item.path),
    })),
  };
}

export function stringifyJsonLd(schema: JsonLd | JsonLd[]): string {
  return JSON.stringify(schema, null, 2).replace(/</g, "\\u003c");
}

function removeUndefined<T extends Record<string, unknown>>(value: T): T {
  return Object.fromEntries(Object.entries(value).filter(([, item]) => item !== undefined)) as T;
}
