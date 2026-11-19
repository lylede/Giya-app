<?php

namespace App\Support;

use App\Models\PushSubscription;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Notifications in the phone's notification bar (Web Push).
 *
 * The phone's browser gives GIYA an address at its push service (Apple,
 * Google or Mozilla) and two keys. To show a notification, GIYA encrypts the
 * text with those keys (RFC 8291, aes128gcm) and posts it to that address,
 * signed with GIYA's own key pair so the push service knows who is sending
 * (VAPID, RFC 8292). The push service delivers it when the phone is online,
 * and the service worker (public/sw.js) shows it.
 *
 * Written with PHP's own OpenSSL rather than a package: nothing to install,
 * and every step is the one the RFCs describe.
 *
 * On iPhone this works only for GIYA added to the Home Screen and opened from
 * there (iOS 16.4 and later); on Android in Chrome it also works in the browser.
 */
class WebPush
{
    /** How long a push service keeps a notification for a phone that is off (seconds). */
    public const TTL = 86400;

    /** The record size written in the header; one record holds the whole message. */
    private const RECORD_SIZE = 4096;

    public static function enabled(): bool
    {
        return config('services.webpush.public') !== '' && config('services.webpush.private') !== '';
    }

    public static function publicKey(): string
    {
        return (string) config('services.webpush.public');
    }

    /* ---------------------------------------------------------------- send */

    /**
     * Push to every browser of these devotees, after the page has been sent:
     * the admin who saved a schedule does not wait for a hundred phones.
     *
     * @param  array<int>  $userIds
     * @param  array{title: string, body?: ?string, url?: ?string, tag?: ?string}  $message
     */
    public static function toUsers(array $userIds, array $message): void
    {
        if (! self::enabled() || $userIds === []) {
            return;
        }

        $send = function () use ($userIds, $message) {
            PushSubscription::whereIn('user_id', array_values(array_unique($userIds)))
                ->orderBy('id')
                ->chunkById(200, function ($subs) use ($message) {
                    foreach ($subs as $sub) {
                        self::send($sub, $message);
                    }
                });
        };

        app()->runningUnitTests() ? $send() : app()->terminating($send);
    }

    /**
     * One notification to one browser. True when the push service took it.
     * A subscription the push service says is gone (404 / 410: the devotee
     * turned notifications off, cleared the browser, removed the app) is
     * deleted so GIYA stops trying.
     *
     * @param  array{title: string, body?: ?string, url?: ?string, tag?: ?string}  $message
     */
    public static function send(PushSubscription $sub, array $message): bool
    {
        try {
            $payload = json_encode([
                'title' => mb_substr($message['title'], 0, 120),
                'body'  => isset($message['body']) ? mb_substr((string) $message['body'], 0, 240) : '',
                'url'   => $message['url'] ?? '/home',
                'tag'   => $message['tag'] ?? null,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $body = self::encrypt($payload, $sub->p256dh, $sub->auth);
            $audience = self::origin($sub->endpoint);

            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization'    => 'vapid t='.self::jwt($audience).', k='.self::publicKey(),
                    'Content-Encoding' => 'aes128gcm',
                    'Content-Type'     => 'application/octet-stream',
                    'TTL'              => (string) self::TTL,
                    'Urgency'          => 'normal',
                ])
                ->withBody($body, 'application/octet-stream')
                ->post($sub->endpoint);

            if (in_array($response->status(), [404, 410], true)) {
                $sub->delete();

                return false;
            }
            if (! $response->successful()) {
                Log::warning('Web push refused', ['status' => $response->status(), 'body' => mb_substr($response->body(), 0, 300)]);

                return false;
            }

            $sub->forceFill(['last_sent_at' => now()])->save();

            return true;
        } catch (\Throwable $e) {
            Log::warning('Web push failed: '.$e->getMessage());

            return false;
        }
    }

    /* ---------------------------------------------------------- encryption */

