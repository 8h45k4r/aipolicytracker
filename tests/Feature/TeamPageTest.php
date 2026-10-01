<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The People page names who maintains, researches for and advises the project,
 * says what an affiliation does and does not mean, and publishes each person
 * as structured data so the maintainer and a contributor are not confused.
 */
class TeamPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_listed_person_appears_with_role_bio_portrait_and_links(): void
    {
        $page = $this->get('/team')->assertOk()->assertSee('People behind AIPolicyTracker');

        foreach (['core', 'contributors'] as $group) {
            foreach (config("team.$group") as $person) {
                $page->assertSee($person['name'])->assertSee($person['role'])->assertSee(e($person['bio']), false);
                $page->assertSee('images/team/'.$person['photo']);
                foreach ($person['links'] as $link) {
                    $page->assertSee('href="'.$link['url'].'"', false);
                }
            }
        }

        $page->assertSee('Maintainer')->assertSee('AI Governance Research Contributor');
    }

    public function test_affiliations_are_identification_not_endorsement(): void
    {
        $this->get('/team')->assertOk()
            ->assertSee('do not imply institutional endorsement')
            ->assertSee('does not provide legal advice')
            ->assertSee('Being listed on this page is not the same as being on the roster');
    }

    public function test_each_person_is_published_as_a_schema_org_person(): void
    {
        $html = $this->get('/team')->assertOk()->getContent();

        foreach (['core', 'contributors'] as $group) {
            foreach (config("team.$group") as $person) {
                $this->assertStringContainsString('"@type":"Person","@id":', $html);
                $this->assertStringContainsString(json_encode($person['name']), $html);
                foreach ($person['links'] as $link) {
                    $this->assertStringContainsString(json_encode($link['url'], JSON_UNESCAPED_SLASHES), $html);
                }
            }
        }

        // The maintainer is one entity across the roster and the People page.
        $this->assertStringContainsString(route('reviewers.show', 'bhaskar-bhatt').'#person', $html);
    }

    public function test_every_portrait_file_exists_and_is_square(): void
    {
        foreach (['core', 'contributors', 'advisors'] as $group) {
            foreach (config("team.$group") as $person) {
                if (empty($person['photo'])) {
                    continue;
                }
                $path = public_path('images/team/'.$person['photo']);
                $this->assertFileExists($path);
                [$w, $h] = getimagesize($path);
                $this->assertSame($w, $h, $person['photo'].' is not square');
                $this->assertLessThan(120 * 1024, filesize($path), $person['photo'].' is heavier than a portrait needs to be');
            }
        }
    }

    public function test_the_page_is_reachable_from_about_the_footer_the_sitemap_and_llms_txt(): void
    {
        $this->get('/about')->assertOk()->assertSee(route('team'));
        $this->get('/sitemap-static.xml')->assertOk()->assertSee(route('team'));
        $this->get('/llms.txt')->assertOk()->assertSee(route('team'));
        $this->assertTrue(collect(config('navigation.primary'))->pluck('sections')->flatten(1)->pluck('items')->flatten(1)->contains('route', 'team'));
    }
}
