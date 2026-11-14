# Interdeft SEO & AI Search

The `interdeft-geo` npm package generates SEO metadata, Schema.org JSON-LD, sitemaps, crawler policies, and optional discovery files for Next.js, Laravel, Vite, and static websites. It also provides a CLI to audit the HTML and discovery endpoints a site serves.

## Install

Node.js 20.19 or later is required.

```bash
npm install interdeft-geo
npx interdeft-geo doctor
npx interdeft-geo install --site-name "Example Site" --site-url https://example.com
```

Edit `geo-seo.config.json` with your public site details and canonical pages. For Next.js App Router, generated route handlers read that JSON through `lib/geo-seo.ts` (or `src/lib/geo-seo.ts`). Existing metadata files, public files, and route handlers keep ownership of their URLs. For Pages Router, Vite, Laravel, and static sites, the installer writes discovery files to `public/`. Laravel also gets a Blade partial for the matching page's head.

```bash
npx interdeft-geo init --site-name "Example Site" --site-url https://example.com
npx interdeft-geo generate --out public
npx interdeft-geo score
npx interdeft-geo audit https://example.com
```

`init`, `install`, and `generate` skip files already present. Use `--backup` to save a numbered backup before replacement, or `--force` to replace the exact output file. When generated files share a URL with a different Next.js route or metadata file, the installer leaves that URL with its existing owner.

## TypeScript usage

```ts
import {
  createGeoSeoConfig,
  generateMetadata,
  generateRobotsTxt,
  generateSitemapXml,
  generateLlmsTxt,
} from "interdeft-geo";

const site = createGeoSeoConfig({
  siteName: "Example Site",
  siteUrl: "https://example.com",
  description: "Repair and maintenance for commercial HVAC systems.",
  pages: [
    { title: "Emergency repairs", description: "24-hour HVAC repair.", path: "/repair" },
    { title: "Request received", description: "Confirmation page.", path: "/thanks", noIndex: true },
  ],
  robots: {
    disallow: ["/account"],
    crawlerPolicies: { "OAI-SearchBot": "inherit", GPTBot: "block" },
  },
});

const metadata = generateMetadata(site, site.pages[0]!);
const robots = generateRobotsTxt(site);
const sitemap = generateSitemapXml(site);
const llms = generateLlmsTxt(site);
```

Use `canonical` on a page entry to specify an alternate canonical URL. Noncanonical and `noIndex` pages are excluded from page lists in the sitemap and discovery exports. The CLI audit checks rendered metadata and validates discovery response bodies, including sitemaps advertised in `robots.txt`. The config score is a setup checklist.

`llms.txt` follows the Markdown proposal. `ai-visibility.json` and `/.well-known/agent.json` are optional Interdeft exports. Google Search eligibility follows its normal indexing and snippet rules.

### Upgrade from the former npm package

Install `interdeft-geo` and update imports and package scripts to the new package name. The `geo-seo` and `codeclinch-geo` binary aliases remain available from the new package. Keep your existing JSON config. For an old Next.js scaffold, migrate its hardcoded library module to read the config file and keep any framework-owned robots or sitemap routes. Public attribution is opt-in with `branding.enabled: true`. Stock legacy branding is migrated to Interdeft and requires a new opt-in. Explicit custom branding is retained.

## WordPress plugin

The installable ZIP is built from the tracked source under `wordpress-plugin/codeclinch-geo/`. The directory and main filename keep the existing WordPress plugin identity. See the [WordPress installation and upgrade guide](wordpress-plugin/codeclinch-geo/README.md).

```bash
npm run pack:wordpress
```

## Development

```bash
npm ci
npm run typecheck
npm test
npm run build
npm pack --dry-run
```
