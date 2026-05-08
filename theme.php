<?php
function html_head($title = '', $seo = []) {
    $site = SITE_NAME;
    $t = $title ? htmlspecialchars($title) . ' - ' . $site : $site;
    $desc = htmlspecialchars($seo['description'] ?? SITE_TAGLINE);
    $og = htmlspecialchars($seo['og_title'] ?? ($title ?: SITE_NAME));
    $type = htmlspecialchars($seo['og_type'] ?? 'website');
    $img = htmlspecialchars($seo['og_image'] ?? '');
    $canon = isset($seo['canonical']) ? '<link rel="canonical" href="' . htmlspecialchars($seo['canonical']) . '">' : '';
    $noindex = !empty($seo['noindex']) ? '<meta name="robots" content="noindex,nofollow">' . "\n" : '';
    echo '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
' . $noindex . '<title>' . $t . '</title>
<meta name="description" content="' . $desc . '">
<meta property="og:title" content="' . $og . '">
<meta property="og:description" content="' . $desc . '">
<meta property="og:type" content="' . $type . '">
' . ($img ? '<meta property="og:image" content="' . $img . '">' . "\n" : '') . '<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="' . $og . '">
<meta name="twitter:description" content="' . $desc . '">
' . ($img ? '<meta name="twitter:image" content="' . $img . '">' . "\n" : '') . $canon . '
<link rel="stylesheet" href="assets/style.css?v=20260510">
<script>if(localStorage.getItem("theme")!=="light")document.documentElement.classList.add("dark");</script>
</head>
<body>';
}

function render_nav($active = '') {
    echo '<main>';
}

function render_footer() {
    echo '</main>
<footer>
  <span>&copy; ' . date('Y') . ' ' . htmlspecialchars(SITE_NAME) . '</span>
  <span>
    <a href="feed.php">RSS</a>
    <a href="javascript:void(0)" onclick="document.documentElement.classList.toggle(\'dark\');localStorage.setItem(\'theme\',document.documentElement.classList.contains(\'dark\')?\'dark\':\'light\')">Mode</a>
  </span>
</footer>';
}