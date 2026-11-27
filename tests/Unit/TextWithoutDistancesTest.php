<?php

namespace Tests\Unit;

use App\Support\Text;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TextWithoutDistancesTest extends TestCase
{
    public static function replies(): array
    {
        return [
            'bracket'          => ['Catmon Church (about 3 km) is lovely.', 'Catmon Church is lovely.'],
            'direction kept'   => ['It is about 3.2 km north of Catmon.', 'It is north of Catmon.'],
            'away'             => ['It is about 3 km away.', 'It is nearby.'],
            'less than one'    => ['It is less than 1 km away.', 'It is nearby.'],
            'range, spelled'   => ['Sogod is 3-5 kilometers north of here.', 'Sogod is north of here.'],
            'within'           => ['Churches within 5 km of Carmen are many.', 'Churches near Carmen are many.'],
            'cebuano'          => ['Mga 12 km gikan sa Cebu City.', 'Mga gikan sa Cebu City.'],
        ];
    }

    #[DataProvider('replies')]
    public function test_distances_are_taken_out(string $in, string $out): void
    {
        $this->assertSame($out, Text::withoutDistances($in));
    }

    public function test_times_speeds_and_indents_are_left_alone(): void
    {
        $text = "Mass is at 9:00 AM, 10 minutes after.\n   drive about 40 min\nDrive at 60 km/h here.";

        $this->assertSame($text, Text::withoutDistances($text));
    }
}
