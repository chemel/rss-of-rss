<?php

namespace App\Service;

use GuzzleHttp\Psr7\Exception\MalformedUriException;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;

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
     * @return list<string> feed URLs extracted from the HTML
     *
     * @throws \Exception if the HTML cannot be parsed
     */
    public function parseHtmlFeedsUrls(string $html, string $pageUrl): array
    {
        $urls = [];

        // DOMDocument::loadHTML() throws a ValueError on an empty string
        if ('' === trim($html)) {
            return $urls;
        }

        $document = new \DOMDocument();
        $previousErrorSetting = libxml_use_internal_errors(true);

        try {
            $parsed = $document->loadHTML($html);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorSetting);
        }

        if (!$parsed) {
            throw new \Exception('Failed to parse HTML');
        }

        foreach ($document->getElementsByTagName('link') as $link) {
            $relations = preg_split('/\s+/', strtolower(trim($link->getAttribute('rel'))));
            $type = strtolower(trim($link->getAttribute('type')));
            $href = trim($link->getAttribute('href'));
            $title = $link->getAttribute('title');

            if (
                in_array('alternate', $relations, true)
                && 'application/rss+xml' === $type
                && '' !== $href
                && !preg_match('/comment/i', $title.' '.$href)
            ) {
                try {
                    $urls[] = (string) UriResolver::resolve(new Uri($pageUrl), new Uri($href));
                } catch (MalformedUriException) {
                    // Skip malformed hrefs instead of aborting the whole page
                    continue;
                }
            }
        }

        return $urls;
    }

    private static function isHttpUrl(string $url): bool
    {
        $scheme = parse_url($url, \PHP_URL_SCHEME);

        return \is_string($scheme)
            && \in_array(strtolower($scheme), ['http', 'https'], true)
            && false !== filter_var($url, \FILTER_VALIDATE_URL);
    }
}