    /**
     * RFC 8291: the message encrypted for one browser, as the aes128gcm body
     * (salt, record size, GIYA's one-time public key, then the ciphertext).
     */
    public static function encrypt(string $payload, string $p256dh, string $authSecret, ?string $salt = null, $ephemeral = null): string
    {
        $uaPublic = self::b64d($p256dh);      // the browser's public key, 65 bytes
        $auth = self::b64d($authSecret);      // 16 bytes
        if (strlen($uaPublic) !== 65 || $uaPublic[0] !== "\x04" || strlen($auth) !== 16) {
            throw new RuntimeException('Bad subscription keys');
        }

        // A fresh key pair for this one message.
        $ephemeral ??= openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $d = openssl_pkey_get_details($ephemeral)['ec'];
        $asPublic = "\x04".str_pad($d['x'], 32, "\0", STR_PAD_LEFT).str_pad($d['y'], 32, "\0", STR_PAD_LEFT);

        $shared = openssl_pkey_derive(self::publicPem($uaPublic), $ephemeral, 32);
        if ($shared === false) {
            throw new RuntimeException('Key agreement failed');
        }

        // IKM = HKDF(auth, shared, "WebPush: info" || 0 || ua_public || as_public)
        $prkKey = hash_hmac('sha256', $shared, $auth, true);
        $ikm = hash_hmac('sha256', "WebPush: info\0".$uaPublic.$asPublic."\x01", $prkKey, true);

        $salt ??= random_bytes(16);
        $prk = hash_hmac('sha256', $ikm, $salt, true);
        $cek = substr(hash_hmac('sha256', "Content-Encoding: aes128gcm\0\x01", $prk, true), 0, 16);
        $nonce = substr(hash_hmac('sha256', "Content-Encoding: nonce\0\x01", $prk, true), 0, 12);

        // One record: the text, then the 0x02 "last record" delimiter.
        $tag = '';
        $cipher = openssl_encrypt($payload."\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
        if ($cipher === false) {
            throw new RuntimeException('Encryption failed');
        }

        return $salt.pack('N', self::RECORD_SIZE).chr(65).$asPublic.$cipher.$tag;
    }

    /* ---------------------------------------------------------------- VAPID */

    /** RFC 8292: a short-lived ES256 token that says this push comes from GIYA. */
    public static function jwt(string $audience): string
    {
        $header = self::b64e(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $claims = self::b64e(json_encode([
            'aud' => $audience,
            'exp' => time() + 12 * 3600,
            'sub' => (string) config('services.webpush.subject'),
        ], JSON_UNESCAPED_SLASHES));

        $signed = $header.'.'.$claims;
        if (! openssl_sign($signed, $der, self::privatePem(), OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Signing failed');
        }

        return $signed.'.'.self::b64e(self::derToRaw($der));
    }

    /**
     * A new VAPID key pair, as the two base64url strings for .env:
     * public = the 65-byte uncompressed point, private = the 32-byte scalar.
     *
     * @return array{public: string, private: string}
     */
    public static function newKeys(): array
    {
        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $ec = openssl_pkey_get_details($key)['ec'];

        return [
            'public'  => self::b64e("\x04".str_pad($ec['x'], 32, "\0", STR_PAD_LEFT).str_pad($ec['y'], 32, "\0", STR_PAD_LEFT)),
            'private' => self::b64e(str_pad($ec['d'], 32, "\0", STR_PAD_LEFT)),
        ];
    }

    /** GIYA's private key as PEM, rebuilt from the two .env values (SEC1 ECPrivateKey). */
    private static function privatePem(): string
    {
        $d = self::b64d((string) config('services.webpush.private'));
        $pub = self::b64d(self::publicKey());
        if (strlen($d) !== 32 || strlen($pub) !== 65) {
            throw new RuntimeException('VAPID keys are missing or malformed');
        }
        $der = "\x30\x77\x02\x01\x01\x04\x20".$d
            ."\xa0\x0a\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07"
            ."\xa1\x44\x03\x42\x00".$pub;

        return "-----BEGIN EC PRIVATE KEY-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END EC PRIVATE KEY-----\n";
    }

    /** A P-256 public point as a PEM public key (SubjectPublicKeyInfo). */
    private static function publicPem(string $point): string
    {
        $der = "\x30\x59\x30\x13\x06\x07\x2a\x86\x48\xce\x3d\x02\x01\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07\x03\x42\x00".$point;

        return "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END PUBLIC KEY-----\n";
    }

    /** OpenSSL signs ECDSA as DER; JWT wants r and s as two 32-byte numbers. */
    private static function derToRaw(string $der): string
    {
        $pos = 2;
        if (ord($der[1]) & 0x80) {
            $pos += ord($der[1]) & 0x7f;
        }
        $parts = [];
        for ($i = 0; $i < 2; $i++) {
            $len = ord($der[$pos + 1]);
            $int = substr($der, $pos + 2, $len);
            $parts[] = str_pad(ltrim($int, "\0"), 32, "\0", STR_PAD_LEFT);
            $pos += 2 + $len;
        }

        return $parts[0].$parts[1];
    }

    private static function origin(string $url): string
    {
        $p = parse_url($url);

        return $p['scheme'].'://'.$p['host'].(isset($p['port']) ? ':'.$p['port'] : '');
    }

    public static function b64e(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    public static function b64d(string $text): string
    {
        $text = strtr(trim($text), '-_', '+/');

        return (string) base64_decode($text.str_repeat('=', (4 - strlen($text) % 4) % 4), true);
    }
}
