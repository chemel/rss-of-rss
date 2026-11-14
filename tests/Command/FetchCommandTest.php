<?php

namespace App\Tests\Command;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class FetchCommandTest extends KernelTestCase
{
    private const string RSS_OF_RSS = <<<'XML'
        <?xml version="1.0"?>
        <rss version="2.0"><channel>
            <item><link>https://one.example/</link></item>
            <item><link>https://two.example/</link></item>
            <item><link>https://down.example/</link></item>
        </channel></rss>
        XML;

    private string $outputFile;

    protected function setUp(): void
    {
        $this->outputFile = sys_get_temp_dir().'/rss-of-rss-'.bin2hex(random_bytes(6)).'/feeds.txt';
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove(\dirname($this->outputFile));

        parent::tearDown();
    }

    public function testOutputOptionSavesUniqueFeedUrlsOnePerLine(): void
    {
        $tester = $this->createTester();
        $exitCode = $tester->execute(['url' => 'https://rss-of-rss.example/feed.xml', '--output' => $this->outputFile]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertFileExists($this->outputFile);
        self::assertSame(
            "https://one.example/feed.xml\nhttps://one.example/other.xml\nhttps://two.example/rss\n",
            str_replace(\PHP_EOL, "\n", file_get_contents($this->outputFile)),
        );
        self::assertStringContainsString('Saved 3 feed urls', $tester->getDisplay());
    }

    public function testNoFileIsWrittenWithoutTheOption(): void
    {
        $tester = $this->createTester();
        $exitCode = $tester->execute(['url' => 'https://rss-of-rss.example/feed.xml']);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertFileDoesNotExist($this->outputFile);
        self::assertStringNotContainsString('Saved', $tester->getDisplay());
    }

    public function testAppendOptionKeepsExistingUrlsAndAddsOnlyNewOnes(): void
    {
        // No trailing newline and a blank line: the history file may have been edited by hand
        new Filesystem()->dumpFile($this->outputFile, "https://old.example/rss\n\nhttps://one.example/feed.xml");

        $tester = $this->createTester();
        $exitCode = $tester->execute([
            'url' => 'https://rss-of-rss.example/feed.xml',
            '--output' => $this->outputFile,
            '--append' => true,
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame(
            "https://old.example/rss\nhttps://one.example/feed.xml\nhttps://one.example/other.xml\nhttps://two.example/rss\n",
            str_replace(\PHP_EOL, "\n", file_get_contents($this->outputFile)),
        );
        self::assertStringContainsString('Added 2 new feed urls', $tester->getDisplay());
        self::assertStringContainsString('(4 in total)', $tester->getDisplay());
        self::assertStringContainsString('* https://one.example/other.xml', $tester->getDisplay());
        self::assertStringNotContainsString('* https://one.example/feed.xml', $tester->getDisplay());
    }

    public function testAppendOptionCreatesTheFileWhenItDoesNotExist(): void
    {
        $tester = $this->createTester();
        $exitCode = $tester->execute([
            'url' => 'https://rss-of-rss.example/feed.xml',
            '--output' => $this->outputFile,
            '--append' => true,
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame(
            "https://one.example/feed.xml\nhttps://one.example/other.xml\nhttps://two.example/rss\n",
            str_replace(\PHP_EOL, "\n", file_get_contents($this->outputFile)),
        );
        self::assertStringContainsString('Added 3 new feed urls', $tester->getDisplay());
    }

    public function testAppendOptionRequiresTheOutputOption(): void
    {
        $tester = $this->createTester();
        $exitCode = $tester->execute(['url' => 'https://rss-of-rss.example/feed.xml', '--append' => true]);

        self::assertSame(Command::INVALID, $exitCode);
        self::assertStringContainsString('requires the --output option', $tester->getDisplay());
    }

    private function createTester(): CommandTester
    {
        $pages = [
            'https://rss-of-rss.example/feed.xml' => new MockResponse(self::RSS_OF_RSS),
            'https://one.example/' => new MockResponse(
                '<html><head>'
                .'<link rel="alternate" type="application/rss+xml" href="/feed.xml">'
                .'<link rel="alternate" type="application/rss+xml" href="/other.xml">'
                // Duplicate: must be saved only once
                .'<link rel="alternate" type="application/rss+xml" href="https://one.example/feed.xml">'
                .'</head></html>',
            ),
            'https://two.example/' => new MockResponse(
                '<html><head><link rel="alternate" type="application/rss+xml" href="rss"></head></html>',
            ),
            'https://down.example/' => new MockResponse('', ['http_code' => 500]),
        ];

        self::bootKernel();
        self::getContainer()->set(
            HttpClientInterface::class,
            new MockHttpClient(static fn (string $method, string $url): MockResponse => $pages[$url]),
        );

        return new CommandTester(new Application(self::$kernel)->find('app:fetch'));
    }
}
