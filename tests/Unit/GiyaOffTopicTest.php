<?php

namespace Tests\Unit;

use App\Services\GiyaAssistant;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class GiyaOffTopicTest extends TestCase
{
    public static function offTopic(): array
    {
        return [
            ['create a code using python for hello world'],
            ['write a javascript function to sort an array'],
            ['help me debug my program'],
            ['solve this equation 2x + 3 = 7'],
            ['what is 25 * 4 = ?'],
            ['can you make an html page'],
            ['Pwede ko mangayo og code sa PHP?'],
        ];
    }

    public static function onTopic(): array
    {
        return [
            ['what is the oldest church in cebu'],
            ['what is the dress code for the basilica?'],
            ['plan a visita iglesia in the south'],
            ['unsa ang oras sa misa sa Argao?'],
            ['who are you'],
            ['how many churches can I visit in 1 day?'],
            ['is there a code of conduct inside the church'],
        ];
    }

    #[DataProvider('offTopic')]
    public function test_plainly_off_topic_requests_are_caught(string $q): void
    {
        $this->assertTrue(GiyaAssistant::offTopic($q), $q);
    }

    #[DataProvider('onTopic')]
    public function test_pilgrimage_questions_are_not_caught(string $q): void
    {
        $this->assertFalse(GiyaAssistant::offTopic($q), $q);
    }
}
