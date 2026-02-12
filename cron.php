<?php
/**
 * Cron Jobs Handler
 * Run: curl https://yourdomain.com/cron.php?key=YOUR_SECRET_KEY
 * Schedule: 0 * * * * (every hour)
 */

define('APP_INIT', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

// Verify cron key
$key = $_GET['key'] ?? '';
if (!hash_equals(CRON_SECRET_KEY, $key)) {
    http_response_code(403);
    die('Unauthorized');
}

// Prevent timeout
set_time_limit(300); // 5 minutes
ignore_user_abort(true);

$startTime = microtime(true);
$results = [];

// ==================== Task 1: Expire Subscriptions ====================
try {
    $expiredCount = db()->execute(
        "UPDATE subscriptions
         SET status = 'expired'
         WHERE status = 'active'
         AND end_at < NOW()"
    );

    $results[] = "Expired subscriptions: " . ($expiredCount ? 'Updated' : 'None');
} catch (Exception $e) {
    $results[] = "Error expiring subscriptions: " . $e->getMessage();
    error_log("Cron error - expire subscriptions: " . $e->getMessage());
}

// ==================== Task 2: Clean Old Rate Limits ====================
try {
    $cleanedCount = db()->execute(
        "DELETE FROM rate_limits WHERE expires_at < NOW()"
    );

    $results[] = "Cleaned rate limits: " . ($cleanedCount ? 'Done' : 'None');
} catch (Exception $e) {
    $results[] = "Error cleaning rate limits: " . $e->getMessage();
    error_log("Cron error - clean rate limits: " . $e->getMessage());
}

// ==================== Task 3: Clean Old Audit Logs (Optional) ====================
try {
    // Keep logs for 90 days
    $cleanedLogs = db()->execute(
        "DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)"
    );

    $results[] = "Cleaned old audit logs: " . ($cleanedLogs ? 'Done' : 'None');
} catch (Exception $e) {
    $results[] = "Error cleaning audit logs: " . $e->getMessage();
    error_log("Cron error - clean audit logs: " . $e->getMessage());
}

// ==================== Task 4: Update Teacher Statistics ====================
try {
    db()->execute(
        "UPDATE teacher_profiles tp
         SET total_students = (
             SELECT COUNT(DISTINCT student_id)
             FROM subscriptions
             WHERE teacher_id = tp.user_id AND status = 'active'
         )"
    );

    $results[] = "Updated teacher statistics: Done";
} catch (Exception $e) {
    $results[] = "Error updating teacher stats: " . $e->getMessage();
    error_log("Cron error - update teacher stats: " . $e->getMessage());
}

// ==================== Task 5: Send Expiry Reminders (Optional) ====================
try {
    // Find subscriptions expiring in 3 days
    $expiringSubscriptions = db()->fetchAll(
        "SELECT s.*, u.name, u.email, u.lang, t.name as teacher_name
         FROM subscriptions s
         INNER JOIN users u ON s.student_id = u.id
         INNER JOIN users t ON s.teacher_id = t.id
         WHERE s.status = 'active'
         AND s.end_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 3 DAY)
         AND s.end_at > DATE_ADD(NOW(), INTERVAL 2 DAY)"
    );

    $remindersSent = 0;
    foreach ($expiringSubscriptions as $sub) {
        // In production: Send email notification
        // For now, create notification record
        db()->execute(
            "INSERT INTO notifications (user_id, type, title_en, title_ar, message_en, message_ar, created_at)
             VALUES (?, 'subscription_expiring', 'Subscription Expiring Soon', 'اشتراكك على وشك الانتهاء',
                     'Your subscription with {$sub['teacher_name']} expires in 3 days',
                     'اشتراكك مع {$sub['teacher_name']} ينتهي خلال 3 أيام',
                     NOW())",
            [$sub['student_id']]
        );
        $remindersSent++;
    }

    $results[] = "Expiry reminders sent: " . $remindersSent;
} catch (Exception $e) {
    $results[] = "Error sending reminders: " . $e->getMessage();
    error_log("Cron error - send reminders: " . $e->getMessage());
}

// ==================== Task 6: Clean Abandoned Exam Attempts ====================
try {
    // Mark attempts as abandoned if not submitted within 2x duration
    $abandonedCount = db()->execute(
        "UPDATE exam_attempts ea
         INNER JOIN exams e ON ea.exam_id = e.id
         SET ea.status = 'abandoned'
         WHERE ea.status = 'in_progress'
         AND ea.started_at < DATE_SUB(NOW(), INTERVAL (e.duration_minutes * 2) MINUTE)"
    );

    $results[] = "Abandoned exam attempts: " . ($abandonedCount ? 'Marked' : 'None');
} catch (Exception $e) {
    $results[] = "Error marking abandoned attempts: " . $e->getMessage();
    error_log("Cron error - abandoned attempts: " . $e->getMessage());
}

// ==================== Task 7: Database Optimization (Weekly) ====================
try {
    // Run on Sundays only
    if (date('w') == 0) {
        // Optimize tables
        $tables = ['users', 'subscriptions', 'payments', 'lessons', 'exam_attempts'];
        foreach ($tables as $table) {
            db()->execute("OPTIMIZE TABLE " . $table);
        }
        $results[] = "Database optimization: Done (weekly)";
    } else {
        $results[] = "Database optimization: Skipped (runs weekly)";
    }
} catch (Exception $e) {
    $results[] = "Error optimizing database: " . $e->getMessage();
    error_log("Cron error - database optimization: " . $e->getMessage());
}

// Calculate execution time
$executionTime = round(microtime(true) - $startTime, 2);
$results[] = "Total execution time: {$executionTime}s";

// Log cron execution
try {
    db()->execute(
        "INSERT INTO audit_logs (user_id, event_type, description, metadata, created_at)
         VALUES (NULL, 'cron_executed', 'Cron jobs executed', ?, NOW())",
        [json_encode($results)]
    );
} catch (Exception $e) {
    error_log("Failed to log cron execution: " . $e->getMessage());
}

// Output results
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'timestamp' => date('Y-m-d H:i:s'),
    'execution_time' => $executionTime,
    'results' => $results
]);
