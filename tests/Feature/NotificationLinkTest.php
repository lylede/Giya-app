<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Vercel and Render share one database: a bell link must stay on the site it is opened on. */
class NotificationLinkTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::create([
            'name' => 'Test', 'email' => 'devotee@example.com', 'password_hash' => bcrypt('secret'),
            'role' => 'devotee', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_a_new_link_is_saved_without_the_site_address(): void
    {
        $n = Notification::notify($this->user()->id, 'Hi', ['url' => 'https://giya-app-phi.vercel.app/plan/7?rate=1#stars']);

        $this->assertSame('/plan/7?rate=1#stars', $n->getRawOriginal('url'));
    }

    public function test_an_old_full_link_is_trimmed_in_the_bell_feed(): void
    {
        $user = $this->user();
        $n = Notification::notify($user->id, 'Old');
        Notification::whereKey($n->id)->update(['url' => 'https://giya-app-phi.vercel.app/churches/12']);

        $this->actingAs($user)->getJson(route('notifications.index'))
            ->assertJsonPath('items.0.url', '/churches/12');
    }

    public function test_no_link_stays_no_link(): void
    {
        $this->assertNull(Notification::notify($this->user()->id, 'Plain')->url);
    }
}
