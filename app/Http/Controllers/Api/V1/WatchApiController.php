<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Follow;
use App\Services\Alerts\WatchTypes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** /api/v1/watches: the account's watches, over a personal access token (Sanctum). */
class WatchApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $follows = Follow::where('user_id', $request->user()->id)->orderBy('subject_type')->orderBy('subject_slug')->get();

        return response()->json(['data' => $follows->map(fn ($f) => $this->row($f))->all(), 'meta' => ['total' => $follows->count(), 'types' => WatchTypes::all()]]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['type' => ['required', 'in:'.implode(',', WatchTypes::all())], 'subject' => ['required_unless:type,search', 'nullable', 'string', 'max:160', 'regex:/^[A-Za-z0-9._-]+$/'], 'params' => ['nullable', 'array']]);
        $resolved = WatchTypes::resolve($data['type'], (string) ($data['subject'] ?? ''), (array) ($data['params'] ?? []));
        if (! $resolved) {
            return response()->json(['message' => 'Unknown subject for this watch type.'], 422);
        }
        $user = $request->user();
        abort_if($user->entitled('saved.server') === false, 402, 'This feature requires a Pro plan.');
        $follow = Follow::firstOrCreate(['user_id' => $user->id, 'subject_type' => $data['type'], 'subject_slug' => $resolved['slug']], ['label' => $resolved['label'], 'params' => $resolved['params']]);

        return response()->json(['data' => $this->row($follow)], $follow->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $follow = Follow::where('user_id', $request->user()->id)->whereKey($id)->first();
        abort_unless($follow, 404);
        $follow->delete();

        return response()->json(['deleted' => true]);
    }

    private function row(Follow $f): array
    {
        $resolved = WatchTypes::resolve($f->subject_type, $f->subject_slug, (array) ($f->params ?? []));

        return ['id' => $f->id, 'type' => $f->subject_type, 'subject' => $f->subject_slug, 'label' => $f->label ?? $resolved['label'] ?? $f->subject_slug, 'url' => $resolved['url'] ?? null, 'params' => $f->params, 'created_at' => $f->created_at?->toAtomString()];
    }
}
