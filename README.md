# Crown

Shows which of six people currently holds the crown. An admin page lets you pass it on.

- `index.php`: the public crown page. It checks for changes every 15 seconds.
- `login.php`: everyone logs in here by picking their name. The superuser (`superuser` in `config.php`, using `admin_password` in `config.local.php`) goes to the admin page; everyone else goes to `account.php`.
- `account.php`: a logged-in player sets their own avatar icon and colour, and changes their password.
- `admin.php`: the superuser's page. Tap a person, then pick the game they won and who else played, to give them the crown. Also manages the games list, locations, player icons and history. Player details also sets each player's login password (stored hashed in `data/passwords.json`).
- `history.php`: every change of crown holder, with the game and players.
- `stats.php`: how long each person has held the crown, and how often each game has been played.
- `api.php`: the current holder as JSON.
- `config.php`: the six people (name and icon), and the starting list of games. Games are then managed from the admin page (stored in `data/games.json`).

## Setup

1. Edit `config.php`: set the names and icons.
2. Copy `config.local.example.php` to `config.local.php` and **set `admin_password`**. This file is in `.gitignore`, so it is never committed: upload it to the host separately.
3. Upload the folder to any web host running PHP 8.0 or newer (Apache, so the `.htaccess` files apply).
4. Make sure the web server can write to `data/`.

## On Android

Open the site in Chrome, then use ⋮ → **Add to Home screen**. Do the same for `/admin.php` if you want a separate admin shortcut. The admin login lasts 30 days.
