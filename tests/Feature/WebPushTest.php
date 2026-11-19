<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Notification;
use App\Models\PushSubscription;
use App\Models\User;
use App\Support\Notifier;
use App\Support\WebPush;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Notifications in the phone's notification bar.
 *
 * No push service can be reached from a test, so these check the two things
 * a push service would refuse - the encryption and the signature - by doing
 * the receiving side here, and fake the push service for the rest.
 */
class WebPushTest extends TestCase
{
    use RefreshDatabase;

    /** A browser's side of a subscription: its key pair and auth secret. */
    private array $browser;

    protected function setUp(): void
    {
        parent::setUp();

        $keys = WebPush::newKeys();
        config([
            'services.webpush.public'  => $keys['public'],
            'services.webpush.private' => $keys['private'],
            'services.webpush.subject' => 'https://giya-ai.app',
        ]);

        $key = WebPush::newEcKey();
        $ec = openssl_pkey_get_details($key)['ec'];
        $this->browser = [
            'key'    => $key,
            'p256dh' => WebPush::b64e("\x04".str_pad($ec['x'], 32, "\0", STR_PAD_LEFT).str_pad($ec['y'], 32, "\0", STR_PAD_LEFT)),
            'auth'   => WebPush::b64e(random_bytes(16)),
        ];
    }

    private function devotee(string $email = 'pilgrim@example.com'): User
    {
        return User::create([
            'name' => 'Pilgrim', 'email' => $email,
            'password_hash' => bcrypt('secret'), 'role' => 'devotee',
            'status' => 'Active', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function subscribe(User $user, string $endpoint = 'https://fcm.googleapis.com/fcm/send/abc'): PushSubscription
    {
        return PushSubscription::create([
            'user_id' => $user->id, 'endpoint' => $endpoint,
            'endpoint_hash' => PushSubscription::hashOf($endpoint),
            'p256dh' => $this->browser['p256dh'], 'auth' => $this->browser['auth'],
        ]);
    }

    /** What the browser does with an aes128gcm body (RFC 8291), done here. */
    private function decrypt(string $body): string
    {
        $salt = substr($body, 0, 16);
        $idlen = ord($body[20]);
        $asPublic = substr($body, 21, $idlen);
        $cipher = substr($body, 21 + $idlen);

        $asPem = "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode(
            hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200').$asPublic
        ), 64, "\n")."-----END PUBLIC KEY-----\n";
        $shared = openssl_pkey_derive($asPem, $this->browser['key'], 32);

        $ua = WebPush::b64d($this->browser['p256dh']);
        $auth = WebPush::b64d($this->browser['auth']);
        $ikm = hash_hkdf('sha256', $shared, 32, "WebPush: info\0".$ua.$asPublic, $auth);
        $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);

        $plain = openssl_decrypt(substr($cipher, 0, -16), 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, substr($cipher, -16));
        $this->assertNotFalse($plain, 'The browser could not decrypt the message.');

        return rtrim($plain, "\x02");
    }

    public function test_the_message_decrypts_with_the_browsers_keys(): void
    {
        $body = WebPush::encrypt('{"title":"Sinulog Mass"}', $this->browser['p256dh'], $this->browser['auth']);

        $this->assertSame(4096, unpack('N', substr($body, 16, 4))[1]);
        $this->assertSame('{"title":"Sinulog Mass"}', $this->decrypt($body));
    }

