<?php
require __DIR__ . '/lib.php';

// Matches restarted after the pandemic on this date; earlier ones are only counted when asked for.
const PANDEMIC_RESTART = '2021-07-08';
$includePrePandemic = isset($_GET['pre']);

// Oldest first, so each change lasts until the next one (the last until now).
$changes = array_reverse(history(null));
$hasPrePandemic = $changes && substr($changes[0]['at'], 0, 10) < PANDEMIC_RESTART;
if (!$includePrePandemic) {
    $changes = array_values(array_filter($changes, fn($c) => substr($c['at'], 0, 10) >= PANDEMIC_RESTART));
}
$now = time();

$heldFor = array_fill_keys(array_keys(people()), 0);
// When each person last stopped holding the crown; the current holder's spell ends now.
$lastHeldUntil = array_fill_keys(array_keys(people()), null);
foreach ($changes as $i => $c) {
    $start = strtotime($c['at']);
    $end = isset($changes[$i + 1]) ? strtotime($changes[$i + 1]['at']) : $now;
    if (isset($heldFor[$c['to']])) {
        $heldFor[$c['to']] += max(0, $end - $start);
        $lastHeldUntil[$c['to']] = $end;
    }
}
$totalHeld = array_sum($heldFor);
arsort($heldFor);

// Each person's longest unbroken spell with the crown; matches they kept it at don't break a spell.
// The same spells give each person's most matches won in a row (keeping the crown counts as a win).
$longestReign = array_fill_keys(array_keys(people()), null);
$longestStreak = array_fill_keys(array_keys(people()), null);
$reignStart = null;
$reignWins = 0;
foreach ($changes as $i => $c) {
    $reignStart ??= strtotime($c['at']);
    $reignWins++;
    $next = $changes[$i + 1] ?? null;
    if ($next && $next['to'] === $c['to']) {
        continue;
    }
    $reignEnd = $next ? strtotime($next['at']) : $now;
    $length = $reignEnd - $reignStart;
    if (isset(people()[$c['to']])) {
        if ($length > ($longestReign[$c['to']]['length'] ?? -1)) {
            $longestReign[$c['to']] = ['length' => $length, 'from' => $reignStart, 'to' => $reignEnd, 'ongoing' => !$next];
        }
        if ($reignWins > ($longestStreak[$c['to']]['wins'] ?? 0)) {
            $longestStreak[$c['to']] = ['wins' => $reignWins, 'from' => $reignStart, 'last' => strtotime($c['at']), 'ongoing' => !$next];
        }
    }
    $reignStart = null;
    $reignWins = 0;
}
uasort($longestReign, fn($a, $b) => ($b['length'] ?? -1) <=> ($a['length'] ?? -1));
$maxReign = max(1, ...array_values(array_map(fn($r) => $r['length'] ?? 0, $longestReign)));
uasort($longestStreak, fn($a, $b) => ($b['wins'] ?? 0) <=> ($a['wins'] ?? 0));
$maxStreak = max(1, ...array_values(array_map(fn($s) => $s['wins'] ?? 0, $longestStreak)));

// Days since each person last had the crown, longest wait first; null means never held it.
$holderId = $changes ? end($changes)['to'] : null;
$daysSince = [];
foreach ($lastHeldUntil as $id => $until) {
    $daysSince[$id] = $until === null ? null : ($id === $holderId ? 0 : (int) floor(($now - $until) / 86400));
}
uasort($daysSince, fn($a, $b) => ($b ?? PHP_INT_MAX) <=> ($a ?? PHP_INT_MAX));
$maxDaysSince = max(1, ...array_values(array_map(fn($d) => $d ?? 0, $daysSince)));
$gameCounts = game_counts();
arsort($gameCounts);
// [label, count, unknown?] rows, with matches that have no game recorded last.
$gameRows = array_map(null, array_keys($gameCounts), array_values($gameCounts), array_fill(0, count($gameCounts), false));
$unknownGames = count(array_filter($changes, fn($c) => empty($c['game'])));
if ($unknownGames) {
    $gameRows[] = ['Unknown', $unknownGames, true];
}
$maxGame = $gameRows ? max(array_column($gameRows, 1)) : 0;

// When each game was last played, longest ago first.
$gameLastPlayed = [];
foreach ($changes as $c) {
    if (!empty($c['game'])) {
        $gameLastPlayed[$c['game']] = strtotime($c['at']);
    }
}
asort($gameLastPlayed);
$maxGameDaysSince = $gameLastPlayed ? max(1, (int) floor(($now - reset($gameLastPlayed)) / 86400)) : 1;

