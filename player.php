<?php
require __DIR__ . '/lib.php';

$id = (string) ($_GET['id'] ?? '');
$person = people()[$id] ?? null;
if (!$person) {
    http_response_code(404);
    page_head('Player Not Found');
    ?>
    <main class="stage">
        <h1>Player not found</h1>
        <a href="./" class="show-all">← Back to the crown</a>
    </main>
    </body>
    </html>
    <?php
    exit;
}

// Oldest first, so each change lasts until the next one (the last until now).
$changes = array_reverse(history(null));
$hasPrePandemic = $changes && is_pre_pandemic($changes[0]);
if (!include_pre_pandemic()) {
    $changes = array_values(array_filter($changes, fn($c) => !is_pre_pandemic($c)));
}
$now = time();
$total = count($changes);
$holderId = $changes ? end($changes)['to'] : null;
$plural = fn(int $n, string $one, string $many) => $n === 1 ? "1 $one" : number_format($n) . " $many";
$played = fn(array $c) => in_array($id, [$c['to'], ...($c['players'] ?? [])], true);

// Time with the crown, out of all the time anyone held it, and when they last stopped holding it.
$heldFor = 0;
$allHeld = 0;
$lastHeldUntil = null;
foreach ($changes as $i => $c) {
    $length = max(0, (isset($changes[$i + 1]) ? strtotime($changes[$i + 1]['at']) : $now) - strtotime($c['at']));
    $allHeld += $length;
    if ($c['to'] === $id) {
        $heldFor += $length;
        $lastHeldUntil = strtotime($c['at']) + $length;
    }
}
$daysSince = $lastHeldUntil === null ? null : ($holderId === $id ? 0 : (int) floor(($now - $lastHeldUntil) / 86400));

// Most matches won in a row: a run of wins with nobody else winning in between (keeping the crown counts).
$streak = null;
$run = 0;
$runFrom = null;
foreach ($changes as $i => $c) {
    if ($c['to'] !== $id) {
        continue;
    }
    $runFrom ??= strtotime($c['at']);
    $run++;
    $next = $changes[$i + 1] ?? null;
    if ($next && $next['to'] === $id) {
        continue;
    }
    if ($run > ($streak['wins'] ?? 0)) {
        $streak = ['wins' => $run, 'from' => $runFrom, 'last' => strtotime($c['at']), 'ongoing' => !$next];
    }
    $run = 0;
    $runFrom = null;
}

// Wins, split into taking the crown and keeping it; and matches played.
$wins = array_values(array_filter($changes, fn($c) => $c['to'] === $id));
$kept = count(array_filter($wins, fn($c) => $c['from'] === $id));
$attended = array_values(array_filter($changes, $played));

// Wins per game, most first, with wins at an unrecorded game last.
$winsByGame = [];
foreach ($wins as $c) {
    if (!empty($c['game'])) {
        $winsByGame[$c['game']] = ($winsByGame[$c['game']] ?? 0) + 1;
    }
}
arsort($winsByGame);
$unknownWins = count($wins) - array_sum($winsByGame);
$topWins = $winsByGame ? max($winsByGame) : 0;
$topGames = array_keys(array_filter($winsByGame, fn($n) => $n === $topWins));
sort($topGames, SORT_NATURAL | SORT_FLAG_CASE);

// Games picked, most first, with picks of an unrecorded game last: the holder going into a match chooses what's played.
$picks = array_values(array_filter($changes, fn($c) => $c['from'] === $id));
$picksByGame = [];
foreach ($picks as $c) {
    if (!empty($c['game'])) {
        $picksByGame[$c['game']] = ($picksByGame[$c['game']] ?? 0) + 1;
    }
}
arsort($picksByGame);
$unknownPicks = count($picks) - array_sum($picksByGame);

// A colour per game, by its place in the games list so a game keeps its colour on every player's page.
$hues = ['#3987e5', '#d95926', '#199e70', '#c98500', '#d55181', '#008300', '#9085e9', '#e66767', '#f5c542', '#4fb3c9'];
$gameNames = array_values(array_unique([...games(), ...array_keys($winsByGame), ...array_keys($picksByGame)]));
$gameColour = [];
foreach ($gameNames as $n => $game) {
    $gameColour[$game] = $hues[$n % count($hues)];
}

