<?php
require 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

$config = get_telegram_config();
$expected_secret = $config['webhook_secret'] ?? '';
$received_secret = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
if ($expected_secret === '' || !hash_equals($expected_secret, $received_secret)) {
    http_response_code(403);
    echo json_encode(['ok' => false]);
    exit;
}

$update = json_decode(file_get_contents('php://input'), true);
if (!is_array($update)) {
    echo json_encode(['ok' => true]);
    exit;
}

$message = $update['message'] ?? null;
if (!$message) {
    echo json_encode(['ok' => true]);
    exit;
}

$chat_id = $message['chat']['id'] ?? null;
$user_id = $message['from']['id'] ?? null;
$text = trim($message['text'] ?? $message['caption'] ?? '');

if (!$chat_id || !$user_id || $text === '') {
    echo json_encode(['ok' => true]);
    exit;
}

if (preg_match('/^\/id(?:@\w+)?$/i', $text)) {
    telegram_reply($chat_id, 'Your Telegram user ID is <code>' . htmlspecialchars((string) $user_id, ENT_QUOTES) . '</code>. Add this ID in the site admin.');
    echo json_encode(['ok' => true]);
    exit;
}

if (preg_match('/^\/help(?:@\w+)?$/i', $text)) {
    telegram_reply($chat_id, "Send a blog post like this:\n\nTitle on the first line\nPost text below it\n\nUse /draft before the title to save as draft.");
    echo json_encode(['ok' => true]);
    exit;
}

if (!telegram_user_is_allowed($user_id)) {
    telegram_reply($chat_id, 'This Telegram user is not allowed to create posts. Send /id and add that ID in the site admin.');
    echo json_encode(['ok' => true]);
    exit;
}

$result = telegram_create_post_from_text($text);
if (empty($result['ok'])) {
    telegram_reply($chat_id, $result['description'] ?? 'Could not create the post.');
    echo json_encode(['ok' => true]);
    exit;
}

$url = base_url() . 'post.php?slug=' . urlencode($result['slug']);
$status = $result['published'] ? 'published' : 'saved as draft';
telegram_reply($chat_id, 'Post ' . $status . ': <b>' . htmlspecialchars($result['title'], ENT_QUOTES) . '</b>' . "\n" . htmlspecialchars($url, ENT_QUOTES));

echo json_encode(['ok' => true]);
