import type { GeoSeoConfig, MetadataInput } from "../types";
import { generateAgentJson } from "../agent";
import { generateAiVisibilityJson } from "../ai-visibility";
import { generateLlmsTxt } from "../llms";
import { generateMetadata } from "../metadata";
import { generateRobotsTxt, type RobotsOptions } from "../robots";
import { generateSitemapXml } from "../sitemap";

export function createNextMetadata(config: GeoSeoConfig, page: MetadataInput) {
  return generateMetadata(config, page);
}

export function createNextLlmsTxtRoute(config: GeoSeoConfig) {
  return function GET() {
    return new Response(generateLlmsTxt(config), {
      headers: {
        "content-type": "text/plain; charset=utf-8",
      },
    });
  };
}

export function createNextAiVisibilityRoute(config: GeoSeoConfig) {
  return function GET() {
    return Response.json(generateAiVisibilityJson(config));
  };
}

export function createNextAgentRoute(config: GeoSeoConfig) {
  return function GET() {
    return Response.json(generateAgentJson(config));
  };
}

export function createNextRobotsRoute(config: GeoSeoConfig, options?: RobotsOptions) {
  return function GET() {
    return new Response(generateRobotsTxt(config, options), {
      headers: {
        "content-type": "text/plain; charset=utf-8",
      },
    });
  };
}

export function createNextSitemapRoute(config: GeoSeoConfig) {
  return function GET() {
    return new Response(generateSitemapXml(config), {
      headers: {
        "content-type": "application/xml; charset=utf-8",
      },
    });
  };
}