// Matches played at each game, and when this person last played each one.
$playedByGame = [];
$lastPlayed = [];
foreach ($attended as $c) {
    if (!empty($c['game'])) {
        $playedByGame[$c['game']] = ($playedByGame[$c['game']] ?? 0) + 1;
        $lastPlayed[$c['game']] = strtotime($c['at']);
    }
}
arsort($playedByGame);
$maxPlayed = $playedByGame ? max($playedByGame) : 1;
asort($lastPlayed);
$maxSincePlayed = $lastPlayed ? max(1, (int) floor(($now - reset($lastPlayed)) / 86400)) : 1;

page_head($person['name']);
?>
<main class="stage stats">
    <?php if ($holderId === $id): ?><div class="crown">👑</div><?php endif ?>
    <div class="avatar big<?= $holderId === $id ? '' : ' not-holder' ?>"<?= avatar_style($person) ?>><?= e($person['icon']) ?></div>
    <h1><?= e($person['name']) ?></h1>
    <?php if ($hasPrePandemic) pre_pandemic_toggle(['id' => $id]) ?>

    <?php if (!$changes): ?>
        <p class="since">No matches recorded yet.</p>
    <?php else: ?>
        <div class="stat-grid">
            <div class="stat-tile">
                <span class="label">Wins</span>
                <span class="stat-value"><?= count($wins) ?></span>
                <span class="stat-note"><?= count($wins) - $kept ?> taking the crown, <?= $kept ?> keeping it<?php if ($attended): ?> · won <?= round(100 * count($wins) / count($attended)) ?>% of matches played<?php endif ?></span>
            </div>
            <div class="stat-tile">
                <span class="label">Time with the crown</span>
                <span class="stat-value"><?= e(days($heldFor)) ?></span>
                <span class="stat-note"><?= $allHeld ? round(100 * $heldFor / $allHeld) : 0 ?>% of the time</span>
            </div>
            <div class="stat-tile">
                <span class="label">Most wins in a row</span>
                <span class="stat-value"><?= $streak ? $streak['wins'] : 0 ?></span>
                <span class="stat-note">
                    <?php if ($streak): ?>
                        <?= e($streak['wins'] > 1 ? date('j M Y', $streak['from']) . ' to ' . date('j M Y', $streak['last']) : date('j M Y', $streak['from'])) ?><?= $streak['ongoing'] ? ' (still going)' : '' ?>
                    <?php else: ?>
                        No wins yet
                    <?php endif ?>
                </span>
            </div>
            <div class="stat-tile">
                <span class="label">Days since the crown</span>
                <span class="stat-value"><?= match (true) { $daysSince === null => 'Never', $holderId === $id => 'Has it now', default => e(number_format($daysSince)) } ?></span>
                <span class="stat-note"><?= $daysSince ? 'since ' . e(date('j M Y', $lastHeldUntil)) : '' ?></span>
            </div>
            <div class="stat-tile">
                <span class="label">Attendance</span>
                <span class="stat-value"><?= count($attended) ?></span>
                <span class="stat-note">of <?= e($plural($total, 'match', 'matches')) ?> (<?= round(100 * count($attended) / $total) ?>%)</span>
            </div>
            <div class="stat-tile">
                <span class="label">Most won game</span>
                <span class="stat-value stat-game"><?= $topGames ? implode(', ', array_map('game_link', $topGames)) : 'None' ?></span>
                <span class="stat-note"><?= $topGames ? e($plural($topWins, 'win', 'wins')) : 'No wins at a recorded game' ?></span>
            </div>
        </div>

        <?php if ($wins): ?>
            <h2 class="label stats-title">Games won</h2>
            <?php
            $slices = array_map(fn($game, $n) => ['label' => game_label($game), 'game' => $game, 'value' => $n, 'colour' => $gameColour[$game]],
                array_keys($winsByGame), $winsByGame);
            if ($unknownWins) {
                $slices[] = ['label' => 'Unknown', 'game' => null, 'value' => $unknownWins, 'colour' => '#5d547f'];
            }
            ?>
            <div class="donut-wrap">
                <?php donut(array_map(fn($s) => $s + [
                    'tip' => $s['label'] . ': ' . $plural($s['value'], 'win', 'wins') . ' (' . round(100 * $s['value'] / count($wins)) . '%)',
                ], $slices), 'Share of ' . $person['name'] . "'s wins at each game") ?>
                <div class="legend">
                    <?php foreach ($slices as $s): ?>
                        <div class="legend-row">
                            <span class="swatch" style="background: <?= $s['colour'] ?>"></span>
                            <span class="who"><?= $s['game'] ? game_link($s['game']) : 'Unknown' ?></span>
                            <span class="value"><?= e($plural($s['value'], 'win', 'wins')) ?> · <?= round(100 * $s['value'] / count($wins)) ?>%</span>
                        </div>
                    <?php endforeach ?>
                </div>
            </div>
        <?php endif ?>

        <?php if ($picks): ?>
            <h2 class="label stats-title">Games picked</h2>
            <?php
            $pickSlices = array_map(fn($game, $n) => ['label' => game_label($game), 'game' => $game, 'value' => $n, 'colour' => $gameColour[$game]],
                array_keys($picksByGame), $picksByGame);
            if ($unknownPicks) {
                $pickSlices[] = ['label' => 'Unknown', 'game' => null, 'value' => $unknownPicks, 'colour' => '#5d547f'];
            }
            ?>
            <div class="donut-wrap">
                <?php donut(array_map(fn($s) => $s + [
                    'tip' => $s['label'] . ': ' . $plural($s['value'], 'pick', 'picks') . ' (' . round(100 * $s['value'] / count($picks)) . '%)',
                ], $pickSlices), 'Share of ' . $person['name'] . "'s picks at each game") ?>
                <div class="legend">
                    <?php foreach ($pickSlices as $s): ?>
                        <div class="legend-row">
                            <span class="swatch" style="background: <?= $s['colour'] ?>"></span>
                            <span class="who"><?= $s['game'] ? game_link($s['game']) : 'Unknown' ?></span>
                            <span class="value"><?= e($plural($s['value'], 'pick', 'picks')) ?> · <?= round(100 * $s['value'] / count($picks)) ?>%</span>
                        </div>
                    <?php endforeach ?>
                </div>
            </div>
        <?php endif ?>

        <?php if ($playedByGame): ?>
            <h2 class="label stats-title">Games played</h2>
            <div class="bars" aria-label="Number of matches <?= e($person['name']) ?> played at each game">
                <?php foreach ($playedByGame as $game => $n): ?>
                    <div class="bar-row" data-tip="<?= e(game_label($game) . ': played ' . $plural($n, 'time', 'times') . ', won ' . ($winsByGame[$game] ?? 0)) ?>">
                        <span class="bar-label"><?= game_link($game) ?></span>
                        <span class="bar-track"><span class="bar" style="width: <?= round(100 * $n / $maxPlayed, 1) ?>%; background: <?= $gameColour[$game] ?? 'var(--gold)' ?>"></span></span>
                        <span class="bar-value"><?= $n ?></span>
                    </div>
                <?php endforeach ?>
            </div>

            <h2 class="label stats-title">Days since game last played</h2>
            <div class="bars since-crown" aria-label="Days since <?= e($person['name']) ?> last played each game">
                <?php foreach ($lastPlayed as $game => $at): ?>
                    <?php $d = (int) floor(($now - $at) / 86400) ?>
                    <div class="bar-row" data-tip="<?= e(game_label($game) . ': last played ' . date('j M Y', $at)) ?>">
                        <span class="bar-label"><?= game_link($game) ?></span>
                        <span class="bar-track">
                            <?php if ($d): ?><span class="bar" style="width: <?= round(100 * $d / $maxSincePlayed, 1) ?>%; background: <?= $gameColour[$game] ?? 'var(--gold)' ?>"></span><?php endif ?>
                        </span>
                        <span class="bar-value"><?= $d ? e(days($d * 86400)) : 'Today' ?></span>
                    </div>
                <?php endforeach ?>
            </div>
        <?php endif ?>
    <?php endif ?>
    <a href="./" class="show-all">← Back to the crown</a>
</main>
<?php chart_tooltip() ?>
</body>
</html>
