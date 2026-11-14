import { createGeoSeoConfig } from "../../../src";

export const site = createGeoSeoConfig({
  siteName: "Interdeft",
  siteUrl: "https://interdeft.com",
  description: "Web development, ecommerce, AI, SEO and GEO services.",
  defaultImage: "/og-image.jpg",
  aiSummary: "Interdeft builds websites, ecommerce platforms, delivery apps, and AI-ready search experiences.",
  organization: {
    name: "Interdeft",
    logo: "/logo.png",
    sameAs: ["https://www.linkedin.com/company/interdeft"],
  },
  services: [
    {
      name: "SEO and GEO",
      description: "Technical SEO, content structure, schema, llms.txt, and AI search visibility.",
      path: "/services/seo-geo",
      keywords: ["SEO", "GEO", "AI search optimization"],
      proofPoints: ["Structured data", "AI discovery endpoints", "Service-page citation context"],
    },
  ],
  pages: [
    {
      title: "Case Studies",
      description: "Proof-focused project work across websites, ecommerce, apps, and AI search readiness.",
      path: "/work",
      keywords: ["case studies", "web development proof", "SEO proof"],
    },
  ],
});
