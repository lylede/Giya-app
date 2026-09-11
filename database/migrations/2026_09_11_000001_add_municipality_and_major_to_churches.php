<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Which town a church is in, and whether it is that town's principal one.
 *
 * `location` is a free-text address - "P. Burgos St, Cebu City", "Lindogon,
 * Sibonga, Cebu" - so there was nothing to group by. Reading the town out of
 * that string every time it is needed would work until an address is worded
 * unusually, and then the church would quietly belong to nowhere and no one
 * would find out. A column can be wrong too, but it can be seen and corrected.
 *
 * is_major is deliberately separate from is_featured. Featured decides what
 * the home page shows; major says this is the mother church of its town. They
 * are different judgements and coupling them would mean one could not be
 * changed without changing the other.
 */
return new class extends Migration
{
    /**
     * The cities and municipalities a GIYA address can name.
     *
     * Matched longest first, which fillMunicipalities() does by sorting
     * rather than by relying on the order written here: 'Bantayan' is inside
     * 'Daanbantayan', and taken as written it files every Daanbantayan
     * address under a town three hours away.
     */
    private const TOWNS = [
        'Lapu-Lapu City', 'Mandaue City', 'Talisay City', 'Naga City',
        'Carcar City', 'Danao City', 'Toledo City', 'Bogo City', 'Cebu City',
        'Minglanilla', 'Consolacion', 'Compostela', 'San Fernando', 'Sibonga',
        'Argao', 'Dalaguete', 'Liloan', 'Cordova', 'Balamban', 'Barili',
        'Oslob', 'Moalboal', 'Badian', 'Boljoon', 'Dumanjug', 'Ronda',
        'Alcoy', 'Aloguinsan', 'Asturias', 'Catmon', 'Consuelo', 'Ginatilan',
        'Malabuyoc', 'Pinamungajan', 'Samboan', 'Santander', 'Sogod',
        'Tabogon', 'Tabuelan', 'Tuburan', 'Alcantara', 'Alegria', 'Bantayan',
        'Carmen', 'Daanbantayan', 'Madridejos', 'Medellin', 'Pilar',
        'Poro', 'San Francisco', 'San Remigio', 'Santa Fe', 'Sante Fe',
        'Borbon', 'Tudela',
    ];

    /** Ranks that make a church its town's principal one on sight. */
    private const MAJOR_RANKS = ['Basilica', 'Cathedral', 'Shrine'];

    public function up(): void
    {
        Schema::table('churches', function (Blueprint $table) {
            $table->string('municipality', 120)->nullable()->after('location');
            $table->boolean('is_major')->default(false)->after('is_featured');

            // Both are filtered on, and a filter is the one thing that runs
            // for every devotee who opens the map.
            $table->index('municipality');
            $table->index('is_major');
        });

        $this->fillMunicipalities();
        $this->seedMajors();
    }

    public function down(): void
    {
        Schema::table('churches', function (Blueprint $table) {
            $table->dropIndex(['municipality']);
            $table->dropIndex(['is_major']);
            $table->dropColumn(['municipality', 'is_major']);
        });
    }

    /**
     * Read the town out of each address, once.
     *
     * Anything that cannot be read is left null rather than guessed at - a
     * blank is a question someone can answer, and a wrong town is one nobody
     * knows to ask.
     */
    private function fillMunicipalities(): void
    {
        $rows = DB::table('churches')->select('id', 'location', 'address')->get();

        $towns = self::TOWNS;
        usort($towns, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        foreach ($rows as $row) {
            $haystack = trim(($row->location ?? '').' '.($row->address ?? ''));

            foreach ($towns as $town) {
                if (stripos($haystack, $town) !== false) {
                    DB::table('churches')->where('id', $row->id)->update(['municipality' => $town]);
                    break;
                }
            }
        }
    }

    /**
     * A starting point, not an answer.
     *
     * One church per town: a Basilica, Cathedral or Shrine if the town has
     * one, otherwise its oldest record, which for a parish is usually the
     * mother church. Which church truly is a town's principal one is a
     * judgement about that place, so this only makes sure the filter is not
     * empty on the first day - the admin screen is where it gets corrected.
     */
    private function seedMajors(): void
    {
        $towns = DB::table('churches')
            ->whereNotNull('municipality')
            ->distinct()
            ->pluck('municipality');

        foreach ($towns as $town) {
            $pick = DB::table('churches')
                ->join('church_categories', 'churches.category_id', '=', 'church_categories.id')
                ->where('churches.municipality', $town)
                ->where('churches.is_active', true)
                ->orderByRaw(
                    'case when church_categories.name = ? then 0
                          when church_categories.name = ? then 1
                          when church_categories.name ilike ? then 2
                          else 3 end',
                    [self::MAJOR_RANKS[1], self::MAJOR_RANKS[0], '%'.self::MAJOR_RANKS[2].'%']
                )
                ->orderBy('churches.id')
                ->value('churches.id');

            if ($pick) {
                DB::table('churches')->where('id', $pick)->update(['is_major' => true]);
            }
        }
    }
};
