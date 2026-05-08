<?php
// -- Site settings -----------------------------------------------------------
define('SITE_NAME',    'leks');
define('SITE_TAGLINE', 'building cool stuff');
define('SITE_BIO',     'software engineer. building things for the future.');
define('SITE_EMAIL',   'you@example.com');
define('ADMIN_USER',   'admin');
define('PASS_HASH',    'a665a45920422f9d417e4867efdc4fb8a04a1f3fff1fa07e998e86f7f7a27ae3');

// -- Paths -------------------------------------------------------------------
define('PROJECTS_DIR', __DIR__ . '/projects/');
define('POSTS_DIR',    __DIR__ . '/posts/');
define('UPLOADS_DIR',  __DIR__ . '/uploads/');
define('UPLOADS_URL',  'uploads/');
define('STATS_FILE',   __DIR__ . '/data/stats.json');
define('COMMENTS_DIR', __DIR__ . '/data/comments/');
define('POSTS_CACHE_FILE', __DIR__ . '/data/posts-cache.json');
define('TELEGRAM_CONFIG_FILE', __DIR__ . '/data/telegram-config.json');
define('TELEGRAM_SENT_FILE',   __DIR__ . '/data/telegram-sent.json');

// -- Auth --------------------------------------------------------------------
function is_logged_in() {
    return isset($_SESSION['admin']) && $_SESSION['admin'] === true;
}
function require_login() {
    if (!is_logged_in()) { header('Location: admin.php'); exit; }
}
function public_cache_headers($seconds = 300) {
    if (is_logged_in()) {
        header('Cache-Control: no-store, must-revalidate');
    } else {
        header('Cache-Control: public, max-age=' . (int) $seconds);
    }
}

// -- Helpers -----------------------------------------------------------------
function slug($title) {
    $s = strtolower(trim($title));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim($s, '-') ? trim($s, '-') : 'untitled';
}
function reading_time($content) {
    $words = str_word_count(strip_tags($content));
    return max(1, (int) round($words / 200)) . ' min read';
}
function make_excerpt($content, $length = 160) {
    $text = preg_replace('/!\[.*?\]\(.*?\)/', '', $content);
    $text = preg_replace('/\[([^\]]+)\]\([^\)]+\)/', '$1', $text);
    $text = preg_replace('/[#*`_>~]+/', '', $text);
    $text = preg_replace('/\s+/', ' ', trim(strip_tags($text)));
    return mb_substr($text, 0, $length);
}
function base_url() {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path   = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
    return $scheme . '://' . $host . $path . '/';
}

// -- Projects ----------------------------------------------------------------
// Format: title, description, image, tags, url, github, featured, date
function parse_project($file) {
    $raw   = file_get_contents($file);
    $parts = explode("\n---\n", $raw, 2);
    $meta  = [];
    foreach (explode("\n", $parts[0] ?? '') as $line) {
        if (strpos($line, ':') !== false) {
            [$k, $v] = explode(':', $line, 2);
            $meta[trim($k)] = trim($v);
        }
    }
    $tags = [];
    if (!empty($meta['tags'])) {
        $tags = array_values(array_filter(array_map('trim', explode(',', $meta['tags']))));
    }
    return [
        'file'        => $file,
        'slug'        => basename($file, '.txt'),
        'title'       => $meta['title']       ?? 'Untitled',
        'description' => $meta['description'] ?? '',
        'image'       => $meta['image']        ?? '',
        'tags'        => $tags,
        'url'         => $meta['url']          ?? '',
        'github'      => $meta['github']       ?? '',
        'featured'    => ($meta['featured']    ?? 'false') === 'true',
        'published'   => ($meta['published']   ?? 'false') === 'true',
        'date'        => $meta['date']         ?? '',
        'content'     => $parts[1]             ?? '',
    ];
}
function get_all_projects($published_only = true) {
    $files = glob(PROJECTS_DIR . '*.txt') ?: [];
    $projects = array_map('parse_project', $files);
    if ($published_only) $projects = array_filter($projects, fn($p) => $p['published']);
    usort($projects, fn($a, $b) => ($b['featured'] <=> $a['featured']) ?: strcmp($b['date'], $a['date']));
    return array_values($projects);
}
function save_project($slug, $data, $content) {
    $meta  = "title: {$data['title']}\n";
    $meta .= "description: {$data['description']}\n";
    $meta .= "image: {$data['image']}\n";
    $meta .= "tags: " . implode(', ', $data['tags']) . "\n";
    $meta .= "url: {$data['url']}\n";
    $meta .= "github: {$data['github']}\n";
    $meta .= "featured: " . ($data['featured'] ? 'true' : 'false') . "\n";
    $meta .= "published: " . ($data['published'] ? 'true' : 'false') . "\n";
    $meta .= "date: {$data['date']}";
    file_put_contents(PROJECTS_DIR . $slug . '.txt', $meta . "\n---\n" . $content);
}

