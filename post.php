<?php
session_cache_limiter('');
session_start();
require 'config.php';
require 'theme.php';
public_cache_headers();

$slug = preg_replace('/[^a-z0-9-]/', '', $_GET['slug'] ?? '');
$file = POSTS_DIR . $slug . '.txt';
if (!$slug || !file_exists($file)) { header('Location: 404.php'); exit; }

$post = parse_post($file);
if (!$post['published'] && !is_logged_in()) { header('Location: 404.php'); exit; }

if (!is_logged_in()) increment_views($slug);
$adjacent = get_adjacent_posts($slug);
$content = render_markdown($post['content']);

html_head($post['title'], [
    'description' => make_excerpt($post['content']),
    'og_type' => 'article',
    'canonical' => base_url() . 'post.php?slug=' . urlencode($slug),
]);

render_nav();
?>

<section class="article-page">
  <a class="back-link" href="index.php">&larr; All posts</a>

  <h1><?= htmlspecialchars($post['title']) ?></h1>
  <small><?= htmlspecialchars($post['date']) ?> &middot; <?= $post['reading_time'] ?></small>

  <?php if (!empty($post['tags'])): ?>
  <p>
    <?php foreach ($post['tags'] as $t): ?>
      <span class="tag"><?= htmlspecialchars($t) ?></span>
    <?php endforeach ?>
  </p>
  <?php endif; ?>

  <div class="prose"><?= $content ?></div>

  <?php if ($adjacent['prev'] || $adjacent['next']): ?>
  <nav class="post-nav">
    <div class="post-nav-side">
      <?php if ($adjacent['prev']): ?>
        <a href="post.php?slug=<?= htmlspecialchars($adjacent['prev']['slug']) ?>">&larr; <?= htmlspecialchars($adjacent['prev']['title']) ?></a>
      <?php endif; ?>
    </div>
    <div class="post-nav-side" style="text-align:right">
      <?php if ($adjacent['next']): ?>
        <a href="post.php?slug=<?= htmlspecialchars($adjacent['next']['slug']) ?>"><?= htmlspecialchars($adjacent['next']['title']) ?> &rarr;</a>
      <?php endif; ?>
    </div>
  </nav>
  <?php endif; ?>
</section>

<?php render_footer(); ?>