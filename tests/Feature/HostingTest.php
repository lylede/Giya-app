<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\ChurchCategory;
use App\Models\ChurchImage;
use App\Models\User;
use App\Support\Uploads;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Running on Vercel / Render: uploads to Supabase, HTTPS behind a proxy.
 *
 * Supabase itself cannot be reached from a test, so its API is faked and the
 * tests check what GIYA sends and what it saves.
 */
class HostingTest extends TestCase
{
    use RefreshDatabase;

    private const SUPABASE = 'https://abcd1234.supabase.co';

    private function useSupabase(bool $fake = true): void
    {
        config([
            'services.supabase.url'    => self::SUPABASE,
            'services.supabase.key'    => 'sb_secret_test',
            'services.supabase.bucket' => 'giya-uploads',
        ]);

        if ($fake) {
            Http::fake([self::SUPABASE.'/*' => Http::response(['Key' => 'giya-uploads/x', 'Id' => 'abc'], 200)]);
        }
    }

    private function user(string $role = 'devotee', array $extra = []): User
    {
        return User::create(array_merge([
            'name' => 'Test', 'email' => $role.'@example.com', 'password_hash' => bcrypt('secret'),
            'role' => $role, 'status' => 'Active', 'created_at' => now(), 'updated_at' => now(),
        ], $extra));
    }

    private function church(): Church
    {
        $cat = ChurchCategory::create(['name' => 'Shrine', 'created_at' => now(), 'updated_at' => now()]);

        return Church::create([
            'name' => 'Simala Shrine', 'category_id' => $cat->id, 'location' => 'Sibonga',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** A real PNG without needing the GD extension. */
    private function png(string $name = 'photo.png'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'png');
        file_put_contents($path, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        ));

        return new UploadedFile($path, $name, 'image/png', null, true);
    }

    public function test_without_supabase_settings_uploads_stay_local(): void
    {
        config(['services.supabase.url' => null, 'services.supabase.key' => null]);

        $this->assertFalse(Uploads::cloud());
    }

    public function test_a_church_photo_goes_to_supabase_and_its_url_is_saved(): void
    {
        $this->useSupabase();
        $church = $this->church();

        $this->actingAs($this->user('admin'))
            ->post(route('admin.destinations.photo', $church), ['photo' => $this->png()])
            ->assertSessionHas('success');

        $url = ChurchImage::where('church_id', $church->id)->value('image_url');

        $this->assertStringStartsWith(self::SUPABASE.'/storage/v1/object/public/giya-uploads/churches/simala-shrine-', $url);
        $this->assertStringEndsWith('.png', $url);
        $this->assertSame($url, $church->fresh()->imagePath(), 'The church page would not show the uploaded photo.');

        Http::assertSent(fn (Request $r) => $r->method() === 'POST'
            && str_starts_with($r->url(), self::SUPABASE.'/storage/v1/object/giya-uploads/churches/simala-shrine-')
            && $r->hasHeader('apikey', 'sb_secret_test')
            && $r->hasHeader('x-upsert', 'true'));

        // Nothing was written to public/ - it would vanish on Vercel anyway.
        $this->assertFileDoesNotExist(public_path('images/churches/simala-shrine.png'));
    }

    public function test_replacing_a_photo_deletes_the_old_one_from_supabase(): void
    {
        $this->useSupabase();
        $church = $this->church();
        $old = self::SUPABASE.'/storage/v1/object/public/giya-uploads/churches/simala-shrine-20260101000000.png';

        ChurchImage::create([
            'church_id' => $church->id, 'image_url' => $old, 'is_primary' => true,
            'uploaded_at' => now(), 'created_at' => now(),
        ]);

        $this->actingAs($this->user('admin'))
            ->post(route('admin.destinations.photo', $church), ['photo' => $this->png()]);

        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE'
            && $r->url() === self::SUPABASE.'/storage/v1/object/giya-uploads/churches/simala-shrine-20260101000000.png');
        $this->assertSame(1, ChurchImage::where('church_id', $church->id)->count());
    }

    public function test_a_failed_upload_keeps_the_old_photo(): void
    {
        $this->useSupabase(fake: false);
        Http::fake([self::SUPABASE.'/*' => Http::response(['error' => 'Bucket not found'], 404)]);
        $church = $this->church();
        ChurchImage::create([
            'church_id' => $church->id, 'image_url' => 'images/churches/simala-shrine.png', 'is_primary' => true,
            'uploaded_at' => now(), 'created_at' => now(),
        ]);

        $this->actingAs($this->user('admin'))
            ->post(route('admin.destinations.photo', $church), ['photo' => $this->png()])
            ->assertSessionHas('error');

        $this->assertSame('images/churches/simala-shrine.png', ChurchImage::where('church_id', $church->id)->value('image_url'));
    }

