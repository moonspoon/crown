<?php
require __DIR__ . '/lib.php';

// Oldest first, so each change lasts until the next one (the last until now).
$changes = array_reverse(history(null));
$now = time();

$heldFor = array_fill_keys(array_keys(people()), 0);
foreach ($changes as $i => $c) {
    $start = strtotime($c['at']);
    $end = isset($changes[$i + 1]) ? strtotime($changes[$i + 1]['at']) : $now;
    if (isset($heldFor[$c['to']])) {
        $heldFor[$c['to']] += max(0, $end - $start);
    }
}
$totalHeld = array_sum($heldFor);
arsort($heldFor);
$gameCounts = game_counts();
arsort($gameCounts);
// [label, count, unknown?] rows, with matches that have no game recorded last.
$gameRows = array_map(null, array_keys($gameCounts), array_values($gameCounts), array_fill(0, count($gameCounts), false));
$unknownGames = count(array_filter($changes, fn($c) => empty($c['game'])));
if ($unknownGames) {
    $gameRows[] = ['Unknown', $unknownGames, true];
}
$maxGame = $gameRows ? max(array_column($gameRows, 1)) : 0;

// Matches per day of the week, Monday first, in the site's time zone.
$weekdays = array_fill_keys(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'], 0);
foreach ($changes as $c) {
    $weekdays[date('l', strtotime($c['at']))]++;
}
$maxWeekday = max($weekdays);

// Matches each person played in, as winner or one of the other players.
$attended = array_fill_keys(array_keys(people()), 0);
foreach ($changes as $c) {
    foreach (array_unique([$c['to'], ...($c['players'] ?? [])]) as $id) {
        if (isset($attended[$id])) {
            $attended[$id]++;
        }
    }
}
arsort($attended);

// Each person's most won game, with every game tied for the top spot.
$winsByGame = array_fill_keys(array_keys(people()), []);
foreach ($changes as $c) {
    if (!empty($c['game']) && isset($winsByGame[$c['to']])) {
        $winsByGame[$c['to']][$c['game']] = ($winsByGame[$c['to']][$c['game']] ?? 0) + 1;
    }
}
$topWins = [];
foreach ($heldFor as $id => $_) {
    $wins = $winsByGame[$id];
    $best = $wins ? max($wins) : 0;
    $games = array_keys(array_filter($wins, fn($n) => $n === $best));
    sort($games, SORT_NATURAL | SORT_FLAG_CASE);
    $topWins[$id] = ['games' => $games, 'wins' => $best];
}

// Fixed colour per person, in config order, so a colour always means the same person.
$colours = ['#3987e5', '#d95926', '#199e70', '#c98500', '#d55181', '#008300'];
$personColour = [];
foreach (array_keys(people()) as $n => $id) {
    $personColour[$id] = $colours[$n % count($colours)];
}

// Matches per location, most used first. Each location keeps a colour in config order,
// from hues the people don't use (violet, then gold).
$byLocation = array_fill_keys(locations(), 0);
foreach ($changes as $c) {
    $place = entry_location($c);
    $byLocation[$place] = ($byLocation[$place] ?? 0) + 1;
}
$locationHues = ['#9085e9', '#f5c542', '#e66767', '#a99fc9'];
$locationColour = [];
foreach (array_keys($byLocation) as $n => $place) {
    $locationColour[$place] = $locationHues[$n % count($locationHues)];
}
arsort($byLocation);

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
    $gap = 2;
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

page_head('Crown Stats');
?>
<main class="stage stats">
    <h1>Crown stats</h1>
    <?php if (!$changes): ?>
        <p class="since">No matches recorded yet.</p>
    <?php else: ?>
        <p class="since">Since <?= e(date('j M Y', strtotime($changes[0]['at']))) ?></p>

        <h2 class="label stats-title">Time with the crown</h2>
        <div class="donut-wrap">
            <?php donut(array_map(fn($id, $secs) => [
                'value' => $secs,
                'colour' => $personColour[$id],
                'tip' => person_name($id) . ': ' . days($secs) . ' (' . round(100 * $secs / $totalHeld) . '%)',
            ], array_keys($heldFor), $heldFor), 'Share of time each person has held the crown') ?>
            <div class="legend">
                <?php foreach ($heldFor as $id => $secs): ?>
                    <div class="legend-row" data-tip="<?= e(person_name($id) . ': ' . days($secs)) ?>">
                        <span class="swatch" style="background: <?= $personColour[$id] ?>"></span>
                        <span class="who"><?= e(people()[$id]['icon']) ?> <?= e(person_name($id)) ?></span>
                        <span class="value"><?= e(days($secs)) ?> · <?= $totalHeld ? round(100 * $secs / $totalHeld) : 0 ?>%</span>
                    </div>
                <?php endforeach ?>
            </div>
        </div>

        <h2 class="label stats-title">Games played</h2>
        <div class="bars" aria-label="Number of crown matches per game">
            <?php foreach ($gameRows as [$game, $count, $unknown]): ?>
                <div class="bar-row<?= $unknown ? ' unknown' : '' ?>" data-tip="<?= e($game . ': ' . $count . ($count === 1 ? ' match' : ' matches')) ?>">
                    <span class="bar-label"><?= e($game) ?></span>
                    <span class="bar-track"><span class="bar" style="width: <?= round(100 * $count / $maxGame, 1) ?>%"></span></span>
                    <span class="bar-value"><?= $count ?></span>
                </div>
            <?php endforeach ?>
        </div>

        <h2 class="label stats-title">Most won game</h2>
        <div class="legend top-wins">
            <?php foreach ($topWins as $id => $top): ?>
                <div class="legend-row">
                    <span class="swatch" style="background: <?= $personColour[$id] ?>"></span>
                    <span class="who"><?= e(people()[$id]['icon']) ?> <?= e(person_name($id)) ?></span>
                    <?php if ($top['games']): ?>
                        <span class="top-game">
                            <?= e(implode(', ', $top['games'])) ?>
                            <span class="value">· <?= $top['wins'] ?> <?= $top['wins'] === 1 ? 'win' : 'wins' ?></span>
                        </span>
                    <?php else: ?>
                        <span class="value">No wins yet</span>
                    <?php endif ?>
                </div>
            <?php endforeach ?>
        </div>

        <h2 class="label stats-title">Attendance</h2>
        <div class="bars" aria-label="Number of crown matches each person played">
            <?php foreach ($attended as $id => $count): ?>
                <?php $share = round(100 * $count / count($changes)) ?>
                <div class="bar-row" data-tip="<?= e(person_name($id) . ': played ' . $count . ' of ' . count($changes) . ' matches (' . $share . '%)') ?>">
                    <span class="bar-label"><?= e(people()[$id]['icon']) ?> <?= e(person_name($id)) ?></span>
                    <span class="bar-track">
                        <?php if ($count): ?><span class="bar" style="width: <?= $share ?>%; background: <?= $personColour[$id] ?>"></span><?php endif ?>
                    </span>
                    <span class="bar-value"><?= $count ?></span>
                </div>
            <?php endforeach ?>
        </div>

        <h2 class="label stats-title">Location</h2>
        <div class="donut-wrap">
            <?php donut(array_map(fn($place, $count) => [
                'value' => $count,
                'colour' => $locationColour[$place],
                'tip' => $place . ': ' . $count . ' matches (' . round(100 * $count / count($changes)) . '%)',
            ], array_keys($byLocation), $byLocation), 'Share of crown matches at each location') ?>
            <div class="legend">
                <?php foreach ($byLocation as $place => $count): ?>
                    <div class="legend-row">
                        <span class="swatch" style="background: <?= $locationColour[$place] ?>"></span>
                        <span class="who">📍 <?= e($place) ?></span>
                        <span class="value"><?= $count ?> <?= $count === 1 ? 'match' : 'matches' ?> · <?= round(100 * $count / count($changes)) ?>%</span>
                    </div>
                <?php endforeach ?>
            </div>
        </div>

        <h2 class="label stats-title">Day of the week</h2>
        <div class="bars" aria-label="Number of crown matches per day of the week">
            <?php foreach ($weekdays as $day => $count): ?>
                <div class="bar-row" data-tip="<?= e($day . ': ' . $count . ($count === 1 ? ' match' : ' matches')) ?>">
                    <span class="bar-label"><?= e($day) ?></span>
                    <span class="bar-track">
                        <?php if ($count): ?><span class="bar" style="width: <?= round(100 * $count / $maxWeekday, 1) ?>%"></span><?php endif ?>
                    </span>
                    <span class="bar-value"><?= $count ?></span>
                </div>
            <?php endforeach ?>
        </div>
    <?php endif ?>
</main>
<div class="tooltip" id="tooltip" hidden></div>
<a class="admin-link" href="./">← Back to the crown</a>
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
</body>
</html>
