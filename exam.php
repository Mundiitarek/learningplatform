<?php
/**
 * Exam System
 * MCQ, True/False, timed exams, anti-cheat
 */

define('APP_INIT', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

requireRole('student');

$examId = $_GET['id'] ?? 0;
$user = getCurrentUser();
$currentLang = getCurrentLang();
$isRtl = isRtl();

// Get exam
$exam = db()->fetchOne(
    "SELECT e.*, u.name as teacher_name FROM exams e
     INNER JOIN users u ON e.teacher_id = u.id
     WHERE e.id = ? AND e.status = 'published'",
    [$examId]
);

if (!$exam) {
    die('Exam not found');
}

// Check access
$hasAccess = hasActiveSubscription($user['id'], $exam['teacher_id']);
if (!$hasAccess && $user['role'] !== 'admin') {
    die('Access denied');
}

// Check attempts
$attempts = db()->fetchAll(
    "SELECT * FROM exam_attempts WHERE exam_id = ? AND student_id = ? ORDER BY created_at DESC",
    [$examId, $user['id']]
);

$attemptsCount = count($attempts);
$canTakeExam = $attemptsCount < $exam['max_attempts'];

// Handle start exam
if (isset($_POST['start_exam']) && $canTakeExam) {
    requireCsrf();

    db()->execute(
        "INSERT INTO exam_attempts (exam_id, student_id, started_at, status) VALUES (?, ?, NOW(), 'in_progress')",
        [$examId, $user['id']]
    );

    $attemptId = db()->lastInsertId();
    redirect('/exam.php?id=' . $examId . '&attempt=' . $attemptId);
}

// Handle submit exam
if (isset($_POST['submit_exam'])) {
    requireCsrf();

    $attemptId = $_POST['attempt_id'] ?? 0;
    $answers = $_POST['answers'] ?? [];

    // Get attempt
    $attempt = db()->fetchOne(
        "SELECT * FROM exam_attempts WHERE id = ? AND student_id = ? AND status = 'in_progress'",
        [$attemptId, $user['id']]
    );

    if ($attempt) {
        // Get questions
        $questions = db()->fetchAll(
            "SELECT * FROM exam_questions WHERE exam_id = ?",
            [$examId]
        );

        $totalScore = 0;
        $earnedScore = 0;

        foreach ($questions as $q) {
            $totalScore += $q['points'];
            $answerId = $answers[$q['id']] ?? null;

            if ($answerId) {
                // Get correct choice
                $correctChoice = db()->fetchOne(
                    "SELECT * FROM question_choices WHERE question_id = ? AND is_correct = 1",
                    [$q['id']]
                );

                $isCorrect = ($answerId == $correctChoice['id']);
                $pointsEarned = $isCorrect ? $q['points'] : 0;
                $earnedScore += $pointsEarned;

                // Save answer
                db()->execute(
                    "INSERT INTO exam_answers (attempt_id, question_id, choice_id, is_correct, points_earned) VALUES (?, ?, ?, ?, ?)",
                    [$attemptId, $q['id'], $answerId, $isCorrect, $pointsEarned]
                );
            }
        }

        $percentage = $totalScore > 0 ? ($earnedScore / $totalScore) * 100 : 0;
        $passed = $percentage >= $exam['passing_score'];

        // Update attempt
        db()->execute(
            "UPDATE exam_attempts SET score = ?, max_score = ?, percentage = ?, passed = ?, submitted_at = NOW(), status = 'submitted' WHERE id = ?",
            [$earnedScore, $totalScore, $percentage, $passed, $attemptId]
        );

        redirect('/exam.php?id=' . $examId . '&result=' . $attemptId);
    }
}

// Get current attempt
$attemptId = $_GET['attempt'] ?? 0;
$currentAttempt = null;
$questions = [];

if ($attemptId) {
    $currentAttempt = db()->fetchOne(
        "SELECT * FROM exam_attempts WHERE id = ? AND student_id = ? AND status = 'in_progress'",
        [$attemptId, $user['id']]
    );

    if ($currentAttempt) {
        // Get questions with choices
        $questions = db()->fetchAll(
            "SELECT * FROM exam_questions WHERE exam_id = ? ORDER BY " . ($exam['randomize_questions'] ? 'RAND()' : 'order_num'),
            [$examId]
        );

        foreach ($questions as &$q) {
            $q['choices'] = db()->fetchAll(
                "SELECT * FROM question_choices WHERE question_id = ? ORDER BY order_num",
                [$q['id']]
            );
        }
    }
}

// Show results
$showResults = isset($_GET['result']);
$resultAttempt = null;
$resultAnswers = [];

if ($showResults) {
    $resultAttempt = db()->fetchOne(
        "SELECT * FROM exam_attempts WHERE id = ? AND student_id = ?",
        [$_GET['result'], $user['id']]
    );

    if ($resultAttempt) {
        $resultAnswers = db()->fetchAll(
            "SELECT ea.*, eq.question_en, eq.question_ar, eq.points, qc.choice_en, qc.choice_ar
             FROM exam_answers ea
             INNER JOIN exam_questions eq ON ea.question_id = eq.id
             LEFT JOIN question_choices qc ON ea.choice_id = qc.id
             WHERE ea.attempt_id = ?",
            [$resultAttempt['id']]
        );
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>" dir="<?= $isRtl ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($currentLang === 'ar' ? $exam['title_ar'] : $exam['title_en']) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: <?= $isRtl ? "'Tajawal', sans-serif" : "'Inter', sans-serif" ?>; background: #F3F4F6; }
        .container { max-width: 900px; margin: 2rem auto; padding: 0 1rem; }
        .card { background: white; border-radius: 1rem; padding: 2rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 2rem; }
        .exam-header { text-align: center; padding: 2rem; background: linear-gradient(135deg, #4F46E5, #7C3AED); color: white; border-radius: 1rem; margin-bottom: 2rem; }
        .exam-title { font-size: 2rem; margin-bottom: 0.5rem; }
        .exam-meta { opacity: 0.9; font-size: 0.875rem; }
        .question { background: #F9FAFB; padding: 1.5rem; border-radius: 0.75rem; margin-bottom: 1.5rem; border-left: 4px solid #4F46E5; }
        [dir="rtl"] .question { border-left: none; border-right: 4px solid #4F46E5; }
        .question-text { font-size: 1.125rem; font-weight: 600; margin-bottom: 1rem; color: #111827; }
        .choice { padding: 1rem; background: white; border: 2px solid #E5E7EB; border-radius: 0.5rem; margin-bottom: 0.5rem; cursor: pointer; transition: all 0.2s; }
        .choice:hover { border-color: #4F46E5; background: #F0F4FF; }
        .choice input[type="radio"] { margin-<?= $isRtl ? 'left' : 'right' ?>: 0.75rem; }
        .btn { padding: 0.875rem 2rem; background: linear-gradient(135deg, #4F46E5, #4338CA); color: white; border: none; border-radius: 0.5rem; font-weight: 600; font-size: 1rem; cursor: pointer; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.2); }
        .timer { position: fixed; top: 2rem; right: 2rem; background: white; padding: 1rem 1.5rem; border-radius: 0.75rem; box-shadow: 0 4px 6px rgba(0,0,0,0.1); font-weight: 700; font-size: 1.25rem; color: #4F46E5; }
        .result-card { text-align: center; padding: 3rem; }
        .result-score { font-size: 4rem; font-weight: 800; margin: 1rem 0; }
        .result-passed { color: #10B981; }
        .result-failed { color: #EF4444; }
        .attempts-info { background: #FEF3C7; border: 1px solid #F59E0B; padding: 1rem; border-radius: 0.5rem; color: #92400E; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($showResults && $resultAttempt): ?>
        <!-- Results View -->
        <div class="card">
            <div class="result-card">
                <h1 style="font-size: 1.5rem; margin-bottom: 1rem;"><?= $currentLang === 'ar' ? 'نتيجة الامتحان' : 'Exam Results' ?></h1>
                <div class="result-score <?= $resultAttempt['passed'] ? 'result-passed' : 'result-failed' ?>">
                    <?= number_format($resultAttempt['percentage'], 1) ?>%
                </div>
                <div style="font-size: 1.25rem; margin-bottom: 2rem;">
                    <?= $resultAttempt['score'] ?> / <?= $resultAttempt['max_score'] ?> <?= $currentLang === 'ar' ? 'نقطة' : 'points' ?>
                </div>
                <?php if ($resultAttempt['passed']): ?>
                <div style="color: #10B981; font-size: 1.5rem; margin-bottom: 1rem;">✅ <?= $currentLang === 'ar' ? 'ناجح!' : 'Passed!' ?></div>
                <?php else: ?>
                <div style="color: #EF4444; font-size: 1.5rem; margin-bottom: 1rem;">❌ <?= $currentLang === 'ar' ? 'راسب' : 'Failed' ?></div>
                <p><?= $currentLang === 'ar' ? 'الدرجة المطلوبة: ' : 'Passing score: ' ?><?= $exam['passing_score'] ?>%</p>
                <?php endif; ?>
                <div style="margin-top: 2rem;">
                    <a href="/student.php" class="btn"><?= $currentLang === 'ar' ? 'العودة للوحة التحكم' : 'Back to Dashboard' ?></a>
                </div>
            </div>
        </div>

        <?php elseif ($currentAttempt && !empty($questions)): ?>
        <!-- Exam Taking View -->
        <?php if ($exam['duration_minutes'] > 0): ?>
        <div class="timer" id="timer">
            <span id="time-left"><?= $exam['duration_minutes'] ?>:00</span>
        </div>
        <script>
            let timeLeft = <?= $exam['duration_minutes'] ?> * 60;
            const timerEl = document.getElementById('time-left');
            const interval = setInterval(function() {
                timeLeft--;
                const mins = Math.floor(timeLeft / 60);
                const secs = timeLeft % 60;
                timerEl.textContent = mins + ':' + (secs < 10 ? '0' : '') + secs;
                if (timeLeft <= 0) {
                    clearInterval(interval);
                    document.getElementById('examForm').submit();
                }
            }, 1000);
        </script>
        <?php endif; ?>

        <div class="exam-header">
            <h1 class="exam-title"><?= e($currentLang === 'ar' ? $exam['title_ar'] : $exam['title_en']) ?></h1>
            <div class="exam-meta">
                <?= count($questions) ?> <?= $currentLang === 'ar' ? 'سؤال' : 'questions' ?> •
                <?= $exam['duration_minutes'] ?> <?= $currentLang === 'ar' ? 'دقيقة' : 'minutes' ?> •
                <?= $currentLang === 'ar' ? 'درجة النجاح: ' : 'Passing: ' ?><?= $exam['passing_score'] ?>%
            </div>
        </div>

        <form method="POST" id="examForm">
            <?= csrfField() ?>
            <input type="hidden" name="submit_exam" value="1">
            <input type="hidden" name="attempt_id" value="<?= $currentAttempt['id'] ?>">

            <?php foreach ($questions as $index => $q): ?>
            <div class="question">
                <div class="question-text">
                    <?= ($index + 1) ?>. <?= e($currentLang === 'ar' ? $q['question_ar'] : $q['question_en']) ?>
                    <span style="color: #6B7280; font-size: 0.875rem; font-weight: normal;">
                        (<?= $q['points'] ?> <?= $currentLang === 'ar' ? 'نقاط' : 'pts' ?>)
                    </span>
                </div>
                <?php foreach ($q['choices'] as $choice): ?>
                <label class="choice">
                    <input type="radio" name="answers[<?= $q['id'] ?>]" value="<?= $choice['id'] ?>" required>
                    <?= e($currentLang === 'ar' ? $choice['choice_ar'] : $choice['choice_en']) ?>
                </label>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>

            <div style="text-align: center; margin-top: 2rem;">
                <button type="submit" class="btn"><?= $currentLang === 'ar' ? 'إرسال الإجابات' : 'Submit Answers' ?></button>
            </div>
        </form>

        <?php else: ?>
        <!-- Exam Overview -->
        <div class="exam-header">
            <h1 class="exam-title"><?= e($currentLang === 'ar' ? $exam['title_ar'] : $exam['title_en']) ?></h1>
            <div class="exam-meta">👨‍🏫 <?= e($exam['teacher_name']) ?></div>
        </div>

        <div class="card">
            <?php if ($attemptsCount > 0): ?>
            <div class="attempts-info">
                <?= $currentLang === 'ar' ? 'لقد استخدمت ' : 'You have used ' ?>
                <strong><?= $attemptsCount ?></strong> <?= $currentLang === 'ar' ? ' من ' : ' of ' ?>
                <strong><?= $exam['max_attempts'] ?></strong>
                <?= $currentLang === 'ar' ? ' محاولات' : ' attempts' ?>
            </div>
            <?php endif; ?>

            <h2 style="margin-bottom: 1rem;"><?= $currentLang === 'ar' ? 'تفاصيل الامتحان' : 'Exam Details' ?></h2>
            <ul style="list-style: none; padding: 0;">
                <li style="padding: 0.75rem 0; border-bottom: 1px solid #E5E7EB;">
                    ⏱️ <?= $currentLang === 'ar' ? 'المدة: ' : 'Duration: ' ?><strong><?= $exam['duration_minutes'] ?> <?= $currentLang === 'ar' ? 'دقيقة' : 'minutes' ?></strong>
                </li>
                <li style="padding: 0.75rem 0; border-bottom: 1px solid #E5E7EB;">
                    📝 <?= $currentLang === 'ar' ? 'درجة النجاح: ' : 'Passing Score: ' ?><strong><?= $exam['passing_score'] ?>%</strong>
                </li>
                <li style="padding: 0.75rem 0; border-bottom: 1px solid #E5E7EB;">
                    🔄 <?= $currentLang === 'ar' ? 'المحاولات المسموحة: ' : 'Max Attempts: ' ?><strong><?= $exam['max_attempts'] ?></strong>
                </li>
            </ul>

            <?php if (!empty($attempts)): ?>
            <h3 style="margin: 2rem 0 1rem;"><?= $currentLang === 'ar' ? 'محاولاتك السابقة' : 'Your Previous Attempts' ?></h3>
            <?php foreach ($attempts as $att): ?>
            <div style="padding: 1rem; background: #F9FAFB; border-radius: 0.5rem; margin-bottom: 0.5rem; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <div style="font-weight: 600;"><?= formatDate($att['started_at']) ?></div>
                    <div style="font-size: 0.875rem; color: #6B7280;"><?= $att['status'] ?></div>
                </div>
                <?php if ($att['status'] === 'submitted'): ?>
                <div style="font-size: 1.25rem; font-weight: 700; color: <?= $att['passed'] ? '#10B981' : '#EF4444' ?>;">
                    <?= number_format($att['percentage'], 1) ?>%
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>

            <?php if ($canTakeExam): ?>
            <form method="POST" style="margin-top: 2rem; text-align: center;">
                <?= csrfField() ?>
                <button type="submit" name="start_exam" class="btn">
                    <?= $currentLang === 'ar' ? 'بدء الامتحان' : 'Start Exam' ?>
                </button>
            </form>
            <?php else: ?>
            <div style="text-align: center; padding: 2rem; color: #6B7280;">
                <?= $currentLang === 'ar' ? 'لقد استنفدت جميع محاولاتك' : 'You have exhausted all attempts' ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
