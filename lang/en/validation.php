<?php

/*
 * Only the password messages; every other validation message falls back to
 * Laravel's English ones.
 */
return [
    'password' => [
        'mixed' => 'The password needs both an uppercase and a lowercase letter.',
        'numbers' => 'The password needs at least one number.',
        'symbols' => 'The password needs at least one symbol (like ! or #).',
        'letters' => 'The password needs at least one letter.',
        'uncompromised' => 'This password has appeared in a data leak. Please choose a different one.',
    ],
];
