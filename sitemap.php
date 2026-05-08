<?php
require_once 'config.php';

header('Content-Type: application/xml; charset=utf-8');

$base = base_url();
$urls = [];

$urls[] = ['loc' => $base, 'changefreq' => 'weekly', 'priority' => '1.0'];
$urls[] = ['loc' => $base . 'writing.php', 'changefreq' => 'weekly', 'priority' => '0.8'];

foreach (get_all_posts(true) as $p) {
    $urls[] = ['loc' => $base . 'post.php?slug=' . urlencode($p['slug']), 'changefreq' => 'monthly', 'priority' => '0.7'];
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
    echo '  <url>' . "\n";
    echo '    <loc>' . htmlspecialchars($u['loc']) . '</loc>' . "\n";
    echo '    <changefreq>' . $u['changefreq'] . '</changefreq>' . "\n";
    echo '    <priority>' . $u['priority'] . '</priority>' . "\n";
    echo '  </url>' . "\n";
}
echo '</urlset>';
