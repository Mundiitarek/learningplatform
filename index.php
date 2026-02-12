<?php
/**
 * Main Entry Point & Landing Page
 * Handles routing and displays homepage for non-authenticated users
 */

define('APP_INIT', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

// Handle language switching
if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'ar'])) {
    setLang($_GET['lang']);
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// Simple routing
$page = $_GET['page'] ?? 'home';
$currentLang = getCurrentLang();
$isRtl = isRtl();

// Redirect to appropriate dashboard if logged in
if (isLoggedIn() && $page === 'home') {
    $role = getCurrentUserRole();
    switch ($role) {
        case 'admin':
            redirect('/admin.php');
            break;
        case 'teacher':
            redirect('/teacher.php');
            break;
        case 'student':
            redirect('/student.php');
            break;
    }
}

// Get featured teachers for homepage
$featuredTeachers = db()->fetchAll(
    "SELECT u.id, u.name, tp.bio_en, tp.bio_ar, tp.rating, tp.rating_count, tp.total_students,
            GROUP_CONCAT(DISTINCT s.name_en SEPARATOR ', ') as subjects_en,
            GROUP_CONCAT(DISTINCT s.name_ar SEPARATOR '، ') as subjects_ar
     FROM users u
     INNER JOIN teacher_profiles tp ON u.id = tp.user_id
     LEFT JOIN teacher_subjects ts ON u.id = ts.teacher_id
     LEFT JOIN subjects s ON ts.subject_id = s.id
     WHERE u.role = 'teacher' AND u.status = 'active' AND tp.approved = 'approved'
     GROUP BY u.id
     ORDER BY tp.rating DESC, tp.total_students DESC
     LIMIT 6"
);

// Get subjects
$subjects = db()->fetchAll("SELECT * FROM subjects WHERE active = TRUE ORDER BY name_en");
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>" dir="<?= $isRtl ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(t('app_name')) ?> - <?= $currentLang === 'ar' ? 'منصة تعليمية متميزة' : 'Premium Educational Platform' ?></title>
    <meta name="description" content="<?= $currentLang === 'ar' ? 'منصة تعليمية متميزة للثانوية العامة في مصر' : 'Premium educational platform for Egyptian secondary students' ?>">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --primary: #4F46E5;
            --primary-dark: #4338CA;
            --secondary: #06B6D4;
            --success: #10B981;
            --danger: #EF4444;
            --warning: #F59E0B;
            --dark: #1E293B;
            --light: #F8FAFC;
            --white: #FFFFFF;
            --gray-50: #F9FAFB;
            --gray-100: #F3F4F6;
            --gray-200: #E5E7EB;
            --gray-300: #D1D5DB;
            --gray-400: #9CA3AF;
            --gray-500: #6B7280;
            --gray-600: #4B5563;
            --gray-700: #374151;
            --gray-800: #1F2937;
            --gray-900: #111827;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        body {
            font-family: <?= $isRtl ? "'Tajawal', 'Segoe UI', sans-serif" : "'Inter', 'Segoe UI', sans-serif" ?>;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: var(--gray-900);
            line-height: 1.6;
            min-height: 100vh;
        }

        /* Typography */
        h1, h2, h3, h4, h5, h6 { font-weight: 700; line-height: 1.2; }
        h1 { font-size: 2.5rem; }
        h2 { font-size: 2rem; }
        h3 { font-size: 1.5rem; }

        /* Navigation */
        .navbar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: var(--shadow-md);
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 1px solid rgba(79, 70, 229, 0.1);
        }

        .navbar-container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 1rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .navbar-brand {
            font-size: 1.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-decoration: none;
        }

        .navbar-menu {
            display: flex;
            gap: 2rem;
            align-items: center;
            list-style: none;
        }

        .navbar-link {
            color: var(--gray-700);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
            padding: 0.5rem 0;
        }

        .navbar-link:hover {
            color: var(--primary);
        }

        .lang-switcher {
            background: var(--gray-100);
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            cursor: pointer;
            font-weight: 600;
            color: var(--gray-700);
            transition: all 0.3s;
        }

        .lang-switcher:hover {
            background: var(--gray-200);
        }

        /* Buttons */
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            font-size: 1rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: var(--white);
            box-shadow: var(--shadow);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-outline {
            background: transparent;
            color: var(--primary);
            border: 2px solid var(--primary);
        }

        .btn-outline:hover {
            background: var(--primary);
            color: var(--white);
        }

        .btn-white {
            background: var(--white);
            color: var(--primary);
            box-shadow: var(--shadow-md);
        }

        .btn-white:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        /* Hero Section */
        .hero {
            padding: 4rem 1.5rem;
            text-align: center;
            color: var(--white);
        }

        .hero-container {
            max-width: 1280px;
            margin: 0 auto;
        }

        .hero h1 {
            font-size: 3rem;
            margin-bottom: 1.5rem;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            animation: fadeInUp 0.8s ease-out;
        }

        .hero p {
            font-size: 1.25rem;
            margin-bottom: 2rem;
            opacity: 0.95;
            animation: fadeInUp 0.8s ease-out 0.2s both;
        }

        .hero-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
            animation: fadeInUp 0.8s ease-out 0.4s both;
        }

        /* Stats Section */
        .stats {
            background: var(--white);
            padding: 3rem 1.5rem;
            box-shadow: var(--shadow-xl);
        }

        .stats-container {
            max-width: 1280px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
        }

        .stat-card {
            text-align: center;
            padding: 2rem;
            background: linear-gradient(135deg, var(--gray-50), var(--white));
            border-radius: 1rem;
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-label {
            color: var(--gray-600);
            margin-top: 0.5rem;
            font-weight: 500;
        }

        /* Features Section */
        .features {
            padding: 4rem 1.5rem;
            background: var(--gray-50);
        }

        .section-title {
            text-align: center;
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: var(--gray-900);
        }

        .section-subtitle {
            text-align: center;
            color: var(--gray-600);
            margin-bottom: 3rem;
            font-size: 1.125rem;
        }

        .features-grid {
            max-width: 1280px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
        }

        .feature-card {
            background: var(--white);
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: var(--shadow);
            transition: all 0.3s;
            border: 1px solid var(--gray-100);
        }

        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-xl);
            border-color: var(--primary);
        }

        .feature-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .feature-title {
            font-size: 1.25rem;
            margin-bottom: 0.75rem;
            color: var(--gray-900);
        }

        .feature-description {
            color: var(--gray-600);
            line-height: 1.6;
        }

        /* Teachers Section */
        .teachers {
            padding: 4rem 1.5rem;
            background: var(--white);
        }

        .teachers-grid {
            max-width: 1280px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 2rem;
        }

        .teacher-card {
            background: linear-gradient(135deg, var(--white), var(--gray-50));
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: var(--shadow-md);
            transition: all 0.3s;
            border: 2px solid transparent;
        }

        .teacher-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-xl);
            border-color: var(--primary);
        }

        .teacher-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .teacher-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 1.5rem;
            font-weight: 700;
        }

        .teacher-info h3 {
            font-size: 1.25rem;
            color: var(--gray-900);
            margin-bottom: 0.25rem;
        }

        .teacher-rating {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--warning);
            font-size: 0.875rem;
        }

        .teacher-subjects {
            color: var(--gray-600);
            margin-bottom: 1rem;
            font-size: 0.875rem;
        }

        .teacher-stats {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--gray-200);
        }

        .teacher-stat {
            flex: 1;
            text-align: center;
        }

        .teacher-stat-value {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--primary);
        }

        .teacher-stat-label {
            font-size: 0.75rem;
            color: var(--gray-500);
            margin-top: 0.25rem;
        }

        /* Footer */
        .footer {
            background: var(--gray-900);
            color: var(--gray-300);
            padding: 3rem 1.5rem 1.5rem;
        }

        .footer-container {
            max-width: 1280px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .footer-section h4 {
            color: var(--white);
            margin-bottom: 1rem;
        }

        .footer-links {
            list-style: none;
        }

        .footer-links li {
            margin-bottom: 0.5rem;
        }

        .footer-links a {
            color: var(--gray-400);
            text-decoration: none;
            transition: color 0.3s;
        }

        .footer-links a:hover {
            color: var(--primary);
        }

        .footer-bottom {
            text-align: center;
            padding-top: 2rem;
            border-top: 1px solid var(--gray-700);
            color: var(--gray-500);
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero h1 { font-size: 2rem; }
            .hero p { font-size: 1rem; }
            .navbar-menu { flex-direction: column; gap: 1rem; }
            .section-title { font-size: 1.75rem; }
        }

        /* RTL Adjustments */
        [dir="rtl"] .navbar-menu { flex-direction: row-reverse; }
        [dir="rtl"] .hero-actions { flex-direction: row-reverse; }
        [dir="rtl"] .teacher-header { flex-direction: row-reverse; }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="navbar-container">
            <a href="/index.php" class="navbar-brand"><?= e(t('app_name')) ?></a>
            <ul class="navbar-menu">
                <li><a href="/index.php" class="navbar-link"><?= e(t('home')) ?></a></li>
                <li><a href="/index.php?page=login" class="navbar-link"><?= e(t('login')) ?></a></li>
                <li><a href="/index.php?page=register" class="navbar-link"><?= e(t('register')) ?></a></li>
                <li>
                    <button class="lang-switcher" onclick="window.location.href='?lang=<?= $currentLang === 'en' ? 'ar' : 'en' ?>'">
                        <?= $currentLang === 'en' ? 'العربية' : 'English' ?>
                    </button>
                </li>
            </ul>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-container">
            <h1><?= $currentLang === 'ar' ? 'ارتقِ بمستواك التعليمي' : 'Elevate Your Education' ?></h1>
            <p><?= $currentLang === 'ar' ? 'منصة تعليمية متميزة للثانوية العامة مع أفضل المعلمين في مصر' : 'Premium educational platform for secondary students with Egypt\'s best teachers' ?></p>
            <div class="hero-actions">
                <a href="/auth.php?action=register" class="btn btn-white"><?= e(t('register')) ?></a>
                <a href="#teachers" class="btn btn-outline"><?= e(t('browse_teachers')) ?></a>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats">
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-number">50+</div>
                <div class="stat-label"><?= $currentLang === 'ar' ? 'معلم متميز' : 'Expert Teachers' ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-number">10K+</div>
                <div class="stat-label"><?= $currentLang === 'ar' ? 'طالب نشط' : 'Active Students' ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-number">500+</div>
                <div class="stat-label"><?= $currentLang === 'ar' ? 'درس تفاعلي' : 'Interactive Lessons' ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-number">98%</div>
                <div class="stat-label"><?= $currentLang === 'ar' ? 'نسبة النجاح' : 'Success Rate' ?></div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features">
        <h2 class="section-title"><?= $currentLang === 'ar' ? 'لماذا تختارنا؟' : 'Why Choose Us?' ?></h2>
        <p class="section-subtitle"><?= $currentLang === 'ar' ? 'مميزات تجعلنا الخيار الأفضل لتعليمك' : 'Features that make us the best choice for your education' ?></p>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">🎓</div>
                <h3 class="feature-title"><?= $currentLang === 'ar' ? 'معلمون متميزون' : 'Expert Teachers' ?></h3>
                <p class="feature-description"><?= $currentLang === 'ar' ? 'نخبة من أفضل المعلمين ذوي الخبرة في مصر' : 'Elite selection of Egypt\'s most experienced educators' ?></p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">📱</div>
                <h3 class="feature-title"><?= $currentLang === 'ar' ? 'متاح في أي وقت' : 'Anytime, Anywhere' ?></h3>
                <p class="feature-description"><?= $currentLang === 'ar' ? 'تعلم من أي مكان وفي أي وقت عبر جميع الأجهزة' : 'Learn from anywhere at any time on all devices' ?></p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">📝</div>
                <h3 class="feature-title"><?= $currentLang === 'ar' ? 'امتحانات تفاعلية' : 'Interactive Exams' ?></h3>
                <p class="feature-description"><?= $currentLang === 'ar' ? 'اختبر معرفتك مع نظام امتحانات ذكي ومتقدم' : 'Test your knowledge with smart, advanced exam system' ?></p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">📊</div>
                <h3 class="feature-title"><?= $currentLang === 'ar' ? 'تتبع التقدم' : 'Progress Tracking' ?></h3>
                <p class="feature-description"><?= $currentLang === 'ar' ? 'راقب تقدمك ونقاط ضعفك لتحسين أدائك' : 'Monitor your progress and weaknesses to improve performance' ?></p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">💳</div>
                <h3 class="feature-title"><?= $currentLang === 'ar' ? 'دفع آمن' : 'Secure Payment' ?></h3>
                <p class="feature-description"><?= $currentLang === 'ar' ? 'طرق دفع متعددة وآمنة بالكامل' : 'Multiple, completely secure payment methods' ?></p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🏆</div>
                <h3 class="feature-title"><?= $currentLang === 'ar' ? 'محتوى حصري' : 'Exclusive Content' ?></h3>
                <p class="feature-description"><?= $currentLang === 'ar' ? 'دروس ومواد تعليمية حصرية غير متاحة في مكان آخر' : 'Exclusive lessons and materials unavailable elsewhere' ?></p>
            </div>
        </div>
    </section>

    <!-- Featured Teachers -->
    <section class="teachers" id="teachers">
        <h2 class="section-title"><?= $currentLang === 'ar' ? 'معلمونا المتميزون' : 'Our Featured Teachers' ?></h2>
        <p class="section-subtitle"><?= $currentLang === 'ar' ? 'تعلم من أفضل المعلمين في مصر' : 'Learn from the best teachers in Egypt' ?></p>
        <div class="teachers-grid">
            <?php foreach ($featuredTeachers as $teacher): ?>
            <div class="teacher-card">
                <div class="teacher-header">
                    <div class="teacher-avatar"><?= strtoupper(substr($teacher['name'], 0, 2)) ?></div>
                    <div class="teacher-info">
                        <h3><?= e($teacher['name']) ?></h3>
                        <div class="teacher-rating">
                            ⭐ <?= number_format($teacher['rating'], 2) ?> (<?= $teacher['rating_count'] ?>)
                        </div>
                    </div>
                </div>
                <div class="teacher-subjects">
                    <?= e($currentLang === 'ar' ? $teacher['subjects_ar'] : $teacher['subjects_en']) ?>
                </div>
                <div class="teacher-stats">
                    <div class="teacher-stat">
                        <div class="teacher-stat-value"><?= number_format($teacher['total_students']) ?></div>
                        <div class="teacher-stat-label"><?= $currentLang === 'ar' ? 'طالب' : 'Students' ?></div>
                    </div>
                    <div class="teacher-stat">
                        <div class="teacher-stat-value"><?= number_format($teacher['rating'], 1) ?></div>
                        <div class="teacher-stat-label"><?= $currentLang === 'ar' ? 'التقييم' : 'Rating' ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-container">
            <div class="footer-section">
                <h4><?= e(t('app_name')) ?></h4>
                <p><?= $currentLang === 'ar' ? 'منصة تعليمية متميزة للثانوية العامة في مصر' : 'Premium educational platform for Egyptian secondary students' ?></p>
            </div>
            <div class="footer-section">
                <h4><?= $currentLang === 'ar' ? 'روابط سريعة' : 'Quick Links' ?></h4>
                <ul class="footer-links">
                    <li><a href="/index.php"><?= e(t('home')) ?></a></li>
                    <li><a href="/auth.php?action=login"><?= e(t('login')) ?></a></li>
                    <li><a href="/auth.php?action=register"><?= e(t('register')) ?></a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h4><?= $currentLang === 'ar' ? 'للدعم' : 'Support' ?></h4>
                <ul class="footer-links">
                    <li><a href="#"><?= $currentLang === 'ar' ? 'مركز المساعدة' : 'Help Center' ?></a></li>
                    <li><a href="#"><?= $currentLang === 'ar' ? 'اتصل بنا' : 'Contact Us' ?></a></li>
                    <li><a href="#"><?= $currentLang === 'ar' ? 'الشروط والأحكام' : 'Terms & Conditions' ?></a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?= date('Y') ?> <?= e(t('app_name')) ?>. <?= $currentLang === 'ar' ? 'جميع الحقوق محفوظة' : 'All rights reserved' ?>.</p>
        </div>
    </footer>
</body>
</html>
