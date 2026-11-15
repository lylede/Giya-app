<?php

namespace Tests\Feature;

use App\Actions\Auth\EmailVerification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class VerifyCodeTest extends TestCase
{
    use RefreshDatabase;

    private ?string $sentCode = null;
    private ?string $sentText = null;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.verify_email' => true, 'mail.default' => 'array']);

        Event::listen(MessageSending::class, function (MessageSending $e) {
            $this->sentText = $e->message->getTextBody();
            preg_match('/Your code: (\d{6})/', (string) $this->sentText, $m);
            $this->sentCode = $m[1] ?? null;
        });
    }

    private function newUser(): User
    {
        return User::create([
            'name' => 'Maria Santos', 'email' => 'maria@example.com', 'password_hash' => Hash::make('secret123'),
            'role' => 'user', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_the_email_has_a_code_and_a_plain_text_copy(): void
    {
        $user = $this->newUser();

        $this->assertTrue(app(EmailVerification::class)->send($user));

        $this->assertMatchesRegularExpression('/^\d{6}$/', (string) $this->sentCode);
        $this->assertStringContainsString('/email/verify/', (string) $this->sentText);
        $this->assertNotSame($this->sentCode, $user->fresh()->verify_code, 'stored hashed, not as typed');
    }

    public function test_typing_the_code_after_sign_up_confirms_the_account(): void
    {
        $user = $this->newUser();
        app(EmailVerification::class)->send($user);

        $this->withSession(['verify_email' => 'maria@example.com'])
            ->post(route('verification.code-pending'), ['code' => substr($this->sentCode, 0, 3).' '.substr($this->sentCode, 3)])
            ->assertRedirect(route('login'))
            ->assertSessionHas('success');

        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertNull($user->fresh()->verify_code);
    }

    public function test_a_wrong_code_is_refused_and_five_wrong_tries_end_the_code(): void
    {
        $user = $this->newUser();
        app(EmailVerification::class)->send($user);
        $wrong = $this->sentCode === '000000' ? '111111' : '000000';

        for ($i = 1; $i <= 4; $i++) {
            $this->withSession(['verify_email' => 'maria@example.com'])->from(route('verification.pending'))
                ->post(route('verification.code-pending'), ['code' => $wrong])
                ->assertSessionHasErrors(['code' => __('giya.verify.code_wrong')]);
        }
        $this->withSession(['verify_email' => 'maria@example.com'])
            ->post(route('verification.code-pending'), ['code' => $wrong])
            ->assertSessionHasErrors(['code' => __('giya.verify.code_expired')]);

        // Even the right code no longer works: a new email is needed.
        $this->withSession(['verify_email' => 'maria@example.com'])
            ->post(route('verification.code-pending'), ['code' => $this->sentCode])
            ->assertSessionHasErrors('code');
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_an_expired_code_is_refused(): void
    {
        $user = $this->newUser();
        app(EmailVerification::class)->send($user);
        $this->travel(25)->hours();

        $this->withSession(['verify_email' => 'maria@example.com'])
            ->post(route('verification.code-pending'), ['code' => $this->sentCode])
            ->assertSessionHasErrors(['code' => __('giya.verify.code_expired')]);
    }

    public function test_sending_again_replaces_the_old_code(): void
    {
        $user = $this->newUser();
        app(EmailVerification::class)->send($user);
        $old = $this->sentCode;
        do {
            app(EmailVerification::class)->send($user);
        } while ($this->sentCode === $old);

        $this->assertSame('wrong', app(EmailVerification::class)->verifyCode($user, $old));
        $this->assertSame('ok', app(EmailVerification::class)->verifyCode($user, $this->sentCode));
    }

    public function test_a_signed_in_person_can_type_the_code_too(): void
    {
        $user = $this->newUser();
        app(EmailVerification::class)->send($user);

        $this->actingAs($user)->post(route('verification.code'), ['code' => $this->sentCode])->assertRedirect(route('home'));

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_the_check_email_page_shows_the_code_box(): void
    {
        $this->newUser();

        $this->withSession(['verify_email' => 'maria@example.com'])->get(route('verification.pending'))
            ->assertOk()->assertSee('name="code"', false)->assertSee(__('giya.verify.code_label'));
    }
}
