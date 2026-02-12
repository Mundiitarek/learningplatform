<?php
/**
 * Configuration File
 * Bilingual Educational Platform
 *
 * Security: Store sensitive values in environment variables in production
 */

// Prevent direct access
defined('APP_INIT') or define('APP_INIT', true);

// Error Reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Timezone
date_default_timezone_set('Africa/Cairo');

// Database Configuration
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'eduplatform');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

// Application Settings
define('APP_NAME', 'EduPlatform');
define('APP_NAME_AR', 'منصة التعليم');
define('APP_URL', getenv('APP_URL') ?: 'http://localhost/learningplatform');
define('APP_ENV', getenv('APP_ENV') ?: 'development'); // production, development

// Security Settings
define('SESSION_LIFETIME', 3600 * 24); // 24 hours
define('CSRF_TOKEN_LENGTH', 32);
define('SESSION_NAME', 'edu_session');
define('PASSWORD_MIN_LENGTH', 8);

// Rate Limiting
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes
define('API_RATE_LIMIT', 60); // requests per minute

// File Upload Settings
define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024); // 10MB
define('UPLOAD_ALLOWED_TYPES', ['pdf', 'jpg', 'jpeg', 'png', 'webp']);
define('UPLOAD_PATH', __DIR__ . '/uploads');

// Payment Gateway Settings - Paymob (Egypt)
define('PAYMOB_API_KEY', getenv('PAYMOB_API_KEY') ?: 'YOUR_PAYMOB_API_KEY');
define('PAYMOB_INTEGRATION_ID', getenv('PAYMOB_INTEGRATION_ID') ?: 'YOUR_INTEGRATION_ID');
define('PAYMOB_HMAC_SECRET', getenv('PAYMOB_HMAC_SECRET') ?: 'YOUR_HMAC_SECRET');
define('PAYMOB_CURRENCY', 'EGP');
define('PAYMOB_API_URL', 'https://accept.paymobsolutions.com/api');

// Payment Gateway Settings - Stripe (International)
define('STRIPE_PUBLISHABLE_KEY', getenv('STRIPE_PUBLISHABLE_KEY') ?: 'pk_test_YOUR_KEY');
define('STRIPE_SECRET_KEY', getenv('STRIPE_SECRET_KEY') ?: 'sk_test_YOUR_KEY');
define('STRIPE_WEBHOOK_SECRET', getenv('STRIPE_WEBHOOK_SECRET') ?: 'whsec_YOUR_SECRET');
define('STRIPE_CURRENCY', 'USD');

// Commission Settings
define('DEFAULT_COMMISSION_RATE', 0.20); // 20% platform commission
define('PAYOUT_MINIMUM', 100); // Minimum EGP for payout

// Email Settings (SMTP)
define('SMTP_ENABLED', false); // Set to true when configured
define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.gmail.com');
define('SMTP_PORT', getenv('SMTP_PORT') ?: 587);
define('SMTP_USER', getenv('SMTP_USER') ?: 'your-email@gmail.com');
define('SMTP_PASS', getenv('SMTP_PASS') ?: 'your-password');
define('SMTP_FROM', getenv('SMTP_FROM') ?: 'noreply@eduplatform.com');
define('SMTP_FROM_NAME', 'EduPlatform');

// Content Protection
define('VIDEO_PROVIDERS_WHITELIST', ['youtube.com', 'youtu.be', 'vimeo.com', 'player.vimeo.com', 'stream.mux.com', 'cloudflarestream.com']);
define('VIDEO_WATERMARK_ENABLED', true);
define('FILE_TOKEN_TTL', 300); // 5 minutes for download tokens

// Cron Settings
define('CRON_SECRET_KEY', getenv('CRON_SECRET_KEY') ?: bin2hex(random_bytes(32)));

// Logging
define('LOG_PATH', __DIR__ . '/logs');
define('LOG_SECURITY_EVENTS', true);
define('LOG_SQL_QUERIES', false); // Debug only

// Academic Years & Streams
define('ACADEMIC_YEARS', [
    1 => ['en' => 'First Secondary', 'ar' => 'الصف الأول الثانوي'],
    2 => ['en' => 'Second Secondary', 'ar' => 'الصف الثاني الثانوي'],
    3 => ['en' => 'Third Secondary', 'ar' => 'الصف الثالث الثانوي']
]);

define('STREAMS', [
    'scientific' => ['en' => 'Scientific', 'ar' => 'علمي'],
    'literary' => ['en' => 'Literary', 'ar' => 'أدبي']
]);

// Permissions Map for RBAC
define('PERMISSIONS', [
    'manage_users' => ['admin'],
    'manage_teachers' => ['admin'],
    'manage_content' => ['admin', 'teacher'],
    'manage_payments' => ['admin'],
    'view_analytics' => ['admin', 'teacher'],
    'manage_coupons' => ['admin'],
    'manage_settings' => ['admin'],
    'create_lessons' => ['teacher'],
    'take_exams' => ['student'],
    'view_own_progress' => ['student', 'teacher']
]);

// Security Headers
function setSecurityHeaders() {
    // Prevent clickjacking
    header("X-Frame-Options: SAMEORIGIN");

    // XSS Protection
    header("X-XSS-Protection: 1; mode=block");

    // Prevent MIME sniffing
    header("X-Content-Type-Options: nosniff");

    // Referrer Policy
    header("Referrer-Policy: strict-origin-when-cross-origin");

    // Content Security Policy (adjust based on needs)
    if (APP_ENV === 'production') {
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://www.youtube.com https://www.google.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:; frame-src https://www.youtube.com https://player.vimeo.com; connect-src 'self';");
    }

    // HTTPS redirect in production
    if (APP_ENV === 'production' && empty($_SERVER['HTTPS'])) {
        header("Location: https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
        exit;
    }
}

// Session Configuration
function initSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.use_strict_mode', 1);
        ini_set('session.cookie_secure', APP_ENV === 'production' ? 1 : 0);
        ini_set('session.gc_maxlifetime', SESSION_LIFETIME);

        session_name(SESSION_NAME);
        session_start();

        // Regenerate session ID periodically
        if (!isset($_SESSION['last_regeneration'])) {
            $_SESSION['last_regeneration'] = time();
        } elseif (time() - $_SESSION['last_regeneration'] > 300) {
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
    }
}

// Initialize
setSecurityHeaders();
initSecureSession();
