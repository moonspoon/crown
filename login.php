<?php
require __DIR__ . '/lib.php';

start_login_session();

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        $error = 'Session expired, please try again.';
    } elseif (isset($_POST['logout'])) {
        $_SESSION = [];
        session_destroy();
        header('Location: login.php');
        exit;
    } else {
        $id = (string) ($_POST['user'] ?? '');
        if (check_login($id, (string) ($_POST['password'] ?? ''))) {
            session_regenerate_id(true);
            $_SESSION['user'] = $id;
            header('Location: ' . home_page($id));
            exit;
        }
        $error = isset(config()['people'][$id]) && !has_password($id)
            ? "You don't have a password yet. Ask " . person_name(superuser()) . ' to set one.'
            : 'Wrong password.';
    }
} elseif ($user = logged_in()) {
    header('Location: ' . home_page($user));
    exit;
}

$chosen = $_POST['user'] ?? null;
page_head('Log In');
?>
<main class="stage admin">
    <h1>Log in</h1>
    <?php if ($error): ?><p class="flash error"><?= e($error) ?></p><?php endif ?>
    <form method="post" class="login">
        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
        <label for="user">Who are you?</label>
        <select id="user" name="user" autocomplete="username" required>
            <option value="" disabled <?= $chosen === null ? 'selected' : '' ?>>Pick your name</option>
            <?php foreach (people() as $id => $p): ?>
                <option value="<?= e($id) ?>" <?= $id === $chosen ? 'selected' : '' ?>><?= e($p['icon'] . ' ' . $p['name']) ?></option>
            <?php endforeach ?>
        </select>
        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
        <button type="submit">Log in</button>
    </form>
</main>
<a class="admin-link" href="./">← Back to the crown</a>
</body>
</html>
