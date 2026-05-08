<?php
require __DIR__ . '/../config.php';

$root = dirname(__DIR__);
$out = $root . '/docs';
$base_path = rtrim(getenv('PUBLIC_BASE_PATH') ?: '', '/');
$site_url = rtrim(getenv('PUBLIC_SITE_URL') ?: '', '/');

function clean_dir($dir) {
    if (!is_dir($dir)) return;
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($dir);
}

function copy_dir($source, $dest) {
    if (!is_dir($source)) return;
    if (!is_dir($dest)) mkdir($dest, 0755, true);
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $item) {
        $target = $dest . DIRECTORY_SEPARATOR . substr($item->getPathname(), strlen($source) + 1);
        if ($item->isDir()) {
            if (!is_dir($target)) mkdir($target, 0755, true);
        } else {
            copy($item->getPathname(), $target);
        }
    }
}

function url_path($path = '') {
    global $base_path;
    $path = ltrim($path, '/');
    return ($base_path ?: '') . '/' . $path;
}

function page_head($title = '', $description = '', $canonical = '') {
    $full_title = $title ? htmlspecialchars($title) . ' - ' . htmlspecialchars(SITE_NAME) : htmlspecialchars(SITE_NAME);
    $description = htmlspecialchars($description ?: SITE_TAGLINE);
    $canonical_tag = $canonical ? '<link rel="canonical" href="' . htmlspecialchars($canonical) . '">' : '';
    return '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>' . $full_title . '</title>
<meta name="description" content="' . $description . '">
<meta property="og:title" content="' . htmlspecialchars($title ?: SITE_NAME) . '">
<meta property="og:description" content="' . $description . '">
<meta property="og:type" content="website">
<meta name="twitter:card" content="summary">
' . $canonical_tag . '
<link rel="stylesheet" href="' . htmlspecialchars(url_path('assets/style.css')) . '?v=20260510">
<script>if(localStorage.getItem("theme")!=="light")document.documentElement.classList.add("dark");</script>
</head>
<body><main>';
}

function page_footer() {
    return '</main>
<footer>
  <span>&copy; ' . date('Y') . ' ' . htmlspecialchars(SITE_NAME) . '</span>
  <span><a href="' . htmlspecialchars(url_path('feed.xml')) . '">RSS</a><a href="javascript:void(0)" onclick="document.documentElement.classList.toggle(\'dark\');localStorage.setItem(\'theme\',document.documentElement.classList.contains(\'dark\')?\'dark\':\'light\')">Mode</a></span>
</footer></body></html>';
}

function write_file($path, $content) {
    if (!is_dir(dirname($path))) mkdir(dirname($path), 0755, true);
    file_put_contents($path, $content);
}

clean_dir($out);
mkdir($out, 0755, true);
copy_dir($root . '/assets', $out . '/assets');
copy_dir($root . '/uploads', $out . '/uploads');
file_put_contents($out . '/.nojekyll', '');

$posts = get_all_posts(true);

$html = page_head('Writing', 'Articles by ' . SITE_NAME, $site_url ? $site_url . url_path('') : '');
$html .= '<section class="hero"><h1>' . htmlspecialchars(SITE_NAME) . '</h1><p>' . htmlspecialchars(SITE_BIO) . '</p></section>';
$html .= '<section class="section"><div class="section-header"><h2 class="section-title">Writing</h2><small>' . count($posts) . ' posts</small></div>';
if (empty($posts)) {
    $html .= '<small>No posts yet.</small>';
} else {
    $html .= '<div class="article-list">';
    foreach ($posts as $post) {
        $html .= '<a href="' . htmlspecialchars(url_path('post/' . $post['slug'] . '/')) . '" class="article-item">';
        $html .= '<span class="article-date">' . htmlspecialchars($post['date']) . '</span>';
        $html .= '<span class="article-title">' . htmlspecialchars($post['title']) . '</span></a>';
    }
    $html .= '</div>';
}
$html .= '</section>' . page_footer();
write_file($out . '/index.html', $html);

foreach ($posts as $i => $post) {
    $prev = $posts[$i + 1] ?? null;
    $next = $posts[$i - 1] ?? null;
    $canonical = $site_url ? $site_url . url_path('post/' . $post['slug'] . '/') : '';
    $html = page_head($post['title'], make_excerpt($post['content']), $canonical);
    $html .= '<section class="article-page">';
    $html .= '<a class="back-link" href="' . htmlspecialchars(url_path('')) . '">&larr; All posts</a>';
    $html .= '<h1>' . htmlspecialchars($post['title']) . '</h1>';
    $html .= '<small>' . htmlspecialchars($post['date']) . ' &middot; ' . $post['reading_time'] . '</small>';
    if (!empty($post['tags'])) {
        $html .= '<p>';
        foreach ($post['tags'] as $tag) $html .= '<span class="tag">' . htmlspecialchars($tag) . '</span>';
        $html .= '</p>';
    }
    $html .= '<div class="prose">' . render_markdown($post['content']) . '</div>';
    if ($prev || $next) {
        $html .= '<nav class="post-nav"><div class="post-nav-side">';
        if ($prev) $html .= '<a href="' . htmlspecialchars(url_path('post/' . $prev['slug'] . '/')) . '">&larr; ' . htmlspecialchars($prev['title']) . '</a>';
        $html .= '</div><div class="post-nav-side">';
        if ($next) $html .= '<a href="' . htmlspecialchars(url_path('post/' . $next['slug'] . '/')) . '">' . htmlspecialchars($next['title']) . ' &rarr;</a>';
        $html .= '</div></nav>';
    }
    $html .= '</section>' . page_footer();
    write_file($out . '/post/' . $post['slug'] . '/index.html', $html);
}

$recent = array_slice($posts, 0, 3);
$html = page_head('Page Not Found', 'Page not found');
$html .= '<div class="error-page"><h1>404</h1><h2>Page not found</h2><p>This page does not exist or has been moved.</p><a href="' . htmlspecialchars(url_path('')) . '" class="btn btn-primary">Back to home</a>';
if ($recent) {
    $html .= '<div style="margin-top:3rem;text-align:left;max-width:400px;margin:3rem auto 0">';
    foreach ($recent as $post) {
        $html .= '<div style="padding:0.6rem 0;border-bottom:1px solid var(--border)"><a href="' . htmlspecialchars(url_path('post/' . $post['slug'] . '/')) . '">' . htmlspecialchars($post['title']) . '</a><div style="font-size:0.7rem;color:var(--muted);margin-top:0.15rem">' . htmlspecialchars($post['date']) . '</div></div>';
    }
    $html .= '</div>';
}
$html .= '</div>' . page_footer();
write_file($out . '/404.html', $html);

$feed = '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"><channel><title>' . htmlspecialchars(SITE_NAME) . '</title><description>' . htmlspecialchars(SITE_TAGLINE) . '</description><link>' . htmlspecialchars($site_url ?: url_path('')) . '</link>';
foreach ($posts as $post) {
    $link = ($site_url ?: '') . url_path('post/' . $post['slug'] . '/');
    $feed .= '<item><title>' . htmlspecialchars($post['title']) . '</title><link>' . htmlspecialchars($link) . '</link><guid>' . htmlspecialchars($link) . '</guid><pubDate>' . date(DATE_RSS, strtotime($post['date'] ?: 'now')) . '</pubDate><description>' . htmlspecialchars(make_excerpt($post['content'])) . '</description></item>';
}
$feed .= '</channel></rss>';
write_file($out . '/feed.xml', $feed);

echo "Built static site in docs\n";
