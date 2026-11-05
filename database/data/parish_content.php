<?php

/*
| Researched history and free-licensed photos for Archdiocese of Cebu parishes.
| Loaded by `php artisan giya:add-church-content` (never overwrites what an
| admin has written or uploaded).
|
| history: written in our own words from the English Wikipedia article on the
|          church or its town (read 5 Nov 2026). Only facts a source states.
| photo:   from Wikimedia Commons, shown with its author and licence as the
|          licence requires. 'url' is the 1280 px version Commons serves.
|
| Churches not listed here get a short "about" paragraph built from the
| Archdiocese of Cebu data (database/data/archdiocese_parishes.php) until a
| researched history is added.
*/

$c = fn (string $file, string $author, string $licence, string $url) => [
    'url' => $url, 'author' => $author, 'licence' => $licence,
    'page' => 'https://commons.wikimedia.org/wiki/File:'.str_replace(' ', '_', $file),
];
$t = 'https://thumb.wikimedia.org/wikipedia/commons/thumb/';

return [
    [
        'name' => 'Archdiocesan Shrine of Sta. Teresa de Avila', 'town' => 'Talisay',
        'history' => "Talisay was once an Augustinian estate and a visita of San Nicolas, the district south of old Cebu. As its population grew it was made an independent parish on August 16, 1836, under Fr. Juan Soriano, by a royal decree signed on April 25, 1836. The church, named after its patron Saint Teresa of Avila, was built from 1836 to 1848. A typhoon later destroyed its clay tile roof, which was replaced with iron sheets in 1877, and the interior was decorated in 1894.\n\nThe church and convent were heavily damaged near the end of the Second World War, and the parish records were lost, but the facade survived in its original form. Teofilo Camomot led the post-war restoration. Built of coral stone in a classical Graeco-Roman style, it is known for its twin bell towers joined by a balustraded portico on two columns. Cardinal Ricardo Vidal declared it an archdiocesan shrine on October 15, 2007, during its fiesta.",
        'photo' => $c('Saint Teresa of Ávila Church in Talisay, Cebu 2017 æ.jpg', 'Øyvind Holmstad', 'CC BY-SA 4.0', $t.'9/99/Saint_Teresa_of_%C3%81vila_Church_in_Talisay%2C_Cebu_2017_%C3%A6.jpg/1280px-Saint_Teresa_of_%C3%81vila_Church_in_Talisay%2C_Cebu_2017_%C3%A6.jpg'),
    ],
    [
        'name' => 'Archdiocesan Shrine of St. Michael Archangel', 'town' => 'Argao',
        'history' => "Argao was established as a parish in 1703 under the Augustinians. Its stone church, dedicated to Saint Michael the Archangel, was begun in 1734 and completed in 1788. Together with its convent it was fortified to shelter townspeople during Moro raids in the 18th and 19th centuries, and two watchtowers (baluarte) still stand within the church complex.\n\nThe coral stone church has an ornate pediment, double pilasters and carvings of its patron on the facade. Inside, five retablos adorn the sanctuary, the main one holding life-size statues of the archangels Michael, Raphael and Gabriel. Half of the ceiling paintings are by the Cebuano master Raymundo Francia. The convent beside the church once served as a seminary and now houses the parish office and the Museo de la Parroquia de San Miguel.",
        'photo' => $c('Saint Michael the Archangel Church Argao (Rizal Street, Argao, Cebu; 01-18-2023).jpg', 'Patrickroque01', 'CC BY-SA 4.0', $t.'3/3e/Saint_Michael_the_Archangel_Church_Argao_%28Rizal_Street%2C_Argao%2C_Cebu%3B_01-18-2023%29.jpg/1280px-Saint_Michael_the_Archangel_Church_Argao_%28Rizal_Street%2C_Argao%2C_Cebu%3B_01-18-2023%29.jpg'),
    ],
    [
        'name' => 'The Archdiocesan Shrine of Patrocinio de Maria Santisima', 'town' => 'Boljoon',
        'history' => "Boljoon, then called Nabulho, became a visita of Sialo in 1599, dedicated to the Patronage of the Virgin Mary. Fr. Francisco de Zamora made it a parish on October 31, 1690, and Fr. Nicolas de la Cuadra became its first parish priest in 1692. Pirates destroyed the earlier buildings in 1782, and the Augustinian Fr. Ambrosio Otero began the present church in 1783. Fr. Manuel Cordero continued the work and Fr. Julian Bermejo completed it, adding the gates, walls and blockhouse between 1802 and 1808.\n\nBuilt on a hill by the sea as a fortress church against Moro raids, it is made of coral stone and lime, with walls about 2 metres thick and Filipino Baroque details in its choir screen and pulpit. It was declared a National Historical Landmark in 1999 and a National Cultural Treasure in 2001. The image of Patrocinio de Maria Santisima received episcopal coronation on November 14, 2020 and canonical coronation rites on April 23, 2022.",
        'photo' => $c('Patrocinio de Maria Church facade (Cebu South Road, Bonjoon, Cebu; 01-18-2023).jpg', 'Patrickroque01', 'CC BY-SA 4.0', $t.'8/88/Patrocinio_de_Maria_Church_facade_%28Cebu_South_Road%2C_Bonjoon%2C_Cebu%3B_01-18-2023%29.jpg/1280px-Patrocinio_de_Maria_Church_facade_%28Cebu_South_Road%2C_Bonjoon%2C_Cebu%3B_01-18-2023%29.jpg'),
    ],
    [
        'name' => 'San Guillermo de Aquitania Parish', 'town' => 'Dalaguete',
        'history' => "The coral stone church of Dalaguete was built from 1802 to 1825. It has a three-level facade with carved religious decoration, a three-tiered bell tower joined to the church by a low structure, and a convent across from the belfry. Pointed arches over its side windows hint at Gothic influence, while its main and side retablos are Rococo. The ceiling paintings were made by Canuto Avila and his sons in 1935.\n\nThe church is dedicated to San Guillermo, but the town fiesta falls on February 10, the feast of William of Maleval, rather than that of William of Aquitaine, which has long been debated. It was declared a national historical landmark in 2004 and a National Cultural Treasure by the National Museum on June 27, 2019, and its historical marker was unveiled on February 9, 2024.",
        'photo' => $c('San Guillermo Church Dalaguete (San Guillermo Street, Dalaguete, Cebu; 01-18-2023).jpg', 'Patrickroque01', 'CC BY-SA 4.0', $t.'1/16/San_Guillermo_Church_Dalaguete_%28San_Guillermo_Street%2C_Dalaguete%2C_Cebu%3B_01-18-2023%29.jpg/1280px-San_Guillermo_Church_Dalaguete_%28San_Guillermo_Street%2C_Dalaguete%2C_Cebu%3B_01-18-2023%29.jpg'),
    ],
    [
        'name' => 'National Shrine of St. Joseph', 'town' => 'Mandaue',
        'history' => "The Jesuits established Mandaue as a mission in 1638 and built a church in honour of Saint Joseph. After the Jesuits were expelled in 1768, the Augustinian Recollects ran the parish until 1898. The church was damaged by an earthquake in 1922 and repaired by 1936, when a bell tower was added to the top of the facade. During the Second World War a bomb blew open its roof, damaging the bell tower, its life-size images of the Last Supper and its records.\n\nA major renovation in 1998 removed the pillars that held up the roof, changing much of its original look. With the approval of the Catholic Bishops' Conference of the Philippines, Cardinal Ricardo Vidal declared the parish a national shrine on August 6, 2001.",
        'photo' => $c('Mandaue Saint Joseph National Shrine 2026-07-11.jpg', 'LMP 2001', 'CC BY-SA 4.0', $t.'c/ca/Mandaue_Saint_Joseph_National_Shrine_2026-07-11.jpg/1280px-Mandaue_Saint_Joseph_National_Shrine_2026-07-11.jpg'),
    ],
    [
        'name' => 'Our Lady of the Immaculate Conception Parish', 'town' => 'Oslob',
        'history' => "Oslob was first served by the Augustinians, who handed it to the Jesuits from 1737 to 1742. Fr. Julian Bermejo laid the foundation of the present Immaculate Conception Church on May 4, 1830, following a plan by Bishop Santos Gomez Maranon. The church was blessed in 1847 and Oslob became an independent parish in 1848. Fr. Apolinar Alvares built the bell tower in 1858.\n\nThe church burned during the Second World War and again in 1955, and its restoration was finished in 1980. Fire struck its roof and interior once more in 2008, but the image of the Immaculate Conception survived. On March 4, 2025, the church was affiliated with the Papal Basilica of Saint Mary Major in Rome.",
        'photo' => $c('Immaculate Conception Church Oslob front (Eternidad, Oslob, Cebu; 01-18-2023) (cropped).jpg', 'Patrickroque01', 'CC BY-SA 4.0', $t.'e/e9/Immaculate_Conception_Church_Oslob_front_%28Eternidad%2C_Oslob%2C_Cebu%3B_01-18-2023%29_%28cropped%29.jpg/1280px-Immaculate_Conception_Church_Oslob_front_%28Eternidad%2C_Oslob%2C_Cebu%3B_01-18-2023%29_%28cropped%29.jpg'),
    ],
    [
        'name' => 'Our Lady of the Pillar Parish', 'town' => 'Sibonga',
        'history' => "Sibonga began as a visita of Carcar in 1690 under the Augustinians. It became a parish dedicated to Our Lady of the Pillar of Zaragoza in 1830, first with a wooden church. The present church of stone and coral was built from 1866 to 1898 in the Neo-Gothic style, and after a renovation under Fr. Francisco Latorre it was inaugurated on November 17, 1907.\n\nThe church is best known for its ceiling mural by Cebuano artist Raymundo Francia, painted from 1927 to 1931 in trompe l'oeil, which makes the flat ceiling look carved. The National Historical Commission of the Philippines unveiled a historical marker at the church on December 2, 2010.",
        'photo' => $c('Sibonga Church 2024-07-06.jpg', 'LMP 2001', 'CC BY-SA 4.0', $t.'3/3e/Sibonga_Church_2024-07-06.jpg/1280px-Sibonga_Church_2024-07-06.jpg'),
    ],
    [
        'name' => 'Archdiocesan Shrine of St. Catherine of Alexandria', 'town' => 'Carcar',
        'history' => "Carcar has been a parish since 1599, run by the Augustinians. The present church, named after the town's patron Saint Catherine of Alexandria, was built in the 19th century, when Cebu was growing rich as a producer of agricultural goods.\n\nBuilt of coral stone with upper storeys and ceiling of hardwood, the church mixes several styles, with touches of Neo-Mudejar, Revival Baroque and Austrian Baroque and a Gothic Revival choir loft. Its ceiling was painted by Cebuano artist Canuto Avila from the 1910s to the 1920s, in trompe l'oeil with rosettes. The convent beside it shows features of the native bahay na bato.",
        'photo' => $c('Carcar Church 2024-07-06.jpg', 'LMP 2001', 'CC BY-SA 4.0', $t.'d/d3/Carcar_Church_2024-07-06.jpg/1280px-Carcar_Church_2024-07-06.jpg'),
    ],
    [
        'name' => 'Sts. Peter and Paul Parish', 'town' => 'Bantayan',
        'history' => "The first church of Bantayan was built by Augustinian priests on June 15, 1580, making it the oldest parish in the Visayas and Mindanao and older than the Diocese of Cebu itself, founded in 1595. First dedicated to Our Lady of the Assumption, it was later placed under Saint Peter the Apostle. Its territory once reached as far as Biliran and parts of Leyte. Moro raiders burned the church in 1600, and it was soon rebuilt.\n\nIn 1824 Pope Leo XII exempted the people of Bantayan from abstaining from meat during Lent, which is why the island still keeps that custom. The present church was built from 1839 to 1863. Saint Paul was added as a second patron in the 1980s, and from 2018 to 2019 twelve artists painted the 1,130 square metre ceiling mural Dibuho sa Kisame. The church was partly damaged in the 2025 Cebu earthquake.",
        'photo' => $c('Bantayan Church Front View.JPG', 'Sherlock S. Sison', 'CC BY-SA 3.0', $t.'e/ef/Bantayan_Church_Front_View.JPG/1280px-Bantayan_Church_Front_View.JPG'),
    ],
    [
        'name' => 'Sta. Rosa de Lima Parish', 'town' => 'Daanbantayan',
        'history' => "The parish of Daanbantayan was established on April 10, 1858, and its stone church was built in 1886. On August 30, 2022, the Archdiocese of Cebu raised it to an archdiocesan shrine, the first church dedicated to Saint Rose of Lima to receive that status.\n\nThe church was badly damaged in the 2025 Cebu earthquake, losing about 70 to 80 percent of its structure, though the retablo of Saint Rose of Lima survived. There are plans to restore the building.",
        'photo' => $c('St. Rose of Lima Parish Church in Daanbantayan, Cebu.jpg', 'Rabosajr', 'CC BY-SA 3.0', $t.'4/40/St._Rose_of_Lima_Parish_Church_in_Daanbantayan%2C_Cebu.jpg/1280px-St._Rose_of_Lima_Parish_Church_in_Daanbantayan%2C_Cebu.jpg'),
    ],
    [
        'name' => 'Archdiocesan Shrine of the Immaculate Heart of Mary', 'town' => 'Minglanilla',
        'history' => "Fr. Sanchez, the first parish priest of Minglanilla, is credited as the founder of the town in 1858, and he built its roads and bridges together with Fr. Magaz.\n\nMinglanilla is known as the Sugat Capital of the South. Every Easter, the church plaza hosts the Sugat, a re-enactment of the meeting of the risen Christ and his mother, whose images meet on decorated carrozas amid songs and child angels suspended on wires. It draws visitors from nearby towns and from Cebu City.",
        'photo' => $c('Archdiocesan Shrine of the Immaculate Heart of Mary (18 De Julio, Minglanilla, Cebu; 09-07-2022).jpg', 'Patrickroque01', 'CC BY-SA 4.0', $t.'e/e5/Archdiocesan_Shrine_of_the_Immaculate_Heart_of_Mary_%2818_De_Julio%2C_Minglanilla%2C_Cebu%3B_09-07-2022%29.jpg/1280px-Archdiocesan_Shrine_of_the_Immaculate_Heart_of_Mary_%2818_De_Julio%2C_Minglanilla%2C_Cebu%3B_09-07-2022%29.jpg'),
    ],
    [
        'name' => 'Archdiocesan Shrine of St. Anne', 'town' => 'Barili',
        'history' => "The parish of Barili was established in 1614, before the town itself was founded in 1632. A cross erected by the Spanish on their arrival in 1602 still stands in the town. The parish is dedicated to Saint Anne, whose feast is kept every July 26.",
        'photo' => $c('Barili Church.jpg', 'Elmer B. Domingo', 'CC BY-SA 4.0', $t.'3/39/Barili_Church.jpg/1280px-Barili_Church.jpg'),
    ],
    [
        'name' => 'San Juan Nepomuceno Parish', 'town' => 'Moalboal',
        'history' => "In 1851 the Spanish governor approved a petition to make Moalboal a parish, and the bishop's decree followed on January 20, 1852. The San Juan Nepomuceno Parish was officially established on February 6, 1852.\n\nDuring the Second World War the town was burned by Japanese forces in reprisal for an ambush, so no ancestral houses remain. The parish church is the only old structure left, and today only its belfry and outer walls date from the original construction.",
        'photo' => $c('Moalboal church.jpg', 'john valentine ii', 'CC BY-SA 2.0', 'https://upload.wikimedia.org/wikipedia/commons/d/d8/Moalboal_church.jpg'),
    ],
    [
        'name' => 'St. Francis Xavier Parish', 'town' => 'Alegria',
        'history' => "In 1854 the parish priest of Malabuyoc, Fr. Lucas Clavesillas de la Soledad, recommended to the Bishop of Cebu that Alegria be made a parish. After petitions to the governor and to Manila, the town was created a parish on July 3, 1856, together with the barrio of Guiuanon, and the parish was canonically erected on March 13, 1857.\n\nIts titular patron is Saint Francis Xavier, whose feast is kept on December 3, with Saint Joseph as secondary patron. Since 2006 the fiesta has included the Kawayan Festival, which showcases the town's bamboo products.",
        'photo' => $c('Alegria Church, Cebu.jpg', 'Prussiakira', 'CC BY-SA 3.0', $t.'5/5a/Alegria_Church%2C_Cebu.jpg/1280px-Alegria_Church%2C_Cebu.jpg'),
    ],
    [
        'name' => 'San Nicolas de Tolentino Parish', 'town' => 'Malabuyoc',
        'history' => "Malabuyoc separated its parish from Samboan in 1832, and its parish church was finished in 1834. It is the mother town of both Alegria and Ginatilan. The parish is dedicated to Saint Nicholas of Tolentino, and since 2004 his September fiesta has included the Binuyocan Festival.",
        'photo' => $c('Malabuyoc Church in Malabuyoc, Cebu 01.jpg', 'Carlo Joseph Moskito', 'CC BY-SA 4.0', $t.'7/7f/Malabuyoc_Church_in_Malabuyoc%2C_Cebu_01.jpg/1280px-Malabuyoc_Church_in_Malabuyoc%2C_Cebu_01.jpg'),
    ],
    [
        'name' => 'St. Michael Archangel Parish', 'town' => 'Samboan',
        'history' => "Samboan was once a much bigger town that included Alegria, and the people of Alegria were made to work on the construction of its church. The St. Michael Archangel Church is one of the oldest Spanish-built churches in Cebu, made of coral stone, and it is protected as a national heritage structure with a permanent marker from the National Historical Commission. The town fiesta is held every September 29 and 30 in honour of Saint Michael the Archangel.",
        'photo' => $c('Samboan Church in Samboan, Cebu 01.jpg', 'Carlo Joseph Moskito', 'CC BY-SA 4.0', $t.'a/ac/Samboan_Church_in_Samboan%2C_Cebu_01.jpg/1280px-Samboan_Church_in_Samboan%2C_Cebu_01.jpg'),
    ],
    [
        'name' => 'Archdiocesan Shrine of San Antonio de Padua', 'town' => 'Tuburan',
        'history' => "A royal decree of July 26, 1854 made Tuburan a parish separate from Pinamungajan, confirmed by Bishop Romualdo Jimeno Ballesteros on February 13, 1857. Led by Fr. Prospero Salazar, the townspeople first built a modest church of limestone and coral dedicated to Saint Anthony of Padua.\n\nBetween 1884 and 1891 the parish built a stone church in a semi-Romanesque and Baroque style (1884), a limestone bell tower (1885) and a large convent of coral stone and hardwood finished in 1891 under Fr. Mauricio Esmero. The church was destroyed in the Second World War and later rebuilt. The Tubod Festival is held every June 13 for the parish patron.",
    ],
    [
        'name' => 'San Juan Nepomuceno Parish', 'town' => 'San Remigio',
        'history' => "San Remigio began as the visita of Kanghagas under Bantayan. When Bogo became a parish in 1850 it took over Kanghagas, which was renamed Isabel after Queen Isabel II and in 1863 took its permanent name, San Remigio. In 1864 San Remigio and its barrios formed their own parish under San Juan Nepomuceno.",
        'photo' => $c('Church of San Remigio, Cebu.jpg', 'Joelaldor', 'CC BY-SA 4.0', $t.'1/17/Church_of_San_Remigio%2C_Cebu.jpg/1280px-Church_of_San_Remigio%2C_Cebu.jpg'),
    ],
    [
        'name' => 'Archdiocesan Shrine of St. James the Apostle', 'town' => 'Compostela',
        'history' => "Compostela was one of the early barrios of Danao. In 1844 it was named on the suggestion of Fr. Manuel Alonzo, a friar from Ibdes in Spain, who brought with him an image of its patron, Saint James the Great. The first chapel was built of light materials, and as the population grew the present church was built on the same site in 1866. It is still the town's main place of worship.",
        'photo' => $c('Church of Compostela, Cebu.jpg', 'Joelaldor', 'CC BY-SA 4.0', $t.'b/b1/Church_of_Compostela%2C_Cebu.jpg/1280px-Church_of_Compostela%2C_Cebu.jpg'),
    ],
    [
        'name' => 'Sto. Tomas de Villanueva Parish', 'town' => 'Danao',
        'history' => "Danao's Roman Catholic church was completed in 1847, while Captain del Rosario oversaw the building of the town's presidencia, convent and first streets. Some of the Spanish-era culverts from that time still survive under the town's streets. The parish is dedicated to Saint Thomas of Villanova.",
        'photo' => $c('Santo Tomas de Villanueva Church Danao (Cebu North Road, Danao, Cebu; 01-17-2023).jpg', 'Patrickroque01', 'CC BY-SA 4.0', $t.'7/71/Santo_Tomas_de_Villanueva_Church_Danao_%28Cebu_North_Road%2C_Danao%2C_Cebu%3B_01-17-2023%29.jpg/1280px-Santo_Tomas_de_Villanueva_Church_Danao_%28Cebu_North_Road%2C_Danao%2C_Cebu%3B_01-17-2023%29.jpg'),
    ],
    [
        'name' => 'San Sebastian Parish', 'town' => 'Borbon',
        'history' => "Borbon was once a visita of Sogod, and many of its first families had relatives there. It was established as a parish on September 15, 1862, with San Sebastian as its patron. Its first church, described in 1886 as built of wood and tabique on a mortar base with a grass roof, was believed to be one of the oldest in Cebu until it was demolished after the Second World War. The Silmugi Festival, named after the town's pre-Spanish name, honours the patron at his fiesta.",
    ],
    [
        'name' => 'San Narciso Parish', 'town' => 'Consolacion',
        'history' => "When Consolacion became an independent town again in 1920, the people named it after the daughter of the Cebuano governor and chose San Narciso as patron, after the governor's wife, Narcisa. A year after building the town hall they built their first church, but being of wood, nipa and bamboo it was destroyed by a typhoon in 1888. A second church was lost to another typhoon in 1892, and a third was built on the present site just before the Second World War.",
        'photo' => $c('Church of Consolacion, Cebu.jpg', 'Joelaldor', 'CC BY-SA 4.0', $t.'3/34/Church_of_Consolacion%2C_Cebu.jpg/1280px-Church_of_Consolacion%2C_Cebu.jpg'),
    ],
    [
        'name' => 'San Fernando Rey Parish', 'town' => 'Liloan',
        'history' => "Liloan had a parish even before it separated from Mandaue as a town on August 23, 1844. It was formally made a parish on January 27, 1845, by decree of the Bishop of Cebu. Its patron is Saint Ferdinand III, honoured every last week of May with a fiesta that also celebrates the rosquillos, Liloan's famous biscuit.",
        'photo' => $c('San Fernando Rey Parish Church Liloan (Cebu North Road, Liloan, Cebu; 01-17-2023).jpg', 'Patrickroque01', 'CC BY-SA 4.0', $t.'6/64/San_Fernando_Rey_Parish_Church_Liloan_%28Cebu_North_Road%2C_Liloan%2C_Cebu%3B_01-17-2023%29.jpg/1280px-San_Fernando_Rey_Parish_Church_Liloan_%28Cebu_North_Road%2C_Liloan%2C_Cebu%3B_01-17-2023%29.jpg'),
    ],
    [
        'name' => 'Sta. Rosa de Lima Parish', 'town' => 'Alcoy',
        'history' => "The old town of Alcoy, then called Mambaje in today's barangay Daanlungsod, became a visita of Carcar in 1690 and a barrio of Boljoon in 1692. The settlers built a church and three watchtowers, known as Bantayan sa Hari, and chose Saint Rose of Lima as their patron.",
    ],
    [
        'name' => 'St. Francis of Assisi Parish', 'town' => 'Balamban',
        'history' => "According to local oral history, the building of the St. Francis of Assisi Parish church was begun by a priest in 1875, who served the parish until 1909.",
    ],
    [
        'name' => 'St. Augustine Parish', 'town' => 'Carmen',
        'history' => "The parish of Carmen honours Saint Augustine of Hippo, whose fiesta is held every August 28. In 1974 the parish priest, Fr. Jose Motus, and then-mayor Benyong Villamor started a street dance for the Santo Nino modelled on the Ati-Atihan of Kalibo. Renamed Sinulog sa Carmen the next year, it began six years before the Sinulog of Cebu City.",
        'photo' => $c('Church of Carmen, Cebu.jpg', 'Joelaldor', 'CC BY-SA 4.0', $t.'6/62/Church_of_Carmen%2C_Cebu.jpg/1280px-Church_of_Carmen%2C_Cebu.jpg'),
    ],
    [
        'name' => 'St. Raphael the Archangel Parish', 'town' => 'Aloguinsan',
        'history' => "Aloguinsan has the only parish in the Archdiocese of Cebu dedicated to Saint Raphael the Archangel. The town fiesta is held every second Sunday of June together with the Kinsan Festival, named after the dotted grouper that is plentiful from May to July. Fittingly, the saint is sometimes shown holding a fish.",
    ],
    [
        'name' => 'Sta. Monica Parish', 'town' => 'Pinamungajan',
        'history' => "The patroness of Pinamungajan is Saint Monica, mother of Saint Augustine of Hippo. Instead of August 27, the town keeps her memorial on May 4, and since 2008 it has celebrated the Pinamuohan Festival in her honour on that day.",
    ],
    [
        'name' => 'St. James the Apostle Parish', 'town' => 'Badian',
        'history' => "The church of Santiago Apostol stands in the Poblacion of Badian. Its feast on July 25 is celebrated with the Banig Festival, which honours Saint James the Great and the town's craft of weaving banig mats from pandanus, for which Badian is called the Banig Capital of Cebu.",
    ],

    // Photos only (history from the Archdiocese data, or already researched).
    ['name' => 'Cebu Metropolitan Cathedral', 'town' => 'Cebu City', 'photo' => $c('The Cebu Metropolitan Cathedral Church.jpg', 'Nicel Mae', 'CC BY-SA 4.0', $t.'c/c4/The_Cebu_Metropolitan_Cathedral_Church.jpg/1280px-The_Cebu_Metropolitan_Cathedral_Church.jpg')],
    ['name' => 'Archdiocesan Shrine of St. Francis of Assisi', 'town' => 'Naga', 'photo' => $c('Saint Francis of Assisi Parish Naga Cebu (Cebu South Road, Naga, Cebu; 09-07-2022).jpg', 'Patrickroque01', 'CC BY-SA 4.0', $t.'4/44/Saint_Francis_of_Assisi_Parish_Naga_Cebu_%28Cebu_South_Road%2C_Naga%2C_Cebu%3B_09-07-2022%29.jpg/1280px-Saint_Francis_of_Assisi_Parish_Naga_Cebu_%28Cebu_South_Road%2C_Naga%2C_Cebu%3B_09-07-2022%29.jpg')],
    ['name' => 'San Isidro Labrador Parish', 'town' => 'San Fernando', 'photo' => $c('San Isidro Labrador Church SF Cebu (Cebu South Road, San Fernando, Cebu; 01-18-2024).jpg', 'Patrickroque01', 'CC BY-SA 4.0', $t.'3/32/San_Isidro_Labrador_Church_SF_Cebu_%28Cebu_South_Road%2C_San_Fernando%2C_Cebu%3B_01-18-2024%29.jpg/1280px-San_Isidro_Labrador_Church_SF_Cebu_%28Cebu_South_Road%2C_San_Fernando%2C_Cebu%3B_01-18-2024%29.jpg')],
    ['name' => 'St. Francis of Assisi Parish', 'town' => 'Dumanjug', 'photo' => $c('Dumanjug Church facade.jpg', 'Carlo Joseph Moskito', 'CC BY-SA 4.0', $t.'2/23/Dumanjug_Church_facade.jpg/1280px-Dumanjug_Church_facade.jpg')],
    ['name' => 'St. Gregory the Great Parish', 'town' => 'Ginatilan', 'photo' => $c('Ginatilan Church.jpg', 'Elmer B. Domingo', 'CC BY-SA 4.0', $t.'f/fa/Ginatilan_Church.jpg/1280px-Ginatilan_Church.jpg')],
    ['name' => 'Archdiocesan Shrine of St. John de Sahagun', 'town' => 'Toledo', 'photo' => $c('San Juan de Sahagun Parish Toledo (S. Osmeña Street, Toledo, Cebu; 01-18-2024).jpg', 'Patrickroque01', 'CC BY-SA 4.0', $t.'d/d1/San_Juan_de_Sahagun_Parish_Toledo_%28S._Osme%C3%B1a_Street%2C_Toledo%2C_Cebu%3B_01-18-2024%29.jpg/1280px-San_Juan_de_Sahagun_Parish_Toledo_%28S._Osme%C3%B1a_Street%2C_Toledo%2C_Cebu%3B_01-18-2024%29.jpg')],
    ['name' => 'San Isidro Labrador Parish', 'town' => 'Tabogon', 'photo' => $c('Church of Tabogon, Cebu.jpg', 'Joelaldor', 'CC BY-SA 4.0', $t.'3/35/Church_of_Tabogon%2C_Cebu.jpg/1280px-Church_of_Tabogon%2C_Cebu.jpg')],
    ['name' => 'Archdiocesan Shrine of San Vicente Ferrer', 'town' => 'Bogo', 'photo' => $c('Church of Bogo City, Cebu.jpg', 'Joelaldor', 'CC BY-SA 4.0', $t.'5/55/Church_of_Bogo_City%2C_Cebu.jpg/1280px-Church_of_Bogo_City%2C_Cebu.jpg')],
    ['name' => 'Sto. Niño Parish', 'town' => 'Santa Fe', 'photo' => $c('Big Church Santa Fe at Bantayan in the Philippines.jpg', 'Øyvind Holmstad', 'CC BY-SA 4.0', $t.'8/8a/Big_Church_Santa_Fe_at_Bantayan_in_the_Philippines.jpg/1280px-Big_Church_Santa_Fe_at_Bantayan_in_the_Philippines.jpg')],
    ['name' => 'San Guillermo de Aquitania Parish', 'town' => 'Catmon', 'photo' => $c('Church of Catmon, Cebu.jpg', 'Joelaldor', 'CC BY-SA 4.0', $t.'0/09/Church_of_Catmon%2C_Cebu.jpg/1280px-Church_of_Catmon%2C_Cebu.jpg')],
    ['name' => 'St. James the Apostle Parish', 'town' => 'Sogod', 'photo' => $c('Church of Sogod, Cebu.jpg', 'Joelaldor', 'CC BY-SA 4.0', $t.'7/7b/Church_of_Sogod%2C_Cebu.jpg/1280px-Church_of_Sogod%2C_Cebu.jpg')],
    ['name' => 'Sto. Niño de Cebu Parish', 'town' => 'Poro', 'photo' => $c('Sto. Nino Parish Church in Poro, Camotes Islands, Cebu.jpg', 'Khdamayo', 'CC0', 'https://upload.wikimedia.org/wikipedia/commons/b/b7/Sto._Nino_Parish_Church_in_Poro%2C_Camotes_Islands%2C_Cebu.jpg')],
    ['name' => 'Simala Shrine', 'town' => 'Sibonga', 'photo' => $c('Simala-Shrine Cebu 2024.jpg', 'Sargoth', 'CC BY-SA 4.0', $t.'e/e8/Simala-Shrine_Cebu_2024.jpg/1280px-Simala-Shrine_Cebu_2024.jpg')],
];
