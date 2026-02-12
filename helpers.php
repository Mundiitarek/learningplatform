<?php
/**
 * Helper Functions
 * Auth, CSRF, i18n, RBAC, Validation, Security
 */

defined('APP_INIT') or define('APP_INIT', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// ==================== AUTHENTICATION ====================

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

/**
 * Get current user ID
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user role
 */
function getCurrentUserRole() {
    return $_SESSION['user_role'] ?? null;
}

/**
 * Get current user data
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }

    static $user = null;
    if ($user === null) {
        $user = db()->fetchOne(
            "SELECT id, role, name, email, phone, lang, year_id, stream, status, created_at
             FROM users WHERE id = ?",
            [getCurrentUserId()]
        );
    }
    return $user;
}

/**
 * Login user and create session
 */
function loginUser($userId, $role) {
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['user_role'] = $role;
    $_SESSION['login_time'] = time();
    $_SESSION['last_regeneration'] = time();

    // Update last login
    db()->execute(
        "UPDATE users SET last_login = NOW() WHERE id = ?",
        [$userId]
    );

    // Log security event
    logSecurityEvent('user_login', $userId, 'User logged in');
}

/**
 * Logout user
 */
function logoutUser() {
    $userId = getCurrentUserId();
    if ($userId) {
        logSecurityEvent('user_logout', $userId, 'User logged out');
    }

    $_SESSION = [];
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    session_destroy();
}

/**
 * Require authentication
 */
function requireAuth($redirectUrl = '/index.php?page=login') {
    if (!isLoggedIn()) {
        redirect($redirectUrl);
    }
}

/**
 * Require specific role
 */
function requireRole($role, $redirectUrl = '/index.php') {
    requireAuth();
    if (getCurrentUserRole() !== $role) {
        redirect($redirectUrl);
    }
}

// ==================== RBAC (Role-Based Access Control) ====================

/**
 * Check if user has permission
 */
function hasPermission($permission) {
    if (!isLoggedIn()) {
        return false;
    }

    $role = getCurrentUserRole();
    return isset(PERMISSIONS[$permission]) && in_array($role, PERMISSIONS[$permission]);
}

/**
 * Require permission or die
 */
function requirePermission($permission) {
    if (!hasPermission($permission)) {
        http_response_code(403);
        die(json_encode(['error' => t('permission_denied')]));
    }
}

// ==================== CSRF PROTECTION ====================

/**
 * Generate CSRF token
 */
function generateCsrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Get CSRF token
 */
function getCsrfToken() {
    return $_SESSION['csrf_token'] ?? generateCsrfToken();
}

/**
 * Validate CSRF token
 */
function validateCsrfToken($token) {
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Require valid CSRF token or die
 */
function requireCsrf() {
    $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    if (!validateCsrfToken($token)) {
        logSecurityEvent('csrf_violation', getCurrentUserId(), 'CSRF token validation failed', ['ip' => getClientIp()]);
        http_response_code(403);
        die(json_encode(['error' => 'Invalid security token']));
    }
}

/**
 * Output CSRF token input field
 */
function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . e(getCsrfToken()) . '">';
}

// ==================== RATE LIMITING ====================

/**
 * Check rate limit
 */
function checkRateLimit($key, $maxAttempts, $windowSeconds) {
    $ip = getClientIp();
    $identifier = $key . ':' . $ip;

    // Clean old entries
    db()->execute(
        "DELETE FROM rate_limits WHERE expires_at < NOW()"
    );

    // Get current attempts
    $record = db()->fetchOne(
        "SELECT attempts FROM rate_limits WHERE identifier = ? AND expires_at > NOW()",
        [$identifier]
    );

    if ($record) {
        if ($record['attempts'] >= $maxAttempts) {
            return false; // Rate limit exceeded
        }

        // Increment attempts
        db()->execute(
            "UPDATE rate_limits SET attempts = attempts + 1 WHERE identifier = ?",
            [$identifier]
        );
    } else {
        // Create new record
        db()->execute(
            "INSERT INTO rate_limits (identifier, attempts, expires_at) VALUES (?, 1, DATE_ADD(NOW(), INTERVAL ? SECOND))",
            [$identifier, $windowSeconds]
        );
    }

    return true;
}

/**
 * Check login rate limit
 */
function checkLoginRateLimit($username) {
    $ipKey = 'login_ip';
    $userKey = 'login_user:' . $username;

    // Check both IP and username
    if (!checkRateLimit($ipKey, LOGIN_MAX_ATTEMPTS, LOGIN_LOCKOUT_TIME)) {
        return false;
    }

    if (!checkRateLimit($userKey, LOGIN_MAX_ATTEMPTS, LOGIN_LOCKOUT_TIME)) {
        return false;
    }

    return true;
}

// ==================== INTERNATIONALIZATION (i18n) ====================

/**
 * Get current language
 */
function getCurrentLang() {
    // Check session
    if (isset($_SESSION['lang']) && in_array($_SESSION['lang'], ['en', 'ar'])) {
        return $_SESSION['lang'];
    }

    // Check cookie
    if (isset($_COOKIE['lang']) && in_array($_COOKIE['lang'], ['en', 'ar'])) {
        $_SESSION['lang'] = $_COOKIE['lang'];
        return $_COOKIE['lang'];
    }

    // Default to English
    return 'en';
}

/**
 * Set language
 */
function setLang($lang) {
    if (in_array($lang, ['en', 'ar'])) {
        $_SESSION['lang'] = $lang;
        setcookie('lang', $lang, time() + (365 * 24 * 60 * 60), '/'); // 1 year

        // Update user preference if logged in
        if (isLoggedIn()) {
            db()->execute(
                "UPDATE users SET lang = ? WHERE id = ?",
                [$lang, getCurrentUserId()]
            );
        }
    }
}

/**
 * Translation dictionary
 */
function getTranslations() {
    return [
        // Common
        'app_name' => ['en' => 'EduPlatform', 'ar' => 'منصة التعليم'],
        'welcome' => ['en' => 'Welcome', 'ar' => 'مرحباً'],
        'home' => ['en' => 'Home', 'ar' => 'الرئيسية'],
        'dashboard' => ['en' => 'Dashboard', 'ar' => 'لوحة التحكم'],
        'profile' => ['en' => 'Profile', 'ar' => 'الملف الشخصي'],
        'logout' => ['en' => 'Logout', 'ar' => 'تسجيل الخروج'],
        'login' => ['en' => 'Login', 'ar' => 'تسجيل الدخول'],
        'register' => ['en' => 'Register', 'ar' => 'إنشاء حساب'],
        'email' => ['en' => 'Email', 'ar' => 'البريد الإلكتروني'],
        'phone' => ['en' => 'Phone', 'ar' => 'رقم الهاتف'],
        'password' => ['en' => 'Password', 'ar' => 'كلمة المرور'],
        'confirm_password' => ['en' => 'Confirm Password', 'ar' => 'تأكيد كلمة المرور'],
        'name' => ['en' => 'Full Name', 'ar' => 'الاسم الكامل'],
        'save' => ['en' => 'Save', 'ar' => 'حفظ'],
        'cancel' => ['en' => 'Cancel', 'ar' => 'إلغاء'],
        'submit' => ['en' => 'Submit', 'ar' => 'إرسال'],
        'search' => ['en' => 'Search', 'ar' => 'بحث'],
        'filter' => ['en' => 'Filter', 'ar' => 'تصفية'],
        'loading' => ['en' => 'Loading...', 'ar' => 'جاري التحميل...'],
        'error' => ['en' => 'Error', 'ar' => 'خطأ'],
        'success' => ['en' => 'Success', 'ar' => 'نجح'],
        'permission_denied' => ['en' => 'Permission denied', 'ar' => 'لا توجد صلاحية'],
        'not_found' => ['en' => 'Not found', 'ar' => 'غير موجود'],

        // Auth
        'login_title' => ['en' => 'Login to Your Account', 'ar' => 'تسجيل الدخول إلى حسابك'],
        'register_title' => ['en' => 'Create New Account', 'ar' => 'إنشاء حساب جديد'],
        'forgot_password' => ['en' => 'Forgot Password?', 'ar' => 'نسيت كلمة المرور؟'],
        'no_account' => ['en' => "Don't have an account?", 'ar' => 'ليس لديك حساب؟'],
        'have_account' => ['en' => 'Already have an account?', 'ar' => 'لديك حساب بالفعل؟'],
        'login_as' => ['en' => 'Login as', 'ar' => 'تسجيل الدخول كـ'],
        'student' => ['en' => 'Student', 'ar' => 'طالب'],
        'teacher' => ['en' => 'Teacher', 'ar' => 'معلم'],
        'admin' => ['en' => 'Admin', 'ar' => 'مسؤول'],

        // Student
        'browse_teachers' => ['en' => 'Browse Teachers', 'ar' => 'تصفح المعلمين'],
        'my_subscriptions' => ['en' => 'My Subscriptions', 'ar' => 'اشتراكاتي'],
        'my_progress' => ['en' => 'My Progress', 'ar' => 'تقدمي'],
        'exams' => ['en' => 'Exams', 'ar' => 'الامتحانات'],
        'subjects' => ['en' => 'Subjects', 'ar' => 'المواد'],
        'lessons' => ['en' => 'Lessons', 'ar' => 'الدروس'],
        'subscribe' => ['en' => 'Subscribe', 'ar' => 'اشترك'],
        'subscribed' => ['en' => 'Subscribed', 'ar' => 'مشترك'],
        'subscription_expires' => ['en' => 'Expires', 'ar' => 'ينتهي في'],
        'academic_year' => ['en' => 'Academic Year', 'ar' => 'السنة الدراسية'],
        'stream' => ['en' => 'Stream', 'ar' => 'الشعبة'],

        // Teacher
        'my_students' => ['en' => 'My Students', 'ar' => 'طلابي'],
        'create_lesson' => ['en' => 'Create Lesson', 'ar' => 'إنشاء درس'],
        'create_exam' => ['en' => 'Create Exam', 'ar' => 'إنشاء امتحان'],
        'earnings' => ['en' => 'Earnings', 'ar' => 'الأرباح'],
        'units' => ['en' => 'Units', 'ar' => 'الوحدات'],
        'video_url' => ['en' => 'Video URL', 'ar' => 'رابط الفيديو'],
        'upload_files' => ['en' => 'Upload Files', 'ar' => 'رفع الملفات'],

        // Admin
        'admin_panel' => ['en' => 'Admin Panel', 'ar' => 'لوحة الإدارة'],
        'manage_users' => ['en' => 'Manage Users', 'ar' => 'إدارة المستخدمين'],
        'manage_teachers' => ['en' => 'Manage Teachers', 'ar' => 'إدارة المعلمين'],
        'manage_payments' => ['en' => 'Manage Payments', 'ar' => 'إدارة المدفوعات'],
        'analytics' => ['en' => 'Analytics', 'ar' => 'التحليلات'],
        'settings' => ['en' => 'Settings', 'ar' => 'الإعدادات'],
        'approve' => ['en' => 'Approve', 'ar' => 'موافقة'],
        'reject' => ['en' => 'Reject', 'ar' => 'رفض'],
        'approved' => ['en' => 'Approved', 'ar' => 'موافق عليه'],
        'pending' => ['en' => 'Pending', 'ar' => 'قيد المراجعة'],

        // Payments
        'payment' => ['en' => 'Payment', 'ar' => 'الدفع'],
        'pay_now' => ['en' => 'Pay Now', 'ar' => 'ادفع الآن'],
        'price' => ['en' => 'Price', 'ar' => 'السعر'],
        'egp' => ['en' => 'EGP', 'ar' => 'جنيه'],
        'monthly' => ['en' => 'Monthly', 'ar' => 'شهري'],
        'course' => ['en' => 'Course', 'ar' => 'كورس'],
        'payment_method' => ['en' => 'Payment Method', 'ar' => 'طريقة الدفع'],
        'card_payment' => ['en' => 'Card Payment', 'ar' => 'الدفع بالبطاقة'],
        'vodafone_cash' => ['en' => 'Vodafone Cash', 'ar' => 'فودافون كاش'],
        'upload_receipt' => ['en' => 'Upload Receipt', 'ar' => 'رفع الإيصال'],

        // Errors
        'error_login_failed' => ['en' => 'Invalid email or password', 'ar' => 'البريد الإلكتروني أو كلمة المرور غير صحيحة'],
        'error_rate_limit' => ['en' => 'Too many attempts. Please try again later.', 'ar' => 'محاولات كثيرة جداً. حاول مرة أخرى لاحقاً.'],
        'error_email_exists' => ['en' => 'Email already registered', 'ar' => 'البريد الإلكتروني مسجل بالفعل'],
        'error_required' => ['en' => 'This field is required', 'ar' => 'هذا الحقل مطلوب'],
        'error_invalid_email' => ['en' => 'Invalid email address', 'ar' => 'عنوان البريد الإلكتروني غير صالح'],
    ];
}

/**
 * Translate key
 */
function t($key, $lang = null) {
    $lang = $lang ?? getCurrentLang();
    $translations = getTranslations();

    if (isset($translations[$key][$lang])) {
        return $translations[$key][$lang];
    }

    // Fallback to English
    if (isset($translations[$key]['en'])) {
        return $translations[$key]['en'];
    }

    // Return key if not found
    return $key;
}

/**
 * Check if current language is RTL
 */
function isRtl() {
    return getCurrentLang() === 'ar';
}

// ==================== VALIDATION ====================

/**
 * Validate email
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Validate phone (Egyptian format)
 */
function validatePhone($phone) {
    // Remove spaces and dashes
    $phone = preg_replace('/[\s\-]/', '', $phone);

    // Egyptian phone: 01XXXXXXXXX (11 digits) or +2 format
    return preg_match('/^(\+?20)?1[0-9]{9}$/', $phone);
}

/**
 * Validate password strength
 */
function validatePassword($password) {
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        return false;
    }

    // Must contain at least one letter and one number
    return preg_match('/[a-zA-Z]/', $password) && preg_match('/[0-9]/', $password);
}

