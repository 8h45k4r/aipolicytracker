<?php

namespace App\Http\Controllers\Backend\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContributorSubmission;
use App\Models\PolicyInstrument;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Requests that belong to the admin shell rather than to any one page: the command
 * palette's record search, and the "not found" answer for an unknown admin address.
 */
class ShellController extends Controller
{
    /** Most rows of one kind the palette shows. */
    public const PER_KIND = 8;

    /** Shortest query that searches; one letter matches half the table and helps no one. */
    public const MIN_QUERY = 2;

    /**
     * Records matching the palette query, each with the admin page it opens. A kind is
     * searched only for an account whose role may open that page, so the palette never
     * offers a link that would answer 403; an account that may open none is refused.
     *
     * Shape: {"query": string, "results": [{"kind", "label", "detail", "url"}]}.
     */
    public function search(Request $request): JsonResponse
    {
        $user = $request->user();
        $kinds = self::searchableKinds($user);
        abort_if($kinds === [], 403);

        $q = Str::limit(trim((string) $request->query('q', '')), 100, '');
        if (mb_strlen($q) < self::MIN_QUERY) {
            return $this->json($q, []);
        }
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($q)).'%';
        $match = fn (Builder $query, array $columns) => $query->where(function (Builder $w) use ($columns, $like) {
            foreach ($columns as $column) {
                $w->orWhereRaw('LOWER('.$column.') LIKE ? ESCAPE ?', [$like, '\\']);
            }
        });

        $results = [];
        if (in_array('user', $kinds, true)) {
            foreach ($match(User::query(), ['name', 'email'])->orderBy('name')->limit(self::PER_KIND)->get() as $u) {
                $results[] = ['kind' => 'user', 'label' => $u->name, 'detail' => $u->email.' · '.$u->adminRoleLabel(), 'url' => route('backend.admin.users.show', $u)];
            }
        }
        if (in_array('policy', $kinds, true)) {
            // "eu ai act" names the record by its short title or slug (eu-ai-act), not its full title.
            $slugLike = '%'.str_replace(['\\', '%', '_', ' '], ['\\\\', '\\%', '\\_', '-'], mb_strtolower($q)).'%';
            $policies = $match(PolicyInstrument::query(), ['title', 'short_title', 'slug'])->orWhereRaw('LOWER(slug) LIKE ? ESCAPE ?', [$slugLike, '\\']);
            foreach ($policies->orderByRaw('CASE WHEN LOWER(short_title) = ? THEN 0 ELSE 1 END', [mb_strtolower($q)])->orderBy('title')->limit(self::PER_KIND)->get(['id', 'title', 'slug', 'review_status', 'published_at']) as $p) {
                $results[] = ['kind' => 'policy', 'label' => $p->title, 'detail' => $p->slug.' · '.str_replace('_', ' ', (string) $p->review_status).($p->published_at ? '' : ' · unpublished'),
                    'url' => route('backend.review.index', ['type' => 'policy', 'q' => $p->slug])];
            }
        }
        if (in_array('submission', $kinds, true)) {
            $submissions = $match(ContributorSubmission::query(), ['summary', 'submitter_email', 'subject_slug'])->latest('id')->limit(self::PER_KIND)->get(['id', 'summary', 'status', 'type', 'created_at']);
            foreach ($submissions as $s) {
                $results[] = ['kind' => 'submission', 'label' => Str::limit($s->summary, 80), 'detail' => '#'.$s->id.' · '.(ContributorSubmission::TYPES[$s->type] ?? $s->type).' · '.str_replace('_', ' ', (string) $s->status),
                    'url' => route('backend.admin.submissions', ['q' => Str::limit($s->summary, 60, '')]).'#submission-'.$s->id];
            }
        }

        return $this->json($q, $results);
    }

    /**
     * The kinds of record this account may search, by the capability of the page each
     * opens: users and roles for users, the review queue for records and submissions.
     *
     * @return list<'user'|'policy'|'submission'>
     */
    public static function searchableKinds(?User $user): array
    {
        if (! $user) {
            return [];
        }

        return array_values(array_filter([
            $user->can('users.manage') ? 'user' : null,
            $user->can('submissions.decide') ? 'policy' : null,
            $user->can('submissions.decide') ? 'submission' : null,
        ]));
    }

    /** Any /backend address no route claims, for a signed-in administrator. */
    public function missing(): never
    {
        abort(404);
    }

    private function json(string $q, array $results): JsonResponse
    {
        // Personal data (names, addresses): never kept by a shared cache or the browser's.
        return response()->json(['query' => $q, 'results' => $results])->header('Cache-Control', 'no-store, private');
    }
}
