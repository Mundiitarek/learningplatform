<?php
/**
 * Authentication System
 * Login, Register, Logout
 */

define('APP_INIT', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

$action = $_GET['action'] ?? 'login';
$error = '';
$success = '';
$currentLang = getCurrentLang();
$isRtl = isRtl();

// Handle logout
if ($action === 'logout') {
    logoutUser();
    redirect('/index.php');
}

// Handle login POST
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = t('error_required');
    } elseif (!checkLoginRateLimit($email)) {
        $error = t('error_rate_limit');
        logSecurityEvent('rate_limit_exceeded', null, 'Login rate limit exceeded', ['email' => $email]);
    } else {
        // Find user
        $user = db()->fetchOne(
            "SELECT id, role, name, email, password_hash, status FROM users WHERE email = ? OR phone = ?",
            [$email, $email]
        );

        if ($user && password_verify($password, $user['password_hash'])) {
            if ($user['status'] !== 'active') {
                $error = $currentLang === 'ar' ? 'حسابك معطل. اتصل بالدعم.' : 'Your account is suspended. Contact support.';
                logSecurityEvent('login_suspended_account', $user['id'], 'Login attempt on suspended account');
            } else {
                loginUser($user['id'], $user['role']);

                // Redirect based on role
                switch ($user['role']) {
                    case 'admin':
                        redirect('/admin.php');
                        break;
                    case 'teacher':
                        redirect('/teacher.php');
                        break;
                    case 'student':
                        redirect('/student.php');
                        break;
                    default:
                        redirect('/index.php');
                }
            }
        } else {
            $error = t('error_login_failed');
            logSecurityEvent('login_failed', null, 'Failed login attempt', ['email' => $email]);
        }
    }
}

// Handle register POST
if ($action === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? 'student';
    $yearId = $_POST['year_id'] ?? null;
    $stream = $_POST['stream'] ?? null;

    // Validation
    if (empty($name) || empty($password)) {
        $error = t('error_required');
    } elseif (empty($email) && empty($phone)) {
        $error = $currentLang === 'ar' ? 'يجب إدخال البريد الإلكتروني أو رقم الهاتف' : 'Email or phone is required';
    } elseif (!empty($email) && !validateEmail($email)) {
        $error = t('error_invalid_email');
    } elseif (!empty($phone) && !validatePhone($phone)) {
        $error = $currentLang === 'ar' ? 'رقم هاتف غير صالح' : 'Invalid phone number';
    } elseif (!validatePassword($password)) {
        $error = $currentLang === 'ar' ? 'كلمة المرور يجب أن تكون 8 أحرف على الأقل وتحتوي على أرقام وحروف' : 'Password must be at least 8 characters with letters and numbers';
    } elseif ($password !== $confirmPassword) {
        $error = $currentLang === 'ar' ? 'كلمات المرور غير متطابقة' : 'Passwords do not match';
    } elseif (!in_array($role, ['student', 'teacher'])) {
        $error = 'Invalid role';
    } else {
        // Check if email/phone exists
        $existing = db()->fetchOne(
            "SELECT id FROM users WHERE email = ? OR phone = ?",
            [$email, $phone]
        );

        if ($existing) {
            $error = t('error_email_exists');
        } else {
            // Create user
            try {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                db()->execute(
                    "INSERT INTO users (role, name, email, phone, password_hash, lang, year_id, stream, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                    [$role, $name, $email ?: null, $phone ?: null, $passwordHash, $currentLang, $yearId, $stream]
                );

                $userId = db()->lastInsertId();

                // If teacher, create profile
                if ($role === 'teacher') {
                    db()->execute(
                        "INSERT INTO teacher_profiles (user_id, approved, created_at) VALUES (?, 'pending', NOW())",
                        [$userId]
                    );
                }

                logSecurityEvent('user_registered', $userId, 'New user registered', ['role' => $role]);

                // Auto-login
                loginUser($userId, $role);

                // Redirect
                if ($role === 'teacher') {
                    redirect('/teacher.php');
                } else {
                    redirect('/student.php');
                }
            } catch (Exception $e) {
                error_log("Registration error: " . $e->getMessage());
                $error = $currentLang === 'ar' ? 'حدث خطأ. حاول مرة أخرى.' : 'An error occurred. Please try again.';
            }
        }
    }
}