// Matches per day of the week, Monday first, in the site's time zone.
$weekdays = array_fill_keys(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'], 0);
foreach ($changes as $c) {
    $weekdays[date('l', strtotime($c['at']))]++;
}
$maxWeekday = max($weekdays);

// How many matches had each number of players (the winner plus everyone they played against).
$playerCounts = [];
foreach ($changes as $c) {
    $n = count(array_unique([$c['to'], ...($c['players'] ?? [])]));
    $playerCounts[$n] = ($playerCounts[$n] ?? 0) + 1;
}
krsort($playerCounts);
$maxPlayerCount = $playerCounts ? max($playerCounts) : 0;

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

// Matches each person won, split into taking the crown and keeping it.
$winTotals = array_fill_keys(array_keys(people()), ['total' => 0, 'kept' => 0]);
foreach ($changes as $c) {
    if (isset($winTotals[$c['to']])) {
        $winTotals[$c['to']]['total']++;
        if ($c['from'] === $c['to']) {
            $winTotals[$c['to']]['kept']++;
        }
    }
}
uasort($winTotals, fn($a, $b) => $b['total'] <=> $a['total']);
$maxWins = max(1, ...array_values(array_column($winTotals, 'total')));

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

// Each person's avatar colour from Player details, so a colour always means the same person.
// Anyone without one falls back to a fixed colour in config order.
$colours = ['#3987e5', '#d95926', '#199e70', '#c98500', '#d55181', '#008300'];
$personColour = [];
foreach (array_keys(people()) as $n => $id) {
    $personColour[$id] = people()[$id]['colour'] ?? $colours[$n % count($colours)];
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
    <?php if ($hasPrePandemic): ?>
        <form method="get" class="toggle">
            <label>
                <input type="checkbox" name="pre" value="1" <?= $includePrePandemic ? 'checked' : '' ?>
                       onchange="this.form.submit()">
                <span class="switch" aria-hidden="true"></span>
                Include pre-pandemic matches
            </label>
            <noscript><button type="submit">Update</button></noscript>
        </form>
    <?php endif ?>
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

        <h2 class="label stats-title">Wins</h2>
        <div class="bars" aria-label="Number of crown matches each person won">
            <?php foreach ($winTotals as $id => $w): ?>
                <?php
                $taken = $w['total'] - $w['kept'];
                $rate = $attended[$id] ? round(100 * $w['total'] / $attended[$id]) : 0;
                $tip = person_name($id) . ': ' . $w['total'] . ($w['total'] === 1 ? ' win' : ' wins')
                    . " ($taken taking the crown, {$w['kept']} keeping it), won $rate% of matches played";
                ?>
                <div class="bar-row" data-tip="<?= e($tip) ?>">
                    <span class="bar-label"><?= e(people()[$id]['icon']) ?> <?= e(person_name($id)) ?></span>
                    <span class="bar-track">
                        <?php if ($w['total']): ?><span class="bar" style="width: <?= round(100 * $w['total'] / $maxWins, 1) ?>%; background: <?= $personColour[$id] ?>"></span><?php endif ?>
                    </span>
                    <span class="bar-value"><?= $w['total'] ?></span>
                </div>
            <?php endforeach ?>
        </div>

        <h2 class="label stats-title">Longest time with the crown</h2>
        <div class="bars since-crown" aria-label="Each person's longest unbroken spell holding the crown">
            <?php foreach ($longestReign as $id => $reign): ?>
                <?php
                $tip = $reign === null
                    ? person_name($id) . ' has never held the crown'
                    : person_name($id) . ': ' . days($reign['length']) . ', ' . date('j M Y', $reign['from'])
                        . ($reign['ongoing'] ? ' to now (still holding)' : ' to ' . date('j M Y', $reign['to']));
                ?>
                <div class="bar-row" data-tip="<?= e($tip) ?>">
                    <span class="bar-label"><?= e(people()[$id]['icon']) ?> <?= e(person_name($id)) ?></span>
                    <span class="bar-track">
                        <?php if ($reign): ?><span class="bar" style="width: <?= round(100 * $reign['length'] / $maxReign, 1) ?>%; background: <?= $personColour[$id] ?>"></span><?php endif ?>
                    </span>
                    <span class="bar-value"><?= $reign ? e(days($reign['length'])) . ($reign['ongoing'] ? '*' : '') : 'Never' ?></span>
                </div>
            <?php endforeach ?>
        </div>
        <?php if (array_filter(array_column(array_filter($longestReign), 'ongoing'))): ?>
            <p class="since chart-note">* still holding the crown</p>
        <?php endif ?>

        <h2 class="label stats-title">Most wins in a row</h2>
        <div class="bars since-crown" aria-label="Each person's most crown matches won in a row">
            <?php foreach ($longestStreak as $id => $streak): ?>
                <?php
                $tip = $streak === null
                    ? person_name($id) . ' has never won the crown'
                    : person_name($id) . ': ' . $streak['wins'] . ' in a row'
                        . ($streak['wins'] > 1 ? ', ' . date('j M Y', $streak['from']) . ' to ' . date('j M Y', $streak['last']) : ' on ' . date('j M Y', $streak['from']))
                        . ($streak['ongoing'] ? ' (still going)' : '');
                ?>
                <div class="bar-row" data-tip="<?= e($tip) ?>">
                    <span class="bar-label"><?= e(people()[$id]['icon']) ?> <?= e(person_name($id)) ?></span>
                    <span class="bar-track">
                        <?php if ($streak): ?><span class="bar" style="width: <?= round(100 * $streak['wins'] / $maxStreak, 1) ?>%; background: <?= $personColour[$id] ?>"></span><?php endif ?>
                    </span>
                    <span class="bar-value"><?= $streak ? $streak['wins'] . ($streak['wins'] === 1 ? ' win' : ' wins') . ($streak['ongoing'] ? '*' : '') : 'None' ?></span>
                </div>
            <?php endforeach ?>
        </div>
        <?php if (array_filter(array_column(array_filter($longestStreak), 'ongoing'))): ?>
            <p class="since chart-note">* still going</p>
        <?php endif ?>

        <h2 class="label stats-title">Days since last holding the crown</h2>
        <div class="bars since-crown" aria-label="Days since each person last held the crown">
            <?php foreach ($daysSince as $id => $d): ?>
                <?php
                $text = match (true) {
                    $d === null => 'Never',
                    $id === $holderId => 'Has it now',
                    default => days($d * 86400),
                };
                ?>
                <div class="bar-row" data-tip="<?= e(person_name($id) . ': ' . ($id === $holderId ? 'holds the crown now' : ($d === null ? 'has never held the crown' : days($d * 86400) . ' since they last had it'))) ?>">
                    <span class="bar-label"><?= e(people()[$id]['icon']) ?> <?= e(person_name($id)) ?></span>
                    <span class="bar-track">
                        <?php if ($d): ?><span class="bar" style="width: <?= round(100 * $d / $maxDaysSince, 1) ?>%; background: <?= $personColour[$id] ?>"></span><?php endif ?>
                    </span>
                    <span class="bar-value"><?= e($text) ?></span>
                </div>
            <?php endforeach ?>
        </div>

        <h2 class="label stats-title">Games played</h2>
        <div class="bars" aria-label="Number of crown matches per game">
            <?php foreach ($gameRows as [$game, $count, $unknown]): ?>
                <div class="bar-row<?= $unknown ? ' unknown' : '' ?>" data-tip="<?= e($game . ': ' . $count . ($count === 1 ? ' match' : ' matches')) ?>">
                    <span class="bar-label"><?= e(game_label($game)) ?></span>
                    <span class="bar-track"><span class="bar" style="width: <?= round(100 * $count / $maxGame, 1) ?>%"></span></span>
                    <span class="bar-value"><?= $count ?></span>
                </div>
            <?php endforeach ?>
        </div>

        <?php if ($gameLastPlayed): ?>
            <h2 class="label stats-title">Days since game last played</h2>
            <div class="bars since-crown" aria-label="Days since each game was last played">
                <?php foreach ($gameLastPlayed as $game => $at): ?>
                    <?php $d = (int) floor(($now - $at) / 86400) ?>
                    <div class="bar-row" data-tip="<?= e($game . ': last played ' . date('j M Y', $at) . ($d ? ' (' . days($d * 86400) . ' ago)' : ' (today)')) ?>">
                        <span class="bar-label"><?= e(game_label($game)) ?></span>
                        <span class="bar-track">
                            <?php if ($d): ?><span class="bar" style="width: <?= round(100 * $d / $maxGameDaysSince, 1) ?>%"></span><?php endif ?>
                        </span>
                        <span class="bar-value"><?= $d ? e(days($d * 86400)) : 'Today' ?></span>
                    </div>
                <?php endforeach ?>
            </div>
        <?php endif ?>

        <h2 class="label stats-title">Most won game</h2>
        <div class="legend top-wins">
            <?php foreach ($topWins as $id => $top): ?>
                <div class="legend-row">
                    <span class="who"><?= e(people()[$id]['icon']) ?> <?= e(person_name($id)) ?></span>
                    <?php if ($top['games']): ?>
                        <span class="top-game">
                            <?= e(implode(', ', array_map('game_label', $top['games']))) ?>
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

        <h2 class="label stats-title">Players per match</h2>
        <div class="bars" aria-label="Number of crown matches by how many people played">
            <?php foreach ($playerCounts as $n => $count): ?>
                <div class="bar-row" data-tip="<?= e($n . ' players: ' . $count . ($count === 1 ? ' match' : ' matches') . ' (' . round(100 * $count / count($changes)) . '%)') ?>">
                    <span class="bar-label"><?= $n ?> players</span>
                    <span class="bar-track"><span class="bar" style="width: <?= round(100 * $count / $maxPlayerCount, 1) ?>%"></span></span>
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
