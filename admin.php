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
    } elseif (!empty($_SESSION['admin']) && isset($_POST['icon_for'], $_POST['icon'])) {
        $id = (string) $_POST['icon_for'];
        try {
            // Check both before saving either, so a bad colour doesn't half-save the row.
            clean_icon((string) $_POST['icon']);
            if (isset($_POST['colour'])) {
                clean_colour((string) $_POST['colour']);
            }
            set_icon($id, (string) $_POST['icon']);
            if (isset($_POST['colour'])) {
                set_colour($id, (string) $_POST['colour']);
            }
            $message = people()[$id]['name'] . "'s details saved.";
        } catch (InvalidArgumentException $ex) {
            $error = $ex->getMessage();
        }
    } elseif (!empty($_SESSION['admin']) && isset($_POST['add_location'])) {
        try {
            $message = add_location((string) $_POST['add_location']) . ' added.';
        } catch (InvalidArgumentException $ex) {
            $error = $ex->getMessage();
        }
    } elseif (!empty($_SESSION['admin']) && isset($_POST['rename_location_from'], $_POST['rename_location_to'])) {
        $from = (string) $_POST['rename_location_from'];
        try {
            $to = rename_location($from, (string) $_POST['rename_location_to']);
            $message = $to === $from ? 'No change.' : "$from renamed to $to.";
        } catch (InvalidArgumentException $ex) {
            $error = $ex->getMessage();
        }
    } elseif (!empty($_SESSION['admin']) && isset($_POST['default_location'])) {
        try {
            set_default_location((string) $_POST['default_location']);
            $message = 'New matches will default to ' . $_POST['default_location'] . '.';
        } catch (InvalidArgumentException $ex) {
            $error = $ex->getMessage();
        }
    } elseif (!empty($_SESSION['admin']) && isset($_POST['delete_location'])) {
        try {
            delete_location((string) $_POST['delete_location']);
            $message = $_POST['delete_location'] . ' removed from the list.';
        } catch (InvalidArgumentException $ex) {
            $error = $ex->getMessage();
        }
    } elseif (!empty($_SESSION['admin']) && isset($_POST['add_game'])) {
        try {
            $message = add_game((string) $_POST['add_game']) . ' added.';
        } catch (InvalidArgumentException $ex) {
            $error = $ex->getMessage();
        }
    } elseif (!empty($_SESSION['admin']) && isset($_POST['rename_from'], $_POST['rename_to'])) {
        $from = (string) $_POST['rename_from'];
        try {
            // Check the icon first, so a bad one doesn't leave the game renamed without it.
            $icon = trim((string) ($_POST['game_icon'] ?? ''));
            if ($icon !== '') {
                clean_icon($icon);
            }
            $iconChanged = isset($_POST['game_icon']) && $icon !== (game_icons()[$from] ?? '');
            $to = rename_game($from, (string) $_POST['rename_to']);
            if (isset($_POST['game_icon'])) {
                set_game_icon($to, $icon);
            }
            $message = match (true) {
                $to !== $from => "$from renamed to $to.",
                $iconChanged => $icon === '' ? "$to's icon removed." : "$to's icon is now $icon.",
                default => 'No change.',
            };
        } catch (InvalidArgumentException $ex) {
            $error = $ex->getMessage();
        }
    } elseif (!empty($_SESSION['admin']) && isset($_POST['delete_game'])) {
        try {
            delete_game((string) $_POST['delete_game']);
            $message = $_POST['delete_game'] . ' removed from the list.';
        } catch (InvalidArgumentException $ex) {
            $error = $ex->getMessage();
        }
    } elseif (!empty($_SESSION['admin']) && isset($_POST['add_entry'])) {
        $winner = (string) ($_POST['winner'] ?? '');
        try {
            $at = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', (string) ($_POST['at'] ?? ''));
            if (!$at) {
                throw new InvalidArgumentException('Enter when the match was played.');
            }
            $game = (string) ($_POST['game'] ?? '');
            $players = array_map('strval', (array) ($_POST['players'] ?? []));
            $location = (string) ($_POST['location'] ?? '');
            if (isset($_POST['edit_at'], $_POST['edit_to'])) {
                update_history_entry((string) $_POST['edit_at'], (string) $_POST['edit_to'], $at, $winner, $game, $players, $location);
                header('Location: admin.php?edited');
            } elseif (isset($_POST['edit_future'])) {
                update_future_match((string) $_POST['edit_future'], $at, $game, $players, $location);
                header('Location: admin.php?edited=future');
            } elseif ($at->getTimestamp() > time()) {
                // A date still to come arranges a match; it has no winner and stays out of the history.
                add_future_match($at, $game, $players, $location);
                header('Location: admin.php?added=future');
            } else {
                add_history_entry($at, $winner, $game, $players, $location);
                header('Location: admin.php?added');
            }
            exit;
        } catch (InvalidArgumentException $ex) {
            $error = $ex->getMessage();
        }
    } elseif (!empty($_SESSION['admin']) && isset($_POST['delete_at'], $_POST['delete_to'])) {
        if (delete_history_entry((string) $_POST['delete_at'], (string) $_POST['delete_to'])) {
            $message = 'History entry deleted.';
        } else {
            $error = 'That history entry no longer exists.';
        }
    } elseif (!empty($_SESSION['admin']) && isset($_POST['delete_future'])) {
        if (delete_future_match((string) $_POST['delete_future'])) {
            $message = 'Future match deleted.';
        } else {
            $error = 'That future match no longer exists.';
        }
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
                set_holder($id, (string) ($_POST['game'] ?? ''), array_map('strval', (array) ($_POST['players'] ?? [])), (string) ($_POST['location'] ?? ''));
                $message = 'Crown passed to ' . people()[$id]['name'] . '.';
            } catch (InvalidArgumentException $ex) {
                $error = $ex->getMessage();
                $pending = $id;
            }
        }
    }
}

