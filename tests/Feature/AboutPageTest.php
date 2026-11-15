<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AboutPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_anyone_can_open_about_us(): void
    {
        $this->get(route('about'))->assertOk()
            ->assertSee(__('giya.about.title'))
            ->assertSee('Lyle Adrien P. Dedumo')
            ->assertSee('Josh Efraim C. Padernal')
            ->assertSee('images/team/clint.jpg', false)
            ->assertSee('University of Cebu');
    }

    public function test_the_navbar_links_to_it(): void
    {
        $this->get(route('about'))->assertSee('href="'.route('about').'"', false)->assertSee(__('giya.nav.about'));
    }

    public function test_every_team_photo_exists(): void
    {
        foreach (['josh', 'clint', 'lyle', 'ian'] as $name) {
            $this->assertFileExists(public_path("images/team/$name.jpg"));
        }
    }

    public function test_it_reads_in_cebuano(): void
    {
        app()->setLocale('ceb');
        $this->assertSame('Mahitungod sa GIYA', __('giya.about.title'));
        $this->assertSame('Mahitungod Kanamo', __('giya.nav.about'));
    }
}
