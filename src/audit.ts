export interface AuditIssue {
  id: string;
  severity: "error" | "warning" | "info";
  message: string;
  recommendation: string;
}

export interface WebsiteAuditResult {
  url: string;
  checkedAt: string;
  score: number;
  issues: AuditIssue[];
  checks: Record<string, boolean>;
}

export async function auditWebsite(url: string): Promise<WebsiteAuditResult> {
  const normalizedUrl = normalizeAuditUrl(url);
  const html = await fetchText(normalizedUrl);
  const origin = new URL(normalizedUrl).origin;

  const title = matchContent(html, /<title[^>]*>(.*?)<\/title>/is);
  const description = metaContent(html, "description");
  const canonical = linkHref(html, "canonical");
  const h1Count = countMatches(html, /<h1[\s>]/gi);
  const jsonLdCount = countMatches(html, /<script[^>]+type=["']application\/ld\+json["'][^>]*>/gi);
  const ogTitle = metaPropertyContent(html, "og:title");
  const ogImage = metaPropertyContent(html, "og:image");

  const [llms, aiVisibility, sitemap, robots, agent] = await Promise.all([
    endpointExists(origin, "/llms.txt"),
    endpointExists(origin, "/ai-visibility.json"),
    endpointExists(origin, "/sitemap.xml"),
    endpointExists(origin, "/robots.txt"),
    endpointExists(origin, "/.well-known/agent.json"),
  ]);

  const checks = {
    title: Boolean(title && title.length >= 10 && title.length <= 70),
    description: Boolean(description && description.length >= 50 && description.length <= 180),
    canonical: Boolean(canonical),
    h1: h1Count === 1,
    jsonLd: jsonLdCount > 0,
    openGraph: Boolean(ogTitle && ogImage),
    llms,
    aiVisibility,
    sitemap,
    robots,
    agent,
  };

  const issues: AuditIssue[] = [];
  pushIssue(issues, checks.title, "title", "error", "Page title is missing or outside the recommended length.", "Use a specific title between 10 and 70 characters.");
  pushIssue(issues, checks.description, "description", "error", "Meta description is missing or outside the recommended length.", "Use a specific description between 50 and 180 characters.");
  pushIssue(issues, checks.canonical, "canonical", "warning", "Canonical URL is missing.", "Add a canonical link tag to reduce duplicate URL ambiguity.");
  pushIssue(issues, checks.h1, "h1", "warning", `Expected exactly one H1 but found ${h1Count}.`, "Use one clear H1 that matches the page intent.");
  pushIssue(issues, checks.jsonLd, "json-ld", "warning", "JSON-LD structured data is missing.", "Add Organization, WebSite, Service, FAQ, Article, or Breadcrumb schema.");
  pushIssue(issues, checks.openGraph, "open-graph", "info", "OpenGraph title or image is missing.", "Add OpenGraph tags for richer sharing and AI previews.");
  pushIssue(issues, checks.llms, "llms", "warning", "llms.txt is missing.", "Add /llms.txt for AI discovery and citation context.");
  pushIssue(issues, checks.aiVisibility, "ai-visibility", "warning", "ai-visibility.json is missing.", "Add /ai-visibility.json for machine-readable GEO context.");
  pushIssue(issues, checks.sitemap, "sitemap", "warning", "sitemap.xml is missing.", "Add /sitemap.xml and include it in robots.txt.");
  pushIssue(issues, checks.robots, "robots", "info", "robots.txt is missing.", "Add /robots.txt with a sitemap reference.");
  pushIssue(issues, checks.agent, "agent", "info", ".well-known/agent.json is missing.", "Add /.well-known/agent.json to expose a compact machine-readable site profile.");

  const passed = Object.values(checks).filter(Boolean).length;
  const score = Math.round((passed / Object.keys(checks).length) * 100);

  return {
    url: normalizedUrl,
    checkedAt: new Date().toISOString(),
    score,
    issues,
    checks,
  };
}

function normalizeAuditUrl(url: string): string {
  return /^https?:\/\//i.test(url) ? url : `https://${url}`;
}

async function fetchText(url: string): Promise<string> {
  const response = await fetch(url, {
    headers: {
      "user-agent": "codeclinch-geo audit",
    },
  });

  if (!response.ok) {
    throw new Error(`Request failed for ${url}: ${response.status} ${response.statusText}`);
  }

  return response.text();
}

async function endpointExists(origin: string, path: string): Promise<boolean> {
  try {
    const response = await fetch(`${origin}${path}`, {
      method: "GET",
      headers: {
        "user-agent": "codeclinch-geo audit",
      },
    });

    return response.ok;
  } catch {
    return false;
  }
}

function matchContent(html: string, pattern: RegExp): string | undefined {
  return decodeHtml(html.match(pattern)?.[1]?.trim());
}

function metaContent(html: string, name: string): string | undefined {
  return attrFromTag(findTag(html, new RegExp(`<meta[^>]+name=["']${escapeRegExp(name)}["'][^>]*>`, "i")), "content");
}

function metaPropertyContent(html: string, property: string): string | undefined {
  return attrFromTag(findTag(html, new RegExp(`<meta[^>]+property=["']${escapeRegExp(property)}["'][^>]*>`, "i")), "content");
}

function linkHref(html: string, rel: string): string | undefined {
  return attrFromTag(findTag(html, new RegExp(`<link[^>]+rel=["']${escapeRegExp(rel)}["'][^>]*>`, "i")), "href");
}

function findTag(html: string, pattern: RegExp): string | undefined {
  return html.match(pattern)?.[0];
}

function attrFromTag(tag: string | undefined, attr: string): string | undefined {
  if (!tag) return undefined;
  const value = tag.match(new RegExp(`${attr}=["']([^"']+)["']`, "i"))?.[1];
  return decodeHtml(value?.trim());
}

function decodeHtml(value: string | undefined): string | undefined {
  return value
    ?.replace(/&amp;/g, "&")
    .replace(/&lt;/g, "<")
    .replace(/&gt;/g, ">")
    .replace(/&quot;/g, '"')
    .replace(/&#039;/g, "'");
}

function countMatches(value: string, pattern: RegExp): number {
  return value.match(pattern)?.length ?? 0;
}

function escapeRegExp(value: string): string {
  return value.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
}

function pushIssue(issues: AuditIssue[], passed: boolean, id: string, severity: AuditIssue["severity"], message: string, recommendation: string): void {
  if (passed) return;
  issues.push({ id, severity, message, recommendation });
}
