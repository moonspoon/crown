<?php

const DATA_FILE = __DIR__ . '/data/holder.json';
const LOG_FILE = __DIR__ . '/data/history.log';
const GAMES_FILE = __DIR__ . '/data/games.json';
const GAME_ICONS_FILE = __DIR__ . '/data/game_icons.json';
const GAME_PLAYERS_FILE = __DIR__ . '/data/game_players.json';
const ICONS_FILE = __DIR__ . '/data/icons.json';
const COLOURS_FILE = __DIR__ . '/data/colours.json';
// Avatars without a chosen colour use the panel colour (--panel in style.css).
const DEFAULT_AVATAR_COLOUR = '#2a2246';
const LOCATIONS_FILE = __DIR__ . '/data/locations.json';
// Hashed login passwords for everyone but the superuser, whose password is admin_password in config.php.
const PASSWORDS_FILE = __DIR__ . '/data/passwords.json';
const MIN_PASSWORD_LENGTH = 6;
// Matches arranged for a later date. Kept apart from the history, so they never touch the holder or the stats.
const FUTURE_FILE = __DIR__ . '/data/future.json';

function config(): array
{
    static $config = null;
    return $config ??= require __DIR__ . '/config.php';
}

date_default_timezone_set(config()['timezone'] ?? 'Europe/London');

/** The people from config.php, with any icons and avatar colours (null if not chosen) set on the admin page. */
function people(): array
{
    $people = config()['people'];
    $icons = is_file(ICONS_FILE) ? json_decode((string) file_get_contents(ICONS_FILE), true) : [];
    foreach ($icons ?: [] as $id => $icon) {
        if (isset($people[$id])) {
            $people[$id]['icon'] = $icon;
        }
    }
    $colours = is_file(COLOURS_FILE) ? json_decode((string) file_get_contents(COLOURS_FILE), true) : [];
    foreach ($people as $id => &$person) {
        $person['colour'] = $colours[$id] ?? null;
    }
    unset($person);
    return $people;
}

/** Sets the background colour of someone's avatar; the default colour clears it. */
function set_colour(string $id, string $colour): void
{
    if (!isset(config()['people'][$id])) {
        throw new InvalidArgumentException('Unknown person.');
    }
    $colour = clean_colour($colour);
    $colours = is_file(COLOURS_FILE) ? (json_decode((string) file_get_contents(COLOURS_FILE), true) ?: []) : [];
    if ($colour === DEFAULT_AVATAR_COLOUR) {
        unset($colours[$id]);
    } else {
        $colours[$id] = $colour;
    }
    if (!is_dir(dirname(COLOURS_FILE))) {
        mkdir(dirname(COLOURS_FILE), 0775, true);
    }
    file_put_contents(COLOURS_FILE, json_encode($colours, JSON_PRETTY_PRINT), LOCK_EX);
}

/** @return string the colour as #rrggbb; throws if it isn't one */
function clean_colour(string $colour): string
{
    $colour = strtolower(trim($colour));
    if (!preg_match('/^#[0-9a-f]{6}$/', $colour)) {
        throw new InvalidArgumentException('Pick a colour.');
    }
    return $colour;
}

/** The style attribute giving an avatar its person's chosen background, or nothing. */
function avatar_style(array $person): string
{
    return $person['colour'] ? ' style="background: ' . e($person['colour']) . '"' : '';
}

