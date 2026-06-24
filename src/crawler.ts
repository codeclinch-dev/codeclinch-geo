import type { AiCrawlerMatch, AiCrawlerVisit } from "./types";

const AI_CRAWLERS = [
  ["GPTBot", /gptbot/i],
  ["ChatGPT-User", /chatgpt-user/i],
  ["OpenAI SearchBot", /oai-searchbot/i],
  ["ClaudeBot", /claudebot/i],
  ["Claude-User", /claude-user/i],
  ["PerplexityBot", /perplexitybot/i],
  ["Google-Extended", /google-extended/i],
  ["Applebot", /applebot/i],
  ["CCBot", /ccbot/i],
  ["Bytespider", /bytespider/i],
] as const;

export function detectAiCrawler(userAgent = ""): AiCrawlerMatch {
  const match = AI_CRAWLERS.find(([, pattern]) => pattern.test(userAgent));

  if (!match) {
    return { isAiCrawler: false };
  }

  return {
    isAiCrawler: true,
    crawlerName: match[0],
  };
}

export function createAiCrawlerVisit(input: { userAgent: string; path: string; timestamp?: string | Date }): AiCrawlerVisit | undefined {
  const match = detectAiCrawler(input.userAgent);

  if (!match.isAiCrawler || !match.crawlerName) {
    return undefined;
  }

  return {
    type: "ai_crawler_visit",
    crawlerName: match.crawlerName,
    userAgent: input.userAgent,
    path: input.path,
    timestamp: input.timestamp instanceof Date ? input.timestamp.toISOString() : input.timestamp ?? new Date().toISOString(),
  };
}
