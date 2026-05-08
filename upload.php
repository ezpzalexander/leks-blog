<?php
session_start();
require 'config.php';
header('Content-Type: application/json');
if (!is_logged_in()) { echo json_encode(['error' => 'Not logged in']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['image'])) { echo json_encode(['error' => 'No file']); exit; }
$file = $_FILES['image'];
$allowed = ['image/jpeg','image/png','image/gif','image/webp'];
if ($file['error'] !== UPLOAD_ERR_OK) { echo json_encode(['error' => 'Upload error']); exit; }
if (!in_array($file['type'], $allowed)) { echo json_encode(['error' => 'Only JPG/PNG/GIF/WebP']); exit; }
if ($file['size'] > 5 * 1024 * 1024) { echo json_encode(['error' => 'Max 5MB']); exit; }
if (!is_dir(UPLOADS_DIR)) mkdir(UPLOADS_DIR, 0755, true);
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$name = date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
if (!move_uploaded_file($file['tmp_name'], UPLOADS_DIR . $name)) { echo json_encode(['error' => 'Save failed']); exit; }
echo json_encode(['url' => UPLOADS_URL . $name, 'markdown' => '![](' . UPLOADS_URL . $name . ')']);
