#!/usr/bin/env node
// Builds a static snapshot of the portal for GitHub Pages.
//
//   node scripts/static-demo/build.mjs              -> writes scripts/static-demo/out/
//   node scripts/static-demo/build.mjs --publish    -> also commits it to the gh-pages branch and pushes
//
// Before running it:
//   1. Load yukon.sql, sql/portal_schema.sql and sql/demo_seed.sql into an EMPTY database
//      and point includes/config.php at it.
//   2. Start the site with the Yukon media available, for example
//        YUKON_CLIENT_DIR=/path/to/yukon php -S 127.0.0.1:8080 scripts/dev-router.php
//   3. npm install playwright-core (anywhere Node can resolve it); Chrome or Edge must be installed.
//
// Environment: DEMO_ORIGIN (default http://127.0.0.1:8080), STATIC_DEMO_BASE (default
// /yukon-account-portal), CHROME_PATH (optional). The demo logins are read from the comments
// at the top of sql/demo_seed.sql.
//
// How it works: crawl the running site twice, signed out and signed in (penguin login plus
// Parent Center login), save each page's server HTML as a plain .html file next to where the
// .php file lives (so relative links keep working), save the JSON the pages fetch and the Yukon
// media they show, and rewrite links for the Pages sub-path. scripts/static-demo/shim.js is
// injected into every page to answer API requests and intercept forms offline.
import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
const OUT = path.join(ROOT, 'scripts', 'static-demo', 'out');
const ORIGIN = (process.env.DEMO_ORIGIN || 'http://127.0.0.1:8080').replace(/\/$/, '');
const BASE = process.env.STATIC_DEMO_BASE || '/yukon-account-portal';
const REPO_URL = 'https://github.com/brocksexton/yukon-account-portal';
const MAX_PAGES_PER_VIEW = 200;
const MAX_QUERY_VARIANTS_PER_PAGE = 8;