// -- Articles (posts) --------------------------------------------------------
function parse_post($file) {
    $raw   = file_get_contents($file);
    $parts = explode("\n---\n", $raw, 2);
    $meta  = [];
    foreach (explode("\n", $parts[0] ?? '') as $line) {
        if (strpos($line, ':') !== false) {
            [$k, $v] = explode(':', $line, 2);
            $meta[trim($k)] = trim($v);
        }
    }
    $tags = [];
    if (!empty($meta['tags'])) {
        $tags = array_values(array_filter(array_map('trim', explode(',', $meta['tags']))));
    }
    return [
        'file'         => $file,
        'slug'         => basename($file, '.txt'),
        'title'        => $meta['title']     ?? 'Untitled',
        'date'         => $meta['date']       ?? '',
        'published'    => ($meta['published'] ?? 'false') === 'true',
        'tags'         => $tags,
        'content'      => $parts[1]           ?? '',
        'reading_time' => reading_time($parts[1] ?? ''),
    ];
}
function get_all_posts($published_only = true, $limit = 0) {
    $files = glob(POSTS_DIR . '*.txt') ?: [];
    $fingerprint = implode('|', array_map(fn($f) => basename($f) . ':' . filemtime($f) . ':' . filesize($f), $files));
    $cache = file_exists(POSTS_CACHE_FILE) ? (json_decode(file_get_contents(POSTS_CACHE_FILE), true) ?: []) : [];
    if (($cache['fingerprint'] ?? '') === $fingerprint && isset($cache['posts'])) {
        $posts = $cache['posts'];
    } else {
        $posts = array_map('parse_post', $files);
        if (!is_dir(dirname(POSTS_CACHE_FILE))) mkdir(dirname(POSTS_CACHE_FILE), 0755, true);
        file_put_contents(POSTS_CACHE_FILE, json_encode(['fingerprint' => $fingerprint, 'posts' => $posts]), LOCK_EX);
    }
    if ($published_only) $posts = array_filter($posts, fn($p) => $p['published']);
    usort($posts, fn($a, $b) => strcmp($b['date'], $a['date']));
    $posts = array_values($posts);
    return $limit > 0 ? array_slice($posts, 0, $limit) : $posts;
}
function save_post($slug, $title, $date, $published, $tags, $content) {
    $meta = "title: $title\ndate: $date\npublished: " . ($published ? 'true' : 'false') . "\ntags: " . implode(', ', $tags);
    file_put_contents(POSTS_DIR . $slug . '.txt', $meta . "\n---\n" . $content);
    if (file_exists(POSTS_CACHE_FILE)) unlink(POSTS_CACHE_FILE);
}
function get_adjacent_posts($current_slug) {
    $posts = get_all_posts(true);
    $idx   = array_search($current_slug, array_column($posts, 'slug'));
    return [
        'prev' => ($idx !== false && $idx + 1 < count($posts)) ? $posts[$idx + 1] : null,
        'next' => ($idx !== false && $idx > 0)                  ? $posts[$idx - 1] : null,
    ];
}

