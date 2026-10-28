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
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            // Supabase unreachable (wrong SUPABASE_URL, network down): a
            // clear message for the admin instead of a 500 page.
            Log::error('Supabase unreachable.', ['path' => $path, 'message' => $e->getMessage()]);

            throw new RuntimeException('The photo could not be uploaded (storage unreachable).', previous: $e);
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

        $response = self::client()->delete(self::objectUrl($path));

        if ($response->failed() && $response->status() !== 404) {
            Log::warning('Supabase delete failed.', ['path' => $path, 'status' => $response->status()]);
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
        return rtrim((string) config('services.supabase.url'), '/')
            .'/storage/v1/object/public/'.config('services.supabase.bucket').'/';
    }

    private static function objectUrl(string $path): string
    {
        return rtrim((string) config('services.supabase.url'), '/')
            .'/storage/v1/object/'.config('services.supabase.bucket').'/'.ltrim($path, '/');
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
