<?php
require __DIR__ . '/lib.php';

$holder = current_holder();
$people = people();
$current = $holder['id'] ? $people[$holder['id']] : null;
// The match that gave the current holder the crown, if it was recorded with a game or players.
$lastWin = history(1)[0] ?? null;
if (($lastWin['to'] ?? null) !== $holder['id'] || (empty($lastWin['game']) && empty($lastWin['players']))) {
    $lastWin = null;
}
$next = future_matches()[0] ?? null;
// Who's logged in, for the corner link; visitors who never logged in don't get a session started for them.
if (isset($_COOKIE[session_name()])) {
    start_login_session();
}
$me = logged_in();

page_head('The Crown');
?>
<main class="stage">
    <p class="label">Current holder of the crown</p>
    <div class="holder" id="holder">
        <?php if ($current): ?>
            <div class="crown">👑</div>
            <a class="player-link" href="player.php?id=<?= e(urlencode($holder['id'])) ?>">
                <div class="avatar big"<?= avatar_style($current) ?>><?= e($current['icon']) ?></div>
                <h1><?= e($current['name']) ?></h1>
            </a>
            <p class="since" data-since="<?= e($holder['since']) ?>"></p>
            <?php if ($lastWin): ?>
                <p class="won">
                    <?php if (!empty($lastWin['game'])): ?>
                        Won at <strong><?= game_link($lastWin['game']) ?></strong>
                        <?php if (!empty($lastWin['players'])): ?><br>against<?php endif ?>
                    <?php else: ?>
                        Won against
                    <?php endif ?>
                    <?= implode(', ', array_map(fn($id) => player_link($id, false), $lastWin['players'] ?? [])) ?>
                    <br>📍 <?= e(entry_location($lastWin)) ?>
                </p>
            <?php endif ?>
        <?php else: ?>
            <div class="avatar big empty">?</div>
            <h1>Nobody yet</h1>
        <?php endif ?>
    </div>

    <ul class="court">
        <?php foreach ($people as $id => $p): ?>
            <li class="<?= $id === $holder['id'] ? 'has-crown' : '' ?>">
                <a class="player-link" href="player.php?id=<?= e(urlencode($id)) ?>">
                    <span class="avatar"<?= avatar_style($p) ?>><?= e($p['icon']) ?></span>
                    <span class="name"><?= e($p['name']) ?></span>
                </a>
            </li>
        <?php endforeach ?>
    </ul>

    <?php if ($next): ?>
        <p class="won next-match">
            Next match <strong><time datetime="<?= e($next['at']) ?>"><?= e($next['at']) ?></time></strong>
            <?php if ($next['game']): ?><br>playing <strong><?= game_link($next['game']) ?></strong><?php endif ?>
            <?php if ($next['players']): ?><br>with <?= implode(', ', array_map(fn($id) => player_link($id, false), $next['players'])) ?><?php endif ?>
            <br>📍 <?= e($next['location']) ?>
        </p>
    <?php endif ?>

    <nav class="home-links">
        <a href="history.php" class="show-all">Crown history</a>
        <a href="future.php" class="show-all">Future matches</a>
        <a href="stats.php" class="show-all">Stats</a>
        <a href="games.php" class="show-all">Game details</a>
    </nav>
</main>
<?php if ($me): ?>
    <div class="admin-link signed-in">
        <a href="<?= e(home_page($me)) ?>">Logged in as <?= e(trim($people[$me]['icon'] . ' ' . $people[$me]['name'])) ?></a>
        <span aria-hidden="true">·</span>
        <form method="post" action="login.php">
            <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
            <button type="submit" name="logout" value="1" class="link">Log out</button>
        </form>
    </div>
<?php else: ?>
    <a class="admin-link" href="login.php">Log in</a>
<?php endif ?>

<script>
const shownId = <?= json_encode($holder['id']) ?>;

const since = document.querySelector('.since');
if (since && since.dataset.since) {
    since.textContent = 'since ' + new Date(since.dataset.since).toLocaleString();
}
document.querySelectorAll('.next-match time').forEach(t => { t.textContent = match_time(t.dateTime); });

// Pick up changes made from the admin page without a manual refresh.
async function poll() {
    try {
        const res = await fetch('api.php', {cache: 'no-store'});
        const data = await res.json();
        if (data.id !== shownId) location.reload();
    } catch (_) { /* offline: try again next tick */ }
}
setInterval(poll, 15000);
document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });
</script>
</body>
</html>
