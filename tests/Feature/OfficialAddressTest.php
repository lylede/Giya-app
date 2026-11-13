<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficialAddressTest extends TestCase
{
    use RefreshDatabase;

    private const RENDER = 'https://giya-7gy4.onrender.com';

    private function official(?string $url = 'https://giya.example.com'): void
    {
        config(['app.official_url' => $url]);
    }

    public function test_nothing_is_redirected_when_no_official_address_is_set(): void
    {
        $this->official(null);

        $this->get(self::RENDER.'/login')->assertOk();
    }

    public function test_the_old_link_goes_to_the_same_page_on_the_official_address(): void
    {
        $this->official();

        $this->get(self::RENDER.'/login')->assertRedirect('https://giya.example.com/login')->assertStatus(302);
        $this->get(self::RENDER.'/map?church=12&from=share')->assertRedirect('https://giya.example.com/map?church=12&from=share');
        $this->get(self::RENDER.'/')->assertRedirect('https://giya.example.com');
    }

    public function test_the_official_address_itself_is_served(): void
    {
        $this->official('https://giya.example.com/');

        $this->get('https://giya.example.com/login')->assertOk();
        $this->get('https://GIYA.example.com/login')->assertOk();
    }

    public function test_www_and_other_addresses_also_go_to_the_official_one(): void
    {
        $this->official();

        $this->get('https://www.giya.example.com/login')->assertRedirect('https://giya.example.com/login');
    }

    public function test_a_payment_confirmation_or_form_on_its_way_is_not_redirected(): void
    {
        $this->official();

        $this->assertFalse($this->postJson(self::RENDER.'/maya/webhook', [])->isRedirect());
        $this->assertNotSame('https://giya.example.com/login', $this->post(self::RENDER.'/login', [])->headers->get('Location'));
    }

    public function test_the_health_check_still_answers_on_the_old_link(): void
    {
        $this->official();

        $this->get(self::RENDER.'/up')->assertOk();
    }

    public function test_the_address_works_when_typed_without_https(): void
    {
        $this->official('giya.example.com');

        $this->get(self::RENDER.'/login')->assertRedirect('https://giya.example.com/login');
        $this->get('https://giya.example.com/login')->assertOk();
    }

    public function test_an_empty_setting_does_not_break_the_site(): void
    {
        $this->official('   ');

        $this->get(self::RENDER.'/login')->assertOk();
    }
}
