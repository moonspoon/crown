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

page_head('Game Details');
?>
<main class="stage">
    <h1>Game details</h1>
    <?php if ($hasPrePandemic) pre_pandemic_toggle() ?>
    <?php if (!games()): ?>
        <p class="since">No games yet.</p>
    <?php else: ?>
        <ol class="history game-details">
            <?php foreach (games() as $game): ?>
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
    <?php endif ?>
</main>
<a class="admin-link" href="./">← Back to the crown</a>
</body>
</html>
