# codeclinch-geo

Reusable TypeScript helpers for SEO and GEO, built for existing websites and new builds.

The package helps generate:

- SEO metadata for pages and services
- JSON-LD schema for organizations, websites, services, articles, FAQs, and breadcrumbs
- `llms.txt` for AI discovery
- `ai-visibility.json` for machine-readable GEO context
- `.well-known/agent.json` for a compact machine-readable site profile
- `robots.txt` and sitemap XML
- AI crawler detection and lightweight request logging payloads
- GEO/SEO scoring and live website audits
- optional CodeClinch footer branding for sponsored installs

## Install

```bash
npm install codeclinch-geo
```

## CLI

Beginner automatic setup:

```bash
npx codeclinch-geo install --site-name "CodeClinch" --site-url https://codeclinch.com
```

The installer detects the project type and creates the right files.

Check what will be recommended:

```bash
npx codeclinch-geo doctor
```

Supported detection:

- Next.js App Router
- Next.js Pages Router
- Vite/static frontend projects
- Laravel
- plain static HTML

Safe file behavior:

```bash
npx codeclinch-geo install
```

Existing files are skipped by default.

```bash
npx codeclinch-geo install --backup
```

Existing files are backed up as `.geo-seo-backup` before replacement.

```bash
npx codeclinch-geo install --force
```

Existing files are overwritten.

Initialize a config file:

```bash
npx codeclinch-geo init --site-name "CodeClinch" --site-url https://codeclinch.com
```

Generate discovery files into `public/`:

```bash
npx codeclinch-geo generate
```

This creates:

```txt
public/llms.txt
public/ai-visibility.json
public/.well-known/agent.json
public/robots.txt
public/sitemap.xml
```

For a Next.js App Router project, `install` creates route handlers instead:

```txt
geo-seo.config.json
lib/geo-seo.ts or src/lib/geo-seo.ts
components/GeoSeoBranding.tsx or src/components/GeoSeoBranding.tsx
app/llms.txt/route.ts
app/ai-visibility.json/route.ts
app/.well-known/agent.json/route.ts
app/robots.txt/route.ts
app/sitemap.xml/route.ts
```

For Laravel, `install` creates public discovery files plus:

```txt
resources/views/partials/geo-seo.blade.php
resources/views/partials/geo-seo-branding.blade.php
```

## CodeClinch Branding

Branding is enabled by default for sponsored installs:

```ts
const site = createGeoSeoConfig({
  siteName: "Client Website",
  siteUrl: "https://example.com",
  description: "Client website description.",
  branding: {
    enabled: true,
    label: "SEO & GEO powered by CodeClinch",
    url: "https://codeclinch.com",
    placement: "footer",
  },
});
```

Disable it for white-label clients:

```ts
const site = createGeoSeoConfig({
  siteName: "Client Website",
  siteUrl: "https://example.com",
  description: "Client website description.",
  branding: {
    enabled: false,
  },
});
```

Use the helper anywhere:

```ts
import { generateBrandingFooterHtml } from "codeclinch-geo";

const footerHtml = generateBrandingFooterHtml(site);
```

Score the local config:

```bash
npx codeclinch-geo score
```

Audit a live website:

```bash
npx codeclinch-geo audit https://example.com
```

## Basic Usage

```ts
import {
  createGeoSeoConfig,
  generateAgentJson,
  generateMetadata,
  generateOrganizationSchema,
  generateLlmsTxt,
  generateAiVisibilityJson,
} from "codeclinch-geo";

const site = createGeoSeoConfig({
  siteName: "CodeClinch",
  siteUrl: "https://codeclinch.com",
  description: "Web development, ecommerce, AI, SEO and GEO services.",
  defaultImage: "/og-image.jpg",
  organization: {
    name: "CodeClinch",
    logo: "/logo.png",
    sameAs: ["https://www.linkedin.com/company/codeclinch"],
  },
  services: [
    {
      name: "Web Development",
      description: "Fast, conversion-focused websites built for SEO and AI discovery.",
      path: "/services/web-development",
      keywords: ["web development", "Next.js", "SEO", "GEO"],
    },
  ],
});

const metadata = generateMetadata(site, {
  title: "Web Development Company",
  description: "Build better, sell smarter, and rank higher with CodeClinch.",
  path: "/",
});

const organizationSchema = generateOrganizationSchema(site);
const llmsTxt = generateLlmsTxt(site);
const aiVisibility = generateAiVisibilityJson(site);
const agentProfile = generateAgentJson(site);
```

## Next.js App Router

Create `app/llms.txt/route.ts`:

```ts
import { generateLlmsTxt } from "codeclinch-geo";
import { site } from "@/lib/geo-seo";

export function GET() {
  return new Response(generateLlmsTxt(site), {
    headers: {
      "content-type": "text/plain; charset=utf-8",
    },
  });
}
```

Create `app/ai-visibility.json/route.ts`:

```ts
import { generateAiVisibilityJson } from "codeclinch-geo";
import { site } from "@/lib/geo-seo";

export function GET() {
  return Response.json(generateAiVisibilityJson(site));
}
```

Create `app/.well-known/agent.json/route.ts`:

```ts
import { createNextAgentRoute } from "codeclinch-geo";
import { site } from "@/lib/geo-seo";

export const GET = createNextAgentRoute(site);
```

Use metadata in a page:

```ts
import { generateMetadata as createPageMetadata } from "codeclinch-geo";
import { site } from "@/lib/geo-seo";

export const metadata = createPageMetadata(site, {
  title: "Ecommerce Development",
  description: "Online stores built for search, AI discovery, speed, and conversion.",
  path: "/services/ecommerce-development",
});
```

## Static or Laravel Sites

You can generate text files at build time:

```ts
import {
  createGeoSeoConfig,
  generateLlmsTxt,
  generateAiVisibilityJson,
  generateRobotsTxt,
  generateSitemapXml,
  generateAgentJson,
} from "codeclinch-geo";

const site = createGeoSeoConfig({
  siteName: "Client Website",
  siteUrl: "https://example.com",
  description: "Client website description.",
});

console.log(generateLlmsTxt(site));
console.log(JSON.stringify(generateAiVisibilityJson(site), null, 2));
console.log(JSON.stringify(generateAgentJson(site), null, 2));
console.log(generateRobotsTxt(site));
console.log(generateSitemapXml(site));
```

## Development

```bash
npm install
npm run typecheck
npm test
npm run build
```
