<?php

namespace App\Service;

use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Link;

class XmlParser
{
    /**
     * Parse the XML string and extract links from it.
     *
     * @param string $xmlString the XML string to parse
     *
     * @return list<string> absolute http(s) links extracted from the XML
     *
     * @throws \Exception if the XML cannot be parsed or is not an RSS feed
     */
    public function parseXmlLinks(string $xmlString): array
    {
        $previousErrorSetting = libxml_use_internal_errors(true);

        try {
            $xml = simplexml_load_string($xmlString);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorSetting);
        }

        if (false === $xml) {
            throw new \Exception('Failed to parse XML');
        }

        if (!isset($xml->channel)) {
            throw new \Exception('Not an RSS feed: missing <channel> element');
        }

        $links = [];

        foreach ($xml->channel->item as $item) {
            $link = trim((string) $item->link);

            if (self::isHttpUrl($link)) {
                $links[] = $link;
            }
        }

        return $links;
    }

    /**
     * Parse the HTML string and extract feed URLs from it.
     *
     * @param string $html    the HTML string to parse
     * @param string $pageUrl the base URL of the page for resolving relative URLs
     *
     * @return list<string> absolute http(s) feed URLs extracted from the HTML
     */
    public function parseHtmlFeedsUrls(string $html, string $pageUrl): array
    {
        $urls = [];

        if ('' === trim($html)) {
            return $urls;
        }

        // The crawler parses with the native HTML5 parser, detects the charset and honors <base href>
        $crawler = new Crawler($html, $pageUrl);

        foreach ($crawler->filterXPath('descendant-or-self::link') as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            $relations = preg_split('/\s+/', strtolower(trim($node->getAttribute('rel'))));
            $type = strtolower(trim($node->getAttribute('type')));
            $href = trim($node->getAttribute('href'));
            $title = $node->getAttribute('title');

            if (
                in_array('alternate', $relations, true)
                && 'application/rss+xml' === $type
                && '' !== $href
                && !preg_match('/comment/i', $title.' '.$href)
            ) {
                try {
                    $url = new Link($node, $crawler->getBaseHref())->getUri();
                } catch (\InvalidArgumentException|\LogicException) {
                    // Skip unresolvable hrefs instead of aborting the whole page
                    continue;
                }

                // Scheme check only: feed URLs may legitimately contain non-ASCII characters
                if (self::hasHttpScheme($url)) {
                    $urls[] = $url;
                }
            }
        }

        return $urls;
    }

    private static function isHttpUrl(string $url): bool
    {
        return self::hasHttpScheme($url) && false !== filter_var($url, \FILTER_VALIDATE_URL);
    }

    private static function hasHttpScheme(string $url): bool
    {
        $parts = parse_url($url);

        if (!\is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        // Brackets are only valid around an IPv6 literal (rejects hosts like "[bad")
        if (
            str_contains($parts['host'], '[')
            && false === filter_var(trim($parts['host'], '[]'), \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV6)
        ) {
            return false;
        }

        return \in_array(strtolower($parts['scheme']), ['http', 'https'], true);
    }
}
