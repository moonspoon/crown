<?php
require __DIR__ . '/lib.php';

$holder = current_holder();
$people = people();
$current = $holder['id'] ? $people[$holder['id']] : null;
// The match that gave the current holder the crown, if it was recorded with a game.
$lastWin = history(1)[0] ?? null;
if (($lastWin['to'] ?? null) !== $holder['id'] || empty($lastWin['game'])) {
    $lastWin = null;
}

page_head('The Crown');
?>
<main class="stage">
    <p class="label">Current holder of the crown</p>
    <div class="holder" id="holder">
        <?php if ($current): ?>
            <div class="crown">👑</div>
            <div class="avatar big"><?= e($current['icon']) ?></div>
            <h1><?= e($current['name']) ?></h1>
            <p class="since" data-since="<?= e($holder['since']) ?>"></p>
            <?php if ($lastWin): ?>
                <p class="won">
                    Won at <strong><?= e($lastWin['game']) ?></strong>
                    <?php if (!empty($lastWin['players'])): ?>
                        <br>against <?= e(implode(', ', array_map('person_name', $lastWin['players']))) ?>
                    <?php endif ?>
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
                <span class="avatar"><?= e($p['icon']) ?></span>
                <span class="name"><?= e($p['name']) ?></span>
            </li>
        <?php endforeach ?>
    </ul>

    <nav class="home-links">
        <a href="history.php" class="show-all">Crown history</a>
        <a href="stats.php" class="show-all">Stats</a>
    </nav>
</main>
<a class="admin-link" href="admin.php">Admin</a>

<script>
const shownId = <?= json_encode($holder['id']) ?>;

const since = document.querySelector('.since');
if (since && since.dataset.since) {
    since.textContent = 'since ' + new Date(since.dataset.since).toLocaleString();
}

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