    /** An unreachable Supabase used to surface as a 500 page. */
    public function test_an_unreachable_storage_shows_a_message_not_a_500(): void
    {
        $this->useSupabase(fake: false);
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('Could not resolve host'));
        $church = $this->church();

        $this->actingAs($this->user('admin'))
            ->post(route('admin.destinations.photo', $church), ['photo' => $this->png()])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    /** A key pasted with a trailing line break made the request invalid: a 500 on Vercel. */
    public function test_a_key_with_a_stray_line_break_is_trimmed_and_cannot_cause_a_500(): void
    {
        putenv("SUPABASE_SERVICE_KEY=sb_secret_a\nbc \n");
        $config = require config_path('services.php');
        putenv('SUPABASE_SERVICE_KEY');

        $this->assertSame('sb_secret_abc', $config['supabase']['key']);

        // And even if a bad header slips through, saving shows a message.
        $this->useSupabase(fake: false);
        config(['services.supabase.key' => "sb_secret_abc\nbad"]);
        Http::fake();
        $church = $this->church();

        $this->actingAs($this->user('admin'))
            ->post(route('admin.destinations.photo', $church), ['photo' => $this->png()])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_deleting_an_old_photo_never_throws(): void
    {
        $this->useSupabase(fake: false);
        config(['services.supabase.key' => "bad\nkey"]);
        Http::fake();

        Uploads::delete(self::SUPABASE.'/storage/v1/object/public/giya-uploads/churches/old.png');

        $this->assertTrue(true, 'delete() threw');
    }

    /** The S3 endpoint was pasted as SUPABASE_URL: uploads went nowhere, silently. */
    public function test_any_supabase_address_form_is_turned_into_the_project_url(): void
    {
        foreach ([
            'https://abcd1234.supabase.co',
            'https://abcd1234.supabase.co/',
            'https://abcd1234.storage.supabase.co/storage/v1/s3',
            'https://abcd1234.supabase.co/storage/v1',
            'abcd1234.supabase.co',
        ] as $given) {
            config(['services.supabase.url' => $given]);
            $this->assertSame('https://abcd1234.supabase.co', Uploads::projectUrl(), $given);
        }
    }

    public function test_an_upload_supabase_did_not_confirm_is_an_error_not_a_success(): void
    {
        $this->useSupabase(fake: false);
        Http::fake([self::SUPABASE.'/*' => Http::response('<html>not the storage API</html>', 200)]);
        $church = $this->church();

        $this->actingAs($this->user('admin'))
            ->post(route('admin.destinations.photo', $church), ['photo' => $this->png()])
            ->assertSessionHas('error');

        $this->assertSame(0, ChurchImage::where('church_id', $church->id)->count());
    }

    /** The preview stayed white: [hidden] { display:none !important } beat style.display. */
    public function test_the_upload_preview_toggles_the_hidden_attribute(): void
    {
        $view = file_get_contents(resource_path('views/admin/destinations.blade.php'));

        $this->assertStringContainsString('img.hidden = !src;', $view);
        $this->assertStringNotContainsString("img.style.display = 'block'", $view);
    }

    public function test_a_profile_picture_goes_to_supabase(): void
    {
        $this->useSupabase();
        $user = $this->user();

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => 'Test', 'email' => $user->email, 'avatar' => $this->png('me.png'),
        ])->assertRedirect(route('profile'));

        $url = $user->fresh()->avatar_url;
        $this->assertStringStartsWith(self::SUPABASE.'/storage/v1/object/public/giya-uploads/avatars/user-'.$user->id.'-', $url);
    }

    public function test_photos_from_other_sites_are_never_deleted(): void
    {
        $this->useSupabase();

        Uploads::delete('https://example.com/some-photo.jpg');
        Uploads::delete('images/churches/simala-shrine.png');

        Http::assertNothingSent();
    }

    /** Behind Vercel/Render the page must link its CSS over https, or the browser blocks it. */
    public function test_behind_a_trusted_proxy_links_are_https_and_the_real_ip_is_used(): void
    {
        config(['app.trusted_proxies' => '*']);
        (new \App\Providers\AppServiceProvider($this->app))->boot();

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
            ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-For' => '203.0.113.7'])
            ->get('/login')
            ->assertOk()
            ->assertSee('https://', false)
            ->assertDontSee('href="http://localhost', false);

        $this->assertSame('203.0.113.7', request()->ip());

        \Illuminate\Http\Middleware\TrustProxies::flushState();
    }

    public function test_the_deploy_files_are_present_and_point_at_singapore(): void
    {
        $this->assertFileExists(base_path('Dockerfile'));
        $this->assertStringContainsString('pdo_pgsql', file_get_contents(base_path('Dockerfile')));
        $this->assertStringContainsString('sin1', file_get_contents(base_path('vercel.json')));
        $this->assertStringContainsString('region: singapore', file_get_contents(base_path('render.yaml')));
        $this->assertStringContainsString('.env', file_get_contents(base_path('.dockerignore')));
    }
}