if (isset($_GET['added']) && !$error) {
    $message = $_GET['added'] === 'future' ? 'Future match added.' : 'History entry added.';
}
if (isset($_GET['edited']) && !$error) {
    $message = $_GET['edited'] === 'future' ? 'Future match updated.' : 'History entry updated.';
}

// The history entry or future match being edited with the add form, if any.
$editing = null;
if (isset($_GET['edit'], $_GET['to'])) {
    $editing = find_history_entry((string) $_GET['edit'], (string) $_GET['to']);
} elseif (isset($_GET['edit_future'])) {
    $editing = find_future_match((string) $_GET['edit_future']);
}
if ((isset($_GET['edit']) || isset($_GET['edit_future'])) && !$editing && !$error) {
    $error = 'That entry no longer exists.';
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
        <?php if (isset($_GET['locations'])): ?>
            <h2 class="label stats-title">Locations</h2>
            <form method="post" class="game-add">
                <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                <input name="add_location" placeholder="New location" maxlength="60" required aria-label="New location name">
                <button type="submit">Add</button>
            </form>
            <?php $counts = location_counts() ?>
            <div class="game-list">
                <?php foreach (locations() as $place): ?>
                    <?php $played = $counts[$place] ?? 0 ?>
                    <div class="game-row location-row">
                        <form method="post" class="game-rename">
                            <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                            <input type="hidden" name="rename_location_from" value="<?= e($place) ?>">
                            <input name="rename_location_to" value="<?= e($place) ?>" maxlength="60" required aria-label="Name for <?= e($place) ?>">
                            <button type="submit">Save</button>
                        </form>
                        <span class="game-played"><?= $played ?>×</span>
                        <?php if ($place === default_location()): ?>
                            <span class="default-badge">Default</span>
                        <?php else: ?>
                            <form method="post">
                                <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                                <input type="hidden" name="default_location" value="<?= e($place) ?>">
                                <button type="submit" class="link default-button">Make default</button>
                            </form>
                        <?php endif ?>
                        <form method="post" class="game-delete"
                              data-confirm="Are you sure you want to remove <?= e($place) ?> from the list?<?= $played ? ' Its ' . $played . ' past ' . ($played === 1 ? 'match stays' : 'matches stay') . ' in the history.' : '' ?>">
                            <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                            <input type="hidden" name="delete_location" value="<?= e($place) ?>">
                            <button type="submit" class="link">Delete</button>
                        </form>
                    </div>
                <?php endforeach ?>
            </div>
            <p class="since game-note">Renaming a location also renames it in the history. × is how many recorded matches were played there. The default is preselected for new matches.</p>
            <a href="admin.php" class="show-all">← Back to admin</a>
            <script>
                document.querySelectorAll('.game-delete').forEach(form => {
                    form.addEventListener('submit', ev => {
                        if (!confirm(form.dataset.confirm)) ev.preventDefault();
                    });
                });
            </script>
        <?php elseif (isset($_GET['icons'])): ?>
            <h2 class="label stats-title">Player details</h2>
            <div class="game-list">
                <?php foreach (people() as $id => $p): ?>
                    <form method="post" class="icon-row">
                        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                        <input type="hidden" name="icon_for" value="<?= e($id) ?>">
                        <span class="avatar"<?= avatar_style($p) ?>><?= e($p['icon']) ?></span>
                        <span class="icon-name"><?= e($p['name']) ?></span>
                        <input name="icon" value="<?= e($p['icon']) ?>" required aria-label="Icon for <?= e($p['name']) ?>">
                        <input type="color" name="colour" value="<?= e($p['colour'] ?? DEFAULT_AVATAR_COLOUR) ?>"
                               aria-label="Background colour for <?= e($p['name']) ?>">
                        <button type="submit">Save</button>
                        <button type="button" class="link colour-reset">Default colour</button>
                    </form>
                <?php endforeach ?>
            </div>
            <p class="since game-note">Type or paste an emoji (on a phone, switch to the emoji keyboard), or a couple of letters. Pick a background colour for the avatar, then Save.</p>
            <a href="admin.php" class="show-all">← Back to admin</a>
            <script>
                // Preview the colour on the avatar before saving.
                document.querySelectorAll('.icon-row').forEach(row => {
                    const avatar = row.querySelector('.avatar');
                    const colour = row.querySelector('input[type="color"]');
                    colour.addEventListener('input', () => { avatar.style.background = colour.value; });
                    row.querySelector('.colour-reset').addEventListener('click', () => {
                        colour.value = <?= json_encode(DEFAULT_AVATAR_COLOUR) ?>;
                        avatar.style.background = '';
                    });
                });
            </script>
        <?php elseif (isset($_GET['games'])): ?>
            <h2 class="label stats-title">Games</h2>
            <form method="post" class="game-add">
                <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                <input name="add_game" placeholder="New game" maxlength="60" required aria-label="New game name">
                <button type="submit">Add</button>
            </form>
            <?php $counts = game_counts() ?>
            <div class="game-list">
                <?php foreach (games() as $game): ?>
                    <?php $played = $counts[$game] ?? 0 ?>
                    <div class="game-row">
                        <form method="post" class="game-rename">
                            <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                            <input type="hidden" name="rename_from" value="<?= e($game) ?>">
                            <input name="game_icon" class="game-icon" value="<?= e(game_icons()[$game] ?? '') ?>" placeholder="🎲"
                                   aria-label="Icon for <?= e($game) ?>">
                            <input name="rename_to" value="<?= e($game) ?>" maxlength="60" required aria-label="Name for <?= e($game) ?>">
                            <button type="submit">Save</button>
                        </form>
                        <span class="game-played"><?= $played ?>×</span>
                        <form method="post" class="game-delete"
                              data-confirm="Are you sure you want to remove <?= e($game) ?> from the list?<?= $played ? ' Its ' . $played . ' past ' . ($played === 1 ? 'match stays' : 'matches stay') . ' in the history.' : '' ?>">
                            <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                            <input type="hidden" name="delete_game" value="<?= e($game) ?>">
                            <button type="submit" class="link">Delete</button>
                        </form>
                    </div>
                <?php endforeach ?>
            </div>
            <p class="since game-note">The first box is the game's icon: an emoji, or leave it empty for none. Renaming a game also renames it in the history. × is how many recorded matches it has.</p>
            <a href="admin.php" class="show-all">← Back to admin</a>
            <script>
                document.querySelectorAll('.game-delete').forEach(form => {
                    form.addEventListener('submit', ev => {
                        if (!confirm(form.dataset.confirm)) ev.preventDefault();
                    });
                });
            </script>
        <?php elseif (isset($_GET['add']) || $editing): ?>
            <?php
            // Keep the admin's choices after a validation error; otherwise start from the entry being
            // edited, or with everyone playing for a new match.
            $isFuture = isset($_GET['edit_future']);
            // From the Future matches list, start a week ahead at the usual 7:45pm so the form opens for a future match.
            $chosenAt = $_POST['at'] ?? ($editing
                ? date('Y-m-d\TH:i', strtotime($editing['at']))
                : date('Y-m-d\TH:i', isset($_GET['future']) ? strtotime('+1 week 19:45') : time()));
            $chosenWinner = $_POST['winner'] ?? ($editing['to'] ?? null);
            $chosenGame = $_POST['game'] ?? ($editing['game'] ?? null);
            $chosenPlayers = isset($_POST['add_entry'])
                ? (array) ($_POST['players'] ?? [])
                : ($editing ? array_merge($isFuture ? [] : [$editing['to']], $editing['players'] ?? []) : array_keys(people()));
            ?>
            <form method="post" class="confirm add-entry">
                <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                <?php if ($editing && $isFuture): ?>
                    <input type="hidden" name="edit_future" value="<?= e($editing['at']) ?>">
                <?php elseif ($editing): ?>
                    <input type="hidden" name="edit_at" value="<?= e($editing['at']) ?>">
                    <input type="hidden" name="edit_to" value="<?= e($editing['to']) ?>">
                <?php endif ?>
                <h2 class="label"><?= $editing ? 'Edit match' : 'Add a match' ?></h2>

                <fieldset>
                    <legend class="label">When</legend>
                    <input type="datetime-local" name="at" value="<?= e($chosenAt) ?>" required>
                    <p class="since future-note" hidden>A future date arranges the match: it shows as the next match, not in the history or stats.</p>
                </fieldset>

                <?php location_picker($_POST['location'] ?? ($editing ? entry_location($editing) : null)) ?>

                <fieldset class="winner-field">
                    <legend class="label">Who won</legend>
                    <div class="chips">
                        <?php foreach (people() as $id => $p): ?>
                            <label>
                                <input type="radio" name="winner" value="<?= e($id) ?>" required
                                    <?= $id === $chosenWinner ? 'checked' : '' ?>>
                                <span><?= e($p['icon']) ?> <?= e($p['name']) ?></span>
                            </label>
                        <?php endforeach ?>
                    </div>
                </fieldset>

                <fieldset>
                    <legend class="label" data-past="Game won" data-future="Game">Game won</legend>
                    <div class="chips">
                        <label>
                            <input type="radio" name="game" value="" <?= in_array($chosenGame, [null, ''], true) ? 'checked' : '' ?>>
                            <span data-past="Unknown" data-future="Not decided">Unknown</span>
                        </label>
                        <?php foreach (games() as $game): ?>
                            <label>
                                <input type="radio" name="game" value="<?= e($game) ?>"
                                    <?= $game === $chosenGame ? 'checked' : '' ?>>
                                <span><?= e(game_label($game)) ?></span>
                            </label>
                        <?php endforeach ?>
                    </div>
                </fieldset>

                <fieldset>
                    <legend class="label" data-past="Who played" data-future="Who's playing">Who played</legend>
                    <div class="chips">
                        <?php foreach (people() as $id => $p): ?>
                            <label>
                                <input type="checkbox" name="players[]" value="<?= e($id) ?>"
                                    <?= in_array($id, $chosenPlayers, true) ? 'checked' : '' ?>>
                                <span><?= e($p['icon']) ?> <?= e($p['name']) ?></span>
                            </label>
                        <?php endforeach ?>
                    </div>
                </fieldset>

                <?php if ($editing): ?>
                    <button type="submit" name="add_entry" value="1" class="primary">Save changes</button>
                <?php else: ?>
                    <button type="submit" name="add_entry" value="1" class="primary" data-past="Add to history" data-future="Add future match">Add to history</button>
                <?php endif ?>
                <a href="admin.php" class="cancel">Cancel</a>
            </form>
            <script>
                // A future date has no winner yet, so hide that question and reword the rest.
                const addForm = document.querySelector('.add-entry');
                const updateForFuture = () => {
                    const future = new Date(addForm.at.value) > new Date();
                    addForm.querySelector('.winner-field').hidden = future;
                    addForm.querySelectorAll('input[name="winner"]').forEach(r => { r.required = !future; });
                    addForm.querySelector('.future-note').hidden = !future;
                    addForm.querySelectorAll('[data-future]').forEach(el => {
                        el.textContent = future ? el.dataset.future : el.dataset.past;
                    });
                };
                addForm.at.addEventListener('input', updateForFuture);
                updateForFuture();
            </script>
        <?php elseif ($pending): ?>
            <?php
            // Keep the admin's choices after a validation error; otherwise start from today's future match
            // if one was arranged, or else assume the old holder played.
            $today = array_values(array_filter(future_matches(), fn($m) => substr($m['at'], 0, 10) === date('Y-m-d')))[0] ?? null;
            $chosenGame = $_POST['game'] ?? ($today['game'] ?? null);
            $chosenPlayers = isset($_POST['confirm'])
                ? (array) ($_POST['players'] ?? [])
                : (($today['players'] ?? []) ?: array_filter([$holder['id']]));
            $chosenLocation = $_POST['location'] ?? ($today['location'] ?? null);
            ?>
            <form method="post" class="confirm">
                <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                <input type="hidden" name="holder" value="<?= e($pending) ?>">
                <p class="label">Crown goes to</p>
                <span class="avatar"<?= avatar_style(people()[$pending]) ?>><?= e(people()[$pending]['icon']) ?></span>
                <h2><?= e(people()[$pending]['name']) ?></h2>

                <?php location_picker($chosenLocation) ?>

                <fieldset>
                    <legend class="label">Game won</legend>
                    <div class="chips">
                        <?php foreach (games() as $game): ?>
                            <label>
                                <input type="radio" name="game" value="<?= e($game) ?>" required
                                    <?= $game === $chosenGame ? 'checked' : '' ?>>
                                <span><?= e(game_label($game)) ?></span>
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
                    <span class="avatar"<?= avatar_style($p) ?>><?= e($p['icon']) ?></span>
                    <span class="name"><?= e($p['name']) ?></span>
                </button>
            <?php endforeach ?>
        </form>
        <nav class="home-links">
            <a href="admin.php?games" class="show-all">Manage games</a>
            <a href="admin.php?locations" class="show-all">Locations</a>
            <a href="admin.php?icons" class="show-all">Player details</a>
        </nav>
        <h2 class="label history-title">Future matches</h2>
        <a href="admin.php?add&amp;future" class="show-all add-entry-link">+ Add an entry</a>
        <?php future_list(future_matches(), $_SESSION['csrf']) ?>
        <h2 class="label history-title">History</h2>
        <a href="admin.php?add" class="show-all add-entry-link">+ Add an entry</a>
        <?php
        // Show the latest changes unless ?all is set; fetch one extra to know if there are more.
        $historyLimit = 50;
        $showAll = isset($_GET['all']);
        $history = history($showAll ? null : $historyLimit + 1);
        $hasMore = !$showAll && count($history) > $historyLimit;
        $history = $hasMore ? array_slice($history, 0, $historyLimit) : $history;
        ?>
        <?php history_list($history, $_SESSION['csrf']) ?>
        <?php if ($hasMore): ?>
            <a href="admin.php?all" class="show-all">Show all</a>
        <?php elseif ($showAll): ?>
            <a href="admin.php" class="show-all">Show latest only</a>
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
