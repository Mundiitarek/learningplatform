<?php
/**
 * Payment Processing
 * Paymob, Stripe, Manual Payments
 */

define('APP_INIT', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

requireRole('student');

$user = getCurrentUser();
$currentLang = getCurrentLang();
$isRtl = isRtl();

$teacherId = $_GET['teacher_id'] ?? 0;
$planId = $_GET['plan_id'] ?? 0;

// Get teacher
$teacher = db()->fetchOne(
    "SELECT u.*, tp.bio_en, tp.bio_ar FROM users u
     INNER JOIN teacher_profiles tp ON u.id = tp.user_id
     WHERE u.id = ? AND u.role = 'teacher' AND tp.approved = 'approved'",
    [$teacherId]
);

if (!$teacher) {
    die('Teacher not found');
}

// Get plans
$plans = db()->fetchAll(
    "SELECT * FROM plans WHERE teacher_id = ? AND active = TRUE",
    [$teacherId]
);

// Selected plan
$selectedPlan = null;
if ($planId) {
    $selectedPlan = db()->fetchOne(
        "SELECT * FROM plans WHERE id = ? AND teacher_id = ?",
        [$planId, $teacherId]
    );
}

// Handle payment creation
if (isset($_POST['create_payment'])) {
    requireCsrf();

    $planId = $_POST['plan_id'] ?? 0;
    $paymentMethod = $_POST['payment_method'] ?? 'paymob';
    $couponCode = trim($_POST['coupon'] ?? '');

    $plan = db()->fetchOne("SELECT * FROM plans WHERE id = ?", [$planId]);

    if ($plan) {
        $amount = $plan['price'];

        // Apply coupon if provided
        if ($couponCode) {
            $coupon = db()->fetchOne(
                "SELECT * FROM coupons WHERE code = ? AND active = TRUE
                 AND (valid_from IS NULL OR valid_from <= NOW())
                 AND (valid_until IS NULL OR valid_until >= NOW())
                 AND (max_uses IS NULL OR used_count < max_uses)
                 AND (teacher_id IS NULL OR teacher_id = ?)",
                [$couponCode, $teacherId]
            );

            if ($coupon) {
                if ($coupon['type'] === 'percentage') {
                    $amount = $amount * (1 - ($coupon['value'] / 100));
                } else {
                    $amount = max(0, $amount - $coupon['value']);
                }
            }
        }

        // Create payment record
        db()->execute(
            "INSERT INTO payments (user_id, teacher_id, plan_id, provider, amount, currency, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())",
            [$user['id'], $teacherId, $planId, $paymentMethod, $amount, $plan['currency']]
        );

        $paymentId = db()->lastInsertId();

        if ($paymentMethod === 'paymob') {
            // Redirect to Paymob
            // In production, integrate with Paymob API to get payment URL
            redirect('/webhook.php?provider=paymob&payment_id=' . $paymentId . '&success=1');
        } elseif ($paymentMethod === 'stripe') {
            // Redirect to Stripe
            redirect('/webhook.php?provider=stripe&payment_id=' . $paymentId . '&success=1');
        } elseif ($paymentMethod === 'manual') {
            redirect('/pay.php?payment_id=' . $paymentId . '&manual=1');
        }
    }
}

// Handle manual payment receipt upload
if (isset($_POST['upload_receipt'])) {
    requireCsrf();

    $paymentId = $_POST['payment_id'] ?? 0;

    if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK) {
        $upload = uploadFile($_FILES['receipt'], 'receipts');

        if ($upload['success']) {
            db()->execute(
                "UPDATE payments SET receipt_url = ?, payment_method = 'manual_upload' WHERE id = ? AND user_id = ?",
                [$upload['path'], $paymentId, $user['id']]
            );

            logSecurityEvent('manual_payment_receipt', $user['id'], 'Student uploaded payment receipt', ['payment_id' => $paymentId]);
            $success = $currentLang === 'ar' ? 'تم رفع الإيصال. سيتم مراجعته من قبل الإدارة.' : 'Receipt uploaded. It will be reviewed by admin.';
        }
    }
}

