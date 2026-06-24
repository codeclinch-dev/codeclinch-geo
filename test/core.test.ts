import { describe, expect, it } from "vitest";
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from "node:fs";
import { join } from "node:path";
import { tmpdir } from "node:os";
import {
  createAiCrawlerVisit,
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
  generateSitemapXml,
  installGeoSeo,
} from "../src";

const site = createGeoSeoConfig({
  siteName: "CodeClinch",
  siteUrl: "https://codeclinch.com/",
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

    expect(site.siteUrl).toBe("https://codeclinch.com");
    expect(metadata.alternates.canonical).toBe("https://codeclinch.com/");
    expect(metadata.openGraph.images?.[0]?.url).toBe("https://codeclinch.com/og.jpg");
  });

  it("generates llms.txt with services and discovery links", () => {
    const llmsTxt = generateLlmsTxt(site);

    expect(llmsTxt).toContain("# CodeClinch");
    expect(llmsTxt).toContain("AI visibility data: https://codeclinch.com/ai-visibility.json");
    expect(llmsTxt).toContain("SEO and GEO");
  });

  it("generates ai visibility json", () => {
    const aiVisibility = generateAiVisibilityJson(site);

    expect(aiVisibility.schemaVersion).toBe("geo-seo/v1");
    expect(aiVisibility.services[0]?.url).toBe("https://codeclinch.com/services/seo-geo");
  });

  it("generates an agent manifest", () => {
    const agent = generateAgentJson(site);

    expect(agent.schemaVersion).toBe("agent-profile/v1");
    expect(agent.contentEndpoints.llms).toBe("https://codeclinch.com/llms.txt");
    expect(agent.services[0]?.keywords).toEqual(["SEO", "GEO"]);
  });

  it("enables CodeClinch branding by default and allows disabling it", () => {
    expect(site.branding.enabled).toBe(true);
    expect(generateBrandingText(site)).toBe("SEO & GEO powered by CodeClinch");
    expect(generateBrandingHtml(site)).toContain("https://codeclinch.com");
    expect(generateBrandingFooterHtml(site)).toContain("geo-seo-branding");

    const whiteLabel = createGeoSeoConfig({
      siteName: "White Label",
      siteUrl: "https://white-label.example",
      description: "White label website optimized for SEO and GEO discovery.",
      branding: {
        enabled: false,
      },
    });

    expect(generateBrandingHtml(whiteLabel)).toBe("");
    expect(generateBrandingFooterHtml(whiteLabel)).toBe("");
  });

  it("calculates a GEO/SEO score", () => {
    const score = calculateGeoSeoScore(site);

    expect(score.maxScore).toBe(100);
    expect(score.percentage).toBeGreaterThan(40);
    expect(score.items.some((item) => item.id === "services" && item.passed)).toBe(true);
  });

  it("generates sitemap xml", () => {
    const sitemap = generateSitemapXml(site);

    expect(sitemap).toContain("<loc>https://codeclinch.com/</loc>");
    expect(sitemap).toContain("<loc>https://codeclinch.com/work</loc>");
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
      expect(result.writes.some((write) => write.path.endsWith("components/GeoSeoBranding.tsx"))).toBe(true);

      const route = readFileSync(join(root, "app/llms.txt/route.ts"), "utf8");
      expect(route).toContain("createNextLlmsTxtRoute");
      expect(route).toContain("../../lib/geo-seo");

      const brandingComponent = readFileSync(join(root, "components/GeoSeoBranding.tsx"), "utf8");
      expect(brandingComponent).toContain("generateBrandingFooterHtml");
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

      expect(readFileSync(join(root, "public/geo-seo-branding.html"), "utf8")).toContain("CodeClinch");

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
});
