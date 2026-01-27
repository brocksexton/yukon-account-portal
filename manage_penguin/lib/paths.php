<?php

// URL roots
function mp_site_root_url(): string {
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $siteRoot = rtrim(str_replace('\\', '/', dirname($script)), '/');
    // manage_penguin is one level deep
    $siteRoot = rtrim(str_replace('\\', '/', dirname($siteRoot)), '/');
    if ($siteRoot === '.' || $siteRoot === '/') { $siteRoot = ''; }
    return $siteRoot;
}

function mp_yukon_root_url(): string {
    return mp_site_root_url() . '/yukon';
}

// Filesystem roots (for reading crumbs JSON locally)
function mp_hdocs_fs_root(): string {
    // manage_penguin/lib -> manage_penguin -> htdocs
    $root = realpath(__DIR__ . '/../../');
    return $root ?: (__DIR__ . '/../../');
}

function mp_yukon_fs_root(): string {
    $root = realpath(mp_hdocs_fs_root() . '/yukon');
    return $root ?: (mp_hdocs_fs_root() . '/yukon');
}

function mp_crumbs_fs_dir(string $lang = 'en'): string {
    return mp_yukon_fs_root() . '/assets/media/crumbs/' . $lang;
}
