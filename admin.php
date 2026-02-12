<?php
/**
 * Admin Panel
 * Full platform control and management
 */

define('APP_INIT', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

requireRole('admin');

$user = getCurrentUser();
$currentLang = getCurrentLang();
$isRtl = isRtl();
$tab = $_GET['tab'] ?? 'dashboard';

// Handle approve teacher
if (isset($_POST['approve_teacher'])) {
    requireCsrf();
    $teacherId = $_POST['teacher_id'] ?? 0;

    db()->execute(
        "UPDATE teacher_profiles SET approved = 'approved', approved_at = NOW(), approved_by = ? WHERE user_id = ?",
        [$user['id'], $teacherId]
    );

    logSecurityEvent('teacher_approved', $user['id'], 'Admin approved teacher', ['teacher_id' => $teacherId]);
    redirect('/admin.php?tab=teachers&success=1');
}

// Handle manual payment approval
if (isset($_POST['approve_payment'])) {
    requireCsrf();
    $paymentId = $_POST['payment_id'] ?? 0;

    db()->beginTransaction();
    try {
        // Update payment status
        db()->execute("UPDATE payments SET status = 'completed' WHERE id = ?", [$paymentId]);

        // Get payment details
        $payment = db()->fetchOne("SELECT * FROM payments WHERE id = ?", [$paymentId]);

        // Create/activate subscription
        $existingSub = db()->fetchOne(
            "SELECT * FROM subscriptions WHERE student_id = ? AND teacher_id = ? AND status != 'refunded' ORDER BY id DESC LIMIT 1",
            [$payment['user_id'], $payment['teacher_id']]
        );

        if ($existingSub) {
            db()->execute(
                "UPDATE subscriptions SET status = 'active', start_at = NOW(), end_at = DATE_ADD(NOW(), INTERVAL 30 DAY) WHERE id = ?",
                [$existingSub['id']]
            );
        } else {
            db()->execute(
                "INSERT INTO subscriptions (student_id, teacher_id, plan_id, payment_id, status, start_at, end_at, created_at)
                 VALUES (?, ?, ?, ?, 'active', NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), NOW())",
                [$payment['user_id'], $payment['teacher_id'], $payment['plan_id'], $paymentId]
            );
        }

        db()->commit();
        logSecurityEvent('payment_approved', $user['id'], 'Admin approved manual payment', ['payment_id' => $paymentId]);
        redirect('/admin.php?tab=payments&success=1');
    } catch (Exception $e) {
        db()->rollback();
        error_log("Payment approval error: " . $e->getMessage());
    }
}

// Get stats
$stats = [
    'total_users' => db()->fetchOne("SELECT COUNT(*) as count FROM users")['count'],
    'total_students' => db()->fetchOne("SELECT COUNT(*) as count FROM users WHERE role = 'student'")['count'],
    'total_teachers' => db()->fetchOne("SELECT COUNT(*) as count FROM users WHERE role = 'teacher'")['count'],
    'active_subscriptions' => db()->fetchOne("SELECT COUNT(*) as count FROM subscriptions WHERE status = 'active'")['count'],
    'total_revenue' => db()->fetchOne("SELECT SUM(amount) as total FROM payments WHERE status = 'completed'")['total'] ?? 0,
    'pending_teachers' => db()->fetchOne("SELECT COUNT(*) as count FROM teacher_profiles WHERE approved = 'pending'")['count'],
];

// Get data based on tab
$users = [];
$teachers = [];
$payments = [];
$subscriptions = [];

if ($tab === 'users') {
    $users = db()->fetchAll(
        "SELECT * FROM users ORDER BY created_at DESC LIMIT 50"
    );
}

if ($tab === 'teachers') {
    $teachers = db()->fetchAll(
        "SELECT u.*, tp.approved, tp.total_students, tp.total_earnings, tp.rating
         FROM users u
         INNER JOIN teacher_profiles tp ON u.id = tp.user_id
         WHERE u.role = 'teacher'
         ORDER BY tp.approved, u.created_at DESC"
    );
}

