<?php

namespace App\Tests\Service;

use App\Service\FeedsFileService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;

class FeedsFileServiceTest extends KernelTestCase
{
    private string $file;
    private FeedsFileService $service;

    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir().'/rss-of-rss-'.bin2hex(random_bytes(6)).'/feeds.txt';
        $this->service = self::getContainer()->get(FeedsFileService::class);
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove(\dirname($this->file));

        parent::tearDown();
    }

    public function testSaveWritesUniqueUrlsAndOverwritesTheFile(): void
    {
        new Filesystem()->dumpFile($this->file, "https://old.example/rss\n");

        $saved = $this->service->save($this->file, ['https://a.example/feed', 'https://b.example/feed', 'https://a.example/feed']);

        self::assertSame(['https://a.example/feed', 'https://b.example/feed'], $saved->newUrls);
        self::assertSame(['https://a.example/feed', 'https://b.example/feed'], $saved->allUrls);
        self::assertSame(['https://a.example/feed', 'https://b.example/feed'], $this->service->read($this->file));
    }

    public function testSaveInAppendModeKeepsKnownUrlsAndReportsOnlyNewOnes(): void
    {
        // No trailing newline and a blank line: the history file may have been edited by hand
        new Filesystem()->dumpFile($this->file, "https://old.example/rss\n\nhttps://a.example/feed");

        $saved = $this->service->save($this->file, ['https://a.example/feed', 'https://b.example/feed'], append: true);

        self::assertSame(['https://b.example/feed'], $saved->newUrls);
        self::assertSame(['https://old.example/rss', 'https://a.example/feed', 'https://b.example/feed'], $saved->allUrls);
        self::assertSame(
            "https://old.example/rss\nhttps://a.example/feed\nhttps://b.example/feed\n",
            str_replace(\PHP_EOL, "\n", file_get_contents($this->file)),
        );
    }

    public function testReadReturnsAnEmptyListWhenTheFileDoesNotExist(): void
    {
        self::assertSame([], $this->service->read($this->file));
    }
}
