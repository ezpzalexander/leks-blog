<?php
session_start();
require 'config.php';

$action = $_POST['action'] ?? '';
$notice = '';
$notice_type = 'success';

// -- Auth --------------------------------------------------------------------
if ($action === 'login') {
    $u = trim($_POST['username'] ?? '');
    $p = $_POST['password'] ?? '';
    if ($u === ADMIN_USER && hash('sha256', $p) === PASS_HASH) {
        $_SESSION['admin'] = true; header('Location: admin.php'); exit;
    } else { $notice = 'Incorrect credentials.'; $notice_type = 'error'; }
}
if ($action === 'logout') { session_destroy(); header('Location: admin.php'); exit; }

if (!is_logged_in()) {
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Admin - '.SITE_NAME.'</title><link rel="stylesheet" href="assets/style.css?v=20260510"></head><body style="display:flex;align-items:center;justify-content:center;min-height:100vh;padding:1rem"><div style="max-width:340px;width:100%"><h1>Admin</h1><p style="margin-bottom:1.5rem;color:var(--muted)">'.SITE_NAME.'</p>';
    if ($notice) echo '<div style="padding:.5rem .75rem;margin-bottom:1rem;background:rgba(200,100,100,.2);border-radius:4px;color:#f88">'.$notice.'</div>';
    echo '<form method="POST"><input type="hidden" name="action" value="login"><div style="margin-bottom:1rem"><label style="display:block;margin-bottom:.25rem;font-size:.8rem">Username</label><input type="text" name="username" required style="width:100%;padding:.5rem;border:1px solid var(--border);border-radius:4px;background:var(--bg);color:var(--text)"></div><div style="margin-bottom:1rem"><label style="display:block;margin-bottom:.25rem;font-size:.8rem">Password</label><input type="password" name="password" required style="width:100%;padding:.5rem;border:1px solid var(--border);border-radius:4px;background:var(--bg);color:var(--text)"></div><button type="submit" style="width:100%;padding:.65rem;background:var(--accent);color:#fff;border:none;border-radius:4px;cursor:pointer">Sign in</button></form></div></body></html>';
    exit;
}

$view = $_GET['view'] ?? 'posts';

// -- Save post ---------------------------------------------------------------
if ($action === 'save_post') {
    $old_slug = $_POST['old_slug'] ?? '';
    $title    = trim($_POST['title'] ?? 'Untitled');
    $new_slug = slug($title);
    if ($old_slug && $old_slug !== $new_slug && file_exists(POSTS_DIR . $old_slug . '.txt'))
        unlink(POSTS_DIR . $old_slug . '.txt');
    $final = $old_slug && file_exists(POSTS_DIR . $old_slug . '.txt') ? $old_slug : $new_slug;
    $i = 2; while (file_exists(POSTS_DIR . $final . '.txt') && $final !== $old_slug) $final = $new_slug . '-' . $i++;
    save_post($final, $title, $_POST['date'] ?? date('Y-m-d'), isset($_POST['published']),
        array_values(array_filter(array_map('trim', explode(',', $_POST['tags'] ?? '')))),
        $_POST['content'] ?? '');
    $notice = 'Article saved.';
    $telegram_config = get_telegram_config();
    if (isset($_POST['published']) && $telegram_config['auto_send_on_publish'] && telegram_is_configured() && !telegram_post_was_sent($final)) {
        $telegram_response = telegram_send_post($final);
        if (!empty($telegram_response['ok'])) {
            telegram_mark_post_sent($final);
            $notice .= ' Sent to Telegram.';
        } else {
            $notice .= ' Telegram failed: ' . ($telegram_response['description'] ?? 'Unknown error.');
            $notice_type = 'error';
        }
    }
    $view = 'post-editor';
    $_GET['slug'] = $final;
}

// -- Telegram settings & sending -------------------------------------------
if ($action === 'save_telegram') {
    save_telegram_config($_POST);
    $notice = 'Telegram settings saved.';
    $view = 'telegram';
}

if ($action === 'test_telegram') {
    $response = telegram_send_message('Test message from ' . SITE_NAME . ' admin.');
    if (!empty($response['ok'])) {
        $notice = 'Test message sent to Telegram.';
    } else {
        $notice = 'Telegram failed: ' . ($response['description'] ?? 'Unknown error.');
        $notice_type = 'error';
    }
    $view = 'telegram';
}

if ($action === 'set_telegram_webhook') {
    $response = telegram_set_webhook(telegram_webhook_url());
    if (!empty($response['ok'])) {
        $notice = 'Telegram webhook activated.';
    } else {
        $notice = 'Webhook failed: ' . ($response['description'] ?? 'Unknown error.');
        $notice_type = 'error';
    }
    $view = 'telegram';
}