// -- Telegram ---------------------------------------------------------------
function default_telegram_config() {
    return [
        'bot_token' => '',
        'chat_id' => '',
        'parse_mode' => 'HTML',
        'auto_send_on_publish' => false,
        'allowed_user_ids' => '',
        'webhook_secret' => '',
        'publish_from_telegram' => true,
    ];
}
function get_telegram_config() {
    if (!file_exists(TELEGRAM_CONFIG_FILE)) return default_telegram_config();
    $data = json_decode(file_get_contents(TELEGRAM_CONFIG_FILE), true);
    return array_merge(default_telegram_config(), is_array($data) ? $data : []);
}
function save_telegram_config($data) {
    if (!is_dir(dirname(TELEGRAM_CONFIG_FILE))) mkdir(dirname(TELEGRAM_CONFIG_FILE), 0755, true);
    $current = get_telegram_config();
    $secret = trim($data['webhook_secret'] ?? ($current['webhook_secret'] ?? ''));
    if ($secret === '') {
        $secret = bin2hex(random_bytes(24));
    }
    $config = [
        'bot_token' => trim($data['bot_token'] ?? ''),
        'chat_id' => trim($data['chat_id'] ?? ''),
        'parse_mode' => 'HTML',
        'auto_send_on_publish' => !empty($data['auto_send_on_publish']),
        'allowed_user_ids' => trim($data['allowed_user_ids'] ?? ''),
        'webhook_secret' => preg_replace('/[^A-Za-z0-9_-]/', '', $secret),
        'publish_from_telegram' => !empty($data['publish_from_telegram']),
    ];
    file_put_contents(TELEGRAM_CONFIG_FILE, json_encode($config, JSON_PRETTY_PRINT), LOCK_EX);
}
function telegram_is_configured() {
    $config = get_telegram_config();
    return $config['bot_token'] !== '' && $config['chat_id'] !== '';
}
function telegram_bot_api_request($method, $payload = []) {
    $config = get_telegram_config();
    if ($config['bot_token'] === '') {
        return ['ok' => false, 'description' => 'Telegram bot token is not configured yet.'];
    }

    $url = 'https://api.telegram.org/bot' . $config['bot_token'] . '/' . $method;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 15,
        ]);
        $raw = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        if ($raw === false) return ['ok' => false, 'description' => $error ?: 'Could not reach Telegram.'];
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => json_encode($payload),
                'timeout' => 15,
            ],
        ]);
        $raw = @file_get_contents($url, false, $context);
        if ($raw === false) return ['ok' => false, 'description' => 'Could not reach Telegram.'];
    }

    $response = json_decode($raw, true);
    return is_array($response) ? $response : ['ok' => false, 'description' => 'Telegram returned an invalid response.'];
}
function telegram_api_request($method, $payload) {
    $config = get_telegram_config();
    if ($config['chat_id'] === '') {
        return ['ok' => false, 'description' => 'Telegram chat ID is not configured yet.'];
    }
    return telegram_bot_api_request($method, array_merge(['chat_id' => $config['chat_id']], $payload));
}
function telegram_message_for_post($post) {
    $url = base_url() . 'post.php?slug=' . urlencode($post['slug']);
    $excerpt = make_excerpt($post['content'], 220);
    $message = '<b>' . htmlspecialchars($post['title'], ENT_QUOTES) . '</b>';
    if ($excerpt !== '') $message .= "\n\n" . htmlspecialchars($excerpt, ENT_QUOTES);
    $message .= "\n\n" . '<a href="' . htmlspecialchars($url, ENT_QUOTES) . '">Lees de post</a>';
    return $message;
}
function telegram_send_message($message) {
    return telegram_api_request('sendMessage', [
        'text' => $message,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => false,
    ]);
}
function telegram_send_post($slug) {
    $safe_slug = preg_replace('/[^a-z0-9-]/', '', $slug);
    $file = POSTS_DIR . $safe_slug . '.txt';
    if (!$safe_slug || !file_exists($file)) return ['ok' => false, 'description' => 'Post not found.'];
    $post = parse_post($file);
    if (!$post['published']) return ['ok' => false, 'description' => 'Publish the post before sending it to Telegram.'];
    return telegram_send_message(telegram_message_for_post($post));
}
function telegram_sent_posts() {
    if (!file_exists(TELEGRAM_SENT_FILE)) return [];
    $data = json_decode(file_get_contents(TELEGRAM_SENT_FILE), true);
    return is_array($data) ? $data : [];
}
function telegram_mark_post_sent($slug) {
    if (!is_dir(dirname(TELEGRAM_SENT_FILE))) mkdir(dirname(TELEGRAM_SENT_FILE), 0755, true);
    $sent = telegram_sent_posts();
    $sent[$slug] = date('c');
    file_put_contents(TELEGRAM_SENT_FILE, json_encode($sent, JSON_PRETTY_PRINT), LOCK_EX);
}
function telegram_post_was_sent($slug) {
    $sent = telegram_sent_posts();
    return isset($sent[$slug]);
}
function telegram_reply($chat_id, $message) {
    return telegram_bot_api_request('sendMessage', [
        'chat_id' => $chat_id,
        'text' => $message,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true,
    ]);
}
function telegram_allowed_user_ids() {
    $config = get_telegram_config();
    $ids = preg_split('/[\s,]+/', trim($config['allowed_user_ids']));
    return array_values(array_filter(array_map('trim', $ids ?: []), fn($id) => $id !== ''));
}
function telegram_user_is_allowed($user_id) {
    $allowed = telegram_allowed_user_ids();
    return in_array((string) $user_id, $allowed, true);
}
function telegram_webhook_url() {
    return base_url() . 'telegram_webhook.php';
}
function telegram_set_webhook($url = '') {
    $config = get_telegram_config();
    if (($config['webhook_secret'] ?? '') === '') {
        save_telegram_config($config);
        $config = get_telegram_config();
    }
    return telegram_bot_api_request('setWebhook', [
        'url' => $url,
        'secret_token' => $config['webhook_secret'],
        'allowed_updates' => ['message'],
        'drop_pending_updates' => true,
    ]);
}
function telegram_get_webhook_info() {
    return telegram_bot_api_request('getWebhookInfo');
}
function telegram_text_to_post($text) {
    $text = trim(str_replace(["\r\n", "\r"], "\n", $text));
    $published = true;

    if (preg_match('/^\/(post|draft)(?:@\w+)?\s*(.*)$/is', $text, $m)) {
        $published = strtolower($m[1]) === 'post';
        $text = trim($m[2]);
    }

    $lines = preg_split('/\n+/', $text, 2);
    $title = trim($lines[0] ?? '');
    $content = trim($lines[1] ?? '');

    if ($title === '') return null;
    if ($content === '') $content = $title;

    return [
        'title' => mb_substr($title, 0, 120),
        'content' => $content,
        'published' => $published,
    ];
}
function telegram_create_post_from_text($text) {
    $data = telegram_text_to_post($text);
    if (!$data) return ['ok' => false, 'description' => 'Send a title on the first line and the post content below it.'];
    $config = get_telegram_config();
    if (empty($config['publish_from_telegram'])) $data['published'] = false;

    $base_slug = slug($data['title']);
    $final = $base_slug;
    $i = 2;
    while (file_exists(POSTS_DIR . $final . '.txt')) $final = $base_slug . '-' . $i++;

    save_post($final, $data['title'], date('Y-m-d'), $data['published'], ['telegram'], $data['content']);
    return ['ok' => true, 'slug' => $final, 'title' => $data['title'], 'published' => $data['published']];
}

