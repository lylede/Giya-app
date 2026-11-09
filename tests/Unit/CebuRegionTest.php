<?php

namespace Tests\Unit;

use App\Support\CebuRegion;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CebuRegionTest extends TestCase
{
    public static function towns(): array
    {
        return [
            ['Pilar', 'camotes'],
            ['San Francisco', 'camotes'],
            ['City of Carcar', 'south'],
            ['Lindogon, Sibonga, Cebu', 'south'],
            ['Oslob', 'south'],
            ['Moalboal', 'south'],
            ['Toledo City', 'midwest'],
            ['Daanbantayan', 'north'],
            ['City of Bogo', 'north'],
            ['Danao City', 'north'],
            ['Bantayan', 'bantayan'],
            ['Santa Fe', 'bantayan'],
            ['Cebu City', 'metro'],
            ['A.C. Cortes Ave, Mandaue City', 'metro'],
            ['Poblacion, Lapu-Lapu City', 'metro'],
        ];
    }

    #[DataProvider('towns')]
    public function test_each_town_is_in_its_part_of_cebu(string $town, string $region): void
    {
        $this->assertSame($region, CebuRegion::of($town));
    }

    public function test_an_unknown_town_has_no_region(): void
    {
        $this->assertNull(CebuRegion::of('Somewhere'));
        $this->assertSame('OTHER CEBU', CebuRegion::label(null));
    }

    public static function questions(): array
    {
        return [
            ['i want to explore the churches in south cebu', 'south'],
            ['southern churches please', 'south'],
            ['mga simbahan sa habagatan', 'south'],
            ['give northern church', 'north'],
            ['simbahan sa amihanan', 'north'],
            ['churches on Camotes', 'camotes'],
            ['churches near Toledo on the west side', 'midwest'],
            ['what to wear at Simala?', null],
            ['churches in Daanbantayan', null],
        ];
    }

    #[DataProvider('questions')]
    public function test_the_region_a_question_asks_about(string $question, ?string $region): void
    {
        $this->assertSame($region, CebuRegion::asked($question));
    }
}
