<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Laravel's stock smoke test, with the line it ships commented out put back.
 *
 * The landing page reads featured churches, so without a schema it 500s and
 * this has been failing on every run since the first migration - loud enough
 * to train anyone reading the output to ignore a red suite, which is the only
 * thing a smoke test is for.
 */
class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_landing_page_answers(): void
    {
        $this->get('/')->assertStatus(200);
    }
}