// Pages that are left out of the snapshot on purpose.
const SKIP = [
  /^\/(administration|error-pages|partials|yukon|yukon-server|sql|scripts|docs)\//, // staff tools, internals
  /^\/api\//, /^\/manage_penguin\/api\//, // JSON endpoints (captured separately)
  /(^|\/)logout\.php$/, /^\/parent_center\/unlink\.php$/, // actions
  /^\/avatar(_fragment)?\.php$/,
  // Unfinished pages that error against a stock Yukon database (see README, Known limitations)
  /^\/manage_penguin\/pages\/(public_outfits|buy_outfit|outfit)\.php$/,
  /^\/manage_penguin\/public_outfits\.php$/,
];
const OFF_MESSAGES = [
  [/(^|\/)logout\.php$/, null], // mapped to the signed-out view instead
  [/^\/parent_center\/unlink\.php$/, 'Unlinking is turned off in the static demo.'],
  [/^\/play\/?/, 'The game client is not part of this demo.'],
  [/public_outfits|buy_outfit/, 'The public outfit store was never finished, so it is not in the demo.'],
  [/^\/administration\//, 'The administration portal is not part of the static demo.'],
];
const SEEDS = {
  guest: ['/', '/login.php', '/parent_center/index.php', '/parent_center/create_parent.php', '/manage_penguin/index.php'],
  in: [
    '/', '/manage.php', '/login.php', '/manage_penguin/index.php', '/manage_penguin/studio.php',
    '/parent_center/index.php', '/parent_center/dashboard.php',
  ],
};
// API responses the pages only request after a click (Outfit Studio's saved-outfit lists).
const EXTRA_API = {
  guest: [],
  in: ['/manage_penguin/api/outfits.php?limit=3', '/manage_penguin/api/outfits.php?limit=10', '/manage_penguin/api/outfits.php?limit=60', '/manage_penguin/api/owned_items.php?per=1000&slot=all'],
};
// Where the signed-in counterpart of a signed-out page should point, when it is not the same page.
const COUNTERPART_IN = { '/login.php': '/manage_penguin/index.php', '/parent_center/index.php': '/parent_center/dashboard.php', '/parent_center/create_parent.php': '/parent_center/dashboard.php' };

const log = (...args) => console.log('[static-demo]', ...args);
const sha = (text) => crypto.createHash('sha1').update(text).digest('hex').slice(0, 12);

// ---------------------------------------------------------------------------
// Keys and file names
// ---------------------------------------------------------------------------
// Normalized key for a URL on the site: "/parent_center/child.php?uid=2".
function keyFor(urlLike, base = ORIGIN + '/') {
  const url = new URL(urlLike, base);
  let p = decodeURIComponent(url.pathname);
  if (p.endsWith('/')) p += 'index.php';
  else if (!/\.[a-z0-9]+$/i.test(p)) p += '.php'; // extensionless URLs map to .php (see .htaccess)
  url.searchParams.sort();
  const q = url.searchParams.toString();
  return p + (q ? `?${q}` : '');
}

function isPageKey(key) {
  return /\.php$/i.test(key.split('?')[0]);
}

function skipped(key) {
  const p = key.split('?')[0];
  if (SKIP.some((re) => re.test(p))) return true;
  if (/(^|&)(csrf|action)=/.test(key.split('?')[1] || '')) return true;
  return false;
}

// "/parent_center/child.php?uid=2" + in -> "parent_center/child.uid-2.signed-in.html"
function fileFor(key, view) {
  const [p, q] = key.split('?');
  const dir = path.posix.dirname(p).replace(/^\//, '');
  const stem = path.posix.basename(p, '.php');
  const slug = q ? '.' + q.replace(/[^A-Za-z0-9=&_-]/g, '').replace(/=/g, '-').replace(/&/g, '_') : '';
  const name = `${stem}${slug}${view === 'in' ? '.signed-in' : ''}.html`;
  return dir ? `${dir}/${name}` : name;
}

function writeFile(rel, content) {
  const file = path.join(OUT, ...rel.split('/'));
  fs.mkdirSync(path.dirname(file), { recursive: true });
  fs.writeFileSync(file, content);
}

// ---------------------------------------------------------------------------
// Demo logins from sql/demo_seed.sql
// ---------------------------------------------------------------------------
function demoLogins() {
  const seed = fs.readFileSync(path.join(ROOT, 'sql', 'demo_seed.sql'), 'utf8');
  const parent = seed.match(/Parent Center:\s+(\S+@\S+)\s+\/\s+(\S+)/);
  const penguin = seed.match(/Every demo penguin:\s+<username>\s+\/\s+(\S+)/);
  if (!parent || !penguin) throw new Error('Could not read the demo logins from sql/demo_seed.sql');
  return { parentEmail: parent[1], parentPassword: parent[2], penguinUser: 'DemoParent', penguinPassword: penguin[1] };
}

// ---------------------------------------------------------------------------
// Browser
// ---------------------------------------------------------------------------
async function launchBrowser() {
  const { chromium } = await import('playwright-core');
  if (process.env.CHROME_PATH) return chromium.launch({ executablePath: process.env.CHROME_PATH });
  for (const channel of ['chrome', 'msedge']) {
    try { return await chromium.launch({ channel, headless: true }); } catch { /* try next */ }
  }
  throw new Error('Could not start Chrome or Edge. Install one, or set CHROME_PATH.');
}

async function signIn(context, logins) {
  const page = await context.newPage();
  await page.goto(`${ORIGIN}/login.php`);
  await page.fill('#username', logins.penguinUser);
  await page.fill('#password', logins.penguinPassword);
  await Promise.all([page.waitForNavigation(), page.click('form[action="login.php"] button[type=submit]')]);
  await page.goto(`${ORIGIN}/parent_center/index.php`);
  await page.fill('#email', logins.parentEmail);
  await page.fill('#password', logins.parentPassword);
  await Promise.all([page.waitForNavigation(), page.click('form[action="index.php"] button[type=submit]')]);
  if (!page.url().includes('dashboard.php')) throw new Error('Parent Center sign-in failed');
  await page.close();
}

// ---------------------------------------------------------------------------
// Crawl one view
// ---------------------------------------------------------------------------
async function crawlView(view, browser, logins, store) {
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  if (view === 'in') await signIn(context, logins);
  const cookieHeader = async () => (await context.cookies(ORIGIN)).map((c) => `${c.name}=${c.value}`).join('; ');

  const pages = new Map(); // key -> html
  const redirects = {};
  const api = {};
  const queue = [];
  const seen = new Set();
  const variants = new Map();
  const enqueue = (key) => {
    if (seen.has(key) || !isPageKey(key) || skipped(key)) return;
    const p = key.split('?')[0];
    if (key.includes('?')) {
      const n = variants.get(p) || 0;
      if (n >= MAX_QUERY_VARIANTS_PER_PAGE) return;
      variants.set(p, n + 1);
    }
    if (seen.size >= MAX_PAGES_PER_VIEW) return;
    seen.add(key);
    queue.push(key);
  };
  SEEDS[view].forEach((s) => enqueue(keyFor(s)));

  // Capture what the pages load: JSON from the PHP APIs, Yukon media, rendered avatars.
  const page = await context.newPage();
  page.on('response', async (response) => {
    try {
      const request = response.request();
      const url = new URL(response.url());
      if (url.origin !== ORIGIN || request.method() !== 'GET' || response.status() !== 200) return;
      const type = response.headers()['content-type'] || '';
      if (url.pathname.startsWith('/yukon/assets/media/')) {
        store.media.set(url.pathname, await response.body());
      } else if (/\.php$/.test(url.pathname) && type.includes('json')) {
        api[keyFor(url.href)] = await response.text();
      }
    } catch { /* page closed while reading */ }
  });

  for (let i = 0; i < queue.length; i += 1) {
    const key = queue[i];
    const target = ORIGIN + key;
    const response = await fetch(target, { headers: { cookie: await cookieHeader() }, redirect: 'manual' });
    if (response.status >= 300 && response.status < 400) {
      const next = keyFor(response.headers.get('location') || '/', target);
      redirects[key] = next;
      enqueue(next);
      continue;
    }
    if (response.status !== 200 || !(response.headers.get('content-type') || '').includes('text/html')) {
      store.notes.push(`${view}: skipped ${key} (${response.status})`);
      continue;
    }
    const html = await response.text();
    if (/Fatal error|Uncaught |Warning<\/b>:|Notice<\/b>:/.test(html)) {
      store.notes.push(`${view}: skipped ${key} (PHP error in output)`);
      continue;
    }
    pages.set(key, html);

    // Real browser pass: find links (including ones added by scripts) and capture requests.
    try {
      await page.goto(target, { waitUntil: 'networkidle', timeout: 60_000 });
      await page.evaluate(async () => {
        document.querySelectorAll('img[loading=lazy]').forEach((img) => { img.loading = 'eager'; });
        await Promise.all([...document.images].map((img) => (img.complete ? 0 : new Promise((r) => { img.onload = img.onerror = r; setTimeout(r, 4000); }))));
      });
      await page.waitForTimeout(400);
      const hrefs = await page.$$eval('a[href]', (anchors) => anchors.map((a) => a.href));
      for (const href of hrefs) {
        let url;
        try { url = new URL(href); } catch { continue; }
        if (url.origin !== ORIGIN) continue;
        enqueue(keyFor(url.href));
      }
    } catch (error) {
      log(`  browser pass failed for ${key}: ${error.message}`);
    }
  }
  for (const extra of EXTRA_API[view]) {
    const response = await fetch(ORIGIN + extra, { headers: { cookie: await cookieHeader() } });
    if (response.ok && (response.headers.get('content-type') || '').includes('json')) api[keyFor(extra)] = await response.text();
    else store.notes.push(`${view}: no API response for ${extra} (${response.status})`);
  }
  await context.close();
  if (seen.size >= MAX_PAGES_PER_VIEW) log(`  WARNING: ${view} hit the ${MAX_PAGES_PER_VIEW}-page cap.`);
  log(`${view}: ${pages.size} pages, ${Object.keys(redirects).length} redirects, ${Object.keys(api).length} API responses`);
  return { pages, redirects, api };
}

// ---------------------------------------------------------------------------
// Rewriting
// ---------------------------------------------------------------------------
function resolveRedirects(key, redirects) {
  let k = key;
  for (let i = 0; i < 5 && redirects[k]; i += 1) k = redirects[k];
  return k;
}

function offMessage(p) {
  for (const [re, message] of OFF_MESSAGES) if (re.test(p)) return message;
  return 'That page is not part of the static demo.';
}

// Map one URL found in a page to its place in the snapshot. Returns { href } or { off }.
function mapUrl(raw, pageUrl, view, ctx) {
  if (!raw || raw.startsWith('#') || /^(data|mailto|tel|javascript|blob):/i.test(raw)) return null;
  let url;
  try { url = new URL(raw.replace(/&amp;/g, '&'), pageUrl); } catch { return null; }
  if (url.origin !== ORIGIN) return null;
  const p = decodeURIComponent(url.pathname);
  if (/(^|\/)logout\.php$/.test(p)) {
    const home = p.startsWith('/parent_center/') ? '/parent_center/index.php' : '/index.php';
    return { href: `${BASE}/${fileFor(home, 'guest')}` };
  }
  if (p.startsWith('/yukon/assets/media/')) {
    return ctx.media.has(p) ? { href: BASE + p } : { href: BASE + p, missing: p };
  }
  const key = keyFor(url.href);
  if (isPageKey(key)) {
    if (/^\/avatar\.php$/.test(p)) return { href: `${BASE}/__demo/avatars/${sha(key)}.svg`, avatar: key };
    const finalKey = resolveRedirects(key, ctx.views[view].redirects);
    if (ctx.views[view].pages.has(finalKey)) return { href: `${BASE}/${fileFor(finalKey, view)}${url.hash}` };
    const bare = finalKey.split('?')[0];
    if (finalKey.includes('?') && ctx.views[view].pages.has(bare)) return { href: `${BASE}/${fileFor(bare, view)}` };
    return { off: offMessage(p) };
  }
  if (/\/$/.test(url.pathname) || !/\.[a-z0-9]+$/i.test(url.pathname)) return { off: offMessage(p) };
  return { href: BASE + p }; // static file (query strings such as ?v=2 are dropped)
}

function escapeAttr(text) {
  return String(text).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
}

function rewriteHtml(html, key, view, ctx) {
  const pageUrl = ORIGIN + key;
  const parts = html.split(/(<script\b[\s\S]*?<\/script>)/i);
  const out = parts.map((part, index) => {
    if (index % 2 === 1) {
      // Inside scripts only root-relative string literals are prefixed; the shim maps the rest at runtime.
      return part
        .replace(/(['"`])\/(yukon\/assets|manage_penguin|parent_center|assets|css)\//g, `$1${BASE}/$2/`)
        .replace(/(["'])\\\/(yukon\\\/assets|manage_penguin|parent_center|assets|css)\\\//g, (m, q, dir) => `${q}${BASE.replace(/\//g, '\\/')}\\/${dir}\\/`); // JSON-escaped
    }
    let s = part;
    // Attributes that hold URLs.
    s = s.replace(/(\s(?:href|src|action|xlink:href|poster))=(["'])([^"']*)\2/gi, (match, attr, quote, value) => {
      const mapped = mapUrl(value, pageUrl, view, ctx);
      if (!mapped) return match;
      if (mapped.missing) ctx.missingMedia.add(mapped.missing);
      if (mapped.avatar) ctx.avatars.add(mapped.avatar);
      if (mapped.off !== undefined) {
        if (/action$/i.test(attr)) return `${attr}="#"`;
        return `${attr}="#" data-demo-off="${escapeAttr(mapped.off)}"`;
      }
      return `${attr}=${quote}${mapped.href}${quote}`;
    });
    // url(...) in inline styles and <style> blocks.
    s = s.replace(/url\((['"]?)([^'")]+)\1\)/g, (match, quote, value) => {
      const mapped = mapUrl(value.replace(/&quot;/g, ''), pageUrl, view, ctx);
      if (!mapped || !mapped.href) return match;
      if (mapped.missing) ctx.missingMedia.add(mapped.missing);
      return `url(${quote}${mapped.href}${quote})`;
    });
    // Forms: the login forms open the signed-in view, everything else says nothing is saved.
    s = s.replace(/<form\b([^>]*)>/gi, (match, attrs) => {
      let extra = '';
      if (key === '/login.php' || key === '/parent_center/index.php') {
        const target = key === '/login.php' ? '/manage_penguin/index.php' : '/parent_center/dashboard.php';
        extra = ` data-demo-login="${fileFor(target, 'in')}"`;
      } else if (/method=["']?get/i.test(attrs)) {
        extra = ' data-demo-message="Search and filters are not available in the static demo."';
      }
      return `<form${attrs}${extra}>`;
    });
    // CSRF tokens are meaningless in a snapshot.
    s = s.replace(/(<input\b[^>]*name=["'](?:csrf|csrf_token|mp_csrf)["'][^>]*value=["'])[0-9a-f]{16,}(["'])/gi, '$1static-demo$2');
    return s;
  }).join('');
  return out;
}

function injectShim(html, key, view, ctx) {
  let guestKey = key;
  if (!ctx.views.guest.pages.has(guestKey)) {
    if (key.startsWith('/parent_center/')) guestKey = '/parent_center/index.php';
    else if (key.startsWith('/manage_penguin/') || key === '/manage.php') guestKey = '/login.php';
    else guestKey = '/index.php';
  }
  let inKey = COUNTERPART_IN[key] || resolveRedirects(key, ctx.views.in.redirects);
  if (!ctx.views.in.pages.has(inKey)) inKey = '/index.php';
  let outKey = resolveRedirects(key, ctx.views.guest.redirects);
  if (!ctx.views.guest.pages.has(outKey)) outKey = guestKey;
  const config = {
    base: BASE, view, key, repoUrl: REPO_URL,
    counterpart: { guest: fileFor(outKey, 'guest'), in: fileFor(inKey, 'in') },
  };
  const tags = `<script>window.__CP_DEMO__=${JSON.stringify(config)}</script><script src="${BASE}/__demo/shim.js"></script>`;
  return html.replace(/<head([^>]*)>/i, (match) => `${match}${tags}`);
}

// ---------------------------------------------------------------------------
// Static files from the repository
// ---------------------------------------------------------------------------
const STATIC_EXT = /\.(png|jpe?g|webp|gif|svg|ico|css|js)$/i;
const STATIC_SKIP_DIRS = new Set(['.git', 'sql', 'scripts', 'docs', 'node_modules', 'yukon', 'yukon-server', '_next', 'administration', 'includes']);

function copyStatic(dir = ROOT, rel = '') {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    if (entry.isDirectory()) {
      if (!rel && STATIC_SKIP_DIRS.has(entry.name)) continue;
      if (entry.name.startsWith('.')) continue;
      copyStatic(path.join(dir, entry.name), rel ? `${rel}/${entry.name}` : entry.name);
    } else if (STATIC_EXT.test(entry.name)) {
      const relFile = rel ? `${rel}/${entry.name}` : entry.name;
      let content = fs.readFileSync(path.join(dir, entry.name));
      if (/\.css$/i.test(entry.name)) {
        content = content.toString('utf8').replace(/url\((['"]?)\/(?!\/)/g, `url($1${BASE}/`);
      } else if (/\.js$/i.test(entry.name)) {
        content = content.toString('utf8').replace(/(['"`])\/(yukon\/assets|manage_penguin|parent_center|assets|css)\//g, `$1${BASE}/$2/`);
      }
      writeFile(relFile, content);
    }
  }
}

async function fetchMissingMedia(ctx) {
  // Every clothing item shown anywhere: also grab its paper-doll layer and icons so the
  // Outfit Studio can preview items it did not happen to render during the crawl.
  const ids = new Set();
  for (const p of ctx.media.keys()) {
    const m = p.match(/\/clothing\/(?:icon\/\d+|paper|photos)\/(\d+)\.png$/);
    if (m) ids.add(m[1]);
  }
  for (const text of ctx.texts) {
    for (const m of text.matchAll(/\/yukon\/assets\/media\/[A-Za-z0-9_/.-]+\.(?:png|jpe?g|webp|svg)/g)) ctx.missingMedia.add(m[0]);
  }
  for (const id of ids) {
    for (const kind of ['paper', 'icon/120', 'icon/240', 'photos']) ctx.missingMedia.add(`/yukon/assets/media/clothing/${kind}/${id}.png`);
  }
  let fetched = 0;
  for (const p of ctx.missingMedia) {
    if (ctx.media.has(p)) continue;
    const response = await fetch(ORIGIN + p);
    if (response.ok) { ctx.media.set(p, Buffer.from(await response.arrayBuffer())); fetched += 1; }
  }
  for (const [p, body] of ctx.media) writeFile(p.replace(/^\//, ''), body);
  log(`Yukon media: ${ctx.media.size} files (${fetched} fetched after the crawl)`);
}

// ---------------------------------------------------------------------------
// Safety check: nothing local or secret may end up in the snapshot
// ---------------------------------------------------------------------------
function scanOutput() {
  const bad = [/127\.0\.0\.1/, /localhost/i, /[A-Z]:\\\\?(Users|xampp|GitHub)/i, /\/home\/[a-z]/, /PHPSESSID/, /Fatal error/, /Stack trace/];
  const problems = [];
  const walk = (dir) => {
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
      const file = path.join(dir, entry.name);
      if (entry.isDirectory()) walk(file);
      else if (/\.(html|json|js|css|svg)$/i.test(entry.name)) {
        const text = fs.readFileSync(file, 'utf8');
        for (const re of bad) if (re.test(text)) problems.push(`${path.relative(OUT, file)}: ${re}`);
      }
    }
  };
  walk(OUT);
  if (problems.length) throw new Error(`Snapshot contains local details:\n  ${problems.join('\n  ')}`);
}

// ---------------------------------------------------------------------------
// Publish to gh-pages
// ---------------------------------------------------------------------------
function run(cmd, args, cwd = ROOT) {
  const result = spawnSync(cmd, args, { cwd, stdio: 'inherit' });
  if (result.status !== 0) throw new Error(`${cmd} ${args.join(' ')} exited with ${result.status}`);
}

function copyDir(from, to) {
  fs.mkdirSync(to, { recursive: true });
  for (const entry of fs.readdirSync(from, { withFileTypes: true })) {
    const source = path.join(from, entry.name);
    const target = path.join(to, entry.name);
    if (entry.isDirectory()) copyDir(source, target);
    else fs.copyFileSync(source, target);
  }
}

function publish() {
  const worktree = path.join(ROOT, '.gh-pages-worktree');
  if (!fs.existsSync(worktree)) {
    const hasRemote = spawnSync('git', ['-C', ROOT, 'ls-remote', '--exit-code', '--heads', 'origin', 'gh-pages']).status === 0;
    if (hasRemote) {
      run('git', ['fetch', 'origin', 'gh-pages']);
      run('git', ['worktree', 'add', '-B', 'gh-pages', worktree, 'origin/gh-pages']);
    } else {
      run('git', ['worktree', 'add', '--orphan', '-b', 'gh-pages', worktree]);
    }
  }
  for (const entry of fs.readdirSync(worktree)) {
    if (entry !== '.git') fs.rmSync(path.join(worktree, entry), { recursive: true, force: true });
  }
  copyDir(OUT, worktree);
  run('git', ['add', '-A'], worktree);
  if (spawnSync('git', ['-C', worktree, 'diff', '--cached', '--quiet']).status === 0) { log('gh-pages is already up to date.'); return; }
  run('git', ['commit', '-q', '-m', `Update static demo (${new Date().toISOString().slice(0, 10)})`], worktree);
  run('git', ['push', 'origin', 'gh-pages'], worktree);
  log(`Published. It can take a minute to appear at https://brocksexton.github.io${BASE}/`);
}

// ---------------------------------------------------------------------------
async function main() {
  const logins = demoLogins();
  fs.rmSync(OUT, { recursive: true, force: true });
  fs.mkdirSync(OUT, { recursive: true });
  const store = { media: new Map(), notes: [] };
  const browser = await launchBrowser();
  const views = {};
  try {
    for (const view of ['guest', 'in']) {
      log(`Crawling the ${view === 'in' ? 'signed-in' : 'signed-out'} view...`);
      views[view] = await crawlView(view, browser, logins, store);
    }
  } finally {
    await browser.close();
  }

  const ctx = { views, media: store.media, missingMedia: new Set(), avatars: new Set(), texts: [] };
  const manifest = { base: BASE, snapshot: new Date().toISOString(), pages: {}, redirects: {}, api: {} };
  for (const view of ['guest', 'in']) {
    manifest.redirects[view] = views[view].redirects;
    manifest.api[view] = {};
    for (const [key, html] of views[view].pages) {
      manifest.pages[key] = manifest.pages[key] || {};
      manifest.pages[key][view] = fileFor(key, view);
      const rewritten = injectShim(rewriteHtml(html, key, view, ctx), key, view, ctx);
      writeFile(fileFor(key, view), rewritten);
      ctx.texts.push(rewritten);
    }
    for (const [key, body] of Object.entries(views[view].api)) {
      const file = `__demo/api/${view}/${sha(key)}.json`;
      const plain = body.replace(/\\\//g, '/'); // PHP's json_encode escapes slashes
      writeFile(file, plain.replace(/(["'])\/(yukon\/assets|manage_penguin|parent_center|assets|css)\//g, `$1${BASE}/$2/`));
      manifest.api[view][key] = file;
      ctx.texts.push(plain);
    }
  }

  // Rendered avatars (avatar.php returns SVG), fetched with the signed-in session's view of the data.
  for (const key of ctx.avatars) {
    const response = await fetch(ORIGIN + key);
    if (response.ok) {
      const svg = (await response.text()).replace(/(href=")\/(?!\/)/g, `$1${BASE}/`);
      writeFile(`__demo/avatars/${sha(key)}.svg`, svg);
      ctx.texts.push(svg);
    }
  }

  await fetchMissingMedia(ctx);
  copyStatic();

  // Not-found page: GitHub Pages serves 404.html for any missing path.
  const missing = await fetch(`${ORIGIN}/error-pages/404.php`);
  const notFoundKey = '/error-pages/404.php';
  views.guest.pages.set(notFoundKey, '');
  const notFound = injectShim(rewriteHtml(await missing.text(), notFoundKey, 'guest', ctx), notFoundKey, 'guest', ctx);
  writeFile('404.html', notFound);

  writeFile('__demo/manifest.json', JSON.stringify(manifest));
  fs.copyFileSync(path.join(ROOT, 'scripts', 'static-demo', 'shim.js'), path.join(OUT, '__demo', 'shim.js'));
  writeFile('.nojekyll', '');
  writeFile('robots.txt', 'User-agent: *\nDisallow: /\n');
  scanOutput();
  for (const note of store.notes) log(`  ${note}`);
  log(`Done: ${path.relative(ROOT, OUT)}/`);
  if (process.argv.includes('--publish')) publish();
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