if ($tab === 'payments') {
    $payments = db()->fetchAll(
        "SELECT p.*, u.name as user_name, t.name as teacher_name
         FROM payments p
         INNER JOIN users u ON p.user_id = u.id
         LEFT JOIN users t ON p.teacher_id = t.id
         ORDER BY p.created_at DESC
         LIMIT 100"
    );
}

if ($tab === 'subscriptions') {
    $subscriptions = db()->fetchAll(
        "SELECT s.*, u.name as student_name, t.name as teacher_name, pl.title_en
         FROM subscriptions s
         INNER JOIN users u ON s.student_id = u.id
         INNER JOIN users t ON s.teacher_id = t.id
         LEFT JOIN plans pl ON s.plan_id = pl.id
         ORDER BY s.created_at DESC
         LIMIT 100"
    );
}
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>" dir="<?= $isRtl ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('admin_panel') ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --primary: #4F46E5;
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
        body { font-family: <?= $isRtl ? "'Tajawal', sans-serif" : "'Inter', sans-serif" ?>; background: #F3F4F6; color: var(--gray-900); }
        .dashboard { display: flex; min-height: 100vh; }
        .sidebar { width: 280px; background: var(--white); box-shadow: 2px 0 10px rgba(0,0,0,0.05); position: fixed; height: 100vh; overflow-y: auto; }
        .sidebar-header { padding: 2rem 1.5rem; border-bottom: 1px solid var(--gray-200); }
        .sidebar-brand { font-size: 1.5rem; font-weight: 800; background: linear-gradient(135deg, var(--danger), var(--warning)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; text-decoration: none; }
        .sidebar-user { margin-top: 1rem; padding: 1rem; background: var(--gray-50); border-radius: 0.75rem; }
        .sidebar-user-name { font-weight: 600; color: var(--danger); }
        .sidebar-user-role { font-size: 0.875rem; color: var(--gray-600); text-transform: uppercase; }
        .sidebar-nav { padding: 1rem 0; }
        .sidebar-nav-item { padding: 0.875rem 1.5rem; display: flex; align-items: center; gap: 0.75rem; color: var(--gray-700); text-decoration: none; transition: all 0.2s; border-left: 3px solid transparent; }
        .sidebar-nav-item:hover { background: var(--gray-50); color: var(--primary); }
        .sidebar-nav-item.active { background: rgba(79, 70, 229, 0.05); color: var(--primary); border-left-color: var(--primary); font-weight: 600; }
        .main-content { flex: 1; margin-<?= $isRtl ? 'right' : 'left' ?>: 280px; padding: 2rem; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .header-title { font-size: 2rem; color: var(--gray-900); }
        .btn { padding: 0.625rem 1.25rem; border-radius: 0.5rem; text-decoration: none; font-weight: 600; transition: all 0.3s; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-primary { background: linear-gradient(135deg, var(--primary), #4338CA); color: var(--white); }
        .btn-success { background: var(--success); color: var(--white); }
        .btn-danger { background: var(--danger); color: var(--white); }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.2); }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { background: var(--white); padding: 1.5rem; border-radius: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .stat-card-value { font-size: 2rem; font-weight: 800; }
        .stat-card-label { color: var(--gray-600); font-size: 0.875rem; margin-top: 0.25rem; }
        .card { background: var(--white); border-radius: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 2rem; overflow: hidden; }
        .card-header { padding: 1.5rem; border-bottom: 1px solid var(--gray-200); }
        .card-title { font-size: 1.25rem; font-weight: 700; }
        .card-body { padding: 1.5rem; }
        .table { width: 100%; border-collapse: collapse; }
        .table th { text-align: <?= $isRtl ? 'right' : 'left' ?>; padding: 0.75rem; background: var(--gray-50); font-weight: 600; color: var(--gray-700); border-bottom: 2px solid var(--gray-200); }
        .table td { padding: 0.75rem; border-bottom: 1px solid var(--gray-200); }
        .badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; display: inline-block; }
        .badge-success { background: #D1FAE5; color: #065F46; }
        .badge-warning { background: #FEF3C7; color: #92400E; }
        .badge-danger { background: #FEE2E2; color: #991B1B; }
        .badge-primary { background: #DBEAFE; color: #1E40AF; }
        .alert { padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; }
        .alert-success { background: #D1FAE5; color: #065F46; border: 1px solid #A7F3D0; }
    </style>
</head>
<body>
    <div class="dashboard">
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="/admin.php" class="sidebar-brand">🛡️ <?= t('admin_panel') ?></a>
                <div class="sidebar-user">
                    <div class="sidebar-user-name"><?= e($user['name']) ?></div>
                    <div class="sidebar-user-role"><?= t('admin') ?></div>
                </div>
            </div>
            <nav class="sidebar-nav">
                <a href="?tab=dashboard" class="sidebar-nav-item <?= $tab === 'dashboard' ? 'active' : '' ?>">📊 <?= t('dashboard') ?></a>
                <a href="?tab=users" class="sidebar-nav-item <?= $tab === 'users' ? 'active' : '' ?>">👥 <?= t('manage_users') ?></a>
                <a href="?tab=teachers" class="sidebar-nav-item <?= $tab === 'teachers' ? 'active' : '' ?>">
                    👨‍🏫 <?= t('manage_teachers') ?>
                    <?php if ($stats['pending_teachers'] > 0): ?>
                    <span class="badge badge-danger"><?= $stats['pending_teachers'] ?></span>
                    <?php endif; ?>
                </a>
                <a href="?tab=payments" class="sidebar-nav-item <?= $tab === 'payments' ? 'active' : '' ?>">💳 <?= t('manage_payments') ?></a>
                <a href="?tab=subscriptions" class="sidebar-nav-item <?= $tab === 'subscriptions' ? 'active' : '' ?>">📚 <?= $currentLang === 'ar' ? 'الاشتراكات' : 'Subscriptions' ?></a>
                <a href="?tab=analytics" class="sidebar-nav-item <?= $tab === 'analytics' ? 'active' : '' ?>">📈 <?= t('analytics') ?></a>
                <a href="?tab=settings" class="sidebar-nav-item <?= $tab === 'settings' ? 'active' : '' ?>">⚙️ <?= t('settings') ?></a>
                <a href="/auth.php?action=logout" class="sidebar-nav-item">🚪 <?= t('logout') ?></a>
            </nav>
        </aside>

        <main class="main-content">
            <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">✅ <?= $currentLang === 'ar' ? 'تم الحفظ بنجاح!' : 'Successfully saved!' ?></div>
            <?php endif; ?>

            <?php if ($tab === 'dashboard'): ?>
            <div class="header">
                <h1 class="header-title"><?= t('admin_panel') ?></h1>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-card-value" style="color: var(--primary);"><?= number_format($stats['total_users']) ?></div>
                    <div class="stat-card-label"><?= $currentLang === 'ar' ? 'إجمالي المستخدمين' : 'Total Users' ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-value" style="color: var(--success);"><?= number_format($stats['total_students']) ?></div>
                    <div class="stat-card-label"><?= $currentLang === 'ar' ? 'الطلاب' : 'Students' ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-value" style="color: var(--warning);"><?= number_format($stats['total_teachers']) ?></div>
                    <div class="stat-card-label"><?= $currentLang === 'ar' ? 'المعلمون' : 'Teachers' ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-value" style="color: var(--primary);"><?= number_format($stats['active_subscriptions']) ?></div>
                    <div class="stat-card-label"><?= $currentLang === 'ar' ? 'اشتراكات نشطة' : 'Active Subscriptions' ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-value" style="color: var(--success);"><?= formatPrice($stats['total_revenue'], 'EGP') ?></div>
                    <div class="stat-card-label"><?= $currentLang === 'ar' ? 'إجمالي الإيرادات' : 'Total Revenue' ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-value" style="color: var(--danger);"><?= $stats['pending_teachers'] ?></div>
                    <div class="stat-card-label"><?= $currentLang === 'ar' ? 'معلمون قيد المراجعة' : 'Pending Teachers' ?></div>
                </div>
            </div>

            <?php elseif ($tab === 'users'): ?>
            <div class="header">
                <h1 class="header-title"><?= t('manage_users') ?></h1>
            </div>
            <div class="card">
                <div class="card-body">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th><?= t('name') ?></th>
                                <th><?= t('email') ?></th>
                                <th><?= $currentLang === 'ar' ? 'الدور' : 'Role' ?></th>
                                <th><?= $currentLang === 'ar' ? 'الحالة' : 'Status' ?></th>
                                <th><?= $currentLang === 'ar' ? 'تاريخ التسجيل' : 'Registered' ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                            <tr>
                                <td><?= $u['id'] ?></td>
                                <td><?= e($u['name']) ?></td>
                                <td><?= e($u['email']) ?></td>
                                <td><span class="badge badge-primary"><?= e($u['role']) ?></span></td>
                                <td><span class="badge badge-<?= $u['status'] === 'active' ? 'success' : 'danger' ?>"><?= e($u['status']) ?></span></td>
                                <td><?= formatDate($u['created_at']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php elseif ($tab === 'teachers'): ?>
            <div class="header">
                <h1 class="header-title"><?= t('manage_teachers') ?></h1>
            </div>
            <div class="card">
                <div class="card-body">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th><?= t('name') ?></th>
                                <th><?= t('email') ?></th>
                                <th><?= $currentLang === 'ar' ? 'الحالة' : 'Status' ?></th>
                                <th><?= $currentLang === 'ar' ? 'الطلاب' : 'Students' ?></th>
                                <th><?= $currentLang === 'ar' ? 'الأرباح' : 'Earnings' ?></th>
                                <th><?= $currentLang === 'ar' ? 'إجراء' : 'Action' ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($teachers as $teacher): ?>
                            <tr>
                                <td><?= $teacher['id'] ?></td>
                                <td><?= e($teacher['name']) ?></td>
                                <td><?= e($teacher['email']) ?></td>
                                <td><span class="badge badge-<?= $teacher['approved'] === 'approved' ? 'success' : 'warning' ?>"><?= e($teacher['approved']) ?></span></td>
                                <td><?= $teacher['total_students'] ?></td>
                                <td><?= formatPrice($teacher['total_earnings'], 'EGP') ?></td>
                                <td>
                                    <?php if ($teacher['approved'] === 'pending'): ?>
                                    <form method="POST" style="display:inline;">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="teacher_id" value="<?= $teacher['id'] ?>">
                                        <button type="submit" name="approve_teacher" class="btn btn-success"><?= t('approve') ?></button>
                                    </form>
                                    <?php else: ?>
                                    <span class="badge badge-success">✓</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php elseif ($tab === 'payments'): ?>
            <div class="header">
                <h1 class="header-title"><?= t('manage_payments') ?></h1>
            </div>
            <div class="card">
                <div class="card-body">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th><?= $currentLang === 'ar' ? 'الطالب' : 'Student' ?></th>
                                <th><?= $currentLang === 'ar' ? 'المعلم' : 'Teacher' ?></th>
                                <th><?= $currentLang === 'ar' ? 'المبلغ' : 'Amount' ?></th>
                                <th><?= $currentLang === 'ar' ? 'الطريقة' : 'Provider' ?></th>
                                <th><?= $currentLang === 'ar' ? 'الحالة' : 'Status' ?></th>
                                <th><?= $currentLang === 'ar' ? 'إجراء' : 'Action' ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?= $payment['id'] ?></td>
                                <td><?= e($payment['user_name']) ?></td>
                                <td><?= e($payment['teacher_name'] ?? '-') ?></td>
                                <td><?= formatPrice($payment['amount'], $payment['currency']) ?></td>
                                <td><span class="badge badge-primary"><?= e($payment['provider']) ?></span></td>
                                <td><span class="badge badge-<?= $payment['status'] === 'completed' ? 'success' : ($payment['status'] === 'pending' ? 'warning' : 'danger') ?>"><?= e($payment['status']) ?></span></td>
                                <td>
                                    <?php if ($payment['status'] === 'pending' && $payment['provider'] === 'manual'): ?>
                                    <form method="POST" style="display:inline;">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="payment_id" value="<?= $payment['id'] ?>">
                                        <button type="submit" name="approve_payment" class="btn btn-success"><?= t('approve') ?></button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php endif; ?>
        </main>
    </div>
</body>
</html>
