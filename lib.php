<?php

const DATA_FILE = __DIR__ . '/data/holder.json';
const LOG_FILE = __DIR__ . '/data/history.log';
const GAMES_FILE = __DIR__ . '/data/games.json';
const ICONS_FILE = __DIR__ . '/data/icons.json';
const LOCATIONS_FILE = __DIR__ . '/data/locations.json';

function config(): array
{
    static $config = null;
    return $config ??= require __DIR__ . '/config.php';
}

date_default_timezone_set(config()['timezone'] ?? 'Europe/London');

/** The people from config.php, with any icons changed on the admin page. */
function people(): array
{
    $people = config()['people'];
    $icons = is_file(ICONS_FILE) ? json_decode((string) file_get_contents(ICONS_FILE), true) : [];
    foreach ($icons ?: [] as $id => $icon) {
        if (isset($people[$id])) {
            $people[$id]['icon'] = $icon;
        }
    }
    return $people;
}

function set_icon(string $id, string $icon): string
{
    if (!isset(config()['people'][$id])) {
        throw new InvalidArgumentException('Unknown person.');
    }
    $icon = trim($icon);
    if ($icon === '') {
        throw new InvalidArgumentException('Enter an emoji or a couple of letters.');
    }
    // Emoji can take several bytes each (flags, skin tones, families), so allow a little room.
    if (strlen($icon) > 32) {
        throw new InvalidArgumentException('Icons should be a single emoji or a couple of letters.');
    }
    $icons = is_file(ICONS_FILE) ? (json_decode((string) file_get_contents(ICONS_FILE), true) ?: []) : [];
    $icons[$id] = $icon;
    if (!is_dir(dirname(ICONS_FILE))) {
        mkdir(dirname(ICONS_FILE), 0775, true);
    }
    file_put_contents(ICONS_FILE, json_encode($icons, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    return $icon;
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
    return $new;
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
                    <span><?= e(person_name($h['from'])) ?> → <strong><?= e(person_name($h['to'])) ?></strong></span>
                    <?php if (!empty($h['game']) || !empty($h['players'])): ?>
                        <small>
                            Won<?= empty($h['game']) ? '' : ' at ' . e($h['game']) ?>
                            <?php if (!empty($h['players'])): ?>
                                against <?= e(implode(', ', array_map('person_name', $h['players']))) ?>
                            <?php endif ?>
                        </small>
                    <?php endif ?>
                    <small class="where">📍 <?= e(entry_location($h)) ?></small>
                </div>
                <div class="history-meta">
                    <time datetime="<?= e($h['at']) ?>"><?= e($h['at']) ?></time>
                    <?php if ($deleteToken !== null): ?>
                        <form method="post" class="delete-entry"
                              data-confirm="<?= e(person_name($h['to']) . (empty($h['game']) ? '' : ' winning ' . $h['game'])) ?>">
                            <input type="hidden" name="csrf" value="<?= e($deleteToken) ?>">
                            <input type="hidden" name="delete_at" value="<?= e($h['at']) ?>">
                            <input type="hidden" name="delete_to" value="<?= e($h['to']) ?>">
                            <button type="submit" class="link">Delete</button>
                        </form>
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
</head>
<body>
<?php
}