if ($action === 'clear_telegram_webhook') {
    $response = telegram_set_webhook('');
    if (!empty($response['ok'])) {
        $notice = 'Telegram webhook removed.';
    } else {
        $notice = 'Webhook failed: ' . ($response['description'] ?? 'Unknown error.');
        $notice_type = 'error';
    }
    $view = 'telegram';
}

if ($action === 'send_post_telegram') {
    $s = preg_replace('/[^a-z0-9-]/', '', $_POST['slug'] ?? '');
    $response = telegram_send_post($s);
    if (!empty($response['ok'])) {
        telegram_mark_post_sent($s);
        $notice = 'Article sent to Telegram.';
    } else {
        $notice = 'Telegram failed: ' . ($response['description'] ?? 'Unknown error.');
        $notice_type = 'error';
    }
    $view = $_POST['return_view'] ?? 'posts';
    if (!empty($_POST['return_slug'])) $_GET['slug'] = preg_replace('/[^a-z0-9-]/', '', $_POST['return_slug']);
}

// -- Delete post -------------------------------------------------------------
if ($action === 'delete_post') {
    $s = preg_replace('/[^a-z0-9-]/', '', $_POST['slug'] ?? '');
    if ($s && file_exists(POSTS_DIR . $s . '.txt')) unlink(POSTS_DIR . $s . '.txt');
    if (file_exists(POSTS_CACHE_FILE)) unlink(POSTS_CACHE_FILE);
    header('Location: admin.php?view=posts'); exit;
}

// -- Load items for editing --------------------------------------------------
$edit_post = null;
if ($view === 'post-editor' && isset($_GET['slug'])) {
    $f = POSTS_DIR . preg_replace('/[^a-z0-9-]/', '', $_GET['slug']) . '.txt';
    if (file_exists($f)) $edit_post = parse_post($f);
}

$pending = count_pending_comments();
echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Admin - '.SITE_NAME.'</title><link rel="stylesheet" href="assets/style.css?v=20260510"></head><body>';
?>

<main style="max-width:800px;margin:0 auto;padding:1rem">
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:2rem;padding-bottom:1rem;border-bottom:1px solid var(--border)">
  <span style="font-weight:700;font-size:1.25rem"><?= SITE_NAME ?> Admin</span>
  <div>
    <a href="admin.php?view=posts" style="margin-right:1rem;color:var(--accent)">Writing</a>
    <a href="admin.php?view=telegram" style="margin-right:1rem;color:var(--accent)">Telegram</a>
    <form method="POST" style="display:inline"><input type="hidden" name="action" value="logout"><button type="submit" style="background:none;border:none;color:var(--muted);cursor:pointer">Log out</button></form>
  </div>
</div>

<?php if ($notice): ?>
<div style="padding:.5rem .75rem;margin-bottom:1rem;background:<?= $notice_type === 'error' ? 'rgba(200,100,100,.2)' : 'rgba(100,150,100,.2)' ?>;border-radius:4px"><?= htmlspecialchars($notice) ?></div>
<?php endif ?>

<?php if ($view === 'posts'): ?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem">
  <h2 style="margin:0">Writing</h2>
  <a href="admin.php?view=post-editor" style="padding:.5rem 1rem;background:var(--accent);color:#fff;border-radius:4px;text-decoration:none">+ New article</a>
</div>
<?php $all_posts = get_all_posts(false); ?>
<?php if (empty($all_posts)): ?>
<div style="padding:2rem;text-align:center;border:1px dashed var(--border);border-radius:4px;color:var(--muted)">No articles yet.</div>
<?php else: ?>
<?php foreach ($all_posts as $p): ?>
<div style="display:flex;justify-content:space-between;align-items:center;padding:.75rem 0;border-bottom:1px solid var(--border)">
  <div>
    <div style="font-weight:600"><?= htmlspecialchars($p['title']) ?></div>
    <div style="font-size:.8rem;color:var(--muted)"><?= htmlspecialchars($p['date']) ?> &middot; <?= $p['reading_time'] ?></div>
  </div>
  <div style="display:flex;gap:.5rem">
    <span style="font-size:.7rem;padding:.15rem .35rem;border:1px solid var(--border);border-radius:3px;color:var(--muted)"><?= $p['published'] ? 'Published' : 'Draft' ?></span>
    <?php if ($p['published']): ?>
    <form method="POST" style="display:inline">
      <input type="hidden" name="action" value="send_post_telegram">
      <input type="hidden" name="slug" value="<?= htmlspecialchars($p['slug']) ?>">
      <input type="hidden" name="return_view" value="posts">
      <button type="submit" style="padding:.25rem .5rem;border:1px solid var(--border);border-radius:4px;background:transparent;color:var(--text);font-size:.8rem;cursor:pointer"><?= telegram_post_was_sent($p['slug']) ? 'Resend Telegram' : 'Send Telegram' ?></button>
    </form>
    <?php endif ?>
    <a href="admin.php?view=post-editor&slug=<?= urlencode($p['slug']) ?>" style="padding:.25rem .5rem;border:1px solid var(--border);border-radius:4px;text-decoration:none;color:var(--text);font-size:.8rem">Edit</a>
    <a href="post.php?slug=<?= urlencode($p['slug']) ?>" style="padding:.25rem .5rem;border:1px solid var(--border);border-radius:4px;text-decoration:none;color:var(--text);font-size:.8rem" target="_blank">View</a>
  </div>
