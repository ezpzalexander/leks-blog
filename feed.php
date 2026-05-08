<?php
require 'config.php';
$posts   = get_all_posts(true, 20);
$baseurl = base_url();
header('Content-Type: application/rss+xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
  <channel>
    <title><?= htmlspecialchars(SITE_NAME) ?></title>
    <link><?= htmlspecialchars($baseurl) ?></link>
    <description><?= htmlspecialchars(SITE_TAGLINE) ?></description>
    <language>en</language>
    <atom:link href="<?= htmlspecialchars($baseurl) ?>feed.php" rel="self" type="application/rss+xml"/>
    <?php foreach ($posts as $p):
      $url  = $baseurl . 'post.php?slug=' . urlencode($p['slug']);
      $desc = htmlspecialchars(make_excerpt($p['content']));
      $date = $p['date'] ? date(DATE_RSS, strtotime($p['date'])) : date(DATE_RSS);
    ?>
    <item>
      <title><?= htmlspecialchars($p['title']) ?></title>
      <link><?= htmlspecialchars($url) ?></link>
      <guid><?= htmlspecialchars($url) ?></guid>
      <pubDate><?= $date ?></pubDate>
      <description><?= $desc ?></description>
    </item>
    <?php endforeach ?>
  </channel>
</rss>
