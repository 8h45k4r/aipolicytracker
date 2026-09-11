<?php

namespace App\Http\Controllers\Backend\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tool;
use App\Models\ToolFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Admin CRUD for the free-tool library: tools, their files and publication status. */
class ToolController extends Controller
{
    public function index(Request $request): View
    {
        $status = in_array($request->query('status'), array_keys(Tool::STATUSES), true) ? $request->query('status') : null;
        $tools = Tool::withCount(['files', 'downloads'])->when($status, fn ($q) => $q->where('status', $status))->orderBy('sort_order')->orderBy('title')->get();
        $counts = Tool::selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');

        return view('backend.admin.tools.index', compact('tools', 'status', 'counts'));
    }

    public function create(): View
    {
        return view('backend.admin.tools.form', ['tool' => new Tool(['status' => 'draft', 'version' => '1.0', 'updated_on' => now(), 'fields' => [], 'instructions' => [], 'frameworks' => [], 'topics' => [], 'related_guides' => [], 'related_policies' => []]), 'guides' => $this->guideOptions(), 'tools' => Tool::orderBy('title')->get(['slug', 'title'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $tool = Tool::create($data + ['updated_by' => $request->user()->id]);

        return redirect()->route('backend.admin.tools.edit', $tool)->with('success', 'Tool created as '.$tool->status.'. Upload at least one file before publishing.');
    }

    public function edit(Tool $tool): View
    {
        $tool->load('files');

        return view('backend.admin.tools.form', ['tool' => $tool, 'guides' => $this->guideOptions(), 'tools' => Tool::where('id', '!=', $tool->id)->orderBy('title')->get(['slug', 'title'])]);
    }

    public function update(Request $request, Tool $tool): RedirectResponse
    {
        $data = $this->validated($request, $tool);
        if ($data['status'] === 'published' && ! $tool->files()->where('is_active', true)->exists()) {
            return back()->withInput()->withErrors(['status' => 'Upload at least one active file before publishing.']);
        }
        $tool->update($data + ['updated_by' => $request->user()->id]);

        return redirect()->route('backend.admin.tools.edit', $tool)->with('success', 'Tool saved.');
    }

    /** Archive rather than delete: download records keep their history. */
    public function destroy(Tool $tool): RedirectResponse
    {
        $tool->update(['status' => 'archived']);

        return redirect()->route('backend.admin.tools.index')->with('success', $tool->title.' archived. It is no longer listed or downloadable; existing download records are kept.');
    }

    public function fileStore(Request $request, Tool $tool): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:'.implode(',', ToolFile::ALLOWED)],
            'label' => ['nullable', 'string', 'max:40'],
            'version' => ['nullable', 'string', 'max:16'],
        ]);
        $upload = $data['file'];
        $ext = strtolower($upload->getClientOriginalExtension());
        $name = Str::slug(pathinfo($upload->getClientOriginalName(), PATHINFO_FILENAME)).'.'.$ext;
        $path = 'tools/'.$tool->slug.'/'.$name;
        Storage::disk(ToolFile::DISK)->putFileAs('tools/'.$tool->slug, $upload, $name);
        $abs = Storage::disk(ToolFile::DISK)->path($path);
        $tool->files()->updateOrCreate(['file_name' => $name], [
            'label' => $data['label'] ?: (Tool::LABELS[$ext] ?? strtoupper($ext)), 'disk_path' => $path, 'mime' => $upload->getClientMimeType(),
            'size' => filesize($abs), 'checksum' => hash_file('sha256', $abs), 'version' => $data['version'] ?: $tool->version, 'is_active' => true,
            'sort_order' => ($tool->files()->max('sort_order') ?? 0) + 10,
        ]);
        $tool->touch();

        return back()->with('success', $name.' uploaded.');
    }

    public function fileToggle(Tool $tool, ToolFile $file): RedirectResponse
    {
        abort_unless($file->tool_id === $tool->id, 404);
        $file->update(['is_active' => ! $file->is_active]);

        return back()->with('success', $file->file_name.($file->is_active ? ' is active.' : ' is inactive; it is no longer offered.'));
    }

    public function fileDestroy(Tool $tool, ToolFile $file): RedirectResponse
    {
        abort_unless($file->tool_id === $tool->id, 404);
        Storage::disk(ToolFile::DISK)->delete($file->disk_path);
        $file->delete();

        return back()->with('success', 'File removed.');
    }

    /** Admin preview of a stored file (no download record). */
    public function fileDownload(Tool $tool, ToolFile $file): BinaryFileResponse
    {
        abort_unless($file->tool_id === $tool->id && $file->exists(), 404);

        return response()->download($file->absolutePath(), $file->file_name, ['Cache-Control' => 'private, no-store']);
    }

    private function validated(Request $request, ?Tool $tool = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:tools,slug'.($tool ? ','.$tool->id : '')],
            'type' => ['required', 'in:'.implode(',', array_keys(Tool::TYPES))],
            'status' => ['required', 'in:'.implode(',', array_keys(Tool::STATUSES))],
            'short' => ['required', 'string', 'max:300'],
            'purpose' => ['nullable', 'string', 'max:4000'],
            'fields_text' => ['nullable', 'string', 'max:8000'],
            'instructions_text' => ['nullable', 'string', 'max:4000'],
            'frameworks' => ['nullable', 'array'], 'frameworks.*' => ['in:'.implode(',', array_keys(config('resources.frameworks')))],
            'topics' => ['nullable', 'array'], 'topics.*' => ['in:'.implode(',', array_keys(config('resources.topics')))],
            'related_guides' => ['nullable', 'array'], 'related_guides.*' => ['in:'.implode(',', array_keys(config('content.guides', [])))],
            'related_policies_text' => ['nullable', 'string', 'max:500'],
            'next_slug' => ['nullable', 'string', 'max:120', 'exists:tools,slug'],
            'version' => ['required', 'string', 'max:16'],
            'updated_on' => ['nullable', 'date'],
            'featured' => ['nullable', 'boolean'],
            'seo_title' => ['nullable', 'string', 'max:160'],
            'seo_description' => ['nullable', 'string', 'max:300'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
        ]);
        $lines = fn (?string $t) => array_values(array_filter(array_map('trim', preg_split('/\r?\n/', (string) $t))));
        $fields = [];
        foreach ($lines($data['fields_text'] ?? '') as $line) {
            [$name, $desc] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '');
            if ($name !== '') {
                $fields[] = [$name, $desc];
            }
        }

        return [
            'title' => $data['title'], 'slug' => $data['slug'], 'type' => $data['type'], 'status' => $data['status'], 'short' => $data['short'], 'purpose' => $data['purpose'] ?? null,
            'fields' => $fields, 'instructions' => $lines($data['instructions_text'] ?? ''), 'frameworks' => array_values($data['frameworks'] ?? []), 'topics' => array_values($data['topics'] ?? []),
            'related_guides' => array_values($data['related_guides'] ?? []), 'related_policies' => array_values(array_filter(array_map('trim', explode(',', (string) ($data['related_policies_text'] ?? ''))))),
            'next_slug' => $data['next_slug'] ?: null, 'version' => $data['version'], 'updated_on' => $data['updated_on'] ?? now()->toDateString(), 'featured' => (bool) ($data['featured'] ?? false),
            'seo_title' => $data['seo_title'] ?? null, 'seo_description' => $data['seo_description'] ?? null, 'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
    }

    private function guideOptions(): array
    {
        return collect(config('content.guides', []))->map(fn ($g) => $g['h1'])->all();
    }
}
