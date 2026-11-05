<?php
require __DIR__ . '/lib.php';

$name = (string) ($_GET['name'] ?? '');
// Every recorded match at this game, oldest first; pre-pandemic ones only when switched on.
$matches = array_values(array_filter(log_entries(), fn($m) => ($m['game'] ?? null) === $name));
$hasPrePandemic = (bool) array_filter($matches, 'is_pre_pandemic');
$anyMatches = (bool) $matches;
if (!include_pre_pandemic()) {
    $matches = array_values(array_filter($matches, fn($m) => !is_pre_pandemic($m)));
}
if ($name === '' || (!$anyMatches && !in_array($name, games(), true))) {
    http_response_code(404);
    page_head('Game Not Found');
    ?>
    <main class="stage">
        <h1>Game not found</h1>
        <a href="games.php" class="show-all">← All games</a>
    </main>
    </body>
    </html>
    <?php
    exit;
}

$range = game_players()[$name] ?? null;
$now = time();
$first = $matches ? strtotime($matches[0]['at']) : null;
$last = $matches ? strtotime(end($matches)['at']) : null;
$daysSince = $last === null ? null : (int) floor(($now - $last) / 86400);

// Wins at this game per person, most first.
$wins = array_fill_keys(array_keys(people()), 0);
foreach ($matches as $m) {
    if (isset($wins[$m['to']])) {
        $wins[$m['to']]++;
    }
}
arsort($wins);
$personColour = person_colours();

// Matches per location, most used first.
$byLocation = array_fill_keys(locations(), 0);
foreach ($matches as $m) {
    $place = entry_location($m);
    $byLocation[$place] = ($byLocation[$place] ?? 0) + 1;
}
$locationColour = location_colours(array_keys($byLocation));
$byLocation = array_filter($byLocation);
arsort($byLocation);

// Matches per day of the week, Monday first, in the site's time zone.
$weekdays = array_fill_keys(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'], 0);
foreach ($matches as $m) {
    $weekdays[date('l', strtotime($m['at']))]++;
}
$maxWeekday = max(1, max($weekdays));

// How many matches had each number of players (the winner plus everyone they played against).
$playerCounts = [];
foreach ($matches as $m) {
    $n = count(array_unique([$m['to'], ...($m['players'] ?? [])]));
    $playerCounts[$n] = ($playerCounts[$n] ?? 0) + 1;
}
krsort($playerCounts);
$maxPlayerCount = $playerCounts ? max($playerCounts) : 1;

$total = count($matches);
$plural = fn(int $n, string $one, string $many) => $n === 1 ? "1 $one" : number_format($n) . " $many";

page_head(game_label($name));
?>
<main class="stage stats">
    <h1><?= e(game_label($name)) ?></h1>
    <?php if ($players = players_text($range)): ?>
        <p class="since"><?= e($players) ?></p>
    <?php endif ?>
    <?php if ($hasPrePandemic) pre_pandemic_toggle(['name' => $name]) ?>

    <?php if (!$matches): ?>
        <p class="won"><?= $anyMatches ? 'Not played since the pandemic.' : 'Not played yet.' ?></p>
    <?php else: ?>
        <p class="won">
            Played <strong><?= e($plural($total, 'time', 'times')) ?></strong>
            <?php if ($total > 1): ?>since <?= e(date('j M Y', $first)) ?><?php endif ?>
            <br>Last played <strong><?= $daysSince ? e(days($daysSince * 86400)) . '</strong> ago, on ' : 'today</strong>, ' ?><?= e(date('j M Y', $last)) ?>
        </p>

        <h2 class="label stats-title">Wins</h2>
        <div class="donut-wrap">
            <?php donut(array_map(fn($id, $n) => [
                'value' => $n,
                'colour' => $personColour[$id],
                'tip' => person_name($id) . ': ' . $plural($n, 'win', 'wins') . ' (' . round(100 * $n / $total) . '%)',
            ], array_keys($wins), $wins), 'Share of ' . $name . ' matches each person won') ?>
            <div class="legend">
                <?php foreach ($wins as $id => $n): ?>
                    <div class="legend-row">
                        <span class="swatch" style="background: <?= $personColour[$id] ?>"></span>
                        <span class="who"><?= e(people()[$id]['icon']) ?> <?= e(person_name($id)) ?></span>
                        <span class="value"><?= e($plural($n, 'win', 'wins')) ?> · <?= round(100 * $n / $total) ?>%</span>
                    </div>
                <?php endforeach ?>
            </div>
        </div>

        <h2 class="label stats-title">Location</h2>
        <div class="donut-wrap">
            <?php donut(array_map(fn($place, $n) => [
                'value' => $n,
                'colour' => $locationColour[$place],
                'tip' => $place . ': ' . $plural($n, 'match', 'matches') . ' (' . round(100 * $n / $total) . '%)',
            ], array_keys($byLocation), $byLocation), 'Share of ' . $name . ' matches at each location') ?>
            <div class="legend">
                <?php foreach ($byLocation as $place => $n): ?>
                    <div class="legend-row">
                        <span class="swatch" style="background: <?= $locationColour[$place] ?>"></span>
                        <span class="who">📍 <?= e($place) ?></span>
                        <span class="value"><?= e($plural($n, 'match', 'matches')) ?> · <?= round(100 * $n / $total) ?>%</span>
                    </div>
                <?php endforeach ?>
            </div>
        </div>

        <h2 class="label stats-title">Day of the week</h2>
        <div class="bars" aria-label="Number of <?= e($name) ?> matches per day of the week">
            <?php foreach ($weekdays as $day => $n): ?>
                <div class="bar-row" data-tip="<?= e($day . ': ' . $plural($n, 'match', 'matches')) ?>">
                    <span class="bar-label"><?= e($day) ?></span>
                    <span class="bar-track">
                        <?php if ($n): ?><span class="bar" style="width: <?= round(100 * $n / $maxWeekday, 1) ?>%"></span><?php endif ?>
                    </span>
                    <span class="bar-value"><?= $n ?></span>
                </div>
            <?php endforeach ?>
        </div>

        <h2 class="label stats-title">Players per match</h2>
        <div class="bars" aria-label="Number of <?= e($name) ?> matches by how many people played">
            <?php foreach ($playerCounts as $players => $n): ?>
                <div class="bar-row" data-tip="<?= e($players . ' players: ' . $plural($n, 'match', 'matches') . ' (' . round(100 * $n / $total) . '%)') ?>">
                    <span class="bar-label"><?= $players ?> players</span>
                    <span class="bar-track"><span class="bar" style="width: <?= round(100 * $n / $maxPlayerCount, 1) ?>%"></span></span>
                    <span class="bar-value"><?= $n ?></span>
                </div>
            <?php endforeach ?>
        </div>

        <h2 class="label stats-title">History</h2>
        <?php history_list(array_reverse($matches)) ?>
    <?php endif ?>
    <a href="games.php<?= include_pre_pandemic() ? '?pre=1' : '' ?>" class="show-all">← All games</a>
</main>
<a class="admin-link" href="./">← Back to the crown</a>
<?php chart_tooltip() ?>
</body>
</html>
