<?php
require __DIR__ . '/lib.php';

session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict', 'lifetime' => 60 * 60 * 24 * 30]);
session_start();
$_SESSION['csrf'] ??= bin2hex(random_bytes(16));

$error = null;
$message = null;
// Person tapped in the picker, waiting for the game and players to be filled in.
$pending = null;

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
        $id = (string) $_POST['holder'];
        if (!isset(people()[$id])) {
            $error = 'Unknown person.';
        } elseif (current_holder()['id'] === $id) {
            $message = people()[$id]['name'] . ' already has the crown.';
        } elseif (!isset($_POST['confirm'])) {
            $pending = $id;
        } else {
            try {
                set_holder($id, (string) ($_POST['game'] ?? ''), array_map('strval', (array) ($_POST['players'] ?? [])));
                $message = 'Crown passed to ' . people()[$id]['name'] . '.';
            } catch (InvalidArgumentException $ex) {
                $error = $ex->getMessage();
                $pending = $id;
            }
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
        <?php if ($pending): ?>
            <?php
            // Keep the admin's choices after a validation error; otherwise assume the old holder played.
            $chosenGame = $_POST['game'] ?? null;
            $chosenPlayers = isset($_POST['confirm']) ? (array) ($_POST['players'] ?? []) : array_filter([$holder['id']]);
            ?>
            <form method="post" class="confirm">
                <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                <input type="hidden" name="holder" value="<?= e($pending) ?>">
                <p class="label">Crown goes to</p>
                <span class="avatar"><?= e(people()[$pending]['icon']) ?></span>
                <h2><?= e(people()[$pending]['name']) ?></h2>

                <fieldset>
                    <legend class="label">Game won</legend>
                    <div class="chips">
                        <?php foreach (games() as $game): ?>
                            <label>
                                <input type="radio" name="game" value="<?= e($game) ?>" required
                                    <?= $game === $chosenGame ? 'checked' : '' ?>>
                                <span><?= e($game) ?></span>
                            </label>
                        <?php endforeach ?>
                    </div>
                </fieldset>

                <fieldset>
                    <legend class="label">Who else played</legend>
                    <div class="chips">
                        <?php foreach (people() as $id => $p): ?>
                            <?php if ($id === $pending) continue ?>
                            <label>
                                <input type="checkbox" name="players[]" value="<?= e($id) ?>"
                                    <?= in_array($id, $chosenPlayers, true) ? 'checked' : '' ?>>
                                <span><?= e($p['icon']) ?> <?= e($p['name']) ?></span>
                            </label>
                        <?php endforeach ?>
                    </div>
                </fieldset>

                <button type="submit" name="confirm" value="1" class="primary">Pass the crown</button>
                <a href="admin.php" class="cancel">Cancel</a>
            </form>
            <script>
                // The server also rejects this, but don't let the button be pressed until someone is ticked.
                const confirmForm = document.querySelector('.confirm');
                const passButton = confirmForm.querySelector('button.primary');
                const updatePassButton = () => {
                    passButton.disabled = !confirmForm.querySelector('input[name="players[]"]:checked');
                };
                confirmForm.addEventListener('change', updatePassButton);
                updatePassButton();
            </script>
        <?php else: ?>
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
        <h2 class="label history-title">History</h2>
        <?php
        // Show the latest changes unless ?all is set; fetch one extra to know if there are more.
        $historyLimit = 50;
        $showAll = isset($_GET['all']);
        $history = history($showAll ? null : $historyLimit + 1);
        $hasMore = !$showAll && count($history) > $historyLimit;
        $history = $hasMore ? array_slice($history, 0, $historyLimit) : $history;
        ?>
        <?php if (!$history): ?>
            <p class="since">No changes yet.</p>
        <?php else: ?>
            <ol class="history">
                <?php foreach ($history as $h): ?>
                    <li>
                        <div>
                            <span><?= e(person_name($h['from'])) ?> → <strong><?= e(person_name($h['to'])) ?></strong></span>
                            <?php if (!empty($h['game'])): ?>
                                <small>
                                    Won at <?= e($h['game']) ?>
                                    against <?= e(implode(', ', array_map('person_name', $h['players'] ?? []))) ?>
                                </small>
                            <?php endif ?>
                        </div>
                        <time datetime="<?= e($h['at']) ?>"><?= e($h['at']) ?></time>
                    </li>
                <?php endforeach ?>
            </ol>
            <?php if ($hasMore): ?>
                <a href="admin.php?all" class="show-all">Show all</a>
            <?php elseif ($showAll): ?>
                <a href="admin.php" class="show-all">Show latest only</a>
            <?php endif ?>
            <script>
                document.querySelectorAll('.history time').forEach(t => {
                    t.textContent = new Date(t.dateTime).toLocaleString();
                });
            </script>
        <?php endif ?>
        <?php endif ?>

        <form method="post" class="logout">
            <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
            <button type="submit" name="logout" value="1" class="link">Log out</button>
        </form>
    <?php endif ?>
</main>
<a class="admin-link" href="./">← Back to the crown</a>
</body>
</html>
