<?php

namespace Database\Seeders;

use App\Models\Church;
use Illuminate\Database\Seeder;

/**
 * Researched history for the churches GIYA ships with.
 *
 * Safe to run on a database that already has data:
 *
 *     php artisan db:seed --class=ChurchHistorySeeder
 *
 * It only fills a church's history when that history is still EMPTY, so it
 * never overwrites text an admin has written. Churches are matched by any of
 * the names they go by (the seeder and the CSV import spell some differently).
 *
 * Every fact below was checked against a published source in October 2026.
 * Two seeded churches (San Agustin Church, Sacred Heart Parish) are left out
 * on purpose: no reliable source was found, and an invented history is worse
 * than none. Add theirs from the admin panel once the parish confirms it.
 *
 * Sources: Wikipedia (Basilica del Santo Niño, Magellan's Cross Pavilion,
 * Cebu Metropolitan Cathedral, Simala Shrine, Pedro Calungsod); SunStar
 * ("A place for San Pedro", 2017); Cebu Daily News (Redemptorists in Cebu;
 * Guadalupe devotion); benjielayug.com (National Shrine of Nuestra Señora
 * de la Regla).
 */
class ChurchHistorySeeder extends Seeder
{
    /** @var array<int, array{names: string[], history: string}> */
    public const HISTORIES = [
        [
            'names'   => ['Basilica del Santo Niño', 'Basilica Minore del Santo Niño', 'Basilica Minore del Sto. Niño'],
            'history' => "The church was founded on April 28, 1565, by the Augustinian friar Andrés de Urdaneta, on the spot where a soldier of Miguel López de Legazpi's expedition found the image of the Santo Niño in a partly burned hut. It is the oldest Roman Catholic church in the Philippines, and the image is believed to be the one Ferdinand Magellan gave as a baptismal gift in 1521.\n\nThe present stone church was begun on February 24, 1735, under Fray Juan de Albarrán and completed on January 16, 1740. Pope Paul VI raised it to a minor basilica on April 1, 1965, for the 400th anniversary of the Christianization of the Philippines.\n\nThe magnitude 7.2 Bohol earthquake of October 15, 2013 destroyed most of its bell tower and façade; the basilica reopened in December 2014 and the bell tower was fully restored in 2016. The Sinulog festival honours the Santo Niño every third Sunday of January, and the Kaplag on April 28 marks the finding of the image.",
        ],
        [
            'names'   => ["Magellan's Cross Chapel", "Magellan's Cross", "Magellan's Cross Pavilion"],
            'history' => "The cross was planted by members of Ferdinand Magellan's Spanish expedition on April 21, 1521, when they arrived in Cebu. It marks the coming of Christianity to the Philippines, when Rajah Humabon and his household were baptized.\n\nThe original wooden cross is now encased in a cross of tindalo wood, added in 1835 to stop people chipping off pieces as relics. The octagonal coral-stone pavilion (kiosk) that shelters it was built in 1834. Its ceiling mural shows the baptism of Rajah Humabon by Fr. Pedro Valderrama and Magellan's men planting the cross. The pavilion stands beside the Basilica del Santo Niño.",
        ],
        [
            'names'   => ['Cebu Metropolitan Cathedral', 'Metropolitan Cathedral of Cebu'],
            'history' => "Cebu became a diocese on August 14, 1595, and its first bishop, Pedro de Agurto, arrived in 1598. Building a lasting cathedral took more than two centuries: attempts between 1689 and 1737 were set back by a typhoon, lack of funds and soft ground. The façade and main body were finished in 1786 and the church was consecrated in 1815, though never fully completed. A new phase began in 1891 and was finished under Bishop Juan Bautista Gorordo between 1904 and 1909.\n\nAir raids on September 12, 1944 destroyed most of the cathedral; only the belfry, façade and walls survived. It was rebuilt in the 1950s under architect José Ma. Zaragoza. Its patron is St. Vitalis of Milan, and it was consecrated to the Most Holy Name of Jesus on November 18, 1994. It was declared an Important Cultural Property in 2023.",
        ],
        [
            'names'   => ['Simala Shrine', 'Monastery of the Holy Eucharist', 'Our Lady of Lindogon Shrine'],
            'history' => "Officially the Monastery of the Holy Eucharist, the shrine in Lindogon, Simala, Sibonga was established in 1998 by the Marian Monks of Eucharistic Adoration, who came from Pampanga. Its image of the Virgin Mary, known as Our Lady of Lindogon, was originally a statue of Our Lady of Fatima donated to the monks.\n\nThe image is reported to have first shed tears in 1998, after which people suffering from dengue in the area were said to be healed. It has been reported to weep several times since, most recently on September 8, 2016. Its castle-like architecture and the many letters of thanksgiving left by pilgrims have made it one of the most visited pilgrimage sites in Cebu.",
        ],
        [
            'names'   => ['San Pedro Calungsod Shrine', 'Archdiocesan Shrine of San Pedro Calungsod'],
            'history' => "The shrine honours San Pedro Calungsod, a young Visayan catechist who went as a lay missionary to the Mariana Islands (Guam) around 1668 with the Jesuit Diego Luis de San Vitores. Both were martyred in Tumon, Guam on April 2, 1672. Pope John Paul II beatified Pedro on March 5, 2000, and Pope Benedict XVI canonized him on October 21, 2012, making him the second Filipino saint.\n\nAfter his beatification the Archdiocese of Cebu built a shrine to promote devotion to the first Visayan 'Beato'. It stands on the grounds of the Archbishop's Residence on D. Jakosalem Street, was funded by Ricardo Cardinal Vidal and benefactors, designed by Prudence Salutillo, and consecrated on December 11, 2002. His feast is now kept on October 21, the anniversary of his canonization.",
        ],
        [
            'names'   => ['Redemptorist Church', 'Redemptorist Church Cebu'],
            'history' => "The Redemptorists, a missionary order, arrived in Cebu in July 1906, landing first in Opon (now Lapu-Lapu City). In Cebu City their church is a centre of the devotion to Our Mother of Perpetual Help, which began in Cebu on September 15, 1946.\n\nDuring the Second World War, the original image of the Santo Niño was kept for safety in the chapel of Our Mother of Perpetual Help at the Redemptorist monastery.",
        ],
        [
            'names'   => ['Our Lady of Guadalupe Parish', 'Our Lady of Guadalupe Church'],
            'history' => "The parish in Barangay Guadalupe is dedicated to Our Lady of Guadalupe, a devotion that began in Mexico. Ricardo Cardinal Vidal declared Our Lady of Guadalupe the principal patroness of Cebu, and the archdiocese has described her as one of the most popular devotions among pilgrims.\n\nNearby in Barangay Kalunasan is the Langub (cave) of Our Lady of Guadalupe, where local tradition holds that she appeared to residents. It is among the most visited religious sites in Cebu City.",
        ],
        [
            'names'   => ['Our Lady of the Rule Parish', 'National Shrine of Nuestra Señora de la Regla', 'Virgen de la Regla Parish', 'Opon Church'],
            'history' => "The parish of Opon, now Lapu-Lapu City, was entrusted to the Augustinians in the 18th century. In 1735 its first parish priest, Fr. Francisco Avalle, introduced the devotion to the Virgen de la Regla (Our Lady of the Rule), first with a picture and later with a hardwood statue.\n\nA Baroque church of Mactan coral stone was built between 1735 and 1744, and the convent that still stands was built by Fr. Simón Aguirre in 1855. The image was canonically crowned by Archbishop Julio Rosales on November 27, 1954, the first in the Visayas to be so honoured. The old church, damaged in the Second World War, was replaced by a concrete church in 1960. The feast is celebrated every November 21.",
        ],
    ];

    public function run(): void
    {
        foreach (self::HISTORIES as $entry) {
            Church::whereIn('name', $entry['names'])
                ->where(fn ($q) => $q->whereNull('history')->orWhere('history', ''))
                ->update(['history' => $entry['history'], 'updated_at' => now()]);
        }

        // The assistant caches what it knows; make it read the new history.
        \App\Services\GiyaAssistant::forget();
    }
}
