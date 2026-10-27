<?php
// Edit this file to set the six contenders, the games and the admin password.

return [
    'admin_password' => 'KingSp00n',

    // id => [name, icon]. Icon can be an emoji or a couple of letters.
    // Keep the ids stable: the current holder is stored by id.
    'people' => [
        'p1' => ['name' => 'Spoon', 'icon' => '🦁'],
        'p2' => ['name' => 'Henry', 'icon' => '🐻'],
        'p3' => ['name' => 'Leo', 'icon' => '🦊'],
        'p4' => ['name' => 'Drew', 'icon' => '🐼'],
        'p5' => ['name' => 'Mark', 'icon' => '🐯'],
        'p6' => ['name' => 'Gareth', 'icon' => '🐸'],
    ],

    // Games the crown can be won at. Picked from a list when passing the crown.
    'games' => [
        'Power Grid',
        'Dominion',
        'Catan',
        'Catan Seafarers',
        'Catan Starfarers',
        'Catan Hawaii',
        'Ticket to Ride',
        'Carcassonne',
        'Heat',
        'Hot Streak',
        'Wingspan',
        'Deep Regrets',
        'Ra',
        'Forest Shuffle',
        'Samurai',
        'Ingenious',
        'Tzolkin',
        'The Quest for El Dorado',
        'Pandemic: Contagion',
        '7 Wonders',
    ],
];
