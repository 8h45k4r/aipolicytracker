<?php

namespace App\Services\Social;

use App\Enums\ImpactLevel;
use App\Models\ChangeEvent;
use App\Models\SocialPost;
use Illuminate\Support\Collection;

/**
 * Turns new, verified changes into posts and sends the ones that are due.
 *
 * Queueing and sending are separate steps so that in review mode a person
 * reads each post before it goes out, and so that a refused or rate-limited
 * post is kept and retried instead of lost.
 */
class XPublisher
{
    public function __construct(private XClient $client) {}

    /** Changes that should have a post and do not have one yet. */
    public function eligible(): Collection
    {
        $since = now()->subDays((int) config('social.x.max_age_days', 21));
        $levels = $this->impactLevels();

        return ChangeEvent::published()
            ->with(['jurisdiction', 'policyInstrument'])
            ->where('review_status', 'verified')
            ->whereIn('impact_level', $levels)
            ->where('occurred_on', '>=', $since->toDateString())
            ->where(fn ($q) => $q->whereNull('first_published_at')->orWhere('first_published_at', '>=', $since))
            // Template releases are the site's own housekeeping, not policy news.
            ->where('slug', 'not like', 'template-%')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('social_posts')->whereColumn('social_posts.change_event_id', 'change_events.id')->where('network', 'x'))
            ->orderBy('first_published_at')->orderBy('occurred_on')
            ->get();
    }

    /** Create a post for each eligible change: a draft in review mode, queued otherwise. */
    public function queue(): int
    {
        $status = config('social.x.mode') === 'review' ? 'draft' : 'queued';
        $made = 0;
        foreach ($this->eligible() as $change) {
            SocialPost::firstOrCreate(
                ['change_event_id' => $change->id, 'network' => 'x'],
                ['status' => $status, 'text' => ChangePost::compose($change)],
            );
            $made++;
        }

        return $made;
    }

    public function postedThisMonth(): int
    {
        return SocialPost::postedThisMonth()->count();
    }

    public function capReached(): bool
    {
        return $this->postedThisMonth() >= (int) config('social.x.monthly_cap', 40);
    }

    /**
     * Send the queued posts that are due, oldest first, a few per run so a busy
     * day reaches followers spread out rather than as a burst.
     *
     * @return array{posted: int, failed: int, held: string|null}
     */
    public function publishDue(?int $limit = null): array
    {
        $result = ['posted' => 0, 'failed' => 0, 'held' => null];
        if (! config('social.x.enabled')) {
            return ['held' => 'Posting to X is off.'] + $result;
        }
        if (! $this->client->configured()) {
            return ['held' => 'The X keys are not set.'] + $result;
        }
        $due = SocialPost::with('changeEvent')->where('network', 'x')->where('status', 'queued')->orderBy('created_at')->limit($limit ?? (int) config('social.x.per_run', 2))->get();
        foreach ($due as $post) {
            if ($this->capReached()) {
                $result['held'] = 'This month\'s cap of '.(int) config('social.x.monthly_cap').' posts is reached; the rest wait for next month or a higher cap.';
                break;
            }
            $this->send($post) ? $result['posted']++ : $result['failed']++;
        }

        return $result;
    }

    /** Send one post now. True when X accepted it. */
    public function send(SocialPost $post): bool
    {
        $post->increment('attempts');
        try {
            $id = $this->client->post($post->text);
        } catch (XPostFailed $e) {
            $giveUp = ! $e->retryable || $post->attempts >= SocialPost::MAX_ATTEMPTS;
            $post->update(['status' => $giveUp ? 'failed' : 'queued', 'error' => $e->getMessage()]);

            return false;
        }
        $post->update(['status' => 'posted', 'external_id' => $id, 'posted_at' => now(), 'error' => null]);

        return true;
    }

    /** @return list<string> The impact levels at or above the configured minimum. */
    private function impactLevels(): array
    {
        $order = [ImpactLevel::Routine->value, ImpactLevel::High->value, ImpactLevel::Urgent->value];
        $from = array_search(config('social.x.min_impact', 'routine'), $order, true);

        return array_slice($order, $from === false ? 0 : $from);
    }
}
