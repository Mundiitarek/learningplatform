<?php
/**
 * API Endpoints for AJAX Requests
 * JSON responses, CSRF protected
 */

define('APP_INIT', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

header('Content-Type: application/json');

// Rate limiting for API
if (!checkRateLimit('api:' . getClientIp(), API_RATE_LIMIT, 60)) {
    http_response_code(429);
    die(json_encode(['error' => 'Rate limit exceeded']));
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ==================== Mark Lesson Progress ====================
if ($action === 'mark_progress') {
    requireAuth();

    $lessonId = $_GET['lesson_id'] ?? 0;
    $userId = getCurrentUserId();

    try {
        db()->execute(
            "UPDATE lesson_progress
             SET watched_duration = watched_duration + 30,
                 updated_at = NOW()
             WHERE student_id = ? AND lesson_id = ?",
            [$userId, $lessonId]
        );

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update progress']);
    }
    exit;
}

// ==================== Complete Lesson ====================
if ($action === 'complete_lesson' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAuth();
    requireCsrf();

    $lessonId = $_POST['lesson_id'] ?? 0;
    $userId = getCurrentUserId();

    try {
        db()->execute(
            "UPDATE lesson_progress
             SET completed = 1,
                 completed_at = NOW()
             WHERE student_id = ? AND lesson_id = ?",
            [$userId, $lessonId]
        );

        echo json_encode(['success' => true, 'message' => 'Lesson marked as completed']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to complete lesson']);
    }
    exit;
}

// ==================== Search Teachers ====================
if ($action === 'search_teachers') {
    $query = $_GET['q'] ?? '';
    $subjectId = $_GET['subject_id'] ?? 0;
    $yearId = $_GET['year_id'] ?? 0;

    $sql = "SELECT u.id, u.name, tp.rating, tp.total_students,
                   GROUP_CONCAT(DISTINCT s.name_en) as subjects
            FROM users u
            INNER JOIN teacher_profiles tp ON u.id = tp.user_id
            LEFT JOIN teacher_subjects ts ON u.id = ts.teacher_id
            LEFT JOIN subjects s ON ts.subject_id = s.id
            WHERE u.role = 'teacher'
            AND u.status = 'active'
            AND tp.approved = 'approved'";

    $params = [];

    if ($query) {
        $sql .= " AND u.name LIKE ?";
        $params[] = "%{$query}%";
    }

    if ($subjectId) {
        $sql .= " AND ts.subject_id = ?";
        $params[] = $subjectId;
    }

    if ($yearId) {
        $sql .= " AND ts.year_id = ?";
        $params[] = $yearId;
    }

    $sql .= " GROUP BY u.id ORDER BY tp.rating DESC LIMIT 20";

    try {
        $teachers = db()->fetchAll($sql, $params);
        echo json_encode(['success' => true, 'teachers' => $teachers]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Search failed']);
    }
    exit;
}

// ==================== Get Notifications ====================
if ($action === 'get_notifications') {
    requireAuth();

    $userId = getCurrentUserId();
    $lang = getCurrentLang();

    try {
        $notifications = db()->fetchAll(
            "SELECT id, type,
                    IF(? = 'ar', title_ar, title_en) as title,
                    IF(? = 'ar', message_ar, message_en) as message,
                    link, is_read, created_at
             FROM notifications
             WHERE user_id = ?
             ORDER BY created_at DESC
             LIMIT 20",
            [$lang, $lang, $userId]
        );

        $unreadCount = db()->fetchOne(
            "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0",
            [$userId]
        )['count'];

        echo json_encode([
            'success' => true,
            'notifications' => $notifications,
            'unread_count' => $unreadCount
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch notifications']);
    }
    exit;
}

// ==================== Mark Notification as Read ====================
if ($action === 'mark_notification_read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAuth();
    requireCsrf();

    $notificationId = $_POST['notification_id'] ?? 0;
    $userId = getCurrentUserId();

    try {
        db()->execute(
            "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?",
            [$notificationId, $userId]
        );

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to mark notification']);
    }
    exit;
}

// ==================== Validate Coupon ====================
if ($action === 'validate_coupon') {
    requireAuth();

    $code = $_GET['code'] ?? '';
    $teacherId = $_GET['teacher_id'] ?? 0;

    try {
        $coupon = db()->fetchOne(
            "SELECT * FROM coupons
             WHERE code = ?
             AND active = TRUE
             AND (valid_from IS NULL OR valid_from <= NOW())
             AND (valid_until IS NULL OR valid_until >= NOW())
             AND (max_uses IS NULL OR used_count < max_uses)
             AND (teacher_id IS NULL OR teacher_id = ?)",
            [$code, $teacherId]
        );

        if ($coupon) {
            echo json_encode([
                'success' => true,
                'valid' => true,
                'coupon' => [
                    'type' => $coupon['type'],
                    'value' => $coupon['value'],
                    'code' => $coupon['code']
                ]
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'valid' => false,
                'message' => 'Invalid or expired coupon'
            ]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to validate coupon']);
    }
    exit;
}

// ==================== Get Dashboard Stats (Admin) ====================
if ($action === 'get_dashboard_stats') {
    requirePermission('view_analytics');

    $period = $_GET['period'] ?? '7days'; // 7days, 30days, 90days, year

    $dateCondition = match($period) {
        '7days' => "created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
        '30days' => "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
        '90days' => "created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)",
        'year' => "created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)",
        default => "created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
    };

    try {
        $stats = [
            'new_users' => db()->fetchOne("SELECT COUNT(*) as count FROM users WHERE {$dateCondition}")['count'],
            'new_subscriptions' => db()->fetchOne("SELECT COUNT(*) as count FROM subscriptions WHERE {$dateCondition}")['count'],
            'revenue' => db()->fetchOne("SELECT SUM(amount) as total FROM payments WHERE status = 'completed' AND {$dateCondition}")['total'] ?? 0,
            'active_students' => db()->fetchOne("SELECT COUNT(DISTINCT student_id) as count FROM subscriptions WHERE status = 'active'")['count'],
        ];

        // Daily revenue chart data
        $revenueChart = db()->fetchAll(
            "SELECT DATE(created_at) as date, SUM(amount) as revenue
             FROM payments
             WHERE status = 'completed' AND {$dateCondition}
             GROUP BY DATE(created_at)
             ORDER BY date"
        );

        echo json_encode([
            'success' => true,
            'stats' => $stats,
            'revenue_chart' => $revenueChart
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch stats']);
    }
    exit;
}

// ==================== Update Video Progress ====================
if ($action === 'update_video_progress' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAuth();

    $lessonId = $_POST['lesson_id'] ?? 0;
    $position = intval($_POST['position'] ?? 0);
    $userId = getCurrentUserId();

    try {
        db()->execute(
            "UPDATE lesson_progress
             SET last_position = ?,
                 watched_duration = GREATEST(watched_duration, ?),
                 updated_at = NOW()
             WHERE student_id = ? AND lesson_id = ?",
            [$position, $position, $userId, $lessonId]
        );

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update progress']);
    }
    exit;
}

// ==================== Health Check ====================
if ($action === 'health') {
    try {
        // Check database connection
        db()->fetchOne("SELECT 1 as test");

        echo json_encode([
            'success' => true,
            'status' => 'healthy',
            'timestamp' => date('Y-m-d H:i:s'),
            'php_version' => PHP_VERSION,
            'database' => 'connected'
        ]);
    } catch (Exception $e) {
        http_response_code(503);
        echo json_encode([
            'success' => false,
            'status' => 'unhealthy',
            'error' => 'Database connection failed'
        ]);
    }
    exit;
}

// Invalid action
http_response_code(400);
echo json_encode(['error' => 'Invalid action']);