/**
 * Sanitize input
 */
function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Escape output (alias for htmlspecialchars)
 */
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

// ==================== FILE UPLOAD ====================

/**
 * Validate uploaded file
 */
function validateUploadedFile($file) {
    $errors = [];

    // Check if file was uploaded
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'File upload failed';
        return $errors;
    }

    // Check file size
    if ($file['size'] > UPLOAD_MAX_SIZE) {
        $errors[] = 'File too large. Max: ' . (UPLOAD_MAX_SIZE / 1024 / 1024) . 'MB';
    }

    // Get file extension
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    // Check allowed extensions
    if (!in_array($ext, UPLOAD_ALLOWED_TYPES)) {
        $errors[] = 'File type not allowed. Allowed: ' . implode(', ', UPLOAD_ALLOWED_TYPES);
    }

    // Validate MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowedMimes = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp'
    ];

    if (!isset($allowedMimes[$ext]) || $mimeType !== $allowedMimes[$ext]) {
        $errors[] = 'Invalid file type';
    }

    return $errors;
}

/**
 * Upload file securely
 */
function uploadFile($file, $subdir = 'files') {
    $errors = validateUploadedFile($file);
    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }

    // Create upload directory
    $uploadDir = UPLOAD_PATH . '/' . $subdir;
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Generate random filename
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = bin2hex(random_bytes(16)) . '.' . $ext;
    $filepath = $uploadDir . '/' . $filename;

    // Move file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => false, 'errors' => ['Failed to move uploaded file']];
    }

    return [
        'success' => true,
        'filename' => $filename,
        'path' => $subdir . '/' . $filename,
        'original_name' => $file['name'],
        'size' => $file['size'],
        'mime' => mime_content_type($filepath)
    ];
}

