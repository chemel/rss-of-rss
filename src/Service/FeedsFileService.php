<?php

namespace App\Service;

use App\Model\SavedFeeds;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

class FeedsFileService
{
    public function __construct(
        private readonly Filesystem $filesystem,
    ) {
    }

    /**
     * Write feed urls to a file, one per line and without duplicates.
     *
     * @param string       $path   the file to write, created along with its directories if missing
     * @param list<string> $urls   the feed urls to save
     * @param bool         $append keep the urls already in the file instead of overwriting it
     *
     * @throws IOException if the file cannot be read or written
     */
    public function save(string $path, array $urls, bool $append = false): SavedFeeds
    {
        $urls = array_values(array_unique($urls));

        // In append mode the file is the history: known urls are kept, in their original order
        $knownUrls = $append ? $this->read($path) : [];
        $newUrls = array_values(array_diff($urls, $knownUrls));
        $allUrls = [...$knownUrls, ...$newUrls];

        $this->filesystem->dumpFile($path, [] === $allUrls ? '' : implode(\PHP_EOL, $allUrls).\PHP_EOL);

        return new SavedFeeds($newUrls, $allUrls);
    }

    /**
     * Read the feed urls saved in a file.
     *
     * @return list<string> the unique, non-empty lines of the file, or an empty list if it does not exist
     *
     * @throws IOException if the file cannot be read
     */
    public function read(string $path): array
    {
        if (!$this->filesystem->exists($path)) {
            return [];
        }

        $lines = array_map(trim(...), preg_split('/\R/', $this->filesystem->readFile($path)));

        return array_values(array_unique(array_filter($lines, static fn (string $line): bool => '' !== $line)));
    }
}
