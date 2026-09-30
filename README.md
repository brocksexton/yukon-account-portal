> [!CAUTION]
> **This was never meant to be a public instance.**
>
> This repository is a small personal project I built to try some ideas out conceptually. It never ran as a public website and it is not a finished product.
>
> - **The code** (PHP, JavaScript, SQL) is yours to use however you like, under the MIT License.
> - **The CSS, images and Club Penguin-style assets are not.** Please do not use them on the public web. The styling is deliberately made to look like Club Penguin, and Club Penguin's art, characters, logos and branding belong to Disney. See [NOTICE](NOTICE).

# Yukon Account Portal

A website and account portal for a private Club Penguin server running [wizguin/yukon-server](https://github.com/wizguin/yukon-server) and the [wizguin/yukon](https://github.com/wizguin/yukon) HTML5 client. I built it in January 2026 as a one-month learning project: plain PHP and MySQL/MariaDB, no framework, reading and writing the same database the game server uses.

**Live demo:** https://brocksexton.github.io/yukon-account-portal/

The demo is a static snapshot running on made-up data. Nothing you do there is saved, and a switch in the bottom-left corner flips between the signed-out and signed-in views. Signed in, you are `DemoParent`, a demo player who is also signed in to the Parent Center with five child accounts.

## Features

**Main site**
- Home page with a signed-out and a signed-in version (penguin card, coins, rank, news placeholders)
- Penguin login against the Yukon `users` table (bcrypt, banned accounts refused)
- Layered penguin avatars built from the Yukon paper-doll art, as HTML layers or an inline SVG (`avatar.php`)

**Manage My Penguin** (`/manage_penguin/`)
- Overview: rank, colour, inventory count, saved outfits and commission totals
- Inventory browser with search and slot filters, using item names from Yukon's `items.json`
- Outfit Studio: try items on a live stage, switch scenes, compare with the current look, equip (checked server-side for ownership and the right slot) and save looks to a library
- My Outfits: saved looks you can apply, rename, make public or delete
- Commissions: coins earned when other players buy items from your public outfits
- Security tab for changing the website password

**Parent Center** (`/parent_center/`)
- Separate parent login (email and password), created by verifying an existing, unbanned penguin
- Dashboard with linked children, total coins, and alerts for children who are permanently banned, currently banned (with the expiry time) or were banned in the last 30 days
- Each child's status shows in the list: banned rows are highlighted and badged
- Per-child pages: overview with playercard and equipped items, full inventory, puffles, ban history, friends (with mutual friends marked) and a chat-history placeholder
- Create a child penguin directly, or claim an existing one by verifying its login
- Special-role badges (PSA agent, Tour Guide) based on owned items

**Administration portal** (`/administration/`, rank 3 and up)
- Dashboard: registered users, active bans, recent sign-ups and bans, per-world population
- User search and a user page to add coins, grant items (single, presets or ranges), ban and unban
- Ban management for temporary and permanent bans
- Optional server page that runs yukon-server's npm scripts (off by default, see below)

**Also:** themed error and maintenance pages in `error-pages/`.

## Screenshots

All screenshots use the made-up demo data in `sql/demo_seed.sql`.

### Manage My Penguin

| Overview | Outfit Studio |
| --- | --- |
| ![Manage My Penguin overview](docs/screenshots/manage-overview.webp) | ![Outfit Studio](docs/screenshots/manage-studio.webp) |
| **Inventory** | **My Outfits** |
| ![Inventory](docs/screenshots/manage-inventory.webp) | ![My saved outfits](docs/screenshots/manage-my-outfits.webp) |
| **Commissions** | **Security** |
| ![Commission payouts](docs/screenshots/manage-commissions.webp) | ![Security tab](docs/screenshots/manage-security.webp) |

### Parent Center

The dashboard shows the parent's own penguin and five children, each in a different state: two banned rows, alerts at the top, and badges on the banned penguins.

![Parent Center dashboard](docs/screenshots/parent-dashboard.webp)

How each child state looks on the child's own page:

| Active, clean record (SnowySprout) | Temporarily banned (IcebergIvy) |
| --- | --- |
| ![Active child](docs/screenshots/child-active.webp) | ![Temporarily banned child](docs/screenshots/child-temp-banned.webp) |
| **Permanently banned (PebbleWaddle)** | **Recently banned, ban expired (FrostyFinn)** |
| ![Permanently banned child](docs/screenshots/child-perma-banned.webp) | ![Recently banned child](docs/screenshots/child-recently-banned.webp) |
| **Brand-new account (CocoaPuff)** | **Parent Center login** |
| ![New child account](docs/screenshots/child-new-account.webp) | ![Parent Center login](docs/screenshots/parent-login.webp) |

| Puffles | Inventory |
| --- | --- |
| ![Puffles](docs/screenshots/child-puffles.webp) | ![Child inventory](docs/screenshots/child-inventory.webp) |
| **Ban history** | **Friends** |
| ![Ban history](docs/screenshots/child-bans.webp) | ![Friends](docs/screenshots/child-friends.webp) |

### Main site, administration and mobile

| Home (signed out) | Home (signed in) |
| --- | --- |
| ![Home page](docs/screenshots/home.webp) | ![Home page, signed in](docs/screenshots/home-signed-in.webp) |
| **Admin dashboard** | **Admin user page** |
| ![Admin dashboard](docs/screenshots/admin-dashboard.webp) | ![Admin user page](docs/screenshots/admin-user.webp) |
| **Admin ban management** | **Parent Center: create a child account** |
| ![Admin ban management](docs/screenshots/admin-bans.webp) | ![Create child account](docs/screenshots/parent-create-child.webp) |

<p>
  <img src="docs/screenshots/mobile-home.webp" alt="Home page on a phone" width="260">
  <img src="docs/screenshots/mobile-parent-dashboard.webp" alt="Parent Center dashboard on a phone" width="260">
</p>

## How it fits with yukon-server and yukon

```
 players ──> yukon (HTML5 client) ──> yukon-server (Node.js) ──┐
                                                               ├──> MySQL / MariaDB (one database)
 players ──> this portal (PHP) ────────────────────────────────┘
               └─ shows art from the yukon client's assets/media folder
```

- **One shared database.** The portal connects to the database yukon-server uses. It reads Yukon's own tables (`users`, `inventories`, `pets`, `buddies`, `bans`, `worlds`) and adds four of its own (`parents`, `parent_children`, `user_outfits`, `outfit_commissions`; see `sql/portal_schema.sql`).
- **Same accounts.** Penguin passwords are the bcrypt hashes Yukon stores, checked with PHP's `password_verify()`. Accounts created in the Parent Center are ordinary Yukon users (Yukon's insert trigger gives them their igloo, colour and starter postcard).
- **Changes show up in game.** Equipping items in the Studio writes the `users` clothing columns; admin actions write `inventories`, `bans`, `users.coins` and `users.permaBan`. Players usually need to log in again to see them.
- **Art comes from the yukon client.** Avatars, item icons and puffle/playercard art are loaded from `/yukon/assets/media/...` (the client's `assets/media` folder), and item names and slots come from `yukon/assets/media/crumbs/en/items.json`. None of that media is in this repository.
- **The Play buttons** link to wherever you host the built yukon client (`PLAY_URL`).

## Setup

### Requirements
- PHP 8.0 or newer with the `mysqli` extension (developed on PHP 8.2)
- MySQL or MariaDB (the one yukon-server uses; developed on MariaDB 10.4)
- Apache with `mod_rewrite` (the included `.htaccess` is used), or PHP's built-in server for local development
- A working [yukon-server](https://github.com/wizguin/yukon-server) and [yukon](https://github.com/wizguin/yukon) client with its media assets (see their READMEs; the client's media is distributed separately from its repository)

### Steps

1. **Set up Yukon first.** Follow the yukon-server README (create the database from its `yukon.sql`) and the yukon README (install, merge the assets into `assets/`, build).
2. **Put this portal at the web root of its own host,** for example `portal.example.com` or a local `portal.test`. Many links are root-relative (`/login.php`, `/assets/...`), so it does not work from a sub-folder.
3. **Make the Yukon media reachable at `/yukon/assets/media/`.** The simplest way is a `yukon` folder (or symlink) in the web root that points at your yukon client checkout. The portal also reads `yukon/assets/media/crumbs/en/items.json` and the colour icons from disk. The `.htaccess` blocks everything under `/yukon` except `assets/media`, and blocks `/yukon-server` entirely; better still, keep yukon-server outside the web root.
4. **Add the portal's tables** to the Yukon database:
   ```sh
   mysql -u <user> -p <yukon_database> < sql/portal_schema.sql
   ```
5. **Configure it:**
   ```sh
   cp includes/config.example.php includes/config.php
   ```
   Then set the database credentials, `SITE_NAME`, `PLAY_URL` and the rest. `includes/config.php` is git-ignored.
6. **Make a staff account** if you want the administration portal: set `rank` to 3 or higher for your penguin in `users` (4 for "Owner", which also unlocks "Give all items").

### Running it locally

```sh
YUKON_CLIENT_DIR=/path/to/yukon php -S 127.0.0.1:8080 scripts/dev-router.php
```

`scripts/dev-router.php` stands in for the `.htaccess` rules and serves `/yukon/assets/media/` from `YUKON_CLIENT_DIR`. The Studio and inventory still read `./yukon/assets/media/crumbs/en/*.json` and `./yukon/assets/media/color/icon/120/` from disk, so keep a `yukon` folder (or symlink) in the project root as well.

### Demo data

To try it without real players, load the demo data into an **empty** database:

```sh
mysql -u root -p -e "CREATE DATABASE portal_demo CHARACTER SET utf8mb4"
mysql -u root -p portal_demo < yukon.sql            # from wizguin/yukon-server
mysql -u root -p portal_demo < sql/portal_schema.sql
mysql -u root -p portal_demo < sql/demo_seed.sql
```

The demo logins are listed at the top of `sql/demo_seed.sql`. Do not load it into a real server.

### Rebuilding the static demo

```sh
npm install                 # installs playwright-core, only needed for this
DEMO_ORIGIN=http://127.0.0.1:8080 npm run demo:static    # writes scripts/static-demo/out/
npm run demo:publish        # same, then commits it to the gh-pages branch and pushes
```

It needs the site running on the demo data (above) and Chrome or Edge installed. See the comments at the top of `scripts/static-demo/build.mjs`.

## What to change if you reuse it

- [ ] **Replace the look.** Swap `css/`, the `assets/`, `parent_center/assets/`, `manage_penguin/assets/` and `error-pages/assets/` images and the logo text for your own. The Club Penguin-style art and CSS are not licensed for public use (see the notice at the top).
- [ ] **Site name and copy.** `SITE_NAME`, `SITE_TAGLINE` and `PRIVATE_ACCESS_NOTICE` in `includes/config.php`, plus the invite-only wording in `index.php`, `login.php` and the Parent Center footer.
- [ ] **Database account.** Use a dedicated MySQL user with only the rights the portal needs, not `root`.
- [ ] **HTTPS and cookies.** Serve it over HTTPS and set `COOKIE_SECURE` to `true`.
- [ ] **Staff ranks.** Check `ADMIN_MIN_RANK` and the rank labels against your server's rank scheme.
- [ ] **Server controls.** Leave `ADMIN_ALLOW_SERVER_CONTROL` off unless the portal runs only on your own machine. Behind a reverse proxy every request can look like it comes from `127.0.0.1`.
- [ ] **Home page news.** `home_news_posts()` in `index.php` returns placeholder posts.
- [ ] **Login protection.** There is no rate limiting or lockout on the penguin or parent logins; add some before exposing it.
- [ ] **Review it.** This was a learning project. Read through it (particularly the admin actions and the Parent Center linking rules) before running it anywhere real.

## Known limitations

- **Public outfit store is unfinished.** `manage_penguin/pages/public_outfits.php`, `buy_outfit.php` and `api/outfit_purchase.php` were written against a different inventory layout (a comma-separated `inventories.items` column) and fail on a stock Yukon database. `manage_penguin/pages/outfit.php` is a leftover tab that is no longer wired up. None of these are in the demo.
- **Commissions are read-only.** The Overview and Commissions tabs read `outfit_commissions`, but nothing in this repository writes to it yet.
- **The Security tab's form does nothing yet.** `manage_penguin/index.php` does not handle the POST; the older `manage.php` page (where a penguin login lands) does change the password.
- **`manage.php` live refresh is broken.** Its script assigns through optional chaining, which is a syntax error, so the page shows data from the initial load only.
- **Chat History** in the Parent Center is a placeholder.
- **Parent labels** show on a child's page but not in the dashboard list (the list reads a `label` field; the column is `alias`).
- **No registration, email or 2FA.** The site assumes invite-only accounts; the Security tab lists those as planned.
- **Theme.** The site was first skinned with the compiled CSS and fonts from another private server's public website (Club Penguin Journey). Those files are not included. `css/theme.css` is a from-scratch stand-in and the Burbank Small and CCComicrazy fonts fall back to Nunito and Bangers from Google Fonts, so it looks close to, but not exactly like, the original.

## Credits and trademarks

- **Club Penguin** and all related characters, art, logos and names are trademarks and copyrights of Disney. This project is not affiliated with, endorsed by or connected to Disney or Club Penguin.
- **[Yukon](https://github.com/wizguin/yukon)** and **[yukon-server](https://github.com/wizguin/yukon-server)** are by wizguin. This portal is an independent add-on and is not affiliated with the Yukon project.
- The Outfit Studio's winter scene (`manage_penguin/assets/studio/scenes/`) is fan art signed "Sorian". Other images in this repository are Club Penguin art or edits of it.
- Loaded from CDNs at runtime: [Bootstrap](https://getbootstrap.com/) (MIT), [GSAP](https://gsap.com/), [vanilla-tilt](https://github.com/micku7zu/vanilla-tilt.js) (MIT), [SweetAlert2](https://sweetalert2.github.io/) (MIT), [Font Awesome Free](https://fontawesome.com/), and the [Nunito](https://fonts.google.com/specimen/Nunito) and [Bangers](https://fonts.google.com/specimen/Bangers) fonts (SIL Open Font License).

## License

The code in this repository is released under the [MIT License](LICENSE).

That license covers the code only. The CSS, images and other Club Penguin-style assets are **not** licensed for use on the public web and remain the property of their owners; see [NOTICE](NOTICE).
