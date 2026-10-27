# Crown

Shows which of six people currently holds the crown. An admin page lets you pass it on.

- `index.php`: the public crown page. It checks for changes every 15 seconds.
- `admin.php`: password-protected page. Tap a person, then pick the game they won and who else played, to give them the crown.
- `history.php`: every change of crown holder, with the game and players.
- `stats.php`: how long each person has held the crown, and how often each game has been played.
- `api.php`: the current holder as JSON.
- `config.php`: the six people (name and icon), the list of games and the admin password.

## Setup

1. Edit `config.php`: set the names and icons, and **change `admin_password`**.
2. Upload the folder to any web host running PHP 8.0 or newer (Apache, so the `.htaccess` files apply).
3. Make sure the web server can write to `data/`.

## On Android

Open the site in Chrome, then use ⋮ → **Add to Home screen**. Do the same for `/admin.php` if you want a separate admin shortcut. The admin login lasts 30 days.
