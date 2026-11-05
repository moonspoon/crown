<?php
require __DIR__ . '/lib.php';

$counts = game_counts();
$ranges = game_players();

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

page_head('Game Details');
?>
<main class="stage">
    <h1>Game details</h1>
    <?php if (!games()): ?>
        <p class="since">No games yet.</p>
    <?php else: ?>
        <ol class="history game-details">
            <?php foreach (games() as $game): ?>
                <?php $played = $counts[$game] ?? 0 ?>
                <li>
                    <div>
                        <span><strong><?= e(game_label($game)) ?></strong></span>
                        <?php if ($players = players_text($ranges[$game] ?? null)): ?>
                            <small><?= e($players) ?></small>
                        <?php endif ?>
                    </div>
                    <span class="times-played"><?= $played ? 'Played ' . $played . ($played === 1 ? ' time' : ' times') : 'Not played yet' ?></span>
                </li>
            <?php endforeach ?>
        </ol>
    <?php endif ?>
</main>
<a class="admin-link" href="./">← Back to the crown</a>
</body>
</html>
