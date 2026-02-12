<?php
/**
 * Payment Webhooks Handler
 * Paymob, Stripe, and test success handler
 */

define('APP_INIT', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

$provider = $_GET['provider'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// Test success (for development only)
if (isset($_GET['success']) && isset($_GET['payment_id'])) {
    $paymentId = $_GET['payment_id'];

    db()->beginTransaction();
    try {
        // Update payment
        db()->execute("UPDATE payments SET status = 'completed' WHERE id = ?", [$paymentId]);

        // Get payment details
        $payment = db()->fetchOne("SELECT * FROM payments WHERE id = ?", [$paymentId]);

        if ($payment) {
            // Get plan
            $plan = db()->fetchOne("SELECT * FROM plans WHERE id = ?", [$payment['plan_id']]);

            if ($plan) {
                // Create subscription
                $endDate = date('Y-m-d H:i:s', strtotime('+' . $plan['duration_days'] . ' days'));

                db()->execute(
                    "INSERT INTO subscriptions (student_id, teacher_id, plan_id, payment_id, status, start_at, end_at, created_at)
                     VALUES (?, ?, ?, ?, 'active', NOW(), ?, NOW())",
                    [$payment['user_id'], $payment['teacher_id'], $payment['plan_id'], $paymentId, $endDate]
                );

                // Update teacher stats
                db()->execute(
                    "UPDATE teacher_profiles SET
                     total_earnings = total_earnings + ?,
                     total_students = (SELECT COUNT(DISTINCT student_id) FROM subscriptions WHERE teacher_id = ? AND status = 'active')
                     WHERE user_id = ?",
                    [$payment['amount'] * (1 - DEFAULT_COMMISSION_RATE), $payment['teacher_id'], $payment['teacher_id']]
                );

                logSecurityEvent('payment_completed', $payment['user_id'], 'Payment completed via ' . $provider, ['payment_id' => $paymentId]);
            }
        }

        db()->commit();

        // Redirect to success page
        header('Location: /student.php?tab=subscriptions&payment_success=1');
        exit;
    } catch (Exception $e) {
        db()->rollback();
        error_log("Webhook error: " . $e->getMessage());
        http_response_code(500);
        die('Webhook processing failed');
    }
}

// Paymob webhook
if ($provider === 'paymob' && $method === 'POST') {
    $payload = file_get_contents('php://input');
    $data = json_decode($payload, true);

    // Log webhook
    db()->execute(
        "INSERT INTO payment_events (provider, event_type, payload, created_at) VALUES (?, ?, ?, NOW())",
        ['paymob', 'webhook_received', $payload]
    );

    // Verify HMAC signature
    $hmac = $_GET['hmac'] ?? '';
    // In production: verify HMAC with PAYMOB_HMAC_SECRET

    // Process payment
    if (isset($data['obj']['success']) && $data['obj']['success'] === true) {
        $transactionId = $data['obj']['id'] ?? '';
        $amount = $data['obj']['amount_cents'] / 100;

        // Find payment by transaction ID or amount
        $payment = db()->fetchOne(
            "SELECT * FROM payments WHERE provider = 'paymob' AND amount = ? AND status = 'pending' ORDER BY created_at DESC LIMIT 1",
            [$amount]
        );

        if ($payment) {
            db()->execute(
                "UPDATE payments SET status = 'completed', transaction_id = ? WHERE id = ?",
                [$transactionId, $payment['id']]
            );

            // Create subscription (same logic as above)
            // ...
        }
    }

    http_response_code(200);
    echo json_encode(['status' => 'ok']);
    exit;
}

// Stripe webhook
if ($provider === 'stripe' && $method === 'POST') {
    $payload = file_get_contents('php://input');

    // Log webhook
    db()->execute(
        "INSERT INTO payment_events (provider, event_type, payload, created_at) VALUES (?, ?, ?, NOW())",
        ['stripe', 'webhook_received', $payload]
    );

    // Verify Stripe signature
    $signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
    // In production: verify with Stripe webhook secret

    $event = json_decode($payload, true);

    if ($event['type'] === 'payment_intent.succeeded') {
        $paymentIntent = $event['data']['object'];
        // Process successful payment
        // ...
    }

    http_response_code(200);
    echo json_encode(['status' => 'ok']);
    exit;
}

http_response_code(400);
die('Invalid webhook');