function set_icon(string $id, string $icon): string
{
    if (!isset(config()['people'][$id])) {
        throw new InvalidArgumentException('Unknown person.');
    }
    $icon = clean_icon($icon);
    $icons = is_file(ICONS_FILE) ? (json_decode((string) file_get_contents(ICONS_FILE), true) ?: []) : [];
    $icons[$id] = $icon;
    if (!is_dir(dirname(ICONS_FILE))) {
        mkdir(dirname(ICONS_FILE), 0775, true);
    }
    file_put_contents(ICONS_FILE, json_encode($icons, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    return $icon;
}

/** @return string the icon, trimmed; throws if it isn't usable */
function clean_icon(string $icon): string
{
    $icon = trim($icon);
    if ($icon === '') {
        throw new InvalidArgumentException('Enter an emoji or a couple of letters.');
    }
    // Emoji can take several bytes each (flags, skin tones, families), so allow a little room.
    if (strlen($icon) > 32) {
        throw new InvalidArgumentException('Icons should be a single emoji or a couple of letters.');
    }
    return $icon;
}

/** Starts the login session shared by the login, account and admin pages, with a CSRF token. */
function start_login_session(): void
{
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict', 'lifetime' => 60 * 60 * 24 * 30]);
    session_start();
    $_SESSION['csrf'] ??= bin2hex(random_bytes(16));
}

/** Id of the person whose login opens the admin area. */
function superuser(): string
{
    return config()['superuser'] ?? array_key_first(config()['people']);
}

/** Id of the person logged in this session, or null. */
function logged_in(): ?string
{
    $id = $_SESSION['user'] ?? null;
    return is_string($id) && isset(config()['people'][$id]) ? $id : null;
}

function is_superuser(): bool
{
    return logged_in() === superuser();
}

/** Where a person lands after logging in. */
function home_page(string $id): string
{
    return $id === superuser() ? 'admin.php' : 'account.php';
}

/** @return array<string, string> password hashes by person id */
function password_hashes(): array
{
    return is_file(PASSWORDS_FILE) ? (json_decode((string) file_get_contents(PASSWORDS_FILE), true) ?: []) : [];
}

/** Whether a person can log in yet: the superuser always can, others once a password is set. */
function has_password(string $id): bool
{
    return $id === superuser() || isset(password_hashes()[$id]);
}

function check_login(string $id, string $password): bool
{
    if (!isset(config()['people'][$id])) {
        return false;
    }
    if ($id === superuser()) {
        return hash_equals((string) config()['admin_password'], $password);
    }
    $hash = password_hashes()[$id] ?? null;
    return $hash !== null && password_verify($password, $hash);
}

/** Sets a player's login password. The superuser's lives in config.php instead. */
function set_password(string $id, string $password): void
{
    if (!isset(config()['people'][$id])) {
        throw new InvalidArgumentException('Unknown person.');
    }
    if ($id === superuser()) {
        throw new InvalidArgumentException('The admin password is set in config.php.');
    }
    if (strlen($password) < MIN_PASSWORD_LENGTH) {
        throw new InvalidArgumentException('Passwords need at least ' . MIN_PASSWORD_LENGTH . ' characters.');
    }
    $hashes = password_hashes();
    $hashes[$id] = password_hash($password, PASSWORD_DEFAULT);
    if (!is_dir(dirname(PASSWORDS_FILE))) {
        mkdir(dirname(PASSWORDS_FILE), 0775, true);
    }
    file_put_contents(PASSWORDS_FILE, json_encode($hashes, JSON_PRETTY_PRINT), LOCK_EX);
}

/** @return array{locations: string[], default: string} managed from the admin page; config.php supplies the start */
function location_settings(): array
{
    $saved = is_file(LOCATIONS_FILE) ? json_decode((string) file_get_contents(LOCATIONS_FILE), true) : null;
    $list = $saved['locations'] ?? config()['locations'] ?? ['Mill Hill Road'];
    $default = $saved['default'] ?? config()['default_location'] ?? $list[0];
    return ['locations' => $list, 'default' => in_array($default, $list, true) ? $default : $list[0]];
}

/** Where matches are played, in the order they were added. */
function locations(): array
{
    return location_settings()['locations'];
}

/** Preselected when passing the crown or adding a match. */
function default_location(): string
{
    return location_settings()['default'];
}

function save_locations(array $list, string $default): void
{
    if (!is_dir(dirname(LOCATIONS_FILE))) {
        mkdir(dirname(LOCATIONS_FILE), 0775, true);
    }
    $list = array_values($list);
    $settings = ['locations' => $list, 'default' => in_array($default, $list, true) ? $default : $list[0]];
    file_put_contents(LOCATIONS_FILE, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function add_location(string $name): string
{
    $name = clean_name($name, locations(), 'location');
    save_locations([...locations(), $name], default_location());
    return $name;
}

/** Renames the location in the list and in every history entry, so stats stay together. */
function rename_location(string $old, string $new): string
{
    $list = locations();
    $index = array_search($old, $list, true);
    if ($index === false) {
        throw new InvalidArgumentException("$old is no longer in the list.");
    }
    $new = clean_name($new, $list, 'location', $old);
    $list[$index] = $new;
    save_locations($list, default_location() === $old ? $new : default_location());

    $entries = log_entries();
    if ($entries) {
        foreach ($entries as &$entry) {
            if (entry_location($entry) === $old) {
                $entry['location'] = $new;
            }
        }
        unset($entry);
        save_log_entries($entries, false);
    }
    $future = future_matches();
    foreach ($future as &$match) {
        if ($match['location'] === $old) {
            $match['location'] = $new;
        }
    }
    unset($match);
    save_future_matches($future);
    return $new;
}

/** Removes the location from the list; past history entries keep it. The last one can't go. */
function delete_location(string $name): void
{
    $list = locations();
    if (!in_array($name, $list, true)) {
        throw new InvalidArgumentException("$name is no longer in the list.");
    }
    if (count($list) === 1) {
        throw new InvalidArgumentException('There has to be at least one location.');
    }
    save_locations(array_diff($list, [$name]), default_location());
}

function set_default_location(string $name): void
{
    if (!in_array($name, locations(), true)) {
        throw new InvalidArgumentException("$name is no longer in the list.");
    }
    save_locations(locations(), $name);
}

/** @return array<string, int> how many history entries were at each location */
function location_counts(): array
{
    $counts = [];
    foreach (log_entries() as $entry) {
        $place = entry_location($entry);
        $counts[$place] = ($counts[$place] ?? 0) + 1;
    }
    return $counts;
}

/** Location chips for the admin forms, with $chosen (or the default for new matches) selected. */
function location_picker(?string $chosen): void
{
    $chosen ??= default_location();
    ?>
    <fieldset>
        <legend class="label">Where</legend>
        <div class="chips">
            <?php foreach (locations() as $location): ?>
                <label>
                    <input type="radio" name="location" value="<?= e($location) ?>" required
                        <?= $location === $chosen ? 'checked' : '' ?>>
                    <span><?= e($location) ?></span>
                </label>
            <?php endforeach ?>
        </div>
    </fieldset>
    <?php
}

/** A history entry's location; older entries recorded without one were at the unrecorded location. */
function entry_location(array $entry): string
{
    return $entry['location'] ?? config()['unrecorded_location'] ?? locations()[0];
}

/** Alphabetical. Managed from the admin page; config.php only supplies the starting list. */
function games(): array
{
    $games = is_file(GAMES_FILE)
        ? json_decode((string) file_get_contents(GAMES_FILE), true)
        : config()['games'] ?? [];
    natcasesort($games);
    return array_values($games);
}

function save_games(array $games): void
{
    if (!is_dir(dirname(GAMES_FILE))) {
        mkdir(dirname(GAMES_FILE), 0775, true);
    }
    natcasesort($games);
    file_put_contents(GAMES_FILE, json_encode(array_values($games), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

/**
 * Trims a new name and checks it isn't empty, too long or already in $existing (ignoring case).
 * @param string $what what is being named, for the error messages ("game", "location")
 */
function clean_name(string $name, array $existing, string $what, ?string $except = null): string
{
    $name = trim(preg_replace('/\s+/', ' ', $name));
    if ($name === '') {
        throw new InvalidArgumentException("Enter a $what name.");
    }
    if (strlen($name) > 60) {
        throw new InvalidArgumentException(ucfirst($what) . ' names can be at most 60 characters.');
    }
    foreach ($existing as $item) {
        if ($item !== $except && strcasecmp($item, $name) === 0) {
            throw new InvalidArgumentException("$item is already in the list.");
        }
    }
    return $name;
}

function clean_game_name(string $name, ?string $except = null): string
{
    return clean_name($name, games(), 'game', $except);
}

function add_game(string $name): string
{
    $name = clean_game_name($name);
    save_games([...games(), $name]);
    return $name;
}

/** Renames the game in the list and in every history entry, so stats stay together. */
function rename_game(string $old, string $new): string
{
    $games = games();
    $index = array_search($old, $games, true);
    if ($index === false) {
        throw new InvalidArgumentException("$old is no longer in the list.");
    }
    $new = clean_game_name($new, $old);
    $games[$index] = $new;
    save_games($games);

    $entries = log_entries();
    if ($entries) {
        foreach ($entries as &$entry) {
            if (($entry['game'] ?? null) === $old) {
                $entry['game'] = $new;
            }
        }
        unset($entry);
        save_log_entries($entries, false);
    }
    $future = future_matches();
    foreach ($future as &$match) {
        if ($match['game'] === $old) {
            $match['game'] = $new;
        }
    }
    unset($match);
    save_future_matches($future);
    $icons = game_icons();
    if (isset($icons[$old]) && $new !== $old) {
        $icons[$new] = $icons[$old];
        unset($icons[$old]);
        save_game_icons($icons);
    }
    $ranges = game_players();
    if (isset($ranges[$old]) && $new !== $old) {
        $ranges[$new] = $ranges[$old];
        unset($ranges[$old]);
        save_game_players($ranges);
    }
    return $new;
}

/** @return array<string, array{min: ?int, max: ?int}> how many can play each game, by game name */
function game_players(): array
{
    return is_file(GAME_PLAYERS_FILE) ? (json_decode((string) file_get_contents(GAME_PLAYERS_FILE), true) ?: []) : [];
}

function save_game_players(array $ranges): void
{
    if (!is_dir(dirname(GAME_PLAYERS_FILE))) {
        mkdir(dirname(GAME_PLAYERS_FILE), 0775, true);
    }
    file_put_contents(GAME_PLAYERS_FILE, json_encode($ranges, JSON_PRETTY_PRINT), LOCK_EX);
}

/**
 * Checks a game's minimum and maximum players as typed; a blank box means not set.
 * @return array{min: ?int, max: ?int}
 */
function clean_game_players(string $min, string $max): array
{
    $range = [];
    foreach (['min' => $min, 'max' => $max] as $key => $value) {
        $value = trim($value);
        if ($value === '') {
            $range[$key] = null;
        } elseif (ctype_digit($value) && (int) $value >= 1 && (int) $value <= 20) {
            $range[$key] = (int) $value;
        } else {
            throw new InvalidArgumentException('Players should be a number from 1 to 20.');
        }
    }
    if ($range['min'] !== null && $range['max'] !== null && $range['min'] > $range['max']) {
        throw new InvalidArgumentException("Minimum players can't be more than maximum players.");
    }
    return $range;
}

/** Sets how many can play a game; blank boxes clear it. */
function set_game_players(string $game, string $min, string $max): void
{
    $range = clean_game_players($min, $max);
    $ranges = game_players();
    if ($range['min'] === null && $range['max'] === null) {
        unset($ranges[$game]);
    } else {
        $ranges[$game] = $range;
    }
    save_game_players($ranges);
}

/** @return array<string, string> icons chosen for games on the admin page, by game name */
function game_icons(): array
{
    return is_file(GAME_ICONS_FILE) ? (json_decode((string) file_get_contents(GAME_ICONS_FILE), true) ?: []) : [];
}

function save_game_icons(array $icons): void
{
    if (!is_dir(dirname(GAME_ICONS_FILE))) {
        mkdir(dirname(GAME_ICONS_FILE), 0775, true);
    }
    file_put_contents(GAME_ICONS_FILE, json_encode($icons, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

/** Sets a game's icon; an empty icon removes it. */
function set_game_icon(string $game, string $icon): void
{
    $icons = game_icons();
    if (trim($icon) === '') {
        unset($icons[$game]);
    } else {
        $icons[$game] = clean_icon($icon);
    }
    save_game_icons($icons);
}

/** The game's name with its icon in front, if it has one. */
function game_label(string $game): string
{
    $icon = game_icons()[$game] ?? null;
    return $icon ? "$icon $game" : $game;
}

/** HTML for a person's name, after their icon unless turned off, linking to their player page (keeping the pre-pandemic switch if it's on). */
function player_link(string $id, bool $withIcon = true): string
{
    $query = ['id' => $id] + (include_pre_pandemic() ? ['pre' => 1] : []);
    $icon = $withIcon ? (people()[$id]['icon'] ?? '') : '';
    return '<a class="person-link" href="player.php?' . e(http_build_query($query)) . '">'
        . e(trim($icon . ' ' . person_name($id))) . '</a>';
}

/** HTML for the game's label, linking to its game page (keeping the pre-pandemic switch if it's on). */
function game_link(string $game): string
{
    $query = ['name' => $game] + (include_pre_pandemic() ? ['pre' => 1] : []);
    return '<a class="game-link" href="game.php?' . e(http_build_query($query)) . '">' . e(game_label($game)) . '</a>';
}

/** Removes the game from the list; past history entries keep their game name. */
function delete_game(string $name): void
{
    $games = games();
    if (!in_array($name, $games, true)) {
        throw new InvalidArgumentException("$name is no longer in the list.");
    }
    save_games(array_diff($games, [$name]));
}

/** @return array<string, int> how many history entries name each game */
function game_counts(): array
{
    $counts = [];
    foreach (history(null) as $entry) {
        if (!empty($entry['game'])) {
            $counts[$entry['game']] = ($counts[$entry['game']] ?? 0) + 1;
        }
    }
    return $counts;
}

/** @return array{id: ?string, since: ?string} */
function current_holder(): array
{
    $state = is_file(DATA_FILE) ? json_decode((string) file_get_contents(DATA_FILE), true) : null;
    $id = $state['id'] ?? null;
    if (!isset(people()[$id])) {
        return ['id' => null, 'since' => null];
    }
    return ['id' => $id, 'since' => $state['since'] ?? null];
}

/**
 * Checks a match's game and players. An empty game is allowed only when $gameOptional.
 * @param string[] $players ids of everyone who played; the winner is dropped if included
 * @return string[] the other players
 */
function validate_match(string $winner, string $game, array $players, string $location, bool $gameOptional = false): array
{
    if (!in_array($location, locations(), true)) {
        throw new InvalidArgumentException('Pick where the match was played.');
    }
    if (!($gameOptional && $game === '') && !in_array($game, games(), true)) {
        throw new InvalidArgumentException('Pick which game was won.');
    }
    $players = array_values(array_unique(array_diff($players, [$winner])));
    if (!$players) {
        throw new InvalidArgumentException('Pick who else played.');
    }
    foreach ($players as $player) {
        if (!isset(people()[$player])) {
            throw new InvalidArgumentException('Unknown person.');
        }
    }
    return $players;
}

/** @param string[] $players ids of everyone else who played */
function set_holder(string $id, string $game, array $players, string $location): void
{
    if (!isset(people()[$id])) {
        throw new InvalidArgumentException('Unknown person.');
    }
    // Re-selecting the current holder keeps their original "since".
    $previous = current_holder()['id'];
    if ($previous === $id) {
        return;
    }
    $players = validate_match($id, $game, $players, $location);
    if (!is_dir(dirname(DATA_FILE))) {
        mkdir(dirname(DATA_FILE), 0775, true);
    }
    $now = date(DATE_ATOM);
    file_put_contents(DATA_FILE, json_encode(['id' => $id, 'since' => $now]), LOCK_EX);

    // One JSON object per line, oldest first.
    $entry = json_encode([
        'at' => $now,
        'from' => $previous,
        'to' => $id,
        'game' => $game,
        'players' => $players,
        'location' => $location,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
    file_put_contents(LOG_FILE, $entry . "\n", FILE_APPEND | LOCK_EX);
    forget_future_match_on(new DateTimeImmutable($now));
}

/**
 * Removes the history entry recorded at $at for $to, keeping the chain of holders consistent:
 * the next entry takes over its "from", and deleting the latest entry hands the crown back.
 * @return bool false if no entry matched
 */
function delete_history_entry(string $at, string $to): bool
{
    $entries = log_entries();
    foreach ($entries as $i => $entry) {
        if ($entry['at'] !== $at || $entry['to'] !== $to) {
            continue;
        }
        if (isset($entries[$i + 1])) {
            $entries[$i + 1]['from'] = $entry['from'];
        }
        array_splice($entries, $i, 1);
        save_log_entries($entries, $i === count($entries));
        return true;
    }
    return false;
}

/**
 * Adds a past match to the history at its date. The entry after it now takes the crown from
 * this winner, and a match newer than all others makes the winner the current holder.
 * @param string $game empty when it wasn't recorded
 * @param string[] $players ids of everyone who played
 */
function add_history_entry(DateTimeInterface $at, string $winner, string $game, array $players, string $location): void
{
    if (!isset(people()[$winner])) {
        throw new InvalidArgumentException('Pick who won.');
    }
    if ($at->getTimestamp() > time()) {
        throw new InvalidArgumentException("The match can't be in the future.");
    }
    $players = validate_match($winner, $game, $players, $location, true);

    $entries = log_entries();
    $index = 0;
    while (isset($entries[$index]) && strtotime($entries[$index]['at']) <= $at->getTimestamp()) {
        $index++;
    }
    $entry = [
        'at' => $at->format(DATE_ATOM),
        'from' => $entries[$index - 1]['to'] ?? null,
        'to' => $winner,
        'game' => $game === '' ? null : $game,
        'players' => $players,
        'location' => $location,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
    ];
    if (isset($entries[$index])) {
        $entries[$index]['from'] = $winner;
    }
    array_splice($entries, $index, 0, [$entry]);
    save_log_entries($entries, $index === count($entries) - 1);
    forget_future_match_on($at);
}

/**
 * Replaces the history entry recorded at $oldAt for $oldTo. Done as a delete and re-add, so
 * the chain of holders (and the current holder) follows a changed date or winner.
 * @param string[] $players ids of everyone who played
 */
function update_history_entry(string $oldAt, string $oldTo, DateTimeInterface $at, string $winner, string $game, array $players, string $location): void
{
    // Check everything first, so a bad edit can't lose the original entry.
    if (!isset(people()[$winner])) {
        throw new InvalidArgumentException('Pick who won.');
    }
    if ($at->getTimestamp() > time()) {
        throw new InvalidArgumentException("A history entry can't be in the future.");
    }
    validate_match($winner, $game, $players, $location, true);
    if (!delete_history_entry($oldAt, $oldTo)) {
        throw new InvalidArgumentException('That history entry no longer exists.');
    }
    add_history_entry($at, $winner, $game, $players, $location);
}

/** @return ?array the history entry recorded at $at for $to */
function find_history_entry(string $at, string $to): ?array
{
    foreach (log_entries() as $entry) {
        if ($entry['at'] === $at && $entry['to'] === $to) {
            return $entry;
        }
    }
    return null;
}

/**
 * Matches arranged for later, soonest first. A match stays listed until the end of its day,
 * so tonight's match is still the next one while it's being played (until its result is recorded).
 * @return array<array{at: string, game: ?string, players: string[], location: string}>
 */
function future_matches(): array
{
    $all = is_file(FUTURE_FILE) ? json_decode((string) file_get_contents(FUTURE_FILE), true) : [];
    $today = strtotime('today');
    $matches = array_values(array_filter($all ?: [], fn($m) => strtotime($m['at']) >= $today));
    usort($matches, fn($a, $b) => strtotime($a['at']) <=> strtotime($b['at']));
    return $matches;
}

function save_future_matches(array $matches): void
{
    if (!is_dir(dirname(FUTURE_FILE))) {
        mkdir(dirname(FUTURE_FILE), 0775, true);
    }
    file_put_contents(FUTURE_FILE, json_encode(array_values($matches), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

/**
 * Arranges a match for a later date. The game and who's playing may not be known yet.
 * @param string[] $players ids of everyone expected to play
 */
function add_future_match(DateTimeInterface $at, string $game, array $players, string $location): void
{
    $matches = future_matches();
    $matches[] = future_match($at, $game, $players, $location);
    save_future_matches($matches);
}

/** Replaces the future match arranged for $oldAt. */
function update_future_match(string $oldAt, DateTimeInterface $at, string $game, array $players, string $location): void
{
    $match = future_match($at, $game, $players, $location);
    $matches = future_matches();
    $index = array_search($oldAt, array_column($matches, 'at'), true);
    if ($index === false) {
        throw new InvalidArgumentException('That future match no longer exists.');
    }
    $matches[$index] = $match;
    save_future_matches($matches);
}

/** @return ?array the future match arranged for $at */
function find_future_match(string $at): ?array
{
    foreach (future_matches() as $match) {
        if ($match['at'] === $at) {
            return $match;
        }
    }
    return null;
}

/** Checks a future match's details and returns it as stored. */
function future_match(DateTimeInterface $at, string $game, array $players, string $location): array
{
    if ($at->getTimestamp() <= time()) {
        throw new InvalidArgumentException('A future match needs a date in the future.');
    }
    if (!in_array($location, locations(), true)) {
        throw new InvalidArgumentException('Pick where the match will be played.');
    }
    if ($game !== '' && !in_array($game, games(), true)) {
        throw new InvalidArgumentException('Pick which game will be played.');
    }
    $players = array_values(array_unique($players));
    foreach ($players as $player) {
        if (!isset(people()[$player])) {
            throw new InvalidArgumentException('Unknown person.');
        }
    }
    return [
        'at' => $at->format(DATE_ATOM),
        'game' => $game === '' ? null : $game,
        'players' => $players,
        'location' => $location,
    ];
}

/** @return bool false if no future match was arranged for $at */
function delete_future_match(string $at): bool
{
    $matches = future_matches();
    $kept = array_filter($matches, fn($m) => $m['at'] !== $at);
    if (count($kept) === count($matches)) {
        return false;
    }
    save_future_matches($kept);
    return true;
}

/** Once a match's result is recorded, it's no longer a future match. */
function forget_future_match_on(DateTimeInterface $day): void
{
    $date = $day->format('Y-m-d');
    $matches = future_matches();
    $kept = array_filter($matches, fn($m) => substr($m['at'], 0, 10) !== $date);
    if (count($kept) !== count($matches)) {
        save_future_matches($kept);
    }
}

/** Every history entry, oldest first. */
function log_entries(): array
{
    if (!is_file(LOG_FILE)) {
        return [];
    }
    return array_values(array_filter(array_map(
        fn($line) => json_decode($line, true),
        file(LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
    )));
}

/** Rewrites the history; $latestChanged also makes the newest entry's winner the current holder. */
function save_log_entries(array $entries, bool $latestChanged): void
{
    if (!is_dir(dirname(LOG_FILE))) {
        mkdir(dirname(LOG_FILE), 0775, true);
    }
    $lines = array_map('json_encode', $entries);
    file_put_contents(LOG_FILE, $lines ? implode("\n", $lines) . "\n" : '', LOCK_EX);
    if ($latestChanged) {
        $last = end($entries) ?: null;
        file_put_contents(DATA_FILE, json_encode(['id' => $last['to'] ?? null, 'since' => $last['at'] ?? null]), LOCK_EX);
    }
}

/** Most recent changes first; a null limit returns everything. */
function history(?int $limit = 50): array
{
    if (!is_file(LOG_FILE)) {
        return [];
    }
    $lines = file(LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($limit !== null) {
        $lines = array_slice($lines, -$limit);
    }
    $entries = array_map(fn($line) => json_decode($line, true), $lines);
    return array_reverse(array_filter($entries));
}

/**
 * Renders history() entries as a list, with times shown in the viewer's locale.
 * Pass the admin's CSRF token to add a delete button to each entry.
 */
function history_list(array $history, ?string $deleteToken = null): void
{
    if (!$history) {
        echo '<p class="since">No changes yet.</p>';
        return;
    }
    ?>
    <ol class="history">
        <?php foreach ($history as $h): ?>
            <li>
                <div>
                    <span><?= $h['from'] === null ? e(person_name(null)) : player_link($h['from'], false) ?> → <strong><?= player_link($h['to'], false) ?></strong></span>
                    <?php if (!empty($h['game']) || !empty($h['players'])): ?>
                        <small>
                            Won<?= empty($h['game']) ? '' : ' at ' . game_link($h['game']) ?>
                            <?php if (!empty($h['players'])): ?>
                                against <?= implode(', ', array_map(fn($id) => player_link($id, false), $h['players'])) ?>
                            <?php endif ?>
                        </small>
                    <?php endif ?>
                    <small class="where">📍 <?= e(entry_location($h)) ?></small>
                </div>
                <div class="history-meta">
                    <time datetime="<?= e($h['at']) ?>"><?= e($h['at']) ?></time>
                    <?php if ($deleteToken !== null): ?>
                        <div class="entry-actions">
                            <a class="link" href="admin.php?<?= e(http_build_query(['edit' => $h['at'], 'to' => $h['to']])) ?>">Edit</a>
                            <form method="post" class="delete-entry"
                                  data-confirm="<?= e(person_name($h['to']) . (empty($h['game']) ? '' : ' winning ' . $h['game'])) ?>">
                                <input type="hidden" name="csrf" value="<?= e($deleteToken) ?>">
                                <input type="hidden" name="delete_at" value="<?= e($h['at']) ?>">
                                <input type="hidden" name="delete_to" value="<?= e($h['to']) ?>">
                                <button type="submit" class="link">Delete</button>
                            </form>
                        </div>
                    <?php endif ?>
                </div>
            </li>
        <?php endforeach ?>
    </ol>
    <script>
        document.querySelectorAll('.history time').forEach(t => {
            t.textContent = new Date(t.dateTime).toLocaleString();
        });
        document.querySelectorAll('.delete-entry').forEach(form => {
            form.addEventListener('submit', ev => {
                const when = new Date(form.delete_at.value).toLocaleDateString();
                if (!confirm(`Are you sure you want to delete ${form.dataset.confirm} on ${when}?`)) {
                    ev.preventDefault();
                }
            });
        });
    </script>
    <?php
}

/**
 * Renders future_matches(), soonest first, with times shown in the viewer's locale.
 * Pass the admin's CSRF token to add a delete button to each match.
 */
function future_list(array $matches, ?string $deleteToken = null): void
{
    if (!$matches) {
        echo '<p class="since">No future matches arranged.</p>';
        return;
    }
    ?>
    <ol class="history future">
        <?php foreach ($matches as $m): ?>
            <li>
                <div>
                    <span><?= $m['game'] ? '<strong>' . game_link($m['game']) . '</strong>' : 'Game not decided yet' ?></span>
                    <?php if ($m['players']): ?>
                        <small>Playing: <?= implode(', ', array_map(fn($id) => player_link($id, false), $m['players'])) ?></small>
                    <?php endif ?>
                    <small class="where">📍 <?= e($m['location']) ?></small>
                </div>
                <div class="history-meta">
                    <time datetime="<?= e($m['at']) ?>"><?= e($m['at']) ?></time>
                    <?php if ($deleteToken !== null): ?>
                        <div class="entry-actions">
                            <a class="link" href="admin.php?<?= e(http_build_query(['edit_future' => $m['at']])) ?>">Edit</a>
                            <form method="post" class="delete-future">
                                <input type="hidden" name="csrf" value="<?= e($deleteToken) ?>">
                                <input type="hidden" name="delete_future" value="<?= e($m['at']) ?>">
                                <button type="submit" class="link">Delete</button>
                            </form>
                        </div>
                    <?php endif ?>
                </div>
            </li>
        <?php endforeach ?>
    </ol>
    <script>
        document.querySelectorAll('.future time').forEach(t => {
            t.textContent = match_time(t.dateTime);
        });
        document.querySelectorAll('.delete-future').forEach(form => {
            form.addEventListener('submit', ev => {
                if (!confirm(`Are you sure you want to delete the match on ${match_time(form.delete_future.value)}?`)) {
                    ev.preventDefault();
                }
            });
        });
    </script>
    <?php
}

function person_name(?string $id): string
{
    if ($id === null) {
        return 'Nobody';
    }
    return people()[$id]['name'] ?? $id;
}

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

// Matches restarted after the pandemic on this date; earlier ones are only counted when asked for.
const PANDEMIC_RESTART = '2021-07-08';

/** Whether the viewer has switched on pre-pandemic matches (?pre). */
function include_pre_pandemic(): bool
{
    return isset($_GET['pre']);
}

function is_pre_pandemic(array $entry): bool
{
    return substr($entry['at'], 0, 10) < PANDEMIC_RESTART;
}

/** The "Include pre-pandemic matches" switch; $keep holds other query values the page needs. */
function pre_pandemic_toggle(array $keep = []): void
{
    ?>
    <form method="get" class="toggle">
        <?php foreach ($keep as $key => $value): ?>
            <input type="hidden" name="<?= e($key) ?>" value="<?= e($value) ?>">
        <?php endforeach ?>
        <label>
            <input type="checkbox" name="pre" value="1" <?= include_pre_pandemic() ? 'checked' : '' ?>
                   onchange="this.form.submit()">
            <span class="switch" aria-hidden="true"></span>
            Include pre-pandemic matches
        </label>
        <noscript><button type="submit">Update</button></noscript>
    </form>
    <?php
}

/** "2–5 players", "2+ players" or "Up to 5 players"; null when neither is set. */
function players_text(?array $range): ?string
{
    $min = $range['min'] ?? null;
    $max = $range['max'] ?? null;
    return match (true) {
        $min !== null && $max !== null => ($min === $max ? $min : "{$min}–{$max}") . ($max === 1 ? ' player' : ' players'),
        $min !== null => "$min+ players",
        $max !== null => "Up to $max " . ($max === 1 ? 'player' : 'players'),
        default => null,
    };
}

/**
 * Each person's chart colour: their avatar colour from Player details, so a colour always means
 * the same person. Anyone without one falls back to a fixed colour in config order.
 * @return array<string, string>
 */
function person_colours(): array
{
    $fallback = ['#3987e5', '#d95926', '#199e70', '#c98500', '#d55181', '#008300'];
    $people = people();
    $colours = [];
    foreach (array_keys($people) as $n => $id) {
        $colours[$id] = $people[$id]['colour'] ?? $fallback[$n % count($fallback)];
    }
    return $colours;
}

/**
 * A colour for each of $places, given in a fixed order (the location list first) so a place keeps
 * its colour, from hues the people don't use (violet, then gold).
 * @return array<string, string>
 */
function location_colours(array $places): array
{
    $hues = ['#9085e9', '#f5c542', '#e66767', '#a99fc9'];
    $colours = [];
    foreach (array_values($places) as $n => $place) {
        $colours[$place] = $hues[$n % count($hues)];
    }
    return $colours;
}

function days(int $seconds): string
{
    $d = (int) round($seconds / 86400);
    return $d === 1 ? '1 day' : number_format($d) . ' days';
}

/**
 * A donut built from stroked circles: each slice is a dash, offset to start where the last ended.
 * @param array<array{value: int|float, colour: string, tip: string}> $slices
 */
function donut(array $slices, string $label): void
{
    $total = array_sum(array_column($slices, 'value'));
    $r = 70;
    $circumference = 2 * M_PI * $r;
    // Only gap between slices; a single slice is a whole ring.
    $gap = count(array_filter(array_column($slices, 'value'))) > 1 ? 2 : 0;
    $offset = 0;
    ?>
    <svg class="donut" viewBox="0 0 200 200" role="img" aria-label="<?= e($label) ?>">
        <g transform="rotate(-90 100 100)">
            <?php foreach ($slices as $slice): ?>
                <?php if (!$slice['value']) continue ?>
                <?php $len = $circumference * $slice['value'] / $total ?>
                <circle cx="100" cy="100" r="<?= $r ?>" fill="none"
                        stroke="<?= e($slice['colour']) ?>" stroke-width="36"
                        stroke-dasharray="<?= max(0.5, $len - $gap) ?> <?= $circumference ?>"
                        stroke-dashoffset="<?= -$offset ?>"
                        data-tip="<?= e($slice['tip']) ?>"></circle>
                <?php $offset += $len ?>
            <?php endforeach ?>
        </g>
    </svg>
    <?php
}

/** The hover tooltip for chart parts with a data-tip; call once, after the charts. */
function chart_tooltip(): void
{
    ?>
    <div class="tooltip" id="tooltip" hidden></div>
    <script>
    const tooltip = document.getElementById('tooltip');
    document.querySelectorAll('[data-tip]').forEach(el => {
        el.addEventListener('pointermove', ev => {
            tooltip.textContent = el.dataset.tip;
            tooltip.hidden = false;
            tooltip.style.left = ev.clientX + 'px';
            tooltip.style.top = ev.clientY + 'px';
        });
        el.addEventListener('pointerleave', () => { tooltip.hidden = true; });
    });
    </script>
    <?php
}

function page_head(string $title): void
{
    ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#1b1530">
<title><?= e($title) ?></title>
<link rel="manifest" href="manifest.webmanifest">
<link rel="icon" href="favicon.ico" sizes="32x32">
<link rel="icon" href="favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="apple-touch-icon.png">
<link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . '/style.css') ?>">
<script>
// How future match times are shown, in the viewer's locale, e.g. "Thu 12 Nov, 19:45".
function match_time(iso) {
    return new Date(iso).toLocaleString([], {weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit'});
}
</script>
</head>
<body>
<?php
}
