/*
 * Static demo shim, injected as the first script on every page of the GitHub Pages snapshot
 * (see scripts/static-demo/build.mjs). The snapshot is plain files, so this script stands in
 * for the PHP server:
 *   - GET requests the pages make to the PHP APIs are answered from captured JSON;
 *     anything that would save (POST, PUT, DELETE) gets a friendly "nothing is saved" reply;
 *   - forms show the same message instead of posting (the login forms open the signed-in view);
 *   - links and asset paths that assume the site lives at "/" are mapped onto the Pages sub-path,
 *     and links to pages that are not part of the snapshot explain that instead of breaking;
 *   - a small banner switches between the signed-out and signed-in views of the same page.
 */
(function () {
  'use strict';
  var cfg = window.__CP_DEMO__;
  if (!cfg || window.__CP_DEMO_SHIM__) return;
  window.__CP_DEMO_SHIM__ = true;

  var BASE = cfg.base;
  var VIEW = cfg.view; // 'guest' | 'in'
  var SAVE_MESSAGE = 'This is a static demo with made-up data, so nothing is saved.';
  var OFF_MESSAGE = 'That page is not part of the static demo.';

  // ---------------------------------------------------------------------------
  // Manifest: page files per view, captured API responses, redirects
  // ---------------------------------------------------------------------------
  var realFetch = window.fetch.bind(window);
  var manifest = null;
  var manifestPromise = realFetch(BASE + '/__demo/manifest.json')
    .then(function (r) { return r.ok ? r.json() : null; })
    .catch(function () { return null; })
    .then(function (m) { manifest = m || { pages: {}, api: {}, redirects: {} }; return manifest; });

  function startsWithPath(pathname, prefix) {
    return pathname === prefix || pathname.indexOf(prefix + '/') === 0;
  }

  // Path on the original site ("/parent_center/child.php") for a URL pathname on this host.
  function sitePath(pathname) {
    return startsWithPath(pathname, BASE) ? (pathname.slice(BASE.length) || '/') : pathname;
  }

  function normalizeKey(path, search) {
    if (/\/$/.test(path)) path += 'index.php';
    var params = new URLSearchParams(search);
    params.sort();
    var query = params.toString();
    return path + (query ? '?' + query : '');
  }

  // Where a same-origin URL should go in the snapshot: a URL, or null when it is not captured.
  function mapPage(url, view) {
    if (!manifest) return null;
    var key = normalizeKey(sitePath(url.pathname), url.search);
    var seen = 0;
    while (manifest.redirects[view] && manifest.redirects[view][key] && seen < 5) {
      key = manifest.redirects[view][key];
      seen += 1;
    }
    var entry = manifest.pages[key];
    if (entry && entry[view]) return BASE + '/' + entry[view] + url.hash;
    if (!entry && manifest.pages[key.split('?')[0]] && manifest.pages[key.split('?')[0]][view]) {
      return BASE + '/' + manifest.pages[key.split('?')[0]][view];
    }
    return null;
  }

  function isAssetPath(path) {
    return /\.(png|jpe?g|webp|gif|svg|ico|css|js|woff2?|ttf|html)$/i.test(path);
  }

  // ---------------------------------------------------------------------------
  // Toast
  // ---------------------------------------------------------------------------
  var toastTimer = null;
  function toast(message) {
    var el = document.getElementById('cp-demo-toast');
    if (!el) {
      el = document.createElement('div');
      el.id = 'cp-demo-toast';
      el.setAttribute('role', 'status');
      el.style.cssText = 'position:fixed;left:50%;bottom:76px;transform:translateX(-50%);z-index:2147483647;' +
        'max-width:min(92vw,440px);padding:10px 16px;border-radius:12px;background:#062a52;color:#f1f8ff;' +
        'font:600 14px/1.4 system-ui,-apple-system,Segoe UI,sans-serif;box-shadow:0 10px 30px rgba(0,0,0,.35);' +
        'border:1px solid rgba(83,204,249,.55);transition:opacity .2s;text-align:center';
      (document.body || document.documentElement).appendChild(el);
    }
    el.textContent = message;
    el.style.opacity = '1';
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { el.style.opacity = '0'; }, 3600);
  }

  // ---------------------------------------------------------------------------
  // fetch: API requests come from captured files
  // ---------------------------------------------------------------------------
  function jsonResponse(status, body) {
    return new Response(JSON.stringify(body), { status: status, headers: { 'content-type': 'application/json' } });
  }

  window.fetch = function (input, init) {
    var url, method;
    try {
      var request = typeof Request !== 'undefined' && input instanceof Request ? input : null;
      url = new URL(request ? request.url : String(input), location.href);
      method = String((init && init.method) || (request && request.method) || 'GET').toUpperCase();
    } catch (error) {
      return realFetch(input, init);
    }
    if (url.origin !== location.origin) return realFetch(input, init);
    var path = sitePath(url.pathname);
    if (!/\.php$/i.test(path)) {
      if (!startsWithPath(url.pathname, BASE) && path.charAt(0) === '/') return realFetch(BASE + path + url.search, init);
      return realFetch(input, init);
    }
    if (method !== 'GET' && method !== 'HEAD') {
      toast(SAVE_MESSAGE);
      return Promise.resolve(jsonResponse(403, { ok: false, error: SAVE_MESSAGE, message: SAVE_MESSAGE }));
    }
    return manifestPromise.then(function (m) {
      var key = normalizeKey(path, url.search);
      var table = (m.api && m.api[VIEW]) || {};
      var file = table[key] || table[key.split('?')[0]];
      if (!file) {
        // Same endpoint with different parameters (for example another page size): close enough.
        var prefix = key.split('?')[0] + '?';
        for (var k in table) { if (k.indexOf(prefix) === 0) { file = table[k]; break; } }
      }
      if (!file) return jsonResponse(404, { ok: false, error: 'Not part of the static demo.' });
      return realFetch(BASE + '/' + file).then(function (r) {
        return r.text().then(function (text) {
          return new Response(text, { status: 200, headers: { 'content-type': 'application/json' } });
        });
      });
    });
  };

  // ---------------------------------------------------------------------------
  // Links and forms
  // ---------------------------------------------------------------------------
  function go(url) { location.assign(url); }

  window.addEventListener('click', function (event) {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    var anchor = event.target && event.target.closest ? event.target.closest('a[href], [data-demo-off]') : null;
    if (!anchor) return;
    var off = anchor.getAttribute('data-demo-off');
    if (off !== null) {
      event.preventDefault();
      event.stopImmediatePropagation();
      toast(off || OFF_MESSAGE);
      return;
    }
    if (anchor.target === '_blank' || anchor.hasAttribute('download')) return;
    var raw = anchor.getAttribute('href') || '';
    if (raw.charAt(0) === '#' || /^(mailto|tel|javascript):/i.test(raw)) return;
    var url;
    try { url = new URL(raw, location.href); } catch (error) { return; }
    if (url.origin !== location.origin) return;
    if (startsWithPath(url.pathname, BASE) && isAssetPath(url.pathname)) return; // snapshot file
    event.preventDefault();
    event.stopImmediatePropagation();
    manifestPromise.then(function () {
      var mapped = mapPage(url, VIEW);
      if (mapped) go(mapped);
      else toast(OFF_MESSAGE);
    });
  }, true);

  window.addEventListener('submit', function (event) {
    var form = event.target;
    if (!form || !form.getAttribute) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    var login = form.getAttribute('data-demo-login');
    if (login) {
      toast('Static demo: opening the signed-in view.');
      setTimeout(function () { go(BASE + '/' + login); }, 500);
      return;
    }
    toast(form.getAttribute('data-demo-message') || SAVE_MESSAGE);
  }, true);

  // Script navigations such as location.href = '/manage_penguin/index.php?tab=my_outfits'.
  if (window.navigation && window.navigation.addEventListener) {
    window.navigation.addEventListener('navigate', function (event) {
      if (!event.cancelable || event.hashChange || event.downloadRequest || event.formData) return;
      var url;
      try { url = new URL(event.destination.url); } catch (error) { return; }
      if (url.origin !== location.origin) return;
      if (startsWithPath(url.pathname, BASE) && /\.html$/i.test(url.pathname)) return;
      if (startsWithPath(url.pathname, BASE) && isAssetPath(url.pathname)) return;
      event.preventDefault();
      manifestPromise.then(function () {
        var mapped = mapPage(url, VIEW);
        if (mapped) go(mapped);
        else toast(OFF_MESSAGE);
      });
    });
  }

  // Images, links and inline styles created by scripts after load still carry root-relative paths.
  // url(/x) or url("/x") that is not already inside the demo (and not protocol-relative).
  var ROOT_URL = new RegExp('url\\(([\'"]?)/(?!/|' + BASE.slice(1).replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '/)', 'g');
  function fixElement(el) {
    if (el.nodeType !== 1) return;
    var src = el.getAttribute('src');
    if (src && src.charAt(0) === '/' && src.charAt(1) !== '/' && !startsWithPath(src.split(/[?#]/)[0], BASE)) {
      el.setAttribute('src', BASE + src);
    }
    var style = el.getAttribute('style');
    if (style && style.indexOf('url(') !== -1) {
      var fixed = style.replace(ROOT_URL, 'url($1' + BASE + '/');
      if (fixed !== style) el.setAttribute('style', fixed);
    }
  }
  function fixTree(root) {
    if (!root || !root.querySelectorAll) return;
    fixElement(root);
    var nodes = root.querySelectorAll('[src^="/"], [style*="url("]');
    for (var i = 0; i < nodes.length; i += 1) fixElement(nodes[i]);
  }
  new MutationObserver(function (records) {
    for (var i = 0; i < records.length; i += 1) {
      var record = records[i];
      if (record.type === 'attributes') fixElement(record.target);
      else for (var j = 0; j < record.addedNodes.length; j += 1) fixTree(record.addedNodes[j]);
    }
  }).observe(document.documentElement, { subtree: true, childList: true, attributes: true, attributeFilter: ['src', 'style'] });

  // ---------------------------------------------------------------------------
  // Banner: which view you are in, and a switch to the other one
  // ---------------------------------------------------------------------------
  function addBanner() {
    if (document.getElementById('cp-demo-banner')) return;
    var signedIn = VIEW === 'in';
    var bar = document.createElement('div');
    bar.id = 'cp-demo-banner';
    bar.style.cssText = 'position:fixed;left:12px;bottom:12px;z-index:2147483646;display:flex;align-items:center;gap:8px;' +
      'flex-wrap:wrap;max-width:calc(100vw - 24px);padding:6px 6px 6px 12px;border-radius:999px;background:rgba(6,42,82,.94);' +
      'color:#e6f4ff;font:600 12px/1.3 system-ui,-apple-system,Segoe UI,sans-serif;box-shadow:0 8px 24px rgba(0,0,0,.3);' +
      'border:1px solid rgba(83,204,249,.5)';
    var label = document.createElement('span');
    label.textContent = 'Static demo · made-up data';
    var group = document.createElement('span');
    group.style.cssText = 'display:inline-flex;border-radius:999px;background:rgba(255,255,255,.1);padding:2px';
    function option(text, active, href, title) {
      var el = document.createElement(active ? 'span' : 'a');
      el.textContent = text;
      el.title = title;
      el.style.cssText = 'padding:4px 10px;border-radius:999px;text-decoration:none;color:' + (active ? '#062a52' : '#e6f4ff') +
        ';background:' + (active ? '#e6f4ff' : 'transparent');
      if (!active) el.href = href;
      return el;
    }
    group.appendChild(option('Signed out', !signedIn, BASE + '/' + cfg.counterpart.guest, 'Browse as a visitor who has not signed in'));
    group.appendChild(option('Signed in', signedIn, BASE + '/' + cfg.counterpart.in, 'Browse as DemoParent, a made-up player who is also signed in to the Parent Center'));
    var repo = document.createElement('a');
    repo.href = cfg.repoUrl;
    repo.textContent = 'GitHub';
    repo.style.cssText = 'color:#8fd8ff;text-decoration:none;padding:4px 8px 4px 2px';
    bar.appendChild(label);
    bar.appendChild(group);
    bar.appendChild(repo);
    document.body.appendChild(bar);
  }

  function onReady() {
    fixTree(document.body);
    addBanner();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', onReady);
  else onReady();
})();
