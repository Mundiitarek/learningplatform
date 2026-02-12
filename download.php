<?php
/**
 * Secure File Download Handler
 * Token-based authentication, subscription verification
 */

define('APP_INIT', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

requireAuth();

$fileId = $_GET['id'] ?? 0;
$token = $_GET['token'] ?? '';
$user = getCurrentUser();

// Get file details
$file = db()->fetchOne(
    "SELECT lf.*, l.teacher_id, l.is_free_preview
     FROM lesson_files lf
     INNER JOIN lessons l ON lf.lesson_id = l.id
     WHERE lf.id = ?",
    [$fileId]
);

if (!$file) {
    http_response_code(404);
    die('File not found');
}

// Verify token
$expectedToken = hash_hmac('sha256', $fileId, CRON_SECRET_KEY);
if (!hash_equals($expectedToken, $token)) {
    http_response_code(403);
    logSecurityEvent('invalid_download_token', $user['id'], 'Invalid file download token', ['file_id' => $fileId]);
    die('Invalid token');
}

// Check access
$hasAccess = $file['is_free_preview'] ||
              hasActiveSubscription($user['id'], $file['teacher_id']) ||
              $user['role'] === 'admin' ||
              $user['id'] == $file['teacher_id'];

if (!$hasAccess) {
    http_response_code(403);
    logSecurityEvent('unauthorized_download', $user['id'], 'Unauthorized file download attempt', ['file_id' => $fileId]);
    die('Access denied');
}

// Build file path
$filePath = UPLOAD_PATH . '/' . $file['file_path'];

if (!file_exists($filePath)) {
    http_response_code(404);
    die('File not found on disk');
}

// Increment download counter
db()->execute("UPDATE lesson_files SET downloads = downloads + 1 WHERE id = ?", [$fileId]);

// Log download
logSecurityEvent('file_downloaded', $user['id'], 'File downloaded', ['file_id' => $fileId, 'filename' => $file['original_name']]);

// Serve file
header('Content-Type: ' . $file['mime_type']);
header('Content-Disposition: attachment; filename="' . $file['original_name'] . '"');
header('Content-Length: ' . $file['file_size']);
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Read file in chunks to handle large files
$handle = fopen($filePath, 'rb');
while (!feof($handle)) {
    echo fread($handle, 8192);
    flush();
}
fclose($handle);
exit;