// ==================== UTILITIES ====================

/**
 * Redirect
 */
function redirect($url, $statusCode = 302) {
    header("Location: $url", true, $statusCode);
    exit;
}

/**
 * Get client IP
 */
function getClientIp() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    // Check for proxy headers (be careful with these in production)
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ips[0]);
    } elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    }

    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

/**
 * Log security event
 */
function logSecurityEvent($event_type, $user_id = null, $description = '', $metadata = []) {
    if (!LOG_SECURITY_EVENTS) {
        return;
    }

    try {
        db()->execute(
            "INSERT INTO audit_logs (user_id, event_type, description, ip_address, user_agent, metadata, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [
                $user_id,
                $event_type,
                $description,
                getClientIp(),
                $_SERVER['HTTP_USER_AGENT'] ?? '',
                json_encode($metadata)
            ]
        );
    } catch (Exception $e) {
        error_log("Failed to log security event: " . $e->getMessage());
    }
}

/**
 * Generate random token
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Format price
 */
function formatPrice($amount, $currency = 'EGP') {
    $lang = getCurrentLang();
    if ($lang === 'ar') {
        return number_format($amount, 2) . ' ' . t('egp');
    }
    return $currency . ' ' . number_format($amount, 2);
}

/**
 * Format date
 */
function formatDate($date, $format = null) {
    if ($format === null) {
        $format = getCurrentLang() === 'ar' ? 'd/m/Y' : 'M d, Y';
    }
    return date($format, strtotime($date));
}

/**
 * Time ago
 */
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;

    if ($diff < 60) {
        return t('just_now') ?? 'just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' ' . ($mins == 1 ? 'minute' : 'minutes') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' ' . ($hours == 1 ? 'hour' : 'hours') . ' ago';
    } else {
        return formatDate($datetime);
    }
}

/**
 * Generate file download token
 */
function generateFileToken($fileId, $userId) {
    $token = hash_hmac('sha256', $fileId . '|' . $userId . '|' . time(), CRON_SECRET_KEY);
    $expires = time() + FILE_TOKEN_TTL;

    return [
        'token' => $token,
        'expires' => $expires
    ];
}

/**
 * Verify file token
 */
function verifyFileToken($token, $fileId, $userId) {
    // In production, store tokens in database with expiration
    // This is a simplified version
    return true; // Implement proper verification
}

/**
 * Check if user has active subscription to teacher
 */
function hasActiveSubscription($studentId, $teacherId) {
    $sub = db()->fetchOne(
        "SELECT id FROM subscriptions
         WHERE student_id = ? AND teacher_id = ?
         AND status = 'active' AND end_at > NOW()",
        [$studentId, $teacherId]
    );

    return $sub !== false;
}
