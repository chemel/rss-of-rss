<?php

namespace App\Model;

/**
 * Result of writing feed urls to a file.
 */
final readonly class SavedFeeds
{
    /**
     * @param list<string> $newUrls urls that were not in the file before this save
     * @param list<string> $allUrls every url now in the file, in file order
     */
    public function __construct(
        public array $newUrls,
        public array $allUrls,
    ) {
    }
}
