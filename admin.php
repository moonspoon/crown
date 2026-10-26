<?php
require __DIR__ . '/lib.php';

session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict', 'lifetime' => 60 * 60 * 24 * 30]);
session_start();
$_SESSION['csrf'] ??= bin2hex(random_bytes(16));

$error = null;
$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        $error = 'Session expired, please try again.';
    } elseif (isset($_POST['password'])) {
        if (hash_equals(config()['admin_password'], (string) $_POST['password'])) {
            session_regenerate_id(true);
            $_SESSION['admin'] = true;
            header('Location: admin.php');
            exit;
        }
        $error = 'Wrong password.';
    } elseif (isset($_POST['logout'])) {
        $_SESSION = [];
        session_destroy();
        header('Location: admin.php');
        exit;
    } elseif (!empty($_SESSION['admin']) && isset($_POST['holder'])) {
        try {
            $alreadyHolder = current_holder()['id'] === $_POST['holder'];
            set_holder((string) $_POST['holder']);
            $name = people()[$_POST['holder']]['name'];
            $message = $alreadyHolder ? "$name already has the crown." : "Crown passed to $name.";
        } catch (InvalidArgumentException) {
            $error = 'Unknown person.';
        }
    }
}

$holder = current_holder();
page_head('Crown Admin');
?>
<main class="stage admin">
    <h1>Crown admin</h1>

    <?php if ($error): ?><p class="flash error"><?= e($error) ?></p><?php endif ?>
    <?php if ($message): ?><p class="flash ok"><?= e($message) ?></p><?php endif ?>

    <?php if (empty($_SESSION['admin'])): ?>
        <form method="post" class="login">
            <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required autofocus>
            <button type="submit">Log in</button>
        </form>
    <?php else: ?>
        <?php if (config()['admin_password'] === 'change-me'): ?>
            <p class="flash error">You're still using the default password. Change it in config.php.</p>
        <?php endif ?>
        <p class="label">Tap who has the crown</p>
        <form method="post" class="picker">
            <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
            <?php foreach (people() as $id => $p): ?>
                <button type="submit" name="holder" value="<?= e($id) ?>"
                        class="<?= $id === $holder['id'] ? 'has-crown' : '' ?>">
                    <span class="avatar"><?= e($p['icon']) ?></span>
                    <span class="name"><?= e($p['name']) ?></span>
                </button>
            <?php endforeach ?>
        </form>
        <form method="post" class="logout">
            <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
            <button type="submit" name="logout" value="1" class="link">Log out</button>
        </form>
    <?php endif ?>
</main>
<a class="admin-link" href="./">← Back to the crown</a>
</body>
</html>