// -- Markdown renderer -------------------------------------------------------
function render_markdown($text) {
    $t = htmlspecialchars($text, ENT_QUOTES);
    $t = preg_replace_callback('/```(.*?)```/s', fn($m) => '<pre><code>' . $m[1] . '</code></pre>', $t);
    $t = preg_replace('/`([^`]+)`/', '<code>$1</code>', $t);
    $t = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $t);
    $t = preg_replace('/^## (.+)$/m',  '<h2>$1</h2>', $t);
    $t = preg_replace('/^# (.+)$/m',   '<h1>$1</h1>', $t);
    $t = preg_replace('/\*\*\*(.+?)\*\*\*/', '<strong><em>$1</em></strong>', $t);
    $t = preg_replace('/\*\*(.+?)\*\*/',     '<strong>$1</strong>', $t);
    $t = preg_replace('/\*(.+?)\*/',         '<em>$1</em>', $t);
    $t = preg_replace('/~~(.+?)~~/',         '<del>$1</del>', $t);
    $t = preg_replace('/^&gt; (.+)$/m',      '<blockquote>$1</blockquote>', $t);
    $t = preg_replace('/^---$/m',            '<hr>', $t);
    $t = preg_replace_callback('/(?:^[\-\*] .+\n?)+/m', fn($m) => '<ul>' . preg_replace('/^[\-\*] (.+)$/m', '<li>$1</li>', trim($m[0])) . '</ul>', $t);
    $t = preg_replace_callback('/(?:^\d+\. .+\n?)+/m',  fn($m) => '<ol>' . preg_replace('/^\d+\. (.+)$/m',  '<li>$1</li>', trim($m[0])) . '</ol>', $t);
    $t = preg_replace('/!\[([^\]]*)\]\(([^\)]+)\)/', '<img src="$2" alt="$1" loading="lazy" decoding="async">', $t);
    $t = preg_replace('/\[([^\]]+)\]\(([^\)]+)\)/',  '<a href="$2">$1</a>', $t);
    $lines = explode("\n", $t);
    $out = '';
    $block = ['<h1','<h2','<h3','<ul','<ol','<li','<blockquote','<pre','<hr','<img'];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') { $out .= "\n"; continue; }
        $is_block = false;
        foreach ($block as $tag) { if (strpos($line, $tag) === 0) { $is_block = true; break; } }
        $out .= $is_block ? $line . "\n" : '<p>' . $line . "</p>\n";
    }
    return $out;
}