</div>
<?php endforeach ?>
<?php endif ?>

<?php elseif ($view === 'post-editor'): ?>
<?php $ep = $edit_post; ?>
<form method="POST" action="admin.php?view=post-editor<?= $ep ? '&slug='.urlencode($ep['slug']) : '' ?>">
  <input type="hidden" name="action" value="save_post">
  <input type="hidden" name="old_slug" value="<?= $ep ? htmlspecialchars($ep['slug']) : '' ?>">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem">
    <h2 style="margin:0"><?= $ep ? 'Edit Article' : 'New Article' ?></h2>
    <div style="display:flex;gap:.5rem">
      <a href="admin.php?view=posts" style="padding:.5rem 1rem;border:1px solid var(--border);border-radius:4px;text-decoration:none;color:var(--text)">&larr; Back</a>
      <button type="submit" style="padding:.5rem 1rem;background:var(--accent);color:#fff;border:none;border-radius:4px;cursor:pointer">Save</button>
    </div>
  </div>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:1rem">
    <div style="grid-column:span 2"><label style="display:block;margin-bottom:.25rem;font-size:.8rem">Title</label><input type="text" name="title" required value="<?= $ep ? htmlspecialchars($ep['title']) : '' ?>" style="width:100%;padding:.5rem;border:1px solid var(--border);border-radius:4px;background:var(--bg);color:var(--text)"></div>
    <div><label style="display:block;margin-bottom:.25rem;font-size:.8rem">Date</label><input type="date" name="date" value="<?= $ep ? htmlspecialchars($ep['date']) : date('Y-m-d') ?>" style="width:100%;padding:.5rem;border:1px solid var(--border);border-radius:4px;background:var(--bg);color:var(--text)"></div>
    <div><label style="display:block;margin-bottom:.25rem;font-size:.8rem">Tags</label><input type="text" name="tags" placeholder="php, tutorial" value="<?= $ep ? htmlspecialchars(implode(', ', $ep['tags'])) : '' ?>" style="width:100%;padding:.5rem;border:1px solid var(--border);border-radius:4px;background:var(--bg);color:var(--text)"></div>
  </div>
  <div style="margin-bottom:1rem"><label style="display:block;margin-bottom:.25rem;font-size:.8rem">Content (Markdown)</label><textarea name="content" style="width:100%;min-height:300px;padding:.75rem;border:1px solid var(--border);border-radius:4px;background:var(--bg);color:var(--text);font-family:inherit;line-height:1.6"><?= $ep ? htmlspecialchars($ep['content']) : '' ?></textarea></div>
  <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.5rem">
    <label><input type="checkbox" name="published" <?= ($ep && $ep['published']) ? 'checked' : '' ?>> Published</label>
    <?php if ($ep): ?>
    <button type="button" style="margin-left:auto;padding:.25rem .5rem;background:rgba(200,100,100,.2);color:#f88;border:none;border-radius:4px;cursor:pointer" onclick="if(confirm('Delete this article?'))document.getElementById('del-post').submit()">Delete</button>
    <?php endif ?>
  </div>
</form>
<?php if ($ep): ?>
<?php if ($ep['published']): ?>
<form method="POST" style="margin-top:-.75rem;margin-bottom:1rem">
  <input type="hidden" name="action" value="send_post_telegram">
  <input type="hidden" name="slug" value="<?= htmlspecialchars($ep['slug']) ?>">
  <input type="hidden" name="return_view" value="post-editor">
  <input type="hidden" name="return_slug" value="<?= htmlspecialchars($ep['slug']) ?>">
  <button type="submit" style="padding:.5rem 1rem;border:1px solid var(--border);border-radius:4px;background:transparent;color:var(--text);cursor:pointer"><?= telegram_post_was_sent($ep['slug']) ? 'Resend to Telegram' : 'Send to Telegram' ?></button>
</form>
<?php endif ?>
<form id="del-post" method="POST"><input type="hidden" name="action" value="delete_post"><input type="hidden" name="slug" value="<?= htmlspecialchars($ep['slug']) ?>"></form>
<?php endif ?>

