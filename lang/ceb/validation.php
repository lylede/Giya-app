<?php

/*
 * Only the password messages; every other validation message falls back to
 * Laravel's English ones.
 */
return [
    'password' => [
        'uppercase' => 'Ang password kinahanglan adunay bisan usa ka dakong letra (sama sa A o B).',
        'mixed' => 'Ang password kinahanglan adunay dako ug gamay nga letra.',
        'numbers' => 'Ang password kinahanglan adunay numero.',
        'symbols' => 'Ang password kinahanglan adunay simbolo (sama sa ! o #).',
        'letters' => 'Ang password kinahanglan adunay letra.',
        'uncompromised' => 'Kini nga password nakit-an sa usa ka data leak. Palihug pagpili og lain.',
    ],
];
