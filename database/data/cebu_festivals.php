<?php

/*
|--------------------------------------------------------------------------
| Cebu's festivals
|--------------------------------------------------------------------------
| From the Cebu Provincial Tourism list (https://tourism.cebu.gov.ph/festivals/,
| read 29 Oct 2026; 53 celebrations). Loaded by `php artisan giya:import-festivals`.
|
| when:   ['date', month, day]            every year on that date
|         ['nth', n, weekday, month]      e.g. 3rd Sunday (0) of January
|         ['easter']                      Easter Sunday
| type:   Feast Day when it honors a saint or Christ, otherwise Celebration
| church: a word in the name of a GIYA destination in that town; when exactly
|         one matches, the festival is attached to it (its followers get the
|         reminder). Otherwise it is listed as a town festival.
| note:   anything the source says that the date alone does not
|
| Where the source's date badge and its own description disagree, the
| description (which names the feast) was used; those rows say so in `note`.
*/

$sun = 0; $fri = 5;

return [
    // January
    ['Sinulog Festival', 'Cebu City', ['nth', 3, $sun, 1], 'Feast Day', 'Santo Niño', 'church' => 'Santo Ni', 'note' => 'Nine days of novena Masses, a fluvial procession and the grand street parade.'],
    ['Panuhug Festival', 'Santa Fe', ['date', 1, 15], 'Feast Day', 'Santo Niño', 'note' => 'Shell-craft costumes and street dancing on Bantayan Island.'],
    ['Kumbira Festival', 'Compostela', ['date', 1, 17], 'Celebration', null, 'note' => 'Barangay street-dancing competition.'],
    ['Sinulog sa Carmen', 'Carmen', ['nth', 4, $sun, 1], 'Feast Day', 'Santo Niño', 'note' => 'The town\'s own Sinulog, with a procession of Santo Niño images.'],
    ['Tuba Festival', 'Borbon', ['date', 1, 20], 'Feast Day', 'St. Sebastian', 'note' => 'Celebrates tuba, the local coconut wine.'],
    ['Panagbagat Festival', 'Poro', ['nth', 3, $fri, 1], 'Feast Day', 'Santo Niño de Poro', 'note' => 'Also called the Tagbo Festival, on Camotes Island.'],

    // February
    ['Sarok Festival', 'Consolacion', ['date', 2, 6], 'Celebration', null, 'note' => 'Street dancing with the town\'s woven sarok hats.'],
    ['Utanon Festival', 'Dalaguete', ['date', 2, 10], 'Celebration', null, 'note' => 'Harvest celebration of the "Salad Bowl of Cebu".'],
    ['Budbod Kabog Festival', 'Catmon', ['date', 2, 10], 'Celebration', null, 'note' => 'Celebrates budbod kabog, the millet rice cake.'],

    // March
    ['Soli-Soli Festival', 'San Francisco', ['nth', 3, $sun, 3], 'Feast Day', 'St. Joseph the Worker', 'note' => 'On Camotes Island, near Lake Danao. The source shows "15 Mar" but says the third Sunday of March; the third Sunday is used.'],
    ['Hinatdan Festival', 'Ginatilan', ['nth', 2, $sun, 3], 'Feast Day', 'St. Gregory the Great', 'note' => 'The source says the second week of March without a day; the second Sunday is used. Confirm with the town.'],

    // April
    ['Tostado Festival', 'Santander', ['nth', 3, $sun, 4], 'Celebration', null, 'note' => 'Celebrates the town\'s tostado shortbread.'],
    ['Katubhan Festival', 'Medellin', ['date', 4, 27], 'Feast Day', 'Santo Niño', 'note' => 'Sugarcane thanksgiving of the "Sugar Bowl of Cebu".'],
    ['Kadaugan sa Mactan', 'Lapu-Lapu City', ['date', 4, 27], 'Celebration', null, 'note' => 'Reenactment of the 1521 Battle of Mactan.'],

    // May
    ['Pinamuohan Festival', 'Pinamungajan', ['date', 5, 4], 'Feast Day', 'St. Monica', 'church' => 'Monica', 'note' => 'Harvest thanksgiving.'],
    ['Panagtagbo Festival', 'Mandaue City', ['date', 5, 4], 'Feast Day', 'St. Joseph the Worker', 'church' => 'Joseph', 'note' => 'A gathering of the city\'s barangays for its patron.'],
    ['Sikoy-Sikoy Festival', 'San Fernando', ['date', 5, 15], 'Feast Day', 'St. Isidore the Laborer', 'note' => 'Celebrates coastal livelihood.'],
    ['Laphayan Festival', 'San Remigio', ['date', 5, 16], 'Feast Day', 'St. John Nepomucene', 'note' => 'Coastal celebration.'],
    ['Kagasangan Festival', 'Moalboal', ['date', 5, 16], 'Feast Day', 'St. John Nepomucene', 'note' => 'Marine and coral-reef celebration.'],
    ['Rosquillos Festival', 'Liloan', ['date', 5, 17], 'Feast Day', 'San Fernando Rey', 'note' => 'Celebrates Liloan\'s rosquillos cookies.'],
    ['Sanggi Festival', 'Tabogon', ['date', 5, 20], 'Feast Day', 'St. Isidore the Laborer', 'note' => 'Harvest thanksgiving.'],
    ['Kuyayang Festival', 'City of Bogo', ['date', 5, 26], 'Feast Day', 'St. Vincent Ferrer', 'church' => 'Vicente', 'note' => 'Courtship dance festival.'],

    // June
    ['Kinsan Festival', 'Aloguinsan', ['nth', 2, $sun, 6], 'Feast Day', 'St. Raphael the Archangel', 'note' => 'Marine celebration.'],
    ['Hinulawan Festival', 'Toledo City', ['date', 6, 12], 'Feast Day', 'St. John of Sahagun', 'church' => 'Sahagun', 'note' => 'Historical golden celebration.'],
    ['Tubod Festival', 'Tuburan', ['date', 6, 13], 'Feast Day', 'St. Anthony of Padua', 'note' => 'Water thanksgiving.'],
    ['Ani-Anihan Festival', 'Tabuelan', ['date', 6, 24], 'Feast Day', 'St. John the Baptist', 'note' => 'Harvest celebration.'],
    ['Palawod Festival', 'Bantayan', ['date', 6, 29], 'Feast Day', 'Sts. Peter and Paul', 'note' => 'Seafaring celebration on Bantayan Island.'],

    // July
    ['Banig Festival', 'Badian', ['date', 7, 24], 'Celebration', null, 'note' => 'Held July 24-25. Celebrates pandan mat (banig) weaving.'],
    ['Panumod Festival', 'Barili', ['date', 7, 25], 'Celebration', null, 'note' => 'Thanksgiving showing the town\'s livestock and dairy.'],
    ['Panagsogod Festival', 'Sogod', ['date', 7, 25], 'Feast Day', 'St. James the Apostle', 'note' => 'Celebration of new beginnings.'],

    // August
    ['Bonga Festival', 'Sibonga', ['date', 8, 9], 'Feast Day', 'St. Philomena and Our Lady of the Pillar', 'note' => 'Thanksgiving celebration.'],
    ['Dinagat Festival', 'Cordova', ['date', 8, 10], 'Feast Day', 'San Roque', 'note' => 'Also called Dinagat-Bakasi. The source says mid-August; confirm the day with the town.'],
    ['Lalin Festival', 'Asturias', ['date', 8, 16], 'Feast Day', 'San Roque', 'note' => 'Historical celebration.'],
    ['Bahandi Festival', 'Alcantara', ['date', 8, 28], 'Feast Day', 'St. Augustine', 'note' => 'Celebrates the town\'s natural riches.'],
    ['Siloy Festival', 'Alcoy', ['date', 8, 30], 'Feast Day', 'St. Rose of Lima', 'note' => 'Named after the black shama (siloy) bird.'],
    ['Haladaya Festival', 'Daanbantayan', ['date', 8, 30], 'Feast Day', 'St. Rose of Lima', 'note' => 'Also honors the chieftain Datu Daya.'],

    // September
    ['Dagayday Festival', 'Samboan', ['date', 9, 29], 'Feast Day', 'St. Michael the Archangel', 'note' => 'Coastal celebration. The source shows "9 Sep" but says September 29, the feast of St. Michael; Sep 29 is used.'],
    ['Buyoc Festival', 'Malabuyoc', ['date', 9, 10], 'Feast Day', 'St. Nicholas of Tolentine', 'note' => 'Thanksgiving celebration. The source shows "9 Sep" but says September 10, the feast of St. Nicholas; Sep 10 is used.'],
    ['Humba Festival', 'Ronda', ['date', 9, 15], 'Feast Day', 'Our Lady of Sorrows', 'note' => 'Celebrates humba, braised pork.'],
    ['Lingaw-Sadya Festival', 'Balamban', ['date', 9, 23], 'Feast Day', 'St. Francis of Assisi', 'note' => 'Celebration of the town\'s resilience and industry.'],
    ['La Torta Festival', 'Argao', ['date', 9, 28], 'Feast Day', 'St. Michael the Archangel', 'note' => 'Held September 28-29. Celebrates Argao\'s torta cake.'],
    ['Karansa Festival', 'Danao City', ['nth', 3, $sun, 9], 'Feast Day', 'St. Thomas of Villanova', 'church' => 'Thomas', 'note' => 'Street-dancing celebration.'],

    // October
    ['Bisnok Festival', 'Dumanjug', ['date', 10, 4], 'Celebration', null, 'note' => 'Celebrates the native chicken ("Bisaya nga manok").'],
    ['Halad Inasal Festival', 'City of Talisay', ['date', 10, 15], 'Feast Day', 'Santo Niño', 'church' => 'Santo Ni', 'note' => 'Roasted pig paraded as an offering.'],

    // November
    ['Bolho Festival', 'Boljoon', ['date', 11, 9], 'Feast Day', 'Our Lady of the Immaculate Conception', 'note' => 'Historical coastal thanksgiving.'],
    ['Kabkaban Festival', 'City of Carcar', ['date', 11, 24], 'Feast Day', 'St. Catherine of Alexandria', 'church' => 'Catherine', 'note' => 'Held November 24-25. Heritage celebration.'],

    // December
    ['Pamugsay Festival', 'Pilar', ['date', 12, 2], 'Feast Day', 'St. Francis Xavier', 'note' => 'Seafaring celebration on Camotes Island.'],
    ['Que Alegre Festival', 'Alegria', ['date', 12, 2], 'Feast Day', 'St. Francis Xavier', 'note' => 'Music and street dancing.'],
    ['Toslob Festival', 'Oslob', ['date', 12, 8], 'Feast Day', 'Our Lady of the Immaculate Conception', 'note' => 'Marine and heritage celebration.'],
    ['Isda Festival', 'Madridejos', ['date', 12, 8], 'Feast Day', 'Our Lady of the Immaculate Conception', 'note' => 'Fishing celebration on Bantayan Island.'],
    ['Katunggan Festival', 'Tudela', ['date', 12, 8], 'Feast Day', 'Our Lady of the Immaculate Conception', 'note' => 'Mangrove (katunggan) celebration on Camotes Island.'],
    ['Dagitab Festival', 'City of Naga', ['date', 12, 23], 'Feast Day', 'St. Francis of Assisi', 'church' => 'Francis', 'note' => 'Festival of lights.'],

    // Moves with Easter
    ['Sugat Kabanhawan Festival', 'Minglanilla', ['easter'], 'Feast Day', 'the Risen Christ', 'note' => 'The dawn sugat (meeting of the Risen Christ and His Mother) in the "Easter Capital of Cebu".'],
];
