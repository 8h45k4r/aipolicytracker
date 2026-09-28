<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes every rendered social card so the next request draws it again.
 *
 * Not needed after a redesign (the design version is part of each card's key and
 * URL), but useful after a font or brand-asset change on the host, which the
 * drawing code cannot see.
 */
class SocialClearCommand extends Command
{
    protected $signature = 'social:clear';

    protected $description = 'Delete cached social preview cards so they are redrawn on the next request';

    public function handle(): int
    {
        $disk = Storage::disk(config('social.cache_disk'));
        $dir = trim((string) config('social.cache_path'), '/');
        $files = $disk->allFiles($dir);
        $disk->delete($files);
        Cache::forget('seo.corpus-version');
        $this->info(count($files).' cached card(s) deleted; they are redrawn on the next request.');

        return self::SUCCESS;
    }
}
