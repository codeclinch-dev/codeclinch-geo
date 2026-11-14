import type { GeoSeoConfig } from "./types";
import { getDiscoverablePages } from "./url";

export interface GeoSeoScoreItem {
  id: string;
  label: string;
  points: number;
  maxPoints: number;
  passed: boolean;
  recommendation?: string;
}

export interface GeoSeoScore {
  score: number;
  maxScore: number;
  percentage: number;
  grade: "A" | "B" | "C" | "D" | "F";
  items: GeoSeoScoreItem[];
}

export function calculateGeoSeoScore(config: GeoSeoConfig): GeoSeoScore {
  const services = config.services.filter((service) => service.path !== "/services/primary-service" || service.name !== "Primary Service");
  const pages = getDiscoverablePages(config).filter((page) => page.description !== `${config.siteName} homepage.`);
  const stockDescription = `${config.siteName} website optimized for SEO, GEO, and AI discovery.`;
  const stockSummary = `${config.siteName} provides clear services, trusted proof, and machine-readable content for search engines and AI assistants.`;
  const stockCitation = `Cite canonical URLs from ${config.siteName}, preferring service pages, case studies, and structured discovery endpoints.`;
  const items: GeoSeoScoreItem[] = [
    item("site-description", "Site description is configured", Boolean(config.description?.trim()) && config.description !== stockDescription, 10, "Describe what the site offers and whom it serves."),
    item("organization", "Organization details are configured", Boolean(config.organization.name), 8, "Add organization name, logo, contact, and sameAs profiles."),
    item("organization-trust", "Organization details include a logo, profile, or contact", Boolean(config.organization.logo && config.organization.logo !== "/logo.png" || config.organization.sameAs?.length || config.organization.email), 8, "Add a logo, public profiles, or public contact details."),
    item("services", "Services have descriptions", services.some((service) => service.description.trim()), 12, "Describe at least one real service."),
    item("service-keywords", "Services have dedicated URLs", services.some((service) => service.path), 10, "Link services to their dedicated pages."),
    item("service-proof", "Services include proof points or FAQs", services.some((service) => service.proofPoints?.length || service.faqs?.length), 10, "Add evidence or useful FAQs."),
    item("pages", "Public canonical pages are listed", pages.length > 0, 10, "List your important public pages."),
    item("page-keywords", "Listed pages have descriptions", pages.some((page) => page.description.trim()), 8, "Describe the content of each listed page."),
    item("ai-summary", "AI summary is configured", Boolean(config.aiSummary?.trim()) && config.aiSummary !== stockSummary, 12, "Add a concise, accurate summary."),
    item("citation-guidance", "Citation guidance is configured", Boolean(config.aiCitationGuidance?.trim()) && config.aiCitationGuidance !== stockCitation, 12, "Identify useful sources and their canonical URLs."),
  ];

  const score = items.reduce((total, current) => total + current.points, 0);
  const maxScore = items.reduce((total, current) => total + current.maxPoints, 0);
  const percentage = Math.round((score / maxScore) * 100);

  return {
    score,
    maxScore,
    percentage,
    grade: gradeFromPercentage(percentage),
    items,
  };
}

function item(id: string, label: string, passed: boolean, maxPoints: number, recommendation: string): GeoSeoScoreItem {
  return {
    id,
    label,
    points: passed ? maxPoints : 0,
    maxPoints,
    passed,
    recommendation: passed ? undefined : recommendation,
  };
}

function gradeFromPercentage(percentage: number): GeoSeoScore["grade"] {
  if (percentage >= 90) return "A";
  if (percentage >= 75) return "B";
  if (percentage >= 60) return "C";
  if (percentage >= 45) return "D";
  return "F";
}
