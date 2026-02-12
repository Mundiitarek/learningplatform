<?php
/**
 * Lesson Viewer with Content Protection
 * Watermarked video player, secure file access
 */

define('APP_INIT', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

requireAuth();

$lessonId = $_GET['id'] ?? 0;
$user = getCurrentUser();
$currentLang = getCurrentLang();
$isRtl = isRtl();

// Get lesson details
$lesson = db()->fetchOne(
    "SELECT l.*, u.id as unit_id, u.title_en as unit_title_en, u.title_ar as unit_title_ar,
            t.name as teacher_name, l.teacher_id
     FROM lessons l
     INNER JOIN units u ON l.unit_id = u.id
     INNER JOIN users t ON l.teacher_id = t.id
     WHERE l.id = ? AND l.status = 'published'",
    [$lessonId]
);

if (!$lesson) {
    die('Lesson not found');
}

// Check access: free preview OR has active subscription
$hasAccess = $lesson['is_free_preview'] ||
              hasActiveSubscription($user['id'], $lesson['teacher_id']) ||
              $user['role'] === 'admin' ||
              $user['id'] == $lesson['teacher_id'];

if (!$hasAccess) {
    die('<h1 style="text-align:center; padding:3rem; font-family:sans-serif;">🔒 ' .
        ($currentLang === 'ar' ? 'يجب الاشتراك لعرض هذا الدرس' : 'Subscription required to view this lesson') .
        '</h1><p style="text-align:center;"><a href="/student.php?tab=teachers">Browse Teachers</a></p>');
}

// Track progress
$progress = db()->fetchOne(
    "SELECT * FROM lesson_progress WHERE student_id = ? AND lesson_id = ?",
    [$user['id'], $lessonId]
);

if (!$progress) {
    db()->execute(
        "INSERT INTO lesson_progress (student_id, lesson_id, created_at) VALUES (?, ?, NOW())",
        [$user['id'], $lessonId]
    );
}

// Get lesson files
$files = db()->fetchAll(
    "SELECT * FROM lesson_files WHERE lesson_id = ?",
    [$lessonId]
);

// Increment views
db()->execute("UPDATE lessons SET views = views + 1 WHERE id = ?", [$lessonId]);
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>" dir="<?= $isRtl ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($currentLang === 'ar' ? $lesson['title_ar'] : $lesson['title_en']) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: <?= $isRtl ? "'Tajawal', sans-serif" : "'Inter', sans-serif" ?>;
            background: #F3F4F6;
            color: #111827;
        }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        .header {
            background: white;
            padding: 1.5rem;
            border-radius: 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        .breadcrumb {
            color: #6B7280;
            font-size: 0.875rem;
            margin-bottom: 1rem;
        }
        .breadcrumb a {
            color: #4F46E5;
            text-decoration: none;
        }
        .lesson-title {
            font-size: 2rem;
            color: #111827;
            margin-bottom: 0.5rem;
        }
        .lesson-meta {
            color: #6B7280;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .video-container {
            position: relative;
            background: #000;
            border-radius: 1rem;
            overflow: hidden;
            margin-bottom: 2rem;
        }
        .video-wrapper {
            position: relative;
            padding-bottom: 56.25%; /* 16:9 */
            height: 0;
        }
        .video-wrapper iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }
        .watermark {
            position: absolute;
            bottom: 20px;
            right: 20px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            pointer-events: none;
            z-index: 1000;
        }
        [dir="rtl"] .watermark {
            right: auto;
            left: 20px;
        }
        .content {
            background: white;
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        .files {
            background: white;
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .file-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: #F9FAFB;
            border-radius: 0.5rem;
            margin-bottom: 0.5rem;
        }
        .btn {
            padding: 0.5rem 1rem;
            background: linear-gradient(135deg, #4F46E5, #4338CA);
            color: white;
            border-radius: 0.5rem;
            text-decoration: none;
            font-weight: 600;
            display: inline-block;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="breadcrumb">
                <a href="/<?= $user['role'] ?>.php"><?= t('dashboard') ?></a> /
                <?= e($currentLang === 'ar' ? $lesson['unit_title_ar'] : $lesson['unit_title_en']) ?> /
                <?= e($currentLang === 'ar' ? $lesson['title_ar'] : $lesson['title_en']) ?>
            </div>
            <h1 class="lesson-title"><?= e($currentLang === 'ar' ? $lesson['title_ar'] : $lesson['title_en']) ?></h1>
            <div class="lesson-meta">
                <span>👨‍🏫 <?= e($lesson['teacher_name']) ?></span>
                <span>👁️ <?= number_format($lesson['views']) ?> <?= $currentLang === 'ar' ? 'مشاهدة' : 'views' ?></span>
                <?php if ($lesson['is_free_preview']): ?>
                <span style="background:#FEF3C7; color:#92400E; padding:0.25rem 0.75rem; border-radius:9999px; font-size:0.75rem; font-weight:600;">
                    🆓 <?= $currentLang === 'ar' ? 'معاينة مجانية' : 'Free Preview' ?>
                </span>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($lesson['video_provider'] !== 'none' && $lesson['video_id']): ?>
        <div class="video-container">
            <div class="video-wrapper">
                <?php
                $embedUrl = '';
                if ($lesson['video_provider'] === 'youtube') {
                    $embedUrl = 'https://www.youtube.com/embed/' . e($lesson['video_id']) . '?rel=0&modestbranding=1';
                } elseif ($lesson['video_provider'] === 'vimeo') {
                    $embedUrl = 'https://player.vimeo.com/video/' . e($lesson['video_id']);
                } elseif ($lesson['video_provider'] === 'external') {
                    $embedUrl = e($lesson['video_id']);
                }
                ?>
                <iframe src="<?= $embedUrl ?>" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
            </div>
            <?php if (VIDEO_WATERMARK_ENABLED): ?>
            <div class="watermark">
                <?= e($user['name']) ?> • ID: <?= $user['id'] ?> • <?= date('Y-m-d H:i') ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($lesson['content_en'] || $lesson['content_ar']): ?>
        <div class="content">
            <h2 style="margin-bottom:1rem; font-size:1.5rem;"><?= $currentLang === 'ar' ? 'محتوى الدرس' : 'Lesson Content' ?></h2>
            <div>
                <?= nl2br(e($currentLang === 'ar' ? $lesson['content_ar'] : $lesson['content_en'])) ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($files)): ?>
        <div class="files">
            <h2 style="margin-bottom:1rem; font-size:1.5rem;">📎 <?= $currentLang === 'ar' ? 'الملفات المرفقة' : 'Attachments' ?></h2>
            <?php foreach ($files as $file): ?>
            <div class="file-item">
                <div>
                    <div style="font-weight:600;"><?= e($file['original_name']) ?></div>
                    <div style="font-size:0.875rem; color:#6B7280;"><?= number_format($file['file_size'] / 1024) ?> KB</div>
                </div>
                <a href="/download.php?id=<?= $file['id'] ?>&token=<?= hash_hmac('sha256', $file['id'], CRON_SECRET_KEY) ?>" class="btn" target="_blank">
                    <?= $currentLang === 'ar' ? 'تحميل' : 'Download' ?>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Mark lesson as viewed after 30 seconds
        setTimeout(function() {
            fetch('/api.php?action=mark_progress&lesson_id=<?= $lessonId ?>')
                .catch(err => console.log('Progress tracking failed'));
        }, 30000);
    </script>
</body>
</html>
