<?php

namespace App\Support;

use App\Models\PushSubscription;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
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
     * Push to every browser of these devotees.
     *
     * Sent now, before the page answers, twenty at a time in parallel. It was
     * sent after the response, but a host that freezes the container once
     * the page is out (Vercel can) dropped those pushes without a word.
     *
     * @param  array<int>  $userIds
     * @param  array{title: string, body?: ?string, url?: ?string, tag?: ?string}  $message
     */
    public static function toUsers(array $userIds, array $message): void
    {
        if (! self::enabled() || $userIds === []) {
            return;
        }

        try {
            PushSubscription::whereIn('user_id', array_values(array_unique($userIds)))
                ->orderBy('id')
                ->chunkById(20, function ($subs) use ($message) {
                    self::sendMany($subs->all(), $message);
                });
        } catch (\Throwable $e) {
            // A phone that cannot be reached must never break saving a schedule.
            Log::warning('Web push batch failed: '.$e->getMessage());
        }
    }

    /**
     * One notification to one browser. True when the push service took it.
     *
     * @param  array{title: string, body?: ?string, url?: ?string, tag?: ?string}  $message
     */
    public static function send(PushSubscription $sub, array $message): bool
    {
        return self::report($sub, $message)['ok'];
    }

    /**
     * One notification to one browser, and what the push service said:
     * the Send me a test button and giya:push-test show this, so a refusal
     * names its reason instead of failing quietly.
     *
     * @return array{ok: bool, status: ?int, reason: string, service: string}
     */
    public static function report(PushSubscription $sub, array $message): array
    {
        return self::sendMany([$sub], $message)[$sub->id];
    }

    /**
     * Several browsers at once. A browser the push service says is gone
     * (404 / 410: notifications turned off, browser cleared, app removed) is
     * deleted so GIYA stops trying.
     *
     * @param  array<PushSubscription>  $subs
     * @return array<int, array{ok: bool, status: ?int, reason: string, service: string}>
     */
    private static function sendMany(array $subs, array $message): array
    {
        $payload = json_encode([
            'title' => mb_substr($message['title'], 0, 120),
            'body'  => isset($message['body']) ? mb_substr((string) $message['body'], 0, 240) : '',
            'url'   => $message['url'] ?? '/home',
            'tag'   => $message['tag'] ?? null,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $results = [];
        $ready = [];
        foreach ($subs as $sub) {
            $service = (string) parse_url($sub->endpoint, PHP_URL_HOST);
            try {
                $ready[$sub->id] = [
                    'sub'     => $sub,
                    'service' => $service,
                    'body'    => self::encrypt($payload, $sub->p256dh, $sub->auth),
                    'jwt'     => self::jwt(self::origin($sub->endpoint)),
                ];
            } catch (\Throwable $e) {
                $results[$sub->id] = ['ok' => false, 'status' => null, 'reason' => $e->getMessage(), 'service' => $service];
            }
        }

        if ($ready !== []) {
            $responses = Http::pool(fn (Pool $pool) => array_map(
                fn ($r) => $pool->as((string) $r['sub']->id)
                    ->timeout(8)
                    ->withHeaders([
                        'Authorization'    => 'vapid t='.$r['jwt'].', k='.self::publicKey(),
                        'Content-Encoding' => 'aes128gcm',
                        'TTL'              => (string) self::TTL,
                        // High: delivered at once even to a phone asleep in a pocket.
                        'Urgency'          => 'high',
                    ])
                    ->withBody($r['body'], 'application/octet-stream')
                    ->post($r['sub']->endpoint),
                array_values($ready)
            ));

            foreach ($ready as $id => $r) {
                $response = $responses[(string) $id] ?? null;
                $sub = $r['sub'];

                if (! $response instanceof Response) {
                    $why = $response instanceof \Throwable ? $response->getMessage() : 'no answer';
                    Log::warning('Web push failed', ['service' => $r['service'], 'reason' => $why]);
                    $results[$id] = ['ok' => false, 'status' => null, 'reason' => $why, 'service' => $r['service']];

                    continue;
                }

                $status = $response->status();
                if ($response->successful()) {
                    $sub->forceFill(['last_sent_at' => now()])->save();
                    $results[$id] = ['ok' => true, 'status' => $status, 'reason' => '', 'service' => $r['service']];

                    continue;
                }

                $reason = trim(mb_substr(strip_tags($response->body()), 0, 200));
                if (in_array($status, [404, 410], true)) {
                    $sub->delete();
                    $reason = 'This browser is no longer subscribed'.($reason !== '' ? ' ('.$reason.')' : '');
                }
                Log::warning('Web push refused', ['service' => $r['service'], 'status' => $status, 'reason' => $reason]);
                $results[$id] = ['ok' => false, 'status' => $status, 'reason' => $reason, 'service' => $r['service']];
            }
        }

        return $results;
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
        $ephemeral ??= self::newEcKey();
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
        $ec = openssl_pkey_get_details(self::newEcKey())['ec'];

        return [
            'public'  => self::b64e("\x04".str_pad($ec['x'], 32, "\0", STR_PAD_LEFT).str_pad($ec['y'], 32, "\0", STR_PAD_LEFT)),
            'private' => self::b64e(str_pad($ec['d'], 32, "\0", STR_PAD_LEFT)),
        ];
    }

    /**
     * A new P-256 key pair.
     *
     * On Windows, PHP often cannot find its openssl.cnf and openssl_pkey_new()
     * fails ("must be of type OpenSSLAsymmetricKey, false given"). Making an EC
     * key needs nothing from that file, so when the default fails, try the
     * usual places PHP and XAMPP keep it, then a minimal one of our own.
     */
    public static function newEcKey(): \OpenSSLAsymmetricKey
    {
        $options = ['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC];

        $key = openssl_pkey_new($options);
        if ($key !== false) {
            return $key;
        }

        $phpDir = dirname(PHP_BINARY);
        $candidates = array_filter([
            getenv('OPENSSL_CONF') ?: null,
            $phpDir.'/extras/ssl/openssl.cnf',
            $phpDir.'/extras/openssl/openssl.cnf',
            dirname($phpDir).'/apache/conf/openssl.cnf',     // XAMPP
            'C:/xampp/php/extras/openssl/openssl.cnf',
            'C:/xampp/apache/conf/openssl.cnf',
        ]);

        $minimal = null;
        foreach ([...$candidates, null] as $config) {
            if ($config === null) {
                $minimal = tempnam(sys_get_temp_dir(), 'giya-ssl');
                file_put_contents($minimal, "[req]\ndefault_bits = 2048\ndistinguished_name = req_dn\n[req_dn]\n");
                $config = $minimal;
            } elseif (! is_file($config)) {
                continue;
            }

            // The bit count is ignored for an EC key, but PHP refuses a config without one.
            $key = @openssl_pkey_new($options + ['config' => $config, 'private_key_bits' => 2048]);
            if ($key !== false) {
                break;
            }
        }

        if ($minimal !== null) {
            @unlink($minimal);
        }
        while (openssl_error_string() !== false) {
            // Clear the "no such file" errors left by the failed tries.
        }

        if ($key === false) {
            throw new RuntimeException('OpenSSL could not make a key. Check that the openssl extension is enabled in php.ini.');
        }

        return $key;
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
