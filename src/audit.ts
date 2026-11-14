import { DOMParser } from "@xmldom/xmldom";
import { parse, type DefaultTreeAdapterTypes } from "parse5";

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
  endpoints: Record<string, { url: string; status?: number; valid: boolean }>;
}

export async function auditWebsite(url: string): Promise<WebsiteAuditResult> {
  const normalizedUrl = normalizeAuditUrl(url);
  const page = await fetchText(normalizedUrl);
  if (!page.ok) throw new Error(`Request failed for ${normalizedUrl}: HTTP ${page.status}`);
  const origin = new URL(page.url).origin;
  const elements = getElements(parse(page.body));
  const head = elements.filter((element) => element.parentNode?.nodeName === "head");
  const metaTags = head.filter((element) => element.tagName === "meta");
  const title = head.find((element) => element.tagName === "title");
  const description = metaContent(metaTags, "name", "description");
  const canonicalTags = head.filter((element) => element.tagName === "link"
    && attribute(element, "rel")?.toLowerCase().split(/\s+/).includes("canonical"));
  const canonical = canonicalTags[0] && attribute(canonicalTags[0], "href");
  const h1Count = elements.filter((element) => element.tagName === "h1").length;
  const jsonLd = elements.filter((element) => element.tagName === "script"
    && attribute(element, "type")?.trim().toLowerCase() === "application/ld+json").map((element) => parseObject(textContent(element)));
  const ogTitle = metaContent(metaTags, "property", "og:title");
  const ogImage = metaContent(metaTags, "property", "og:image");

  const robots = await checkEndpoint(`${origin}/robots.txt`, (body) => /^(?:user-agent|sitemap)\s*:\s*\S/im.test(body) || body.trim() === "");
  const advertisedSitemaps = [...robots.body.matchAll(/^sitemap\s*:\s*(\S+)\s*$/gim)]
    .map((match) => match[1]!)
    .filter((url) => URL.canParse(url) && new URL(url).origin === origin);
  const sitemapUrls = [...new Set([...advertisedSitemaps, `${origin}/sitemap.xml`, `${origin}/wp-sitemap.xml`])].slice(0, 8);
  const [llms, aiVisibility, sitemaps, agent] = await Promise.all([
    checkEndpoint(`${origin}/llms.txt`, (body) => /^#\s+\S/.test(body.trimStart())),
    checkEndpoint(`${origin}/ai-visibility.json`, (body) => {
      const profile = parseObject(body);
      const site = profile?.site as { name?: unknown; url?: unknown } | undefined;
      return typeof profile?.schemaVersion === "string" && typeof site?.name === "string" && isWebUrl(site.url);
    }),
    Promise.all(sitemapUrls.map((url) => checkEndpoint(url, isSitemap))),
    checkEndpoint(`${origin}/.well-known/agent.json`, (body) => {
      const profile = parseObject(body);
      return typeof profile?.name === "string" && isWebUrl(profile.url)
        && isWebUrl((profile.contentEndpoints as { llms?: unknown } | undefined)?.llms);
    }),
  ]);
  const sitemap = sitemaps.find((result) => result.valid) ?? sitemaps[0]!;
  const robotsDirectives = [page.robotsHeader, ...metaTags
    .filter((element) => ["robots", "googlebot", "bingbot"].includes(attribute(element, "name")?.toLowerCase() ?? ""))
    .map((element) => attribute(element, "content"))].join(",");

  const checks = {
    title: Boolean(title && textContent(title).trim()),
    description: Boolean(description),
    canonical: canonicalTags.length === 1 && isWebUrl(canonical),
    indexable: !/\b(?:noindex|none)\b/i.test(robotsDirectives),
    h1: h1Count === 1,
    jsonLd: jsonLd.length > 0 && jsonLd.every((value) => Boolean(value)),
    openGraph: Boolean(ogTitle && ogImage),
    llms: llms.valid,
    aiVisibility: aiVisibility.valid,
    sitemap: sitemap.valid,
    robots: robots.valid,
    agent: agent.valid,
  };

  const issues: AuditIssue[] = [];
  pushIssue(issues, checks.title, "title", "error", "Page title is missing.", "Use a descriptive page title.");
  pushIssue(issues, checks.description, "description", "warning", "Meta description is missing.", "Use a page-specific description.");
  pushIssue(issues, checks.canonical, "canonical", "warning", "Expected one valid absolute canonical URL.", "Check the rendered canonical link tags.");
  pushIssue(issues, checks.indexable, "indexable", "error", "A robots directive blocks indexing.", "Review the meta tags and X-Robots-Tag if the page should be indexed.");
  pushIssue(issues, checks.h1, "h1", "warning", `Expected exactly one H1 but found ${h1Count}.`, "Use one clear H1 that matches the page intent.");
  pushIssue(issues, checks.jsonLd, "json-ld", "warning", "JSON-LD is missing or malformed.", "Validate structured data against the content visible on the page.");
  pushIssue(issues, checks.openGraph, "open-graph", "info", "OpenGraph title or image is missing.", "Add OpenGraph tags for richer sharing and AI previews.");
  pushIssue(issues, checks.sitemap, "sitemap", "warning", "A valid XML sitemap was not found.", "Check the sitemap advertised in robots.txt.");
  pushIssue(issues, checks.robots, "robots", "info", "A valid robots.txt response was not found.", "Check the status and body of /robots.txt.");

  const scored = [checks.title, checks.description, checks.canonical, checks.indexable, checks.h1, checks.jsonLd, checks.openGraph, checks.sitemap, checks.robots];
  const score = Math.round((scored.filter(Boolean).length / scored.length) * 100);

  return {
    url: page.url,
    checkedAt: new Date().toISOString(),
    score,
    issues,
    checks,
    endpoints: Object.fromEntries(Object.entries({ robots, sitemap, llms, aiVisibility, agent }).map(
      ([name, { url, status, valid }]) => [name, { url, status, valid }],
    )),
  };
}

function normalizeAuditUrl(url: string): string {
  const input = url.trim();
  const parsed = new URL(/^[a-z][a-z\d+.-]*:\/\//i.test(input) ? input : `https://${input}`);
  if (!["http:", "https:"].includes(parsed.protocol) || parsed.username || parsed.password) {
    throw new Error("Enter an HTTP or HTTPS URL without credentials.");
  }
  parsed.hash = "";
  return parsed.href;
}

async function fetchText(url: string) {
  const response = await fetch(url, {
    headers: { "user-agent": "interdeft-geo audit" },
    signal: AbortSignal.timeout(10_000),
  });
  let body = "";
  if (response.ok && response.body) {
    const reader = response.body.getReader();
    const decoder = new TextDecoder();
    let bytes = 0;
    try {
      for (let chunk = await reader.read(); !chunk.done; chunk = await reader.read()) {
        bytes += chunk.value.byteLength;
        if (bytes > 4 * 1024 * 1024) throw new Error(`Response exceeds the audit size limit for ${url}`);
        body += decoder.decode(chunk.value, { stream: true });
      }
      body += decoder.decode();
    } finally {
      await reader.cancel().catch(() => {});
      reader.releaseLock();
    }
  } else {
    await response.body?.cancel();
  }
  return {
    url: response.url || url, body, ok: response.ok, status: response.status,
    contentType: response.headers.get("content-type") ?? "", robotsHeader: response.headers.get("x-robots-tag"),
  };
}

async function checkEndpoint(url: string, validate: (body: string) => boolean) {
  try {
    const response = await fetchText(url);
    const valid = response.ok && !/\b(?:text\/html|application\/xhtml\+xml)\b/i.test(response.contentType)
      && !/^\s*<(?:!doctype\s+html|html\b)/i.test(response.body) && validate(response.body);
    return { url: response.url, status: response.status, valid, body: valid ? response.body : "" };
  } catch {
    return { url, status: undefined, valid: false, body: "" };
  }
}

function isSitemap(body: string): boolean {
  if (/<!DOCTYPE/i.test(body)) return false;
  try {
    const root = new DOMParser({ onError: (level, message) => {
      if (level !== "warning") throw new Error(message);
    } }).parseFromString(body, "application/xml").documentElement;
    const ns = "http://www.sitemaps.org/schemas/sitemap/0.9";
    if (!root || root.namespaceURI !== ns || !["urlset", "sitemapindex"].includes(root.localName ?? "")) return false;
    const expected = root.localName === "urlset" ? "url" : "sitemap";
    const entries = Array.from(root.childNodes).filter((node) => node.nodeType === 1);
    return entries.length > 0 && entries.every((entry) => {
      if (entry.nodeName !== expected && !entry.nodeName.endsWith(`:${expected}`)) return false;
      const locations = Array.from(entry.childNodes).filter((node) => node.nodeType === 1
        && (node.nodeName === "loc" || node.nodeName.endsWith(":loc")));
      return locations.length === 1 && isWebUrl(locations[0]?.textContent?.trim());
    });
  } catch {
    return false;
  }
}

function parseObject(body: string): Record<string, unknown> | undefined {
  try {
    const value = JSON.parse(body);
    return value && typeof value === "object" ? value : undefined;
  } catch {
    return undefined;
  }
}

function isWebUrl(value: unknown): value is string {
  return typeof value === "string" && /^https?:\/\//i.test(value) && URL.canParse(value);
}

function getElements(document: DefaultTreeAdapterTypes.Document): DefaultTreeAdapterTypes.Element[] {
  const elements: DefaultTreeAdapterTypes.Element[] = [];
  const pending: DefaultTreeAdapterTypes.ChildNode[] = [...document.childNodes].reverse();
  while (pending.length) {
    const node = pending.pop()!;
    if ("tagName" in node) {
      elements.push(node);
      for (let index = node.childNodes.length - 1; index >= 0; index--) pending.push(node.childNodes[index]!);
    }
  }
  return elements;
}

function textContent(element: DefaultTreeAdapterTypes.Element): string {
  return element.childNodes.map((child) => "value" in child ? child.value : "").join("");
}

function attribute(element: DefaultTreeAdapterTypes.Element, name: string): string | undefined {
  return element.attrs.find((attr) => attr.name === name)?.value.trim();
}

function metaContent(elements: DefaultTreeAdapterTypes.Element[], key: string, value: string): string | undefined {
  const tag = elements.find((element) => attribute(element, key)?.toLowerCase() === value);
  return tag && attribute(tag, "content");
}

function pushIssue(issues: AuditIssue[], passed: boolean, id: string, severity: AuditIssue["severity"], message: string, recommendation: string): void {
  if (passed) return;
  issues.push({ id, severity, message, recommendation });
}