    public function test_the_vapid_token_is_signed_with_giyas_public_key(): void
    {
        [$h, $p, $s] = explode('.', WebPush::jwt('https://web.push.apple.com'));

        $claims = json_decode(WebPush::b64d($p), true);
        $this->assertSame('https://web.push.apple.com', $claims['aud']);
        $this->assertSame('https://giya-ai.app', $claims['sub']);
        $this->assertGreaterThan(time(), $claims['exp']);
        $this->assertLessThanOrEqual(time() + 86400, $claims['exp']);

        // Back from raw r||s to DER, and verify with the public key alone.
        $raw = WebPush::b64d($s);
        $this->assertSame(64, strlen($raw));
        $int = function (string $n) {
            $n = ltrim($n, "\0");
            if ($n === '' || ord($n[0]) & 0x80) {
                $n = "\0".$n;
            }

            return "\x02".chr(strlen($n)).$n;
        };
        $seq = $int(substr($raw, 0, 32)).$int(substr($raw, 32));
        $der = "\x30".chr(strlen($seq)).$seq;

        $pem = "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode(
            hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200').WebPush::b64d(WebPush::publicKey())
        ), 64, "\n")."-----END PUBLIC KEY-----\n";

        $this->assertSame(1, openssl_verify($h.'.'.$p, $der, $pem, OPENSSL_ALGO_SHA256));
    }

    public function test_a_devotee_can_turn_it_on_and_off_for_a_browser(): void
    {
        $user = $this->devotee();
        $sub = [
            'endpoint' => 'https://web.push.apple.com/QGx',
            'keys'     => ['p256dh' => $this->browser['p256dh'], 'auth' => $this->browser['auth']],
        ];

        $this->actingAs($user)->postJson(route('push.subscribe'), $sub)->assertOk()->assertJson(['ok' => true]);
        $this->actingAs($user)->postJson(route('push.subscribe'), $sub)->assertOk();
        $this->assertSame(1, PushSubscription::where('user_id', $user->id)->count(), 'Subscribing twice made two rows.');

        $this->actingAs($user)->postJson(route('push.unsubscribe'), ['endpoint' => $sub['endpoint']])->assertOk();
        $this->assertSame(0, PushSubscription::count());
    }

    public function test_only_https_push_addresses_are_accepted(): void
    {
        $this->actingAs($this->devotee())->postJson(route('push.subscribe'), [
            'endpoint' => 'http://169.254.169.254/latest',
            'keys'     => ['p256dh' => $this->browser['p256dh'], 'auth' => $this->browser['auth']],
        ])->assertStatus(422);
    }

    public function test_without_keys_it_is_off_and_the_switch_is_not_shown(): void
    {
        config(['services.webpush.public' => '', 'services.webpush.private' => '']);
        $user = $this->devotee();

        $this->actingAs($user)->get(route('profile'))->assertOk()->assertDontSee('data-giya-push', false);
        $this->actingAs($user)->postJson(route('push.subscribe'), [
            'endpoint' => 'https://fcm.googleapis.com/x', 'keys' => ['p256dh' => 'a', 'auth' => 'b'],
        ])->assertStatus(503);
    }

    public function test_the_profile_shows_the_switch_when_keys_are_set(): void
    {
        $this->actingAs($this->devotee())->get(route('profile'))
            ->assertOk()
            ->assertSee('data-giya-push', false)
            ->assertSee('name="giya-push-key"', false);
    }

    public function test_an_announcement_reaches_the_phone_once(): void
    {
        Http::fake(['*' => Http::response('', 201)]);
        $user = $this->devotee();
        $this->subscribe($user);

        $a = Announcement::create(['title' => 'Holy Week hours', 'message' => 'The basilica opens at 4 AM.', 'is_active' => true]);
        Notifier::announcement($a);
        Notifier::announcement($a);   // the form sent twice

        Http::assertSentCount(1);
        Http::assertSent(function (Request $r) {
            $payload = json_decode($this->decrypt($r->body()), true);

            return $r->url() === 'https://fcm.googleapis.com/fcm/send/abc'
                && $r->hasHeader('Content-Encoding', 'aes128gcm')
                && str_starts_with($r->header('Authorization')[0], 'vapid t=')
                && $payload['title'] === 'Holy Week hours'
                && $payload['body'] === 'The basilica opens at 4 AM.';
        });
        $this->assertNotNull(PushSubscription::first()->last_sent_at);
    }

    public function test_a_browser_the_push_service_says_is_gone_is_forgotten(): void
    {
        Http::fake(['*' => Http::response('', 410)]);
        $this->subscribe($this->devotee());

        Notifier::announcement(Announcement::create(['title' => 'News', 'message' => 'x', 'is_active' => true]));

        $this->assertSame(0, PushSubscription::count());
        $this->assertSame(1, Notification::count(), 'The bell note must still be written.');
    }

    public function test_the_test_button_sends_to_this_devotee_only(): void
    {
        Http::fake(['*' => Http::response('', 201)]);
        $me = $this->devotee();
        $this->subscribe($me);
        $this->subscribe($this->devotee('other@example.com'), 'https://fcm.googleapis.com/fcm/send/other');

        $this->actingAs($me)->postJson(route('push.test'))->assertOk()->assertJson(['ok' => true, 'sent' => 1]);

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/abc'));
    }

    public function test_the_service_worker_shows_pushes_and_opens_their_page(): void
    {
        $sw = file_get_contents(public_path('sw.js'));

        $this->assertStringContainsString("addEventListener('push'", $sw);
        $this->assertStringContainsString('showNotification', $sw);
        $this->assertStringContainsString("addEventListener('notificationclick'", $sw);
    }
}
