<?php

namespace Tests\Feature;

use App\Actions\Auth\RegisterAction;
use App\Models\Notification;
use App\Models\User;
use App\Support\Notifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WelcomeNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function signUp(string $email = 'maria@example.com'): User
    {
        return app(RegisterAction::class)->execute(
            ['name' => 'Maria Santos', 'email' => $email, 'password' => 'secret123'], request()
        );
    }

    public function test_a_new_account_finds_three_welcome_notes_in_the_bell(): void
    {
        $user = $this->signUp();

        $notes = Notification::where('user_id', $user->id)->orderByDesc('created_at')->get();

        $this->assertCount(3, $notes);
        $this->assertSame('Welcome to GIYA, Maria!', $notes[0]->title);
        $this->assertSame(['Welcome to GIYA, Maria!', 'Ask Giya AI anything', 'Plan your Visita Iglesia'], $notes->pluck('title')->all());
        $this->assertTrue($notes->every(fn ($n) => ! $n->is_read && filled($n->url)));
    }

    public function test_they_show_as_unread_in_the_bell(): void
    {
        $user = $this->signUp();
        $user->forceFill(['email_verified_at' => now()])->save();

        $this->actingAs($user)->getJson(route('notifications.index'))
            ->assertOk()
            ->assertSee('Welcome to GIYA, Maria!');
    }

    public function test_nobody_gets_them_twice_and_nobody_else_gets_them(): void
    {
        $maria = $this->signUp();
        $jose = $this->signUp('jose@example.com');

        Notifier::welcome($maria);

        $this->assertSame(3, Notification::where('user_id', $maria->id)->count());
        $this->assertSame(3, Notification::where('user_id', $jose->id)->count());
    }
}
