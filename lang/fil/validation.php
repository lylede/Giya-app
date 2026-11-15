<?php

/*
 * Only the password messages; every other validation message falls back to
 * Laravel's English ones.
 */
return [
    'password' => [
        'mixed' => 'Ang password ay dapat may malaki at maliit na titik.',
        'numbers' => 'Ang password ay dapat may numero.',
        'symbols' => 'Ang password ay dapat may simbolo (gaya ng ! o #).',
        'letters' => 'Ang password ay dapat may titik.',
        'uncompromised' => 'Ang password na ito ay lumabas sa isang data leak. Pumili ng iba.',
    ],
];
