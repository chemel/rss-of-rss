<?php

namespace App\Tests\Service;

use App\Service\XmlParser;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class XmlParserTest extends KernelTestCase
{
    public function testParseHtmlFeedsUrlsFindsRssAndAtomFeeds(): void
    {
        $html = <<<'HTML'
            <html><head>
                <link rel="alternate" type="application/rss+xml" href="/rss.xml">
                <link rel="alternate" type="application/atom+xml" href="/atom.xml">
                <link rel="alternate" type="Application/Atom+XML" href="https://other.example/feed.atom">
                <link rel="alternate" type="application/atom+xml" title="Comments feed" href="/comments.atom">
                <link rel="alternate" type="application/json" href="/feed.json">
                <link rel="stylesheet" type="application/atom+xml" href="/not-a-feed.xml">
            </head></html>
            HTML;

        $parser = self::getContainer()->get(XmlParser::class);

        self::assertSame(
            ['https://blog.example/rss.xml', 'https://blog.example/atom.xml', 'https://other.example/feed.atom'],
            $parser->parseHtmlFeedsUrls($html, 'https://blog.example/post'),
        );
    }
}
