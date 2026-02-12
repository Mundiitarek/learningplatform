<?php
/**
 * Teacher Dashboard
 * Content management, student tracking, earnings
 */

define('APP_INIT', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

requireRole('teacher');

$user = getCurrentUser();
$currentLang = getCurrentLang();
$isRtl = isRtl();
$tab = $_GET['tab'] ?? 'dashboard';

// Get teacher profile
$profile = db()->fetchOne("SELECT * FROM teacher_profiles WHERE user_id = ?", [$user['id']]);

// Handle create lesson
if (isset($_POST['create_lesson']) && $tab === 'lessons') {
    requireCsrf();

    $unitId = $_POST['unit_id'] ?? 0;
    $titleEn = trim($_POST['title_en'] ?? '');
    $titleAr = trim($_POST['title_ar'] ?? '');
    $descEn = trim($_POST['description_en'] ?? '');
    $descAr = trim($_POST['description_ar'] ?? '');
    $contentEn = $_POST['content_en'] ?? '';
    $contentAr = $_POST['content_ar'] ?? '';
    $videoProvider = $_POST['video_provider'] ?? 'none';
    $videoId = trim($_POST['video_id'] ?? '');

    if ($titleEn && $titleAr && $unitId) {
        db()->execute(
            "INSERT INTO lessons (unit_id, teacher_id, title_en, title_ar, description_en, description_ar, content_en, content_ar, video_provider, video_id, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'published', NOW())",
            [$unitId, $user['id'], $titleEn, $titleAr, $descEn, $descAr, $contentEn, $contentAr, $videoProvider, $videoId]
        );

        logSecurityEvent('lesson_created', $user['id'], 'Teacher created new lesson', ['title' => $titleEn]);
        redirect('/teacher.php?tab=lessons&success=1');
    }
}

// Get stats
$stats = [
    'total_students' => $profile['total_students'] ?? 0,
    'total_lessons' => db()->fetchOne("SELECT COUNT(*) as count FROM lessons WHERE teacher_id = ?", [$user['id']])['count'],
    'total_earnings' => $profile['total_earnings'] ?? 0,
    'rating' => $profile['rating'] ?? 0,
];

// Get recent students
$recentStudents = db()->fetchAll(
    "SELECT DISTINCT u.id, u.name, u.email, s.created_at, s.status, s.end_at
     FROM subscriptions s
     INNER JOIN users u ON s.student_id = u.id
     WHERE s.teacher_id = ?
     ORDER BY s.created_at DESC
     LIMIT 10",
    [$user['id']]
);

// Get units and lessons
$units = db()->fetchAll(
    "SELECT u.*, s.name_en as subject_name_en, s.name_ar as subject_name_ar,
            (SELECT COUNT(*) FROM lessons WHERE unit_id = u.id) as lesson_count
     FROM units u
     INNER JOIN subjects s ON u.subject_id = s.id
     WHERE u.teacher_id = ?
     ORDER BY u.order_num",
    [$user['id']]
);

// Get subjects for dropdown
$subjects = db()->fetchAll("SELECT * FROM subjects WHERE active = TRUE");
$years = db()->fetchAll("SELECT * FROM academic_years ORDER BY order_num");
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>" dir="<?= $isRtl ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('teacher') ?> - <?= e($user['name']) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --primary: #4F46E5;
            --primary-dark: #4338CA;
            --success: #10B981;
            --warning: #F59E0B;
            --danger: #EF4444;
            --white: #FFFFFF;
            --gray-50: #F9FAFB;
            --gray-100: #F3F4F6;
            --gray-200: #E5E7EB;
            --gray-600: #4B5563;
            --gray-700: #374151;
            --gray-900: #111827;
        }
        body {
            font-family: <?= $isRtl ? "'Tajawal', sans-serif" : "'Inter', sans-serif" ?>;
            background: #F3F4F6;
            color: var(--gray-900);
        }
        .dashboard { display: flex; min-height: 100vh; }
        .sidebar {
            width: 280px;
            background: var(--white);
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }
        .sidebar-header { padding: 2rem 1.5rem; border-bottom: 1px solid var(--gray-200); }
        .sidebar-brand {
            font-size: 1.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary), #06B6D4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-decoration: none;
        }
        .sidebar-user { margin-top: 1rem; padding: 1rem; background: var(--gray-50); border-radius: 0.75rem; }
        .sidebar-user-name { font-weight: 600; color: var(--gray-900); }
        .sidebar-user-role { font-size: 0.875rem; color: var(--gray-600); }
        .sidebar-nav { padding: 1rem 0; }
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
        .sidebar-nav-item:hover { background: var(--gray-50); color: var(--primary); }
        .sidebar-nav-item.active { background: rgba(79, 70, 229, 0.05); color: var(--primary); border-left-color: var(--primary); font-weight: 600; }
        .main-content { flex: 1; margin-<?= $isRtl ? 'right' : 'left' ?>: 280px; padding: 2rem; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem; }
        .header-title { font-size: 2rem; }
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
        .btn-primary { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: var(--white); }
        .btn-outline { background: var(--white); color: var(--gray-700); border: 1px solid var(--gray-200); }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.2); }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card {
            background: var(--white);
            padding: 1.5rem;
            border-radius: 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .stat-card-value { font-size: 2rem; font-weight: 800; color: var(--gray-900); }
        .stat-card-label { color: var(--gray-600); font-size: 0.875rem; margin-top: 0.25rem; }
        .card { background: var(--white); border-radius: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 2rem; }
        .card-header { padding: 1.5rem; border-bottom: 1px solid var(--gray-200); }
        .card-title { font-size: 1.25rem; font-weight: 700; }
        .card-body { padding: 1.5rem; }
        .form-group { margin-bottom: 1.5rem; }
        .form-label { display: block; font-weight: 600; margin-bottom: 0.5rem; color: var(--gray-700); }
        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid var(--gray-200);
            border-radius: 0.5rem;
            font-size: 1rem;
        }
        .form-textarea { min-height: 150px; resize: vertical; font-family: inherit; }
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--primary);
        }
        .badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
        .badge-success { background: #D1FAE5; color: #065F46; }
        .badge-warning { background: #FEF3C7; color: #92400E; }
        .unit-card {
            background: var(--gray-50);
            padding: 1.5rem;
            border-radius: 0.75rem;
            border: 2px solid var(--gray-200);
            margin-bottom: 1rem;
        }
        .unit-header { display: flex; justify-content: space-between; align-items: center; }
        .unit-title { font-size: 1.125rem; font-weight: 700; color: var(--gray-900); }
        .empty-state { text-align: center; padding: 3rem; color: var(--gray-600); }
        @media (max-width: 1024px) {
            .sidebar { transform: translateX(<?= $isRtl ? '' : '-' ?>100%); }
            .main-content { margin-<?= $isRtl ? 'right' : 'left' ?>: 0; }
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="/teacher.php" class="sidebar-brand"><?= e(t('app_name')) ?></a>
                <div class="sidebar-user">
                    <div class="sidebar-user-name"><?= e($user['name']) ?></div>
                    <div class="sidebar-user-role"><?= t('teacher') ?></div>
                </div>
            </div>
            <nav class="sidebar-nav">
                <a href="?tab=dashboard" class="sidebar-nav-item <?= $tab === 'dashboard' ? 'active' : '' ?>">📊 <?= t('dashboard') ?></a>
                <a href="?tab=lessons" class="sidebar-nav-item <?= $tab === 'lessons' ? 'active' : '' ?>">📚 <?= t('lessons') ?></a>
                <a href="?tab=students" class="sidebar-nav-item <?= $tab === 'students' ? 'active' : '' ?>">👥 <?= t('my_students') ?></a>
                <a href="?tab=exams" class="sidebar-nav-item <?= $tab === 'exams' ? 'active' : '' ?>">📝 <?= t('exams') ?></a>
                <a href="?tab=earnings" class="sidebar-nav-item <?= $tab === 'earnings' ? 'active' : '' ?>">💰 <?= t('earnings') ?></a>
                <a href="/auth.php?action=logout" class="sidebar-nav-item">🚪 <?= t('logout') ?></a>
            </nav>
        </aside>

        <main class="main-content">
            <?php if ($tab === 'dashboard'): ?>
            <div class="header">
                <h1 class="header-title"><?= $currentLang === 'ar' ? 'لوحة المعلم' : 'Teacher Dashboard' ?></h1>
            </div>

            <?php if ($profile && $profile['approved'] !== 'approved'): ?>
            <div style="background: #FEF3C7; border: 1px solid #F59E0B; padding: 1rem; border-radius: 0.5rem; margin-bottom: 2rem; color: #92400E;">
                <?= $currentLang === 'ar' ? '⚠️ حسابك قيد المراجعة. سيتم إخطارك عند الموافقة.' : '⚠️ Your account is pending approval. You will be notified once approved.' ?>
            </div>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-card-value"><?= $stats['total_students'] ?></div>
                    <div class="stat-card-label"><?= $currentLang === 'ar' ? 'إجمالي الطلاب' : 'Total Students' ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-value"><?= $stats['total_lessons'] ?></div>
                    <div class="stat-card-label"><?= $currentLang === 'ar' ? 'الدروس المنشورة' : 'Published Lessons' ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-value"><?= formatPrice($stats['total_earnings'], 'EGP') ?></div>
                    <div class="stat-card-label"><?= t('earnings') ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-value">⭐ <?= number_format($stats['rating'], 2) ?></div>
                    <div class="stat-card-label"><?= $currentLang === 'ar' ? 'التقييم' : 'Rating' ?></div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2 class="card-title"><?= $currentLang === 'ar' ? 'الطلاب الجدد' : 'Recent Students' ?></h2></div>
                <div class="card-body">
                    <?php if (empty($recentStudents)): ?>
                    <div class="empty-state"><?= $currentLang === 'ar' ? 'لا يوجد طلاب بعد' : 'No students yet' ?></div>
                    <?php else: ?>
                    <?php foreach ($recentStudents as $student): ?>
                    <div style="padding: 1rem; background: var(--gray-50); border-radius: 0.5rem; margin-bottom: 0.5rem; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <div style="font-weight: 600;"><?= e($student['name']) ?></div>
                            <div style="font-size: 0.875rem; color: var(--gray-600);"><?= e($student['email']) ?></div>
                        </div>
                        <span class="badge badge-<?= $student['status'] === 'active' ? 'success' : 'warning' ?>"><?= e($student['status']) ?></span>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <?php elseif ($tab === 'lessons'): ?>
            <div class="header">
                <h1 class="header-title"><?= t('lessons') ?></h1>
                <button onclick="document.getElementById('createLessonModal').style.display='block'" class="btn btn-primary">
                    + <?= t('create_lesson') ?>
                </button>
            </div>

            <?php foreach ($units as $unit): ?>
            <div class="unit-card">
                <div class="unit-header">
                    <div>
                        <div class="unit-title"><?= e($currentLang === 'ar' ? $unit['title_ar'] : $unit['title_en']) ?></div>
                        <div style="font-size: 0.875rem; color: var(--gray-600); margin-top: 0.25rem;">
                            <?= e($currentLang === 'ar' ? $unit['subject_name_ar'] : $unit['subject_name_en']) ?> • <?= $unit['lesson_count'] ?> <?= $currentLang === 'ar' ? 'درس' : 'lessons' ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if (empty($units)): ?>
            <div class="empty-state"><?= $currentLang === 'ar' ? 'لم تنشئ أي دروس بعد' : 'No lessons created yet' ?></div>
            <?php endif; ?>

            <!-- Create Lesson Modal -->
            <div id="createLessonModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:9999; padding:2rem; overflow-y:auto;">
                <div style="max-width:800px; margin:2rem auto; background:white; border-radius:1rem; padding:2rem;">
                    <h2 style="margin-bottom:1.5rem;"><?= t('create_lesson') ?></h2>
                    <form method="POST">
                        <?= csrfField() ?>
                        <input type="hidden" name="create_lesson" value="1">

                        <div class="form-group">
                            <label class="form-label"><?= $currentLang === 'ar' ? 'الوحدة' : 'Unit' ?></label>
                            <select name="unit_id" class="form-select" required>
                                <option value=""><?= $currentLang === 'ar' ? 'اختر الوحدة' : 'Select Unit' ?></option>
                                <?php foreach ($units as $unit): ?>
                                <option value="<?= $unit['id'] ?>"><?= e($currentLang === 'ar' ? $unit['title_ar'] : $unit['title_en']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label"><?= $currentLang === 'ar' ? 'العنوان بالإنجليزية' : 'Title (English)' ?></label>
                            <input type="text" name="title_en" class="form-input" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label"><?= $currentLang === 'ar' ? 'العنوان بالعربية' : 'Title (Arabic)' ?></label>
                            <input type="text" name="title_ar" class="form-input" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label"><?= $currentLang === 'ar' ? 'مصدر الفيديو' : 'Video Provider' ?></label>
                            <select name="video_provider" class="form-select">
                                <option value="none"><?= $currentLang === 'ar' ? 'بدون فيديو' : 'No video' ?></option>
                                <option value="youtube">YouTube</option>
                                <option value="vimeo">Vimeo</option>
                                <option value="external"><?= $currentLang === 'ar' ? 'رابط خارجي' : 'External URL' ?></option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label"><?= $currentLang === 'ar' ? 'معرف/رابط الفيديو' : 'Video ID/URL' ?></label>
                            <input type="text" name="video_id" class="form-input" placeholder="dQw4w9WgXcQ or https://...">
                        </div>

                        <div style="display:flex; gap:1rem; margin-top:2rem;">
                            <button type="submit" class="btn btn-primary"><?= t('create_lesson') ?></button>
                            <button type="button" onclick="document.getElementById('createLessonModal').style.display='none'" class="btn btn-outline"><?= t('cancel') ?></button>
                        </div>
                    </form>
                </div>
            </div>

            <?php endif; ?>
        </main>
    </div>
</body>
</html>
