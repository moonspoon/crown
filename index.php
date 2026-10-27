<?php
require __DIR__ . '/lib.php';

$holder = current_holder();
$people = people();
$current = $holder['id'] ? $people[$holder['id']] : null;

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

    <a href="history.php" class="show-all">Crown history</a>
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
