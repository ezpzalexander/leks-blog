<?php
session_cache_limiter('');
session_start();
require 'config.php';
require 'theme.php';
public_cache_headers();

$posts = get_all_posts(true);
html_head('Writing', ['description' => 'Articles and thoughts by ' . SITE_NAME]);
?>

<nav class="nav">
  <a class="nav-logo" href="index.php"><?= htmlspecialchars(SITE_NAME) ?></a>
  <div class="nav-links">
    <a href="index.php" class="active">Writing</a>
    <button class="dark-toggle" onclick="toggleDark()">&#9790;</button>
  </div>
</nav>

<section class="section">
  <div class="section-header">
    <h2 class="section-title">Writing</h2>
    <span style="font-family:var(--mono);font-size:0.72rem;color:var(--muted)"><?= count($posts) ?> articles</span>
  </div>
  <?php if (empty($posts)): ?>
    <div class="empty-state">No articles yet.</div>
  <?php else: ?>
    <div class="article-list">
      <?php foreach ($posts as $p): ?>
        <a href="post.php?slug=<?= urlencode($p['slug']) ?>" class="article-item">
          <span class="article-date"><?= htmlspecialchars($p['date']) ?></span>
          <span class="article-title"><?= htmlspecialchars($p['title']) ?></span>
          <span class="article-meta"><?= $p['reading_time'] ?></span>
        </a>
      <?php endforeach ?>
    </div>
  <?php endif ?>
</section>

<footer class="footer">
  <span>&copy; <?= date('Y') ?> <?= htmlspecialchars(SITE_NAME) ?></span>
  <a href="feed.php">RSS</a>
</footer>
</body></html>
