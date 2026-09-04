# Sue v Lee

A shared head-to-head scoreboard for Rummikub, cribbage, backgammon and whatever else gets played. Laravel + Livewire, installable on both phones as an app, no accounts.

## The one idea worth knowing

**Every game stores which direction wins.** Rummikub points are penalties for the tiles left in your hand, so the lowest score wins and the winner is usually on 0. Cribbage and backgammon are the other way round. That direction is a column on `game_types` (`win_direction`: `low` or `high`), and every winner calculation in the app goes through `GameType::winningPlayerIds()`.

Nothing anywhere else compares two scores. If a game ever shows the wrong winner, there is exactly one method to look at.

## Running it locally

```bash
composer install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan serve
```

Then open `http://localhost:8000/<BOARD_KEY>`.

## Environment

| Variable | What it does |
|---|---|
| `BOARD_KEY` | **The address of your scoreboard.** The app lives at `/{BOARD_KEY}` and nowhere else - the bare domain returns a 404. Make it long and random. |
| `BOARD_NAME` | Title shown at the top and on the home screen icon. Defaults to "Sue v Lee". |
| `BOARD_PIN` | Optional. Leave it unset for no PIN at all. Set it to a few digits and each phone is asked once, then remembered for a year. |
| `APP_KEY` | Standard Laravel key. Generate with `php artisan key:generate --show`. |
| `DB_DIR` | Where the SQLite file lives in production. Defaults to `/data`, which is where the Railway volume should be mounted. |

## Security, honestly

There is no login. Access is "you know the URL", optionally plus a shared PIN. For a two-person household scoreboard that is the right trade - accounts would mean password resets and being logged out mid-game for no real gain. The consequence is that anyone you send the URL to can add and delete games, so treat the link the way you'd treat a shared photo album link.

`BOARD_KEY` is compared with `hash_equals`, so it can't be teased out by timing, and a wrong key is a plain 404 rather than "wrong key" - there's nothing to tell an unwanted visitor they're close.

## Deploying to Railway

1. Push this to a Git repo and create a Railway service from it. It builds from the `Dockerfile` (FrankenPHP - one process, no nginx/php-fpm pair).
2. **Add a volume mounted at `/data`.** This is not optional. SQLite lives there; without it every deploy starts from an empty scoreboard. The container refuses to boot if `/data` is missing, so you'll find out immediately rather than after losing a month of games.
3. Set the variables:

   ```
   APP_KEY=base64:...        # php artisan key:generate --show
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://your-app.up.railway.app
   BOARD_KEY=a-long-random-string
   BOARD_NAME=Sue v Lee
   ```

4. Deploy. Migrations and the seeder run on boot; the seeder is idempotent, so redeploys don't duplicate anything.
5. Open `https://your-app.up.railway.app/<BOARD_KEY>` on both phones and use "Add to Home Screen". It installs as a standalone app - no browser bar, own icon.

Session cookies are marked secure in production, so it must be served over HTTPS. Railway does that by default.

### Behind a TLS-terminating proxy

Railway (like most hosts) terminates TLS at its edge and forwards plain HTTP to the container. Laravel builds URLs from the scheme it can see, so without trusting that proxy every generated URL comes out `http://` on an `https://` page. The browser then blocks the stylesheet as mixed content, and - less obviously - the PWA manifest's `start_url` and the service worker registration are `http://` too, so the app will not install to a home screen.

The fix is one line in `bootstrap/app.php`:

```php
$middleware->trustProxies(at: '*');
```

Laravel then reads `X-Forwarded-Proto` and gets the scheme right for assets, the manifest, redirects and secure cookies. `URL::forceScheme('https')` would fix the stylesheet and quietly leave the rest wrong.

Trusting `*` is safe here because the container is only reachable through Railway's proxy. On a host where that is not true, name the proxy addresses instead.

`tests/Feature/HttpsBehindProxyTest.php` guards this: it asserts no `http://` appears in the page and that the manifest's `start_url` is `https://`. Both tests fail if the line above is removed.

## Adding to home screen

- **Android/Chrome:** menu, then "Add to Home screen" or "Install app".
- **iPhone/Safari:** share button, then "Add to Home Screen".

First open on each phone asks "who's on this phone?" - one tap, remembered in a cookie for a year. That's what makes it say "You win" instead of "Lee wins", and it's changeable any time from the line under the title.

## Adding a game

The `+` at the end of the game tabs. Name it, say whether it's won by the highest or lowest score, and name the unit ("points", "strokes", "legs"). That's the whole setup.

## Tests

```bash
php artisan test
```

The suite is mostly about the thing most likely to go quietly wrong - which score wins - including zero beating a positive score, negative scores, draws in both directions, and the four Rummikub games carried over from the old spreadsheet coming out as Lee 3, Sue 1.

## Data model

```
players        Sue, Lee (add more rows; the UI handles any number)
game_types     name, emoji, win_direction (low|high), unit_label
results        game_type_id, played_on
result_scores  result_id, player_id, points
```

Scores hang off a pivot rather than being `sue_points` / `lee_points` columns, so a third player is a row, not a migration and a rewrite.
