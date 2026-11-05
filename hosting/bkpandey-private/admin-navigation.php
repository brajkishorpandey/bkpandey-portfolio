<?php
declare(strict_types=1);

const BP_ADMIN_SECTIONS = [
    'articles' => ['All articles', '/admin'],
    'new' => ['New article', '/admin?new=1'],
    'site' => ['Website & résumé', '/admin?site=1'],
    'contact' => ['Contact email', '/admin?contact=1'],
    'social' => ['Social publishing', '/admin?social=1'],
    'analytics' => ['Google Analytics', '/admin?analytics=1'],
    'traffic' => ['Traffic reports', '/admin?traffic=1'],
    'account' => ['Account & password', '/admin?account=1'],
    'database' => ['Database', '/admin?database=1'],
];

function bp_admin_section(array $query): string {
    foreach (['account', 'database', 'site', 'contact', 'traffic', 'analytics', 'social', 'new'] as $section) {
        if (isset($query[$section])) {
            return $section;
        }
    }
    return 'articles';
}

function bp_admin_return_path(array $query): string {
    $section = bp_admin_section($query);
    $route = BP_ADMIN_SECTIONS[$section][1];
    if (in_array($section, ['traffic', 'analytics'], true)) {
        $state = [];
        foreach (['day', 'from', 'to', 'email', 'size', 'page'] as $key) {
            if (is_string($query[$key] ?? null) && strlen($query[$key]) <= 16) {
                $state[$key] = $query[$key];
            }
        }
        return $route.($state ? '&'.http_build_query($state, '', '&', PHP_QUERY_RFC3986) : '');
    }
    if ($section === 'articles' && is_string($query['edit'] ?? null) && ctype_digit($query['edit'])) {
        return $route.'?edit='.(int) $query['edit'];
    }
    return $route;
}

function bp_admin_navigation(array $query): string {
    $current = bp_admin_section($query);
    $html = '<aside class="admin-sidebar"><button type="button" class="btn admin-menu-toggle" aria-controls="admin-navigation" hidden>Menu <span aria-hidden="true">⌄</span></button>'
        .'<nav class="admin-nav" id="admin-navigation" aria-label="Admin navigation">';
    foreach ([
        'Publishing' => ['articles', 'new', 'social'],
        'Website' => ['site', 'contact', 'traffic', 'analytics'],
        'Settings' => ['account', 'database'],
    ] as $group => $keys) {
        $html .= '<div class="admin-nav-group"><p>'.bp_h($group).'</p><ul>';
        foreach ($keys as $key) {
            [$label, $href] = BP_ADMIN_SECTIONS[$key];
            $html .= '<li><a href="'.bp_h($href).'"'.($current === $key ? ' aria-current="page"' : '').'>'.bp_h($label).'</a></li>';
        }
        $html .= '</ul></div>';
    }
    return $html.'</nav></aside>';
}

function bp_admin_page_start(string $title, bool $signedIn = false): void {
    echo '<!doctype html><html lang="en" class="admin-document"><head><meta charset="utf-8">'
        .'<meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">'
        .'<title>'.bp_h($title).' | Brajkishor Pandey Admin</title><link rel="icon" href="/assets/images/favicon.svg">'
        .'<link rel="stylesheet" href="/assets/css/site.css?v=5"><link rel="stylesheet" href="/assets/css/blog.css">'
        .'<link rel="stylesheet" href="/assets/css/admin.css?v=1"><script defer src="/assets/js/admin.js?v=1"></script></head>'
        .'<body class="admin-body"><a href="#main" class="skip">Skip to content</a>'
        .'<header class="admin-topbar"><div class="admin-topbar-inner"><a class="brand" href="/admin">'
        .'<span class="brand-mark" aria-hidden="true">bp</span><span>Brajkishor Pandey<small>Admin workspace</small></span></a>'
        .'<div class="admin-topbar-actions"><a class="btn" href="/" target="_blank" rel="noopener">View site ↗</a>';
    if ($signedIn) {
        echo '<form method="post" action="/admin"><input type="hidden" name="csrf" value="'.bp_h(bp_csrf()).'">'
            .'<button class="btn" name="action" value="logout">Sign out</button></form>';
    }
    echo '</div></div></header>';
}
