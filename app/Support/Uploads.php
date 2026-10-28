<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Where uploaded photos live on the hosted app: Supabase Storage.
 *
 * Vercel and Render give the app a disk that is wiped on every restart, so a
 * church photo or profile picture saved there would disappear within a day.
 * When SUPABASE_URL and SUPABASE_SERVICE_KEY are set, uploads go to a public
 * Supabase Storage bucket instead and the database keeps the full https URL.
 *
 * On a laptop (no Supabase settings) nothing changes: callers check
 * Uploads::cloud() and keep saving files locally as before.
 *
 * Supabase's own REST API is used through Laravel's HTTP client, so no extra
 * Composer package is needed:
 *   upload  POST   {url}/storage/v1/object/{bucket}/{path}   (x-upsert: true)
 *   delete  DELETE {url}/storage/v1/object/{bucket}/{path}
 *   view    GET    {url}/storage/v1/object/public/{bucket}/{path}
 */
final class Uploads
{
    /** Are uploads going to Supabase? */
    public static function cloud(): bool
    {
        return filled(config('services.supabase.url')) && filled(config('services.supabase.key'));
    }

    /**
     * Upload a file and return its public URL.
     *
     * @throws RuntimeException when Supabase refuses the upload
     */
    public static function put(UploadedFile $file, string $path): string
    {
        try {
            $response = self::client()
                ->withHeaders(['x-upsert' => 'true'])
                ->withBody(
                    (string) file_get_contents($file->getRealPath()),
                    $file->getMimeType() ?: 'application/octet-stream'
                )
                ->post(self::objectUrl($path));
        } catch (\Throwable $e) {
            // Supabase unreachable (wrong SUPABASE_URL, network down) or the
            // request could not even be built (a key with a stray character):
            // a clear message for the admin instead of a 500 page. The cause
            // is logged - never the key itself.
            Log::error('Supabase upload could not be sent.', [
                'path'  => $path,
                'error' => get_class($e).': '.preg_replace('/sb_[a-z]+_\S+|ey[\w-]{20,}\.\S+/', '[key hidden]', $e->getMessage()),
            ]);

            throw new RuntimeException('The photo could not be uploaded.', previous: $e);
        }

        /* Supabase answers a real upload with {"Key": "<bucket>/<path>"}.
           Anything else - a 200 from the wrong endpoint, an HTML page - is
           not a saved photo, and saying "updated" for it left admins with a
           photo that never appeared. */
        if ($response->successful() && ! is_string($response->json('Key'))) {
            Log::error('Supabase upload got an unexpected answer.', [
                'path'   => $path,
                'status' => $response->status(),
                'body'   => mb_substr($response->body(), 0, 300),
            ]);

            throw new RuntimeException('The photo could not be uploaded (unexpected reply from storage - check SUPABASE_URL).');
        }

        if ($response->failed()) {
            Log::error('Supabase upload failed.', [
                'path'   => $path,
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            throw new RuntimeException('The photo could not be uploaded ('.$response->status().').');
        }

        return self::publicUrl($path);
    }

    /** Remove a file this app uploaded. Anything else (seeded photos, other sites) is left alone. */
    public static function delete(?string $url): void
    {
        if (! self::owns($url)) {
            return;
        }

        $path = substr($url, strlen(self::publicBase()));

        // Removing an old photo is housekeeping: if it fails, the save must
        // still succeed, so nothing here is allowed to throw.
        try {
            $response = self::client()->delete(self::objectUrl($path));

            if ($response->failed() && $response->status() !== 404) {
                Log::warning('Supabase delete failed.', ['path' => $path, 'status' => $response->status()]);
            }
        } catch (\Throwable $e) {
            Log::warning('Supabase delete could not be sent.', ['path' => $path, 'error' => get_class($e)]);
        }
    }

    /** Is this URL one of our Supabase uploads? */
    public static function owns(?string $url): bool
    {
        return self::cloud() && is_string($url) && $url !== '' && str_starts_with($url, self::publicBase());
    }

    public static function publicUrl(string $path): string
    {
        return self::publicBase().ltrim($path, '/');
    }

    /** A file name that is new on every upload, so browsers never show a cached old photo. */
    public static function freshName(string $base, UploadedFile $file): string
    {
        $ext = match ($file->guessExtension()) {
            'png'         => 'png',
            'webp'        => 'webp',
            'gif'         => 'gif',
            'jpg', 'jpeg' => 'jpg',
            default       => 'jpg',
        };

        return $base.'-'.now()->format('YmdHis').'.'.$ext;
    }

    private static function publicBase(): string
    {
        return self::projectUrl().'/storage/v1/object/public/'.config('services.supabase.bucket').'/';
    }

    private static function objectUrl(string $path): string
    {
        return self::projectUrl().'/storage/v1/object/'.config('services.supabase.bucket').'/'.ltrim($path, '/');
    }

    /**
     * https://<project-ref>.supabase.co, whatever form SUPABASE_URL was given in.
     *
     * Supabase's dashboard shows several addresses for one project. Pasting
     * the S3 endpoint (https://<ref>.storage.supabase.co/storage/v1/s3)
     * instead of the Project URL sent every upload to the wrong API. The
     * project ref is the first part of the host in all of them.
     */
    public static function projectUrl(): string
    {
        $url  = trim((string) config('services.supabase.url'));
        $host = (string) parse_url(str_contains($url, '://') ? $url : 'https://'.$url, PHP_URL_HOST);

        if (preg_match('/^([a-z0-9]+)\.(?:storage\.)?supabase\.(co|in)$/i', $host, $m)) {
            return 'https://'.strtolower($m[1]).'.supabase.'.$m[2];
        }

        return rtrim($url, '/');   // self-hosted or unusual: use as given
    }

    private static function client(): \Illuminate\Http\Client\PendingRequest
    {
        $key = (string) config('services.supabase.key');

        // Both headers: the legacy service_role key wants Authorization, the
        // newer sb_secret_ keys are read from apikey. Sending both covers either.
        return Http::withToken($key)
            ->withHeaders(['apikey' => $key])
            ->timeout(30);
    }
}
