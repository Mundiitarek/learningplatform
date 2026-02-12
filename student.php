<?php
/**
 * Student Dashboard
 * Browse teachers, view subscriptions, access lessons, track progress
 */

define('APP_INIT', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

requireRole('student');

$user = getCurrentUser();
$currentLang = getCurrentLang();
$isRtl = isRtl();
$tab = $_GET['tab'] ?? 'dashboard';

// Get student stats
$stats = [
    'subscriptions' => db()->fetchOne("SELECT COUNT(*) as count FROM subscriptions WHERE student_id = ? AND status = 'active'", [$user['id']])['count'],
    'completed_lessons' => db()->fetchOne("SELECT COUNT(*) as count FROM lesson_progress WHERE student_id = ? AND completed = 1", [$user['id']])['count'],
    'exams_taken' => db()->fetchOne("SELECT COUNT(*) as count FROM exam_attempts WHERE student_id = ? AND status = 'submitted'", [$user['id']])['count'],
];

// Get active subscriptions
$subscriptions = db()->fetchAll(
    "SELECT s.*, u.name as teacher_name, p.title_en, p.title_ar, p.price, p.currency
     FROM subscriptions s
     INNER JOIN users u ON s.teacher_id = u.id
     INNER JOIN plans p ON s.plan_id = p.id
     WHERE s.student_id = ? AND s.status = 'active'
     ORDER BY s.end_at DESC",
    [$user['id']]
);

// Get available teachers
$teachers = db()->fetchAll(
    "SELECT u.id, u.name, tp.bio_en, tp.bio_ar, tp.rating, tp.rating_count, tp.total_students,
            GROUP_CONCAT(DISTINCT s.name_en SEPARATOR ', ') as subjects_en,
            GROUP_CONCAT(DISTINCT s.name_ar SEPARATOR '، ') as subjects_ar,
            (SELECT COUNT(*) FROM subscriptions sub WHERE sub.student_id = ? AND sub.teacher_id = u.id AND sub.status = 'active') as is_subscribed
     FROM users u
     INNER JOIN teacher_profiles tp ON u.id = tp.user_id
     LEFT JOIN teacher_subjects ts ON u.id = ts.teacher_id
     LEFT JOIN subjects s ON ts.subject_id = s.id
     WHERE u.role = 'teacher' AND u.status = 'active' AND tp.approved = 'approved'
     GROUP BY u.id
     ORDER BY tp.rating DESC",
    [$user['id']]
);

// Get recent lessons
$recentLessons = db()->fetchAll(
    "SELECT l.id, l.title_en, l.title_ar, l.video_provider, u.name as teacher_name,
            lp.completed, lp.watched_duration, lp.last_position, lp.updated_at
     FROM lesson_progress lp
     INNER JOIN lessons l ON lp.lesson_id = l.id
     INNER JOIN users u ON l.teacher_id = u.id
     WHERE lp.student_id = ?
     ORDER BY lp.updated_at DESC
     LIMIT 5",
    [$user['id']]
);
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>" dir="<?= $isRtl ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('dashboard') ?> - <?= e($user['name']) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --primary: #4F46E5;
            --primary-dark: #4338CA;
            --secondary: #06B6D4;
            --success: #10B981;
            --warning: #F59E0B;
            --danger: #EF4444;
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
        }

        body {
            font-family: <?= $isRtl ? "'Tajawal', sans-serif" : "'Inter', sans-serif" ?>;
            background: #F3F4F6;
            color: var(--gray-900);
        }

        /* Dashboard Layout */
        .dashboard {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: var(--white);
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 2rem 1.5rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .sidebar-brand {
            font-size: 1.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-decoration: none;
        }

        .sidebar-user {
            margin-top: 1rem;
            padding: 1rem;
            background: var(--gray-50);
            border-radius: 0.75rem;
        }

        .sidebar-user-name {
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 0.25rem;
        }

        .sidebar-user-role {
            font-size: 0.875rem;
            color: var(--gray-600);
        }

        .sidebar-nav {
            padding: 1rem 0;
        }

        .sidebar-nav-item {
            padding: 0.875rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: var(--gray-700);
            text-decoration: none;
            transition: all 0.2s;
            border-left: 3px solid transparent;
        }

        [dir="rtl"] .sidebar-nav-item {
            border-left: none;
            border-right: 3px solid transparent;
        }

        .sidebar-nav-item:hover {
            background: var(--gray-50);
            color: var(--primary);
        }

        .sidebar-nav-item.active {
            background: rgba(79, 70, 229, 0.05);
            color: var(--primary);
            border-left-color: var(--primary);
            font-weight: 600;
        }

        [dir="rtl"] .sidebar-nav-item.active {
            border-right-color: var(--primary);
        }

        .sidebar-nav-icon {
            font-size: 1.25rem;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-<?= $isRtl ? 'right' : 'left' ?>: 280px;
            padding: 2rem;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .header-title {
            font-size: 2rem;
            color: var(--gray-900);
        }

        .header-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .btn {
            padding: 0.625rem 1.25rem;
            border-radius: 0.5rem;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: var(--white);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .btn-outline {
            background: var(--white);
            color: var(--gray-700);
            border: 1px solid var(--gray-300);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.2);
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--white);
            padding: 1.5rem;
            border-radius: 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }

        .stat-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .stat-card-icon {
            width: 48px;
            height: 48px;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .stat-card-value {
            font-size: 2rem;
            font-weight: 800;
            color: var(--gray-900);
        }

        .stat-card-label {
            color: var(--gray-600);
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }

        /* Card */
        .card {
            background: var(--white);
            border-radius: 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .card-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--gray-900);
        }

        .card-body {
            padding: 1.5rem;
        }

        /* Grid */
        .grid {
            display: grid;
            gap: 1.5rem;
        }

        .grid-2 {
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        }

        /* Teacher Card */
        .teacher-card {
            background: linear-gradient(135deg, var(--white), var(--gray-50));
            border-radius: 0.75rem;
            padding: 1.5rem;
            border: 2px solid var(--gray-100);
            transition: all 0.3s;
        }

        .teacher-card:hover {
            border-color: var(--primary);
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }

        .teacher-card-header {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .teacher-avatar {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 1.5rem;
            font-weight: 700;
            flex-shrink: 0;
        }

        .teacher-info h3 {
            font-size: 1.125rem;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
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
            font-size: 0.875rem;
            margin-bottom: 1rem;
        }

        .teacher-stats {
            display: flex;
            gap: 1.5rem;
            padding: 1rem 0;
            border-top: 1px solid var(--gray-200);
            border-bottom: 1px solid var(--gray-200);
            margin-bottom: 1rem;
        }

        .teacher-stat {
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
        }

        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-success {
            background: #D1FAE5;
            color: #065F46;
        }

        .badge-warning {
            background: #FEF3C7;
            color: #92400E;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--gray-500);
        }

        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-state-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
        }

        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(<?= $isRtl ? '' : '-' ?>100%);
            }
            .main-content {
                margin-<?= $isRtl ? 'right' : 'left' ?>: 0;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="/student.php" class="sidebar-brand"><?= e(t('app_name')) ?></a>
                <div class="sidebar-user">
                    <div class="sidebar-user-name"><?= e($user['name']) ?></div>
                    <div class="sidebar-user-role"><?= t('student') ?></div>
                </div>
            </div>
            <nav class="sidebar-nav">
                <a href="?tab=dashboard" class="sidebar-nav-item <?= $tab === 'dashboard' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon">📊</span>
                    <?= t('dashboard') ?>
                </a>
                <a href="?tab=teachers" class="sidebar-nav-item <?= $tab === 'teachers' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon">👨‍🏫</span>
                    <?= t('browse_teachers') ?>
                </a>
                <a href="?tab=subscriptions" class="sidebar-nav-item <?= $tab === 'subscriptions' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon">📚</span>
                    <?= t('my_subscriptions') ?>
                </a>
                <a href="?tab=progress" class="sidebar-nav-item <?= $tab === 'progress' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon">📈</span>
                    <?= t('my_progress') ?>
                </a>
                <a href="?tab=exams" class="sidebar-nav-item <?= $tab === 'exams' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon">📝</span>
                    <?= t('exams') ?>
                </a>
                <a href="?tab=profile" class="sidebar-nav-item <?= $tab === 'profile' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon">👤</span>
                    <?= t('profile') ?>
                </a>
                <a href="/auth.php?action=logout" class="sidebar-nav-item">
                    <span class="sidebar-nav-icon">🚪</span>
                    <?= t('logout') ?>
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <?php if ($tab === 'dashboard'): ?>
            <div class="header">
                <h1 class="header-title"><?= $currentLang === 'ar' ? 'مرحباً، ' : 'Welcome, ' ?><?= e($user['name']) ?></h1>
                <div class="header-actions">
                    <a href="?lang=<?= $currentLang === 'en' ? 'ar' : 'en' ?>" class="btn btn-outline">
                        <?= $currentLang === 'en' ? 'العربية' : 'English' ?>
                    </a>
                </div>
            </div>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-card-icon" style="background: rgba(79, 70, 229, 0.1); color: var(--primary);">📚</div>
                    </div>
                    <div class="stat-card-value"><?= $stats['subscriptions'] ?></div>
                    <div class="stat-card-label"><?= $currentLang === 'ar' ? 'اشتراكات نشطة' : 'Active Subscriptions' ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-card-icon" style="background: rgba(16, 185, 129, 0.1); color: var(--success);">✅</div>
                    </div>
                    <div class="stat-card-value"><?= $stats['completed_lessons'] ?></div>
                    <div class="stat-card-label"><?= $currentLang === 'ar' ? 'دروس مكتملة' : 'Completed Lessons' ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-card-icon" style="background: rgba(245, 158, 11, 0.1); color: var(--warning);">📝</div>
                    </div>
                    <div class="stat-card-value"><?= $stats['exams_taken'] ?></div>
                    <div class="stat-card-label"><?= $currentLang === 'ar' ? 'امتحانات مكتملة' : 'Exams Taken' ?></div>
                </div>
            </div>

            <!-- Recent Lessons -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><?= $currentLang === 'ar' ? 'آخر الدروس' : 'Recent Lessons' ?></h2>
                    <a href="?tab=progress" class="btn btn-outline"><?= $currentLang === 'ar' ? 'عرض الكل' : 'View All' ?></a>
                </div>
                <div class="card-body">
                    <?php if (empty($recentLessons)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📚</div>
                        <div class="empty-state-title"><?= $currentLang === 'ar' ? 'لم تبدأ أي دروس بعد' : 'No lessons started yet' ?></div>
                        <p><?= $currentLang === 'ar' ? 'اشترك في معلم لبدء التعلم' : 'Subscribe to a teacher to start learning' ?></p>
                        <a href="?tab=teachers" class="btn btn-primary" style="margin-top: 1rem;"><?= t('browse_teachers') ?></a>
                    </div>
                    <?php else: ?>
                    <div class="grid">
                        <?php foreach ($recentLessons as $lesson): ?>
                        <div style="padding: 1rem; background: var(--gray-50); border-radius: 0.5rem; display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <h4 style="color: var(--gray-900); margin-bottom: 0.25rem;"><?= e($currentLang === 'ar' ? $lesson['title_ar'] : $lesson['title_en']) ?></h4>
                                <p style="font-size: 0.875rem; color: var(--gray-600);"><?= e($lesson['teacher_name']) ?> • <?= timeAgo($lesson['updated_at']) ?></p>
                            </div>
                            <?php if ($lesson['completed']): ?>
                            <span class="badge badge-success">✓ <?= $currentLang === 'ar' ? 'مكتمل' : 'Completed' ?></span>
                            <?php else: ?>
                            <a href="/lesson.php?id=<?= $lesson['id'] ?>" class="btn btn-primary"><?= $currentLang === 'ar' ? 'متابعة' : 'Continue' ?></a>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php elseif ($tab === 'teachers'): ?>
            <div class="header">
                <h1 class="header-title"><?= t('browse_teachers') ?></h1>
            </div>

            <div class="grid grid-2">
                <?php foreach ($teachers as $teacher): ?>
                <div class="teacher-card">
                    <div class="teacher-card-header">
                        <div class="teacher-avatar"><?= strtoupper(substr($teacher['name'], 0, 2)) ?></div>
                        <div class="teacher-info">
                            <h3><?= e($teacher['name']) ?></h3>
                            <div class="teacher-rating">
                                ⭐ <?= number_format($teacher['rating'], 2) ?> (<?= $teacher['rating_count'] ?> <?= $currentLang === 'ar' ? 'تقييم' : 'reviews' ?>)
                            </div>
                        </div>
                    </div>
                    <div class="teacher-subjects"><?= e($currentLang === 'ar' ? $teacher['subjects_ar'] : $teacher['subjects_en']) ?></div>
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
                    <?php if ($teacher['is_subscribed']): ?>
                    <span class="badge badge-success" style="width: 100%; text-align: center; display: block; padding: 0.75rem;">
                        ✓ <?= t('subscribed') ?>
                    </span>
                    <?php else: ?>
                    <a href="/pay.php?teacher_id=<?= $teacher['id'] ?>" class="btn btn-primary" style="width: 100%; justify-content: center;">
                        <?= t('subscribe') ?>
                    </a>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <?php elseif ($tab === 'subscriptions'): ?>
            <div class="header">
                <h1 class="header-title"><?= t('my_subscriptions') ?></h1>
            </div>

            <div class="card">
                <div class="card-body">
                    <?php if (empty($subscriptions)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📚</div>
                        <div class="empty-state-title"><?= $currentLang === 'ar' ? 'لا توجد اشتراكات' : 'No Subscriptions' ?></div>
                        <p><?= $currentLang === 'ar' ? 'اشترك في معلم للوصول إلى الدروس' : 'Subscribe to a teacher to access lessons' ?></p>
                        <a href="?tab=teachers" class="btn btn-primary" style="margin-top: 1rem;"><?= t('browse_teachers') ?></a>
                    </div>
                    <?php else: ?>
                    <div class="grid">
                        <?php foreach ($subscriptions as $sub): ?>
                        <div style="padding: 1.5rem; background: var(--gray-50); border-radius: 0.75rem; border: 2px solid var(--gray-200);">
                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                                <div>
                                    <h3 style="font-size: 1.125rem; color: var(--gray-900); margin-bottom: 0.25rem;"><?= e($currentLang === 'ar' ? $sub['title_ar'] : $sub['title_en']) ?></h3>
                                    <p style="color: var(--gray-600);"><?= e($sub['teacher_name']) ?></p>
                                </div>
                                <span class="badge badge-success"><?= t('subscribed') ?></span>
                            </div>
                            <div style="display: flex; justify-content: space-between; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--gray-200);">
                                <span style="color: var(--gray-600); font-size: 0.875rem;"><?= t('subscription_expires') ?>: <?= formatDate($sub['end_at']) ?></span>
                                <span style="font-weight: 700; color: var(--primary);"><?= formatPrice($sub['price'], $sub['currency']) ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php endif; ?>
        </main>
    </div>
</body>
</html>
