import type { GeneratedMetadata, GeoSeoConfig, MetadataInput } from "./types";
import { absoluteUrl } from "./url";

export function generateMetadata(config: GeoSeoConfig, page: MetadataInput): GeneratedMetadata {
  const url = absoluteUrl(config, page.path);
  const image = page.image ?? config.defaultImage;
  const absoluteImage = image ? absoluteUrl(config, image) : undefined;

  return {
    title: page.title,
    description: page.description,
    alternates: {
      canonical: url,
    },
    openGraph: {
      title: page.title,
      description: page.description,
      url,
      siteName: config.siteName,
      locale: config.locale,
      type: "website",
      images: absoluteImage ? [{ url: absoluteImage, alt: page.title }] : undefined,
    },
    twitter: {
      card: absoluteImage ? "summary_large_image" : "summary",
      title: page.title,
      description: page.description,
      images: absoluteImage ? [absoluteImage] : undefined,
    },
    robots: page.noIndex ? { index: false, follow: false } : undefined,
    keywords: page.keywords,
  };
}
