<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\ChurchCategory;
use App\Models\Feedback;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChurchReviewsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_church_page_shows_only_approved_reviews(): void
    {
        $church = $this->createChurch();
        $approvedReviewer = $this->createUser('Approved Reviewer');
        $pendingReviewer = $this->createUser('Pending Reviewer');
        $flaggedReviewer = $this->createUser('Flagged Reviewer');

        Feedback::create([
            'user_id' => $approvedReviewer->id,
            'church_id' => $church->id,
            'rating' => 5,
            'comment' => 'A peaceful place to visit.',
            'status' => 'Approved',
            'created_at' => '2026-08-20 10:00:00',
        ]);
        Feedback::create([
            'user_id' => $pendingReviewer->id,
            'church_id' => $church->id,
            'rating' => 3,
            'comment' => 'This review is still pending.',
            'status' => 'Pending',
            'created_at' => '2026-08-21 10:00:00',
        ]);
        Feedback::create([
            'user_id' => $flaggedReviewer->id,
            'church_id' => $church->id,
            'rating' => 1,
            'comment' => 'This review was flagged.',
            'status' => 'Flagged',
            'created_at' => '2026-08-22 10:00:00',
        ]);

        $response = $this->actingAs($this->createUser('Visitor'))
                         ->get(route('churches.show', $church));

        $response->assertOk()
            ->assertSee('Approved Reviewer')
            ->assertSee('A peaceful place to visit.')
            ->assertSee('August 20, 2026')
            ->assertDontSee('Pending Reviewer')
            ->assertDontSee('This review is still pending.')
            ->assertDontSee('Flagged Reviewer')
            ->assertDontSee('This review was flagged.');
    }

    public function test_the_church_page_shows_an_empty_state_without_approved_reviews(): void
    {
        $church = $this->createChurch();
        $reviewer = $this->createUser('Unpublished Reviewer');

        Feedback::create([
            'user_id' => $reviewer->id,
            'church_id' => $church->id,
            'rating' => 4,
            'comment' => 'Waiting for moderation.',
            'status' => 'Pending',
        ]);

        $response = $this->actingAs($this->createUser('Visitor'))
                         ->get(route('churches.show', $church));

        $response->assertOk()
            ->assertSee('No reviews yet')
            ->assertDontSee('Unpublished Reviewer')
            ->assertDontSee('Waiting for moderation.');
    }

    public function test_inactive_churches_do_not_show_public_reviews(): void
    {
        $church = $this->createChurch(false);

        $this->actingAs($this->createUser('Visitor'))
             ->get(route('churches.show', $church))->assertNotFound();
    }

    private function createChurch(bool $isActive = true): Church
    {
        $category = ChurchCategory::create(['name' => 'Church']);

        return Church::create([
            'category_id' => $category->id,
            'name' => 'Test Church',
            'location' => 'Test Location',
            'is_active' => $isActive,
        ]);
    }

    private function createUser(string $name): User
    {
        return User::create([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)).'@example.com',
            'password_hash' => 'hashed-password',
        ]);
    }
}
