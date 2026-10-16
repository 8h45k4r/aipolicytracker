<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Obligations and changes can be sorted, and the filtered list downloads as CSV in the same order. */
class SortAndExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('policy:import');
    }

    /** @return array<int, array<string, string>> the CSV rows as header-keyed arrays */
    private function csv(string $url): array
    {
        $response = $this->get($url)->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        // A real CSV reader: a change's description can span lines inside one quoted field.
        $handle = fopen('php://memory', 'r+');
        fwrite($handle, $response->streamedContent());
        rewind($handle);
        $header = fgetcsv($handle);
        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            if ($row !== [null]) {
                $rows[] = array_combine($header, $row);
            }
        }
        fclose($handle);

        return $rows;
    }

    public function test_obligations_sort_by_title_and_keep_binding_first_by_default(): void
    {
        $byTitle = $this->csv(route('obligations.export', ['sort' => 'title']));
        $this->assertNotEmpty($byTitle);
        $titles = array_column($byTitle, 'title');
        $sorted = $titles;
        usort($sorted, fn ($a, $b) => strcasecmp($a, $b));
        $this->assertSame($sorted, $titles, 'titles run A to Z');

        $default = $this->csv(route('obligations.export'));
        $bindings = array_map(fn ($r) => $r['binding'] === 'binding' ? 0 : 1, $default);
        $this->assertSame($bindings, collect($bindings)->sort()->values()->all(), 'binding duties come before voluntary ones');
    }

    public function test_obligations_sort_by_jurisdiction_and_category(): void
    {
        $rows = $this->csv(route('obligations.export', ['sort' => 'jurisdiction']));
        $names = array_column($rows, 'jurisdiction');
        $sorted = $names;
        usort($sorted, fn ($a, $b) => strcasecmp($a, $b));
        $this->assertSame($sorted, $names);

        $categories = array_column($this->csv(route('obligations.export', ['sort' => 'category'])), 'category');
        $sorted = $categories;
        sort($sorted);
        $this->assertSame($sorted, $categories);
    }

    public function test_an_unknown_obligation_sort_falls_back_to_the_default(): void
    {
        $this->assertSame($this->csv(route('obligations.export')), $this->csv(route('obligations.export', ['sort' => 'nonsense'])));
    }

    public function test_the_obligations_export_honours_the_filters_on_screen(): void
    {
        $all = $this->csv(route('obligations.export'));
        $category = $all[0]['category'];
        $filtered = $this->csv(route('obligations.export', ['category' => $category]));

        $this->assertNotEmpty($filtered);
        $this->assertCount(count(array_filter($all, fn ($r) => $r['category'] === $category)), $filtered);
        foreach ($filtered as $row) {
            $this->assertSame($category, $row['category']);
        }
    }

    public function test_the_export_routes_are_not_read_as_a_single_record(): void
    {
        $this->get('/obligations/export.csv')->assertOk();
        $this->get('/changes/export.csv')->assertOk();
    }

    public function test_changes_sort_oldest_first_and_urgent_first(): void
    {
        $oldest = $this->csv(route('changes.export', ['sort' => 'oldest']));
        if (count($oldest) > 1) {
            $dates = array_column($oldest, 'occurred_on');
            $sorted = $dates;
            sort($sorted);
            $this->assertSame($sorted, $dates);
        }

        $impact = array_column($this->csv(route('changes.export', ['sort' => 'impact'])), 'impact_level');
        $rank = ['urgent' => 0, 'high' => 1, 'routine' => 2];
        $ranks = array_map(fn ($i) => $rank[$i] ?? 3, $impact);
        $this->assertSame($ranks, collect($ranks)->sort()->values()->all());
    }

    public function test_the_changes_export_honours_the_impact_filter_and_the_listing_offers_the_sort(): void
    {
        $impacts = array_unique(array_column($this->csv(route('changes.export', ['impact' => 'urgent'])), 'impact_level'));
        $this->assertTrue($impacts === [] || $impacts === ['urgent']);

        $html = $this->get(route('changes.index'))->assertOk()->getContent();
        $this->assertStringContainsString('name="sort"', $html);
        $this->assertStringContainsString(route('changes.export'), $html);

        $html = $this->get(route('obligations.index'))->assertOk()->getContent();
        $this->assertStringContainsString('name="sort"', $html);
        $this->assertStringContainsString('Binding first, then category', $html);
        $this->assertStringContainsString(route('obligations.export'), $html);
    }
}
