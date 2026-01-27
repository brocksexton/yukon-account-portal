# Yukon Administration Portal (v1)

This folder is a **standalone Administration Portal** that lives at:

- `/<your web root>/administration/`

It is **not merged into** `parent_center/`.

## Access control

The portal uses the **main site login session** (`$_SESSION['uid']`) and the `users.rank` field.

By default, users must have:

- `rank >= 3`

You can change this in:

- `administration/includes/admin_bootstrap.php` (constant `ADMIN_MIN_RANK`)

If a user is not logged in, they are redirected to `/login.php`.

## Dashboard (read-only v1)

`administration/dashboard.php` currently shows:

- Total Registered Users
- Active Bans (distinct union of `users.permaBan=1` + `bans.expires > NOW()`)
- Bans in the last 7 days
- New users in the last 7 days
- Most Recent Bans (latest 10): user, moderator, issued/expires, reason, status

## Next steps

If you want to expand this into a full admin suite, good next pages are:

- User search + profile drilldown
- Ban management (ban/unban/perma-ban) with CSRF and an audit log
- Charts (joins/bans per day last 30/90 days)
- Online population per world (from `worlds`)
