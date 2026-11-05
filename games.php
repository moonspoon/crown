<?php
require __DIR__ . '/lib.php';

// Times each game was played, leaving out pre-pandemic matches unless switched on.
$entries = log_entries();
$hasPrePandemic = (bool) array_filter($entries, 'is_pre_pandemic');
$counts = [];
$everPlayed = [];
foreach ($entries as $entry) {
    if (empty($entry['game'])) {
        continue;
    }
    $everPlayed[$entry['game']] = true;
    if (include_pre_pandemic() || !is_pre_pandemic($entry)) {
        $counts[$entry['game']] = ($counts[$entry['game']] ?? 0) + 1;
    }
}
$ranges = game_players();

// Optionally only the games that suit a number of players, going by each game's min and max.
// Games with neither set are left out, since there's no telling.
const PLAYER_FILTERS = [4, 5, 6];
$filter = in_array((int) ($_GET['players'] ?? 0), PLAYER_FILTERS, true) ? (int) $_GET['players'] : null;
$shown = array_values(array_filter(games(), function ($game) use ($filter, $ranges) {
    $range = $ranges[$game] ?? null;
    if ($filter === null) {
        return true;
    }
    if (!$range || ($range['min'] === null && $range['max'] === null)) {
        return false;
    }
    return ($range['min'] ?? 1) <= $filter && $filter <= ($range['max'] ?? PHP_INT_MAX);
}));
// The page's address with these query values changed, keeping the rest.
$url = fn(array $change) => 'games.php' . (($q = http_build_query(array_filter($change + ['players' => $filter, 'pre' => include_pre_pandemic() ? 1 : null]))) ? "?$q" : '');

page_head('Game Details');
?>
<main class="stage">
    <h1>Game details</h1>
    <?php if ($hasPrePandemic) pre_pandemic_toggle($filter ? ['players' => $filter] : []) ?>
    <nav class="filter-pills" aria-label="Filter by number of players">
        <a href="<?= e($url(['players' => null])) ?>" class="<?= $filter === null ? 'active' : '' ?>">All</a>
        <?php foreach (PLAYER_FILTERS as $n): ?>
            <a href="<?= e($url(['players' => $n])) ?>" class="<?= $filter === $n ? 'active' : '' ?>"><?= $n ?> players</a>
        <?php endforeach ?>
    </nav>
    <?php if (!games()): ?>
        <p class="since">No games yet.</p>
    <?php elseif (!$shown): ?>
        <p class="since">No games are set up for <?= $filter ?> players. Add each game's minimum and maximum players on the admin page.</p>
    <?php else: ?>
        <ol class="history game-details">
            <?php foreach ($shown as $game): ?>
                <?php $played = $counts[$game] ?? 0 ?>
                <li>
                    <div>
                        <strong><?= game_link($game) ?></strong>
                        <?php if ($players = players_text($ranges[$game] ?? null)): ?>
                            <small><?= e($players) ?></small>
                        <?php endif ?>
                    </div>
                    <span class="times-played"><?= $played ? 'Played ' . $played . ($played === 1 ? ' time' : ' times') : (isset($everPlayed[$game]) ? 'Not played since the pandemic' : 'Not played yet') ?></span>
                </li>
            <?php endforeach ?>
        </ol>
        <?php if ($filter): ?>
            <p class="since chart-note">Games without a minimum or maximum set aren't shown when filtering.</p>
        <?php endif ?>
    <?php endif ?>
</main>
<a class="admin-link" href="./">← Back to the crown</a>
</body>
</html>
