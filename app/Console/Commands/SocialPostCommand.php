<?php

namespace App\Console\Commands;

use App\Services\Social\ChangePost;
use App\Services\Social\XPublisher;
use Illuminate\Console\Command;

class SocialPostCommand extends Command
{
    protected $signature = 'social:post {--dry-run : Print the posts that would be queued; queue and send nothing}';

    protected $description = 'Queue posts to X for new verified changes, then send the ones that are due';

    public function handle(XPublisher $publisher): int
    {
        if ($this->option('dry-run')) {
            $eligible = $publisher->eligible();
            $this->line($eligible->isEmpty() ? 'No change needs a post.' : $eligible->count().' change(s) would be posted:');
            foreach ($eligible as $change) {
                $text = ChangePost::compose($change);
                $this->line(str_repeat('-', 40)."\n".$text."\n[".ChangePost::weight($text).'/'.ChangePost::LIMIT.']');
            }

            return self::SUCCESS;
        }

        if (! config('social.x.enabled')) {
            $this->line('Posting to X is off; nothing queued or sent.');

            return self::SUCCESS;
        }

        $queued = $publisher->queue();
        $result = $publisher->publishDue();
        $this->line("Queued {$queued}, posted {$result['posted']}, failed {$result['failed']}.".($result['held'] ? ' '.$result['held'] : ''));

        return $result['failed'] > 0 && $result['posted'] === 0 ? self::FAILURE : self::SUCCESS;
    }
}
