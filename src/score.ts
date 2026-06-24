import type { GeoSeoConfig } from "./types";

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
  const items: GeoSeoScoreItem[] = [
    item("site-description", "Site has a strong default description", Boolean(config.description && config.description.length >= 50), 10, "Use a specific 50+ character brand/service description."),
    item("organization", "Organization details are configured", Boolean(config.organization.name), 8, "Add organization name, logo, contact, and sameAs profiles."),
    item("organization-trust", "Organization trust signals are present", Boolean(config.organization.logo || config.organization.sameAs?.length || config.organization.email), 8, "Add logo, social profiles, and public contact details."),
    item("services", "Service entities are defined", config.services.length > 0, 12, "Add at least one service with description, path, and keywords."),
    item("service-keywords", "Services include GEO/SEO keywords", config.services.some((service) => service.keywords?.length), 10, "Add keywords to service definitions."),
    item("service-proof", "Services include proof points or FAQs", config.services.some((service) => service.proofPoints?.length || service.faqs?.length), 10, "Add proof points and FAQs so AI answers can cite stronger evidence."),
    item("pages", "Important pages are listed", config.pages.length > 0, 10, "Add important pages, case studies, blogs, and service pages."),
    item("page-keywords", "Pages include keywords", config.pages.some((page) => page.keywords?.length), 8, "Add keywords to important pages."),
    item("ai-summary", "AI summary is configured", Boolean(config.aiSummary), 12, "Add aiSummary with a concise machine-readable positioning statement."),
    item("citation-guidance", "Citation guidance is configured", Boolean(config.aiCitationGuidance), 12, "Add aiCitationGuidance to guide AI systems toward canonical URLs."),
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