// -- Stats & comments --------------------------------------------------------
function get_views($slug) {
    if (!file_exists(STATS_FILE)) return 0;
    return (json_decode(file_get_contents(STATS_FILE), true) ?? [])[$slug] ?? 0;
}
function increment_views($slug) {
    if (!is_dir(dirname(STATS_FILE))) mkdir(dirname(STATS_FILE), 0755, true);
    $data = file_exists(STATS_FILE) ? (json_decode(file_get_contents(STATS_FILE), true) ?? []) : [];
    $data[$slug] = ($data[$slug] ?? 0) + 1;
    file_put_contents(STATS_FILE, json_encode($data), LOCK_EX);
}
function get_comments($slug, $approved_only = true) {
    $file = COMMENTS_DIR . $slug . '.json';
    if (!file_exists($file)) return [];
    $c = json_decode(file_get_contents($file), true) ?? [];
    return $approved_only ? array_values(array_filter($c, fn($x) => $x['approved'])) : $c;
}
function add_comment($slug, $name, $content) {
    if (!is_dir(COMMENTS_DIR)) mkdir(COMMENTS_DIR, 0755, true);
    $file = COMMENTS_DIR . $slug . '.json';
    $c = file_exists($file) ? (json_decode(file_get_contents($file), true) ?? []) : [];
    $c[] = ['id' => uniqid(), 'name' => htmlspecialchars(trim($name), ENT_QUOTES),
            'content' => htmlspecialchars(trim($content), ENT_QUOTES),
            'date' => date('Y-m-d H:i'), 'approved' => false];
    file_put_contents($file, json_encode($c, JSON_PRETTY_PRINT), LOCK_EX);
}
function update_comment($slug, $id, $approved) {
    $file = COMMENTS_DIR . $slug . '.json';
    if (!file_exists($file)) return;
    $c = json_decode(file_get_contents($file), true) ?? [];
    foreach ($c as &$x) { if ($x['id'] === $id) $x['approved'] = $approved; }
    file_put_contents($file, json_encode($c, JSON_PRETTY_PRINT), LOCK_EX);
}
function delete_comment($slug, $id) {
    $file = COMMENTS_DIR . $slug . '.json';
    if (!file_exists($file)) return;
    $c = array_values(array_filter(json_decode(file_get_contents($file), true) ?? [], fn($x) => $x['id'] !== $id));
    file_put_contents($file, json_encode($c, JSON_PRETTY_PRINT), LOCK_EX);
}
function count_pending_comments() {
    $n = 0;
    foreach (glob(COMMENTS_DIR . '*.json') ?: [] as $f)
        foreach (json_decode(file_get_contents($f), true) ?? [] as $c)
            if (!$c['approved']) $n++;
    return $n;
}
