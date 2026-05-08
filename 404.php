<?php
session_cache_limiter('');
session_start();
require 'config.php';
require 'theme.php';
public_cache_headers();
http_response_code(404);
$recent = get_all_posts(true, 3);
html_head('Page Not Found');
?>

<nav class="nav">
  <a class="nav-logo" href="index.php"><?= htmlspecialchars(SITE_NAME) ?></a>
  <div class="nav-links">
    <a href="index.php">Writing</a>
  </div>
</nav>
<div class="error-page">
  <h1>404</h1>
  <h2>Page not found</h2>
  <p>This page doesn't exist or has been moved.</p>
  <a href="index.php" class="btn btn-primary">Back to home</a>
  <?php if (!empty($recent)): ?>
    <div style="margin-top:3rem;text-align:left;max-width:400px;margin:3rem auto 0">
      <div style="font-family:var(--mono);font-size:0.7rem;text-transform:uppercase;letter-spacing:0.1em;color:var(--muted);margin-bottom:1rem">Recent articles</div>
      <?php foreach ($recent as $p): ?>
        <div style="padding:0.6rem 0;border-bottom:1px solid var(--border)">
          <a href="post.php?slug=<?= urlencode($p['slug']) ?>"
             style="font-weight:500;font-size:0.9rem;color:var(--text);transition:color 0.15s"
             onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='var(--text)'">
            <?= htmlspecialchars($p['title']) ?>
          </a>
          <div style="font-family:var(--mono);font-size:0.7rem;color:var(--muted);margin-top:0.15rem"><?= htmlspecialchars($p['date']) ?></div>
        </div>
      <?php endforeach ?>
    </div>
  <?php endif ?>
</div>
<footer class="footer">
  <span>&copy; <?= date('Y') ?> <?= htmlspecialchars(SITE_NAME) ?></span>
</footer>
</body></html>
