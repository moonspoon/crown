<?php
// Edit this file to set the six contenders and the starting games.
// The admin password lives in config.local.php, which is not committed: copy config.local.example.php to make it.

return [
    // The person whose login opens the admin area; their password is admin_password in config.local.php.
    // Everyone else logs in to change their own avatar, with a password set on the admin page.
    'superuser' => 'p1',

    // Times entered on the admin page are in this time zone.
    'timezone' => 'Europe/London',

    // id => [name, icon]. Icon can be an emoji or a couple of letters.
    // Icons changed on the admin page are saved in data/icons.json and override these.
    // Keep the ids stable: the current holder is stored by id.
    'people' => [
        'p1' => ['name' => 'Spoon', 'icon' => '🤖'],
        'p2' => ['name' => 'Henry', 'icon' => '🐌'],
        'p3' => ['name' => 'Leo', 'icon' => '🦁'],
        'p4' => ['name' => 'Drew', 'icon' => '🐼'],
        'p5' => ['name' => 'Mark', 'icon' => '🐯'],
        'p6' => ['name' => 'Gareth', 'icon' => '🐸'],
    ],

    // Starting list of where matches are played. Once locations are changed on the admin
    // page, the list and default live in data/locations.json and these two are ignored.
    'locations' => [
        'Mill Hill Road',
        "Gareth's",
    ],
    // Preselected when passing the crown or adding a match.
    'default_location' => "Gareth's",
    // Where older history entries recorded without a location were played.
    'unrecorded_location' => 'Mill Hill Road',

    // Starting list of games the crown can be won at. Once a game is added, renamed or
    // deleted on the admin page, the list lives in data/games.json and this one is ignored.
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
