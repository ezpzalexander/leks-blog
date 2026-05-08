<?php
session_cache_limiter('');
session_start();
require 'config.php';
require 'theme.php';
public_cache_headers();

$posts = get_all_posts(true);

html_head('Writing', ['description' => 'Articles by ' . SITE_NAME]);

render_nav();
?>

<section class="hero">
  <h1><?= htmlspecialchars(SITE_NAME) ?></h1>
  <p><?= htmlspecialchars(SITE_BIO) ?></p>
</section>

<section class="section">
  <div class="section-header">
    <h2 class="section-title">Writing</h2>
    <small><?= count($posts) ?> posts</small>
  </div>
  <?php if (empty($posts)): ?>
    <small>No posts yet.</small>
  <?php else: ?>
    <div class="article-list">
      <?php foreach ($posts as $p): ?>
        <a href="post.php?slug=<?= urlencode($p['slug']) ?>" class="article-item">
          <span class="article-date"><?= htmlspecialchars($p['date']) ?></span>
          <span class="article-title"><?= htmlspecialchars($p['title']) ?></span>
        </a>
      <?php endforeach ?>
    </div>
  <?php endif ?>
</section>

<?php render_footer(); ?>