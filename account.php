<?php
require __DIR__ . '/lib.php';

start_login_session();
$id = logged_in();
if (!$id) {
    header('Location: login.php');
    exit;
}

$error = null;
$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        $error = 'Session expired, please try again.';
    } elseif (isset($_POST['icon'], $_POST['colour'])) {
        // Always the logged-in person's own avatar, whatever the form says.
        try {
            clean_icon((string) $_POST['icon']);
            clean_colour((string) $_POST['colour']);
            set_icon($id, (string) $_POST['icon']);
            set_colour($id, (string) $_POST['colour']);
            $message = 'Saved.';
        } catch (InvalidArgumentException $ex) {
            $error = $ex->getMessage();
        }
    } elseif (isset($_POST['current_password'], $_POST['new_password'])) {
        if (!check_login($id, (string) $_POST['current_password'])) {
            $error = 'Your current password is wrong.';
        } else {
            try {
                set_password($id, (string) $_POST['new_password']);
                $message = 'Password changed.';
            } catch (InvalidArgumentException $ex) {
                $error = $ex->getMessage();
            }
        }
    }
}

$person = people()[$id];
page_head('My Avatar');
?>
<main class="stage admin">
    <h1>Hi <?= e($person['name']) ?></h1>
    <?php if ($error): ?><p class="flash error"><?= e($error) ?></p><?php endif ?>
    <?php if ($message): ?><p class="flash ok"><?= e($message) ?></p><?php endif ?>

    <h2 class="label stats-title">Your avatar</h2>
    <form method="post" class="icon-row">
        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
        <span class="avatar"<?= avatar_style($person) ?>><?= e($person['icon']) ?></span>
        <span class="icon-name"><?= e($person['name']) ?></span>
        <input name="icon" value="<?= e($person['icon']) ?>" required aria-label="Your icon">
        <input type="color" name="colour" value="<?= e($person['colour'] ?? DEFAULT_AVATAR_COLOUR) ?>" aria-label="Your background colour">
        <button type="submit">Save</button>
        <button type="button" class="link colour-reset">Default colour</button>
    </form>
    <p class="since game-note">Type or paste an emoji (on a phone, switch to the emoji keyboard), or a couple of letters. Pick a background colour, then Save.</p>

    <?php if ($id !== superuser()): ?>
        <h2 class="label stats-title">Change password</h2>
        <form method="post" class="login">
            <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
            <label for="current_password">Current password</label>
            <input id="current_password" name="current_password" type="password" autocomplete="current-password" required>
            <label for="new_password">New password</label>
            <input id="new_password" name="new_password" type="password" autocomplete="new-password" minlength="<?= MIN_PASSWORD_LENGTH ?>" required>
            <button type="submit">Change password</button>
        </form>
    <?php else: ?>
        <a href="admin.php" class="show-all">Admin</a>
    <?php endif ?>

    <a href="player.php?id=<?= e(urlencode($id)) ?>" class="show-all">Your stats</a>
    <form method="post" action="login.php" class="logout">
        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
        <button type="submit" name="logout" value="1" class="link">Log out</button>
    </form>
</main>
<a class="admin-link" href="./">← Back to the crown</a>
<script>
    // Preview the colour on the avatar before saving.
    const row = document.querySelector('.icon-row');
    const avatar = row.querySelector('.avatar');
    const colour = row.querySelector('input[type="color"]');
    colour.addEventListener('input', () => { avatar.style.background = colour.value; });
    row.querySelector('.colour-reset').addEventListener('click', () => {
        colour.value = <?= json_encode(DEFAULT_AVATAR_COLOUR) ?>;
        avatar.style.background = '';
    });
</script>
</body>
</html>
