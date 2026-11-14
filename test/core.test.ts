import { describe, expect, it, vi } from "vitest";
import { existsSync, mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from "node:fs";
import { join } from "node:path";
import { tmpdir } from "node:os";
import {
  createAiCrawlerVisit,
  createStarterConfig,
  auditWebsite,
  createGeoSeoConfig,
  calculateGeoSeoScore,
  detectAiCrawler,
  detectProject,
  generateBrandingFooterHtml,
  generateBrandingHtml,
  generateBrandingText,
  generateAgentJson,
  generateAiVisibilityJson,
  generateLlmsTxt,
  generateMetadata,
  generateLaravelBladeSeo,
  generateRobotsTxt,
  generateSitemapXml,
  installGeoSeo,
} from "../src";

const site = createGeoSeoConfig({
  siteName: "Interdeft",
  siteUrl: "https://interdeft.com/",
  description: "Web development, ecommerce, AI, SEO and GEO services.",
  defaultImage: "/og.jpg",
  services: [
    {
      name: "SEO and GEO",
      description: "Search and AI discovery optimization.",
      path: "/services/seo-geo",
      keywords: ["SEO", "GEO"],
    },
  ],
  pages: [
    {
      title: "Work",
      description: "Selected project work.",
      path: "/work",
    },
  ],
});

describe("geo seo package", () => {
  it("normalizes config and generates metadata", () => {
    const metadata = generateMetadata(site, {
      title: "Home",
      description: "Build better. Sell smarter. Rank higher.",
      path: "/",
    });

    expect(site.siteUrl).toBe("https://interdeft.com");
    expect(metadata.alternates.canonical).toBe("https://interdeft.com/");
    expect(metadata.openGraph.images?.[0]?.url).toBe("https://interdeft.com/og.jpg");
  });

  it("generates llms.txt with services and discovery links", () => {
    const llmsTxt = generateLlmsTxt(site);

    expect(llmsTxt).toContain("# Interdeft");
    expect(llmsTxt).toContain("[AI visibility data](<https://interdeft.com/ai-visibility.json>)");
    expect(llmsTxt).toContain("SEO and GEO");
  });

  it("generates ai visibility json", () => {
    const aiVisibility = generateAiVisibilityJson(site);

    expect(aiVisibility.schemaVersion).toBe("geo-seo/v1");
    expect(aiVisibility.services[0]?.url).toBe("https://interdeft.com/services/seo-geo");
  });

  it("generates an agent manifest", () => {
    const agent = generateAgentJson(site);

    expect(agent.schemaVersion).toBe("agent-profile/v1");
    expect(agent.contentEndpoints.llms).toBe("https://interdeft.com/llms.txt");
    expect(agent.services[0]?.keywords).toEqual(["SEO", "GEO"]);
  });

  it("requires attribution opt-in and migrates stock legacy branding", () => {
    expect(generateBrandingHtml(site)).toBe("");
    const branded = createGeoSeoConfig({ ...site, branding: { enabled: true } });
    expect(generateBrandingText(branded)).toBe("SEO & GEO powered by Interdeft");
    expect(generateBrandingHtml(branded)).toContain("https://interdeft.com");
    expect(generateBrandingFooterHtml(branded)).toContain("geo-seo-branding");
    const legacy = createGeoSeoConfig({
      ...site,
      branding: { enabled: true, label: "SEO & GEO powered by CodeClinch", url: "https://codeclinch.com" },
    });
    expect(generateBrandingFooterHtml(legacy)).toBe("");
    expect(legacy.branding.url).toBe("https://interdeft.com");
  });

  it("calculates a GEO/SEO score", () => {
    const score = calculateGeoSeoScore(site);

    expect(score.maxScore).toBe(100);
    expect(score.percentage).toBeGreaterThan(40);
    expect(score.items.some((item) => item.id === "services" && item.passed)).toBe(true);
  });

  it("generates sitemap xml", () => {
    const sitemap = generateSitemapXml(site);

    expect(sitemap).toContain("<loc>https://interdeft.com/</loc>");
    expect(sitemap).toContain("<loc>https://interdeft.com/work</loc>");
  });

  it("detects AI crawlers and creates logging payloads", () => {
    expect(detectAiCrawler("Mozilla/5.0 GPTBot").crawlerName).toBe("GPTBot");

    const visit = createAiCrawlerVisit({
      userAgent: "Mozilla/5.0 ClaudeBot",
      path: "/services",
      timestamp: "2026-06-24T00:00:00.000Z",
    });

    expect(visit).toEqual({
      type: "ai_crawler_visit",
      crawlerName: "ClaudeBot",
      userAgent: "Mozilla/5.0 ClaudeBot",
      path: "/services",
      timestamp: "2026-06-24T00:00:00.000Z",
    });
  });

  it("detects and installs Next.js App Router files", () => {
    const root = mkdtempSync(join(tmpdir(), "geo-seo-next-"));

    try {
      writeFileSync(
        join(root, "package.json"),
        JSON.stringify({
          dependencies: {
            next: "^15.0.0",
          },
        }),
      );
      writeFileSync(join(root, "package-lock.json"), "{}");
      writeFileSync(join(root, "next.config.js"), "module.exports = {};\n");
      writeFileSync(join(root, "app-placeholder"), "");

      const detectionBefore = detectProject(root);
      expect(detectionBefore.framework).toBe("next-app");

      const result = installGeoSeo({
        root,
        siteName: "Demo Site",
        siteUrl: "https://demo.example",
      });

      expect(result.detection.framework).toBe("next-app");
      expect(result.writes.some((write) => write.path.endsWith("app/llms.txt/route.ts"))).toBe(true);
      expect(result.writes.some((write) => write.path.endsWith("components/GeoSeoBranding.tsx"))).toBe(false);

      const route = readFileSync(join(root, "app/llms.txt/route.ts"), "utf8");
      expect(route).toContain("createNextLlmsTxtRoute");
      expect(route).toContain("../../lib/geo-seo");

      const configModule = readFileSync(join(root, "lib/geo-seo.ts"), "utf8");
      expect(configModule).toContain('../geo-seo.config.json');
      expect(configModule).toContain('from "interdeft-geo"');
    } finally {
      rmSync(root, { recursive: true, force: true });
    }
  });

  it("skips existing files unless backup or force is used", () => {
    const root = mkdtempSync(join(tmpdir(), "geo-seo-static-"));

    try {
      writeFileSync(join(root, "index.html"), "<html></html>");
      installGeoSeo({
        root,
        framework: "static",
        siteName: "Static Site",
        siteUrl: "https://static.example",
      });

      expect(existsSync(join(root, "public/geo-seo-branding.html"))).toBe(false);

      const second = installGeoSeo({
        root,
        framework: "static",
        siteName: "Static Site",
        siteUrl: "https://static.example",
      });

      expect(second.writes.some((write) => write.status === "skipped")).toBe(true);

      const third = installGeoSeo({
        root,
        framework: "static",
        siteName: "Static Site",
        siteUrl: "https://static.example",
        backup: true,
      });

      expect(third.writes.some((write) => write.status === "backed-up")).toBe(true);
    } finally {
      rmSync(root, { recursive: true, force: true });
    }
  });

  it("preserves existing Next.js metadata owners even during forced installs", () => {
    const root = mkdtempSync(join(tmpdir(), "geo-seo-next-conflict-"));
    try {
      mkdirSync(join(root, "app/(site)/sitemap.xml"), { recursive: true });
      mkdirSync(join(root, "public"));
      writeFileSync(join(root, "package.json"), '{"dependencies":{"next":"*"}}');
      writeFileSync(join(root, "app/robots.ts"), "existing robots");
      writeFileSync(join(root, "app/(site)/sitemap.xml/route.js"), "existing sitemap");
      writeFileSync(join(root, "public/llms.txt"), "existing discovery");
      const result = installGeoSeo({ root, force: true });
      expect(result.writes.filter((entry) => entry.status === "skipped")).toHaveLength(3);
      expect(existsSync(join(root, "app/robots.txt/route.ts"))).toBe(false);
      expect(existsSync(join(root, "app/sitemap.xml/route.ts"))).toBe(false);
      expect(existsSync(join(root, "app/llms.txt/route.ts"))).toBe(false);
    } finally {
      rmSync(root, { recursive: true, force: true });
    }
  });

  it("excludes noindex and non-canonical pages from sitemap and discovery", () => {
    const config = createGeoSeoConfig({
      ...site,
      pages: [
        { title: "Private", description: "Hidden", path: "/private", noIndex: true },
        { title: "Duplicate", description: "Duplicate", path: "/duplicate", canonical: "/work" },
        ...site.pages,
      ],
    });
    expect(generateSitemapXml(config)).not.toMatch(/\/(private|duplicate)/);
    expect(generateLlmsTxt(config)).not.toMatch(/\/(private|duplicate)/);
    expect(generateAiVisibilityJson(config).pages.map((page) => page.title)).toEqual(["Work"]);
  });

  it("rejects HTML catch-all responses as discovery endpoints and recognizes WordPress sitemaps", async () => {
    const html = '<title>Example</title><!-- <link rel="canonical" href="https://invalid.test/"> --><link rel = canonical href=https://example.test/><h1>Example</h1>';
    const mockFetch = vi.spyOn(globalThis, "fetch").mockImplementation(async (input) => {
      const path = new URL(String(input)).pathname;
      if (path === "/robots.txt") {
        return new Response("User-agent: *\nSitemap: https://example.test/wp-sitemap.xml", { headers: { "content-type": "text/plain" } });
      }
      if (path === "/wp-sitemap.xml") {
        return new Response('<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><sitemap><loc>https://example.test/posts.xml</loc></sitemap></sitemapindex>');
      }
      return new Response(html, { headers: { "content-type": "text/html", "x-robots-tag": "noindex" } });
    });
    try {
      const result = await auditWebsite("https://example.test");
      expect(result.checks).toMatchObject({ llms: false, aiVisibility: false, agent: false, sitemap: true, robots: true, indexable: false });
      expect(result.checks.canonical).toBe(true);
      expect(result.endpoints.sitemap?.url).toBe("https://example.test/wp-sitemap.xml");
      expect(result.endpoints.llms?.status).toBe(200);
    } finally {
      mockFetch.mockRestore();
    }
  });

  it("generates independent bot policies while preserving site restrictions", () => {
    const robots = generateRobotsTxt(site, { disallow: ["/private"], crawlerPolicies: { GPTBot: "block", "OAI-SearchBot": "inherit" } });
    expect(robots).toContain("User-agent: GPTBot\nDisallow: /\n");
    expect(robots).toContain("User-agent: OAI-SearchBot\nDisallow: /private\n");
  });

  it("keeps Blade interpolation and closing directives inert", () => {
    const result = generateLaravelBladeSeo(site, {
      title: "@endverbatim {{ 7 * 6 }}",
      description: "<?php echo 'unexpected'; ?>",
      noIndex: true,
    });
    expect(result.startsWith("@verbatim\n")).toBe(true);
    expect(result.match(/@endverbatim/g)).toHaveLength(1);
    expect(result).toContain("&#64;endverbatim {{ 7 * 6 }}");
    expect(result).toContain('content="noindex, nofollow"');
    expect(result).not.toContain("<?php");
  });

  it("does not give the starter config a passing setup score", () => {
    expect(calculateGeoSeoScore(createGeoSeoConfig(createStarterConfig())).percentage).toBeLessThan(30);
  });
});
