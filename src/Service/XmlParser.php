<?php

namespace App\Service;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;

class XmlParser
{
    /**
     * Parse the XML string and extract links from it.
     *
     * @param string $xmlString The XML string to parse.
     * @return array An array of links extracted from the XML.
     * @throws \Exception If the XML cannot be parsed.
     */
    public function parseXmlLinks($xmlString): array
    {
        $xml = simplexml_load_string($xmlString);
        $links = [];

        if ($xml === false) {
            throw new \Exception('Failed to parse XML');
        }

        foreach ($xml->channel->item as $item) {
            $links[] = (string)$item->link;
        }

        return $links;
    }

    /**
     * Parse the HTML string and extract feed URLs from it.
     *
     * @param string $html The HTML string to parse.
     * @param string $pageUrl The base URL of the page for resolving relative URLs.
     * @return array An array of feed URLs extracted from the HTML.
     * @throws \Exception If the HTML cannot be parsed.
     */
    public function parseHtmlFeedsUrls(string $html, string $pageUrl): array
    {
        $urls = [];

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
                && $type === 'application/rss+xml'
                && $href !== ''
                && !preg_match('/comment/i', $title . ' ' . $href)
            ) {
                $urls[] = (string) UriResolver::resolve(new Uri($pageUrl), new Uri($href));
            }
        }

        return $urls;
    }
}