<?php elseif ($view === 'telegram'): ?>
<?php $tg = get_telegram_config(); ?>
<?php $webhook_info = telegram_get_webhook_info(); ?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem">
  <h2 style="margin:0">Telegram</h2>
  <form method="POST" style="display:inline">
    <input type="hidden" name="action" value="test_telegram">
    <button type="submit" style="padding:.5rem 1rem;border:1px solid var(--border);border-radius:4px;background:transparent;color:var(--text);cursor:pointer">Send test</button>
  </form>
</div>

<form method="POST" style="margin-bottom:2rem">
  <input type="hidden" name="action" value="save_telegram">
  <div style="margin-bottom:1rem">
    <label style="display:block;margin-bottom:.25rem;font-size:.8rem">Bot token</label>
    <input type="password" name="bot_token" value="<?= htmlspecialchars($tg['bot_token']) ?>" placeholder="123456:ABC..." style="width:100%;padding:.5rem;border:1px solid var(--border);border-radius:4px;background:var(--bg);color:var(--text)">
  </div>
  <div style="margin-bottom:1rem">
    <label style="display:block;margin-bottom:.25rem;font-size:.8rem">Chat ID or channel username</label>
    <input type="text" name="chat_id" value="<?= htmlspecialchars($tg['chat_id']) ?>" placeholder="@channelname or -1001234567890" style="width:100%;padding:.5rem;border:1px solid var(--border);border-radius:4px;background:var(--bg);color:var(--text)">
  </div>
  <div style="margin-bottom:1rem">
    <label style="display:block;margin-bottom:.25rem;font-size:.8rem">Allowed Telegram user IDs</label>
    <input type="text" name="allowed_user_ids" value="<?= htmlspecialchars($tg['allowed_user_ids']) ?>" placeholder="123456789" style="width:100%;padding:.5rem;border:1px solid var(--border);border-radius:4px;background:var(--bg);color:var(--text)">
  </div>
  <div style="margin-bottom:1rem">
    <label style="display:block;margin-bottom:.25rem;font-size:.8rem">Webhook secret</label>
    <input type="text" name="webhook_secret" value="<?= htmlspecialchars($tg['webhook_secret']) ?>" style="width:100%;padding:.5rem;border:1px solid var(--border);border-radius:4px;background:var(--bg);color:var(--text)">
  </div>
  <div style="margin-bottom:1.5rem">
    <label><input type="checkbox" name="auto_send_on_publish" <?= $tg['auto_send_on_publish'] ? 'checked' : '' ?>> Automatically send new published articles once</label>
  </div>
  <div style="margin-bottom:1.5rem">
    <label><input type="checkbox" name="publish_from_telegram" <?= $tg['publish_from_telegram'] ? 'checked' : '' ?>> Publish posts created from Telegram immediately</label>
  </div>
  <button type="submit" style="padding:.5rem 1rem;background:var(--accent);color:#fff;border:none;border-radius:4px;cursor:pointer">Save Telegram settings</button>
</form>

<div style="padding:1rem;border:1px solid var(--border);border-radius:4px;margin-bottom:1rem;line-height:1.6">
  <strong>Incoming posts from Telegram</strong><br>
  Webhook URL: <code><?= htmlspecialchars(telegram_webhook_url()) ?></code><br>
  Current webhook: <code><?= htmlspecialchars($webhook_info['result']['url'] ?? '') ?></code>
  <?php if (!empty($webhook_info['result']['last_error_message'])): ?>
    <br><span style="color:#f88">Last error: <?= htmlspecialchars($webhook_info['result']['last_error_message']) ?></span>
  <?php endif ?>
  <div style="display:flex;gap:.5rem;margin-top:1rem">
    <form method="POST">
      <input type="hidden" name="action" value="set_telegram_webhook">
      <button type="submit" style="padding:.5rem 1rem;background:var(--accent);color:#fff;border:none;border-radius:4px;cursor:pointer">Activate webhook</button>
    </form>
    <form method="POST">
      <input type="hidden" name="action" value="clear_telegram_webhook">
      <button type="submit" style="padding:.5rem 1rem;border:1px solid var(--border);border-radius:4px;background:transparent;color:var(--text);cursor:pointer">Remove webhook</button>
    </form>
  </div>
</div>

<div style="padding:1rem;border:1px solid var(--border);border-radius:4px;color:var(--muted);line-height:1.6">
  <strong style="color:var(--text)">Setup</strong><br>
  1. Create a bot in Telegram via @BotFather and paste the token here.<br>
  2. Save once, then send <code>/id</code> to your bot in Telegram. The bot replies with your user ID after the webhook is active.<br>
  3. Paste your user ID into Allowed Telegram user IDs and save again.<br>
  4. Activate the webhook on the hosted HTTPS version of the site.<br>
  5. Send a message to the bot: first line is the blog title, the rest is the post.
</div>
<?php endif ?>

</main>
</body></html>
