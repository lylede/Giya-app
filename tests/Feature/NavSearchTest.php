<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The burger menu has no search box; the desktop navbar keeps its search. */
class NavSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_is_in_the_navbar_but_not_in_the_burger_menu(): void
    {
        $html = $this->get(route('map'))->assertOk()->getContent();

        $this->assertStringContainsString('class="nav-search"', $html, 'Desktop navbar search stays.');

        $start = strpos($html, 'id="navMobileMenu"');
        $this->assertNotFalse($start);
        $menu = substr($html, $start, strpos($html, '</nav>', $start) - $start);
        $this->assertStringNotContainsString('type="search"', $menu, 'The burger menu has no search box.');
    }
}