// Show manual upload form
$showManualUpload = isset($_GET['manual']) && isset($_GET['payment_id']);
$paymentId = $_GET['payment_id'] ?? 0;
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>" dir="<?= $isRtl ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('payment') ?> - <?= e($teacher['name']) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: <?= $isRtl ? "'Tajawal', sans-serif" : "'Inter', sans-serif" ?>; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 2rem; }
        .container { max-width: 900px; margin: 0 auto; }
        .card { background: white; border-radius: 1rem; padding: 2rem; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); margin-bottom: 2rem; }
        .header { text-align: center; margin-bottom: 2rem; }
        .teacher-avatar { width: 80px; height: 80px; margin: 0 auto 1rem; background: linear-gradient(135deg, #4F46E5, #06B6D4); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 2rem; font-weight: 700; }
        .teacher-name { font-size: 1.75rem; font-weight: 700; color: #111827; margin-bottom: 0.5rem; }
        .teacher-bio { color: #6B7280; }
        .plans-grid { display: grid; gap: 1.5rem; margin-bottom: 2rem; }
        .plan-card { border: 2px solid #E5E7EB; border-radius: 0.75rem; padding: 1.5rem; cursor: pointer; transition: all 0.3s; position: relative; }
        .plan-card:hover { border-color: #4F46E5; box-shadow: 0 10px 15px -3px rgba(79, 70, 229, 0.2); }
        .plan-card.selected { border-color: #4F46E5; background: rgba(79, 70, 229, 0.05); }
        .plan-title { font-size: 1.25rem; font-weight: 700; margin-bottom: 0.5rem; }
        .plan-price { font-size: 2rem; font-weight: 800; color: #4F46E5; margin-bottom: 1rem; }
        .plan-duration { color: #6B7280; font-size: 0.875rem; }
        .form-group { margin-bottom: 1.5rem; }
        .form-label { display: block; font-weight: 600; margin-bottom: 0.5rem; color: #374151; }
        .form-input, .form-select { width: 100%; padding: 0.75rem 1rem; border: 2px solid #E5E7EB; border-radius: 0.5rem; font-size: 1rem; }
        .form-input:focus, .form-select:focus { outline: none; border-color: #4F46E5; }
        .btn { padding: 0.875rem 2rem; border-radius: 0.5rem; font-weight: 600; border: none; cursor: pointer; font-size: 1rem; width: 100%; transition: all 0.3s; }
        .btn-primary { background: linear-gradient(135deg, #4F46E5, #4338CA); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.2); }
        .payment-methods { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .payment-method { border: 2px solid #E5E7EB; padding: 1rem; border-radius: 0.5rem; text-align: center; cursor: pointer; transition: all 0.3s; }
        .payment-method:hover { border-color: #4F46E5; }
        .payment-method.selected { border-color: #4F46E5; background: rgba(79, 70, 229, 0.05); }
        .payment-method input[type="radio"] { display: none; }
        .alert { padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; }
        .alert-success { background: #D1FAE5; color: #065F46; border: 1px solid #A7F3D0; }
        .upload-area { border: 2px dashed #E5E7EB; padding: 2rem; border-radius: 0.5rem; text-align: center; cursor: pointer; transition: all 0.3s; }
        .upload-area:hover { border-color: #4F46E5; background: rgba(79, 70, 229, 0.02); }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($showManualUpload): ?>
        <div class="card">
            <h2 style="margin-bottom: 1.5rem; text-align: center;"><?= $currentLang === 'ar' ? 'رفع إيصال الدفع' : 'Upload Payment Receipt' ?></h2>

            <?php if (isset($success)): ?>
            <div class="alert alert-success"><?= e($success) ?></div>
            <?php endif; ?>

            <p style="color: #6B7280; margin-bottom: 1.5rem; text-align: center;">
                <?= $currentLang === 'ar' ? 'قم بتحويل المبلغ ثم ارفع إيصال الدفع' : 'Transfer the amount and upload the payment receipt' ?>
            </p>

            <form method="POST" enctype="multipart/form-data">
                <?= csrfField() ?>
                <input type="hidden" name="payment_id" value="<?= $paymentId ?>">

                <div class="form-group">
                    <label class="upload-area" for="receipt">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">📄</div>
                        <div><?= $currentLang === 'ar' ? 'انقر لرفع الإيصال' : 'Click to upload receipt' ?></div>
                        <div style="font-size: 0.875rem; color: #6B7280; margin-top: 0.5rem;">JPG, PNG, PDF (Max 10MB)</div>
                    </label>
                    <input type="file" name="receipt" id="receipt" accept=".jpg,.jpeg,.png,.pdf" required style="display:none;">
                </div>

                <button type="submit" name="upload_receipt" class="btn btn-primary"><?= $currentLang === 'ar' ? 'رفع الإيصال' : 'Upload Receipt' ?></button>
            </form>

            <p style="text-align: center; margin-top: 1rem;">
                <a href="/student.php" style="color: #4F46E5;"><?= $currentLang === 'ar' ? 'العودة' : 'Go Back' ?></a>
            </p>
        </div>

        <?php else: ?>
        <div class="card">
            <div class="header">
                <div class="teacher-avatar"><?= strtoupper(substr($teacher['name'], 0, 2)) ?></div>
                <h1 class="teacher-name"><?= e($teacher['name']) ?></h1>
                <p class="teacher-bio"><?= e($currentLang === 'ar' ? ($teacher['bio_ar'] ?: 'معلم متميز') : ($teacher['bio_en'] ?: 'Expert Teacher')) ?></p>
            </div>

            <h2 style="margin-bottom: 1rem;"><?= $currentLang === 'ar' ? 'اختر خطة الاشتراك' : 'Choose Subscription Plan' ?></h2>

            <form method="POST" id="paymentForm">
                <?= csrfField() ?>

                <div class="plans-grid">
                    <?php foreach ($plans as $plan): ?>
                    <label class="plan-card" onclick="selectPlan(<?= $plan['id'] ?>)">
                        <input type="radio" name="plan_id" value="<?= $plan['id'] ?>" required style="display:none;">
                        <div class="plan-title"><?= e($currentLang === 'ar' ? $plan['title_ar'] : $plan['title_en']) ?></div>
                        <div class="plan-price"><?= formatPrice($plan['price'], $plan['currency']) ?></div>
                        <div class="plan-duration"><?= $plan['duration_days'] ?> <?= $currentLang === 'ar' ? 'يوم' : 'days' ?></div>
                    </label>
                    <?php endforeach; ?>
                </div>

                <div class="form-group">
                    <label class="form-label"><?= $currentLang === 'ar' ? 'كود الخصم (اختياري)' : 'Coupon Code (optional)' ?></label>
                    <input type="text" name="coupon" class="form-input" placeholder="<?= $currentLang === 'ar' ? 'أدخل الكود' : 'Enter code' ?>">
                </div>

                <h3 style="margin-bottom: 1rem;"><?= t('payment_method') ?></h3>

                <div class="payment-methods">
                    <label class="payment-method" onclick="selectPaymentMethod('paymob')">
                        <input type="radio" name="payment_method" value="paymob" required>
                        <div style="font-size: 2rem; margin-bottom: 0.5rem;">💳</div>
                        <div style="font-weight: 600;">Paymob</div>
                        <div style="font-size: 0.75rem; color: #6B7280;"><?= $currentLang === 'ar' ? 'بطاقة/محفظة' : 'Card/Wallet' ?></div>
                    </label>

                    <label class="payment-method" onclick="selectPaymentMethod('manual')">
                        <input type="radio" name="payment_method" value="manual">
                        <div style="font-size: 2rem; margin-bottom: 0.5rem;">📱</div>
                        <div style="font-weight: 600;"><?= $currentLang === 'ar' ? 'تحويل يدوي' : 'Manual Transfer' ?></div>
                        <div style="font-size: 0.75rem; color: #6B7280;"><?= $currentLang === 'ar' ? 'فودافون كاش' : 'Vodafone Cash' ?></div>
                    </label>
                </div>

                <button type="submit" name="create_payment" class="btn btn-primary"><?= t('pay_now') ?></button>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <script>
        function selectPlan(id) {
            document.querySelectorAll('.plan-card').forEach(el => el.classList.remove('selected'));
            event.currentTarget.classList.add('selected');
        }

        function selectPaymentMethod(method) {
            document.querySelectorAll('.payment-method').forEach(el => el.classList.remove('selected'));
            event.currentTarget.classList.add('selected');
        }

        document.getElementById('receipt')?.addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name;
            if (fileName) {
                document.querySelector('.upload-area div').textContent = fileName;
            }
        });
    </script>
</body>
</html>
