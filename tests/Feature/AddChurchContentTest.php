<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\ChurchCategory;
use App\Models\ChurchImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddChurchContentTest extends TestCase
{
    use RefreshDatabase;

    private string $content;
    private string $parishes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->content = tempnam(sys_get_temp_dir(), 'c').'.php';
        file_put_contents($this->content, '<?php return '.var_export([
            ['name' => 'Test Town Church Zz', 'town' => 'Argao', 'history' => 'Built in 1734.',
             'photo' => ['url' => 'https://upload.wikimedia.org/wikipedia/commons/a/ab/Test.jpg', 'author' => 'Ann', 'licence' => 'CC BY-SA 4.0', 'page' => 'https://commons.wikimedia.org/wiki/File:Test.jpg']],
        ], true).';');
        $this->parishes = tempnam(sys_get_temp_dir(), 'p').'.php';
        file_put_contents($this->parishes, '<?php return '.var_export([
            ['name' => 'Small Parish Zz', 'town' => 'Carmen', 'address' => 'Poblacion, Carmen', 'vicariate' => 'St. Augustine', 'district' => '7', 'feast' => 'August 28'],
        ], true).';');
    }

    private function church(string $name, string $town, ?string $history = null): Church
    {
        $cat = ChurchCategory::firstOrCreate(['name' => 'Parish'], ['created_at' => now(), 'updated_at' => now()]);

        return Church::create(['name' => $name, 'category_id' => $cat->id, 'location' => $town, 'municipality' => $town,
            'history' => $history, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function run_(bool $dry = false): void
    {
        $this->artisan('giya:add-church-content', array_filter(['--content' => $this->content, '--parishes' => $this->parishes, '--dry-run' => $dry]))->assertSuccessful();
    }

    public function test_history_and_a_credited_commons_photo_are_added(): void
    {
        $c = $this->church('Test Town Church Zz', 'Argao');
        $this->run_();

        $c->refresh();
        $this->assertSame('Built in 1734.', $c->history);
        $this->assertStringStartsWith('https://upload.wikimedia.org/', $c->imagePath());
        $this->assertSame('Photo: Ann, CC BY-SA 4.0, via Wikimedia Commons', $c->photoCredit()['text']);

        $user = User::create(['name' => 'D', 'email' => 'd@example.com', 'password_hash' => bcrypt('x'), 'role' => 'devotee', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($user)->get(route('churches.show', $c))->assertSee('Photo: Ann, CC BY-SA 4.0')->assertSee('commons.wikimedia.org/wiki/File:Test.jpg', false);
    }

    public function test_a_parish_without_research_gets_a_short_true_paragraph(): void
    {
        $c = $this->church('Small Parish Zz', 'Carmen');
        $this->run_();

        $this->assertSame('Small Parish Zz is a parish of the Archdiocese of Cebu, located in Poblacion, Carmen. It belongs to the Vicariate of St. Augustine in Ecclesiastical District 7. The parish fiesta is celebrated on August 28.', $c->fresh()->history);
    }

    public function test_nothing_an_admin_wrote_or_uploaded_is_replaced_and_dry_run_saves_nothing(): void
    {
        $c = $this->church('Test Town Church Zz', 'Argao', 'Written by the admin.');
        ChurchImage::create(['church_id' => $c->id, 'image_url' => 'images/churches/own.png', 'is_primary' => true, 'uploaded_at' => now(), 'created_at' => now()]);
        $other = $this->church('Small Parish Zz', 'Carmen');

        $this->run_();
        $this->run_(dry: true);

        $this->assertSame('Written by the admin.', $c->fresh()->history);
        $this->assertSame(1, ChurchImage::where('church_id', $c->id)->count());

        Church::whereKey($other->id)->update(['history' => null]);
        $this->run_(dry: true);
        $this->assertNull($other->fresh()->history);
    }

    public function test_the_shipped_content_file_has_no_long_dashes_and_only_commons_photos(): void
    {
        $raw = file_get_contents(database_path('data/parish_content.php'));
        $this->assertStringNotContainsString('—', $raw);
        $this->assertStringNotContainsString('–', $raw);

        foreach (require database_path('data/parish_content.php') as $e) {
            if (isset($e['photo'])) {
                $this->assertTrue(ChurchImage::isCommons($e['photo']['url']), $e['name']);
                $this->assertNotEmpty($e['photo']['author']);
                $this->assertNotEmpty($e['photo']['licence']);
            }
        }
    }
}
