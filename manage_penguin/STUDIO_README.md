# Outfit Studio (Option A)

This adds a dedicated **Studio** page at:

- `.../manage_penguin/studio.php`

It keeps the normal site header, and the studio content renders like a small “web game”:

- Scene selector (currently **Winter** + 2 extra built-in gradients)
- Sticky stage with **Preview / Current / Compare** modes
- Slot tabs (Color / Head / Face / Neck / Body / Hand / Feet / Photo / Flag)
- Search + **pagination** (no endless scroll)
- Server-side ownership validation on save (prevents “equipping” items you don’t own)

## Files added

- `manage_penguin/studio.php`
- `manage_penguin/api/studio_items.php`
- `manage_penguin/api/studio_save.php`
- `manage_penguin/assets/studio/studio.css`
- `manage_penguin/assets/studio/studio.js`
- `manage_penguin/assets/studio/scenes/winter.jpg`

## Integration notes

- Uses existing DB tables:
  - `users` columns: `color, head, face, neck, body, hand, feet, photo, flag`
  - `inventories` columns: `userId, itemId`
- Uses Yukon crumbs metadata via `manage_penguin/includes/meta.php` (item slot/type + names when available).

## Scene selection

Scene choice is stored per-browser via `localStorage` key: `studio_scene`.
If you want it per-account, add a `users.studio_scene` column and read/write it in `studio.php` + `studio_save.php`.