// Get academic years for student registration
$academicYears = db()->fetchAll("SELECT * FROM academic_years ORDER BY order_num");
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>" dir="<?= $isRtl ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $action === 'login' ? t('login') : t('register') ?> - <?= e(t('app_name')) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --primary: #4F46E5;
            --primary-dark: #4338CA;
            --danger: #EF4444;
            --success: #10B981;
            --white: #FFFFFF;
            --gray-50: #F9FAFB;
            --gray-100: #F3F4F6;
            --gray-200: #E5E7EB;
            --gray-400: #9CA3AF;
            --gray-500: #6B7280;
            --gray-600: #4B5563;
            --gray-700: #374151;
            --gray-900: #111827;
        }

        body {
            font-family: <?= $isRtl ? "'Tajawal', sans-serif" : "'Inter', sans-serif" ?>;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }

        .auth-container {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            border-radius: 1.5rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            max-width: 480px;
            width: 100%;
            padding: 3rem;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .auth-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .auth-logo {
            font-size: 2rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
        }

        .auth-title {
            font-size: 1.75rem;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
        }

        .auth-subtitle {
            color: var(--gray-600);
            font-size: 0.875rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            color: var(--gray-700);
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }

        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid var(--gray-200);
            border-radius: 0.5rem;
            font-size: 1rem;
            transition: all 0.3s;
            background: var(--white);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .form-select {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid var(--gray-200);
            border-radius: 0.5rem;
            font-size: 1rem;
            background: var(--white);
            cursor: pointer;
        }

        .form-select:focus {
            outline: none;
            border-color: var(--primary);
        }

        .btn {
            width: 100%;
            padding: 0.875rem;
            border: none;
            border-radius: 0.5rem;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: var(--white);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.2);
        }

        .alert {
            padding: 0.875rem 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
        }

        .alert-error {
            background: #FEE2E2;
            color: #991B1B;
            border: 1px solid #FECACA;
        }

        .alert-success {
            background: #D1FAE5;
            color: #065F46;
            border: 1px solid #A7F3D0;
        }

        .auth-footer {
            text-align: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid var(--gray-200);
        }

        .auth-link {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }

        .auth-link:hover {
            text-decoration: underline;
        }

        .lang-switcher {
            position: absolute;
            top: 1rem;
            <?= $isRtl ? 'left' : 'right' ?>: 1rem;
            background: rgba(255, 255, 255, 0.9);
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            cursor: pointer;
            font-weight: 600;
            color: var(--gray-700);
        }

        .role-selector {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .role-option {
            position: relative;
        }

        .role-option input[type="radio"] {
            position: absolute;
            opacity: 0;
        }

        .role-label {
            display: block;
            padding: 1rem;
            border: 2px solid var(--gray-200);
            border-radius: 0.5rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: var(--white);
        }

        .role-option input:checked + .role-label {
            border-color: var(--primary);
            background: rgba(79, 70, 229, 0.05);
            color: var(--primary);
            font-weight: 600;
        }

        .role-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        @media (max-width: 640px) {
            .auth-container {
                padding: 2rem 1.5rem;
            }
        }
    </style>
</head>
<body>
    <button class="lang-switcher" onclick="window.location.href='?action=<?= $action ?>&lang=<?= $currentLang === 'en' ? 'ar' : 'en' ?>'">
        <?= $currentLang === 'en' ? 'العربية' : 'English' ?>
    </button>

    <div class="auth-container">
        <div class="auth-header">
            <div class="auth-logo"><?= e(t('app_name')) ?></div>
            <h1 class="auth-title"><?= $action === 'login' ? t('login_title') : t('register_title') ?></h1>
            <p class="auth-subtitle">
                <?= $action === 'login' ?
                    ($currentLang === 'ar' ? 'أدخل بياناتك للوصول إلى حسابك' : 'Enter your credentials to access your account') :
                    ($currentLang === 'ar' ? 'أنشئ حساباً جديداً لبدء التعلم' : 'Create a new account to start learning')
                ?>
            </p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= e($success) ?></div>
        <?php endif; ?>

        <?php if ($action === 'login'): ?>
        <!-- Login Form -->
        <form method="POST" action="?action=login">
            <?= csrfField() ?>

            <div class="form-group">
                <label class="form-label"><?= t('email') ?> / <?= t('phone') ?></label>
                <input type="text" name="email" class="form-input" required autofocus>
            </div>

            <div class="form-group">
                <label class="form-label"><?= t('password') ?></label>
                <input type="password" name="password" class="form-input" required>
            </div>

            <button type="submit" class="btn btn-primary"><?= t('login') ?></button>
        </form>

        <div class="auth-footer">
            <p><?= t('no_account') ?> <a href="?action=register" class="auth-link"><?= t('register') ?></a></p>
            <p style="margin-top: 0.5rem;"><a href="/index.php" class="auth-link"><?= t('home') ?></a></p>
        </div>

        <?php else: ?>
        <!-- Register Form -->
        <form method="POST" action="?action=register">
            <?= csrfField() ?>

            <div class="role-selector">
                <div class="role-option">
                    <input type="radio" id="role_student" name="role" value="student" checked>
                    <label for="role_student" class="role-label">
                        <div class="role-icon">🎓</div>
                        <?= t('student') ?>
                    </label>
                </div>
                <div class="role-option">
                    <input type="radio" id="role_teacher" name="role" value="teacher">
                    <label for="role_teacher" class="role-label">
                        <div class="role-icon">👨‍🏫</div>
                        <?= t('teacher') ?>
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label"><?= t('name') ?></label>
                <input type="text" name="name" class="form-input" required>
            </div>

            <div class="form-group">
                <label class="form-label"><?= t('email') ?></label>
                <input type="email" name="email" class="form-input">
            </div>

            <div class="form-group">
                <label class="form-label"><?= t('phone') ?></label>
                <input type="tel" name="phone" class="form-input" placeholder="+20 1XXXXXXXXX">
            </div>

            <div class="form-group" id="student_fields">
                <label class="form-label"><?= t('academic_year') ?></label>
                <select name="year_id" class="form-select">
                    <option value=""><?= $currentLang === 'ar' ? 'اختر السنة الدراسية' : 'Select Academic Year' ?></option>
                    <?php foreach ($academicYears as $year): ?>
                        <option value="<?= $year['id'] ?>"><?= e($currentLang === 'ar' ? $year['name_ar'] : $year['name_en']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" id="student_stream">
                <label class="form-label"><?= t('stream') ?></label>
                <select name="stream" class="form-select">
                    <option value=""><?= $currentLang === 'ar' ? 'اختر الشعبة' : 'Select Stream' ?></option>
                    <option value="scientific"><?= $currentLang === 'ar' ? 'علمي' : 'Scientific' ?></option>
                    <option value="literary"><?= $currentLang === 'ar' ? 'أدبي' : 'Literary' ?></option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label"><?= t('password') ?></label>
                <input type="password" name="password" class="form-input" required>
            </div>

            <div class="form-group">
                <label class="form-label"><?= t('confirm_password') ?></label>
                <input type="password" name="confirm_password" class="form-input" required>
            </div>

            <button type="submit" class="btn btn-primary"><?= t('register') ?></button>
        </form>

        <div class="auth-footer">
            <p><?= t('have_account') ?> <a href="?action=login" class="auth-link"><?= t('login') ?></a></p>
        </div>

        <script>
            // Toggle student fields based on role
            document.querySelectorAll('input[name="role"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    const studentFields = document.getElementById('student_fields');
                    const studentStream = document.getElementById('student_stream');
                    if (this.value === 'student') {
                        studentFields.style.display = 'block';
                        studentStream.style.display = 'block';
                    } else {
                        studentFields.style.display = 'none';
                        studentStream.style.display = 'none';
                    }
                });
            });
        </script>
        <?php endif; ?>
    </div>
</body>
</html>
