# Interdeft SEO & AI Search

Interdeft brings discovery exports, crawler policy, SEO diagnostics, and optional metadata controls to WordPress.

## Install or upgrade

1. Upload `interdeft-geo.zip` in **Plugins → Add Plugin → Upload Plugin**. Select replacement for an existing installation.
2. Activate **Interdeft SEO & AI Search** and open its top-level admin menu.
3. In **AI Visibility**, enable discovery exports and mark individual public pages for inclusion. Use pretty permalinks for the root discovery URLs.
4. Review the generated `robots.txt` and XML sitemap. A web-server or CDN-managed robots file takes precedence over WordPress's virtual file.

The existing plugin folder and main file, stored settings, per-page meta, sitemap URLs, and breadcrumbs shortcode keep their previous identifiers. `[interdeft_breadcrumbs]` is available for new content. The legacy XML sitemap URL redirects to the active sitemap provider. Stock footer attribution is reset to opt-in, and custom attribution is retained. The upgrade discards legacy logs containing full request URLs. New optional logs aggregate normalized paths for 30 days.

## Search and discovery

- WordPress owns the sitemap index, canonical, and robots output on sites without a dedicated SEO plugin. Interdeft extends those outputs and publishes its own HTML sitemap.
- Interdeft yields metadata and schema output when it detects a supported SEO plugin. It checks per-page indexability with Yoast or Rank Math. For other SEO owners it omits page entries unless an integration supplies the `interdeft_geo_external_post_discoverable` filter.
- Public discovery requires an indexable site, a published and unprotected canonical page, and an editor's explicit selection. The optional endpoints are `/llms.txt`, `/ai-visibility.json`, and `/.well-known/agent.json`.
- WooCommerce owns Product schema and supplies live prices, inventory, and ratings. Interdeft can enrich that markup with WooCommerce's product brands.
- Crawler policy keeps existing named rules. OpenAI crawl events can be verified against provider IP ranges. All other user-agent matches are labeled accordingly.
- The dashboard and reports describe configuration and sampled findings. Consult your webmaster tools for measured search and AI visibility.

## AI suggestions and privacy

AI suggestions are optional and require admin consent and a provider key. A suggestion sends the site name, URL, summary, and services to the selected provider. Connection tests and suggestion requests count toward the UTC monthly limit. Zero blocks AI requests. Suggestions remain drafts.

For server-managed credentials, define `INTERDEFT_GEO_OPENAI_API_KEY` or `INTERDEFT_GEO_OPENROUTER_API_KEY` in `wp-config.php`. These take precedence over stored keys. See [OpenAI's privacy policy](https://openai.com/policies/privacy-policy/) and [OpenRouter's privacy policy](https://openrouter.ai/privacy).

## Build the ZIP

Run `npm run pack:wordpress` from the repository root. The ZIP is written to `wordpress-plugin/interdeft-geo.zip`.
