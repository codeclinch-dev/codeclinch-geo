import type { GeoSeoConfig, RobotsOptions } from "./types";
import { absoluteUrl } from "./url";

export type { RobotsOptions } from "./types";

const policyAgents = ["OAI-SearchBot", "GPTBot", "Claude-SearchBot", "ClaudeBot", "Claude-User", "Google-Extended"] as const;

export function generateRobotsTxt(config: GeoSeoConfig, options: RobotsOptions = config.robots ?? {}): string {
  const agent = options.userAgent ?? "*";
  if (!/^(?:\*|[\w-]+)$/.test(agent)) throw new Error("Invalid robots.txt user agent.");
  const rules = (options.allow ?? []).map((path) => "Allow: " + validatePath(path));
  const disallow = options.disallow?.length ? options.disallow : [""];
  rules.push(...disallow.map((path) => ("Disallow: " + validatePath(path)).trimEnd()));

  const lines = ["User-agent: " + agent, ...rules];
  for (const crawler of policyAgents) {
    const policy = options.crawlerPolicies?.[crawler];
    if (agent.toLowerCase() === crawler.toLowerCase() || !policy) continue;
    if (policy === "block") {
      lines.push("", "User-agent: " + crawler, "Disallow: /");
    } else if (policy === "inherit" && agent === "*") {
      lines.push("", "User-agent: " + crawler, ...rules);
    }
  }
  lines.push("", "Sitemap: " + absoluteUrl(config, "/sitemap.xml"));
  return lines.join("\n") + "\n";
}

function validatePath(path: string): string {
  if (typeof path !== "string" || /[\r\n]/.test(path) || (path !== "" && !path.startsWith("/"))) {
    throw new Error("A robots.txt path must begin with / and fit on one line.");
  }
  return path;
}
