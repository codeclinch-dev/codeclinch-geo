import { writeFileSync, mkdirSync } from "node:fs";
import { join } from "node:path";
import { createGeoSeoConfig, generateStaticFiles } from "../../src";

const site = createGeoSeoConfig({
  siteName: "Client Website",
  siteUrl: "https://example.com",
  description: "A client website optimized for SEO and AI discovery.",
  pages: [
    {
      title: "Services",
      description: "Service overview page.",
      path: "/services",
    },
  ],
});

const outputDir = join(process.cwd(), "public");
mkdirSync(outputDir, { recursive: true });

for (const [filename, content] of Object.entries(generateStaticFiles(site))) {
  writeFileSync(join(outputDir, filename), content);
}
