# 🚀 Bilingual Educational Platform - Complete Setup Guide

## 📋 Table of Contents
1. [Quick Start](#quick-start)
2. [File Structure](#file-structure)
3. [Database Setup](#database-setup)
4. [Configuration](#configuration)
5. [Payment Gateway Integration](#payment-gateway-integration)
6. [Security Checklist](#security-checklist)
7. [Testing Guide](#testing-guide)
8. [Production Deployment](#production-deployment)

---

## 🎯 Quick Start

### Prerequisites
- PHP 7.4+ (8.0+ recommended)
- MySQL 8.0+
- Apache/Nginx with mod_rewrite
- SSL certificate (for production)

### Installation (5 Minutes)

```bash
# 1. Clone/Upload files
cd /var/www/html/learningplatform

# 2. Create database
mysql -u root -p
CREATE DATABASE eduplatform CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
EXIT;

# 3. Import schema and seed data
mysql -u root -p eduplatform < install.sql
mysql -u root -p eduplatform < seed.sql

# 4. Set permissions
mkdir uploads logs
chmod 755 uploads logs
chown www-data:www-data uploads logs

# 5. Configure environment
# Edit config.php with your settings

# 6. Setup cron job
crontab -e
# Add: 0 * * * * curl https://yourdomain.com/cron.php?key=YOUR_SECRET_KEY
```

### Test Login
- **Admin**: admin@platform.com / Admin@123456
- **Teacher**: teacher@platform.com / Teacher@123456
- **Student**: student@platform.com / Student@123456

⚠️ **CHANGE THESE PASSWORDS IMMEDIATELY!**

---

## 📁 File Structure (17 Files)

```
/learningplatform/
├── config.php           # Configuration & security settings
├── db.php               # Database connection (PDO singleton)
├── helpers.php          # Auth, CSRF, i18n, RBAC, validation
├── index.php            # Landing page & public routes
├── auth.php             # Login/Register/Logout
├── student.php          # Student dashboard
├── teacher.php          # Teacher dashboard & content management
├── admin.php            # Admin panel (full control)
├── lesson.php           # Lesson viewer with watermarks
├── exam.php             # Exam system (MCQ/True-False)
├── pay.php              # Payment processing
├── webhook.php          # Payment webhook handler
├── download.php         # Secure file downloads
├── cron.php             # Background tasks
├── api.php              # AJAX endpoints
├── install.sql          # Database schema (35 tables)
├── seed.sql             # Sample data
└── /uploads/            # User-uploaded files
└── /logs/               # Security & error logs
```

---

## 🗄️ Database Setup

### Core Tables (35 Total)

**Users & Auth:**
- `users` - All users (admin/teacher/student)
- `teacher_profiles` - Teacher-specific data
- `rate_limits` - Login rate limiting
- `audit_logs` - Security event logging

**Academic Structure:**
- `academic_years` - 1st/2nd/3rd Secondary
- `subjects` - Mathematics, Physics, etc.
- `teacher_subjects` - Teacher-subject mapping

**Content:**
- `units` - Course units
- `lessons` - Video lessons, PDFs
- `lesson_files` - File attachments
- `lesson_progress` - Student progress tracking

**Exams:**
- `exams` - Exam definitions
- `exam_questions` - Questions
- `question_choices` - MCQ choices
- `exam_attempts` - Student attempts
- `exam_answers` - Student answers

**Payments & Subscriptions:**
- `plans` - Subscription plans
- `payments` - Payment transactions
- `payment_events` - Webhook logs
- `subscriptions` - Active subscriptions
- `coupons` - Discount codes
- `coupon_uses` - Coupon usage tracking

**Support:**
- `tickets` - Support tickets
- `ticket_messages` - Ticket replies
- `notifications` - User notifications
- `settings` - System settings

---

## ⚙️ Configuration

### Environment Variables (config.php)

```php
// Database
define('DB_HOST', 'localhost');
define('DB_NAME', 'eduplatform');
define('DB_USER', 'root');
define('DB_PASS', 'your_password');

// App URL
define('APP_URL', 'https://yourdomain.com');
define('APP_ENV', 'production'); // development, production

// Security
define('CRON_SECRET_KEY', 'GENERATE_RANDOM_32_CHAR_KEY');

// Payment Gateways
define('PAYMOB_API_KEY', 'your_key');
define('PAYMOB_INTEGRATION_ID', 'your_id');
define('PAYMOB_HMAC_SECRET', 'your_secret');

define('STRIPE_PUBLISHABLE_KEY', 'pk_live_...');
define('STRIPE_SECRET_KEY', 'sk_live_...');
define('STRIPE_WEBHOOK_SECRET', 'whsec_...');

// SMTP (optional)
define('SMTP_ENABLED', true);
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USER', 'your-email@gmail.com');
define('SMTP_PASS', 'your-password');
```

### Generate Secrets

```bash
# Generate CRON_SECRET_KEY
php -r "echo bin2hex(random_bytes(32));"
```

---

## 💳 Payment Gateway Integration

### Paymob (Primary - Egypt)

1. **Sign up**: https://paymob.com
2. **Get credentials**:
   - API Key
   - Integration ID
   - HMAC Secret
3. **Configure webhook**: `https://yourdomain.com/webhook.php?provider=paymob`
4. **Update config.php** with credentials

**Test Cards:**
- Success: 4987654321098769
- Failed: 4000000000000002

### Stripe (International)

1. **Sign up**: https://stripe.com
2. **Get API keys**: Dashboard → Developers → API Keys
3. **Configure webhook**: `https://yourdomain.com/webhook.php?provider=stripe`
4. **Events to listen**: `payment_intent.succeeded`, `payment_intent.failed`

**Test Cards:**
- Success: 4242 4242 4242 4242
- Decline: 4000 0000 0000 0002

### Manual Payments (Vodafone Cash)

1. Student uploads receipt
2. Admin reviews in `/admin.php?tab=payments`
3. Admin clicks "Approve"
4. Subscription activated automatically

---

## 🔒 Security Checklist

### ✅ Implemented Protections

**Authentication & Authorization:**
- [x] Password hashing with `password_hash()`
- [x] Session regeneration on login
- [x] HttpOnly, SameSite, Secure cookies
- [x] RBAC on every endpoint
- [x] Rate limiting (5 attempts / 15 min)

**Input Validation:**
- [x] PDO prepared statements (no string concat)
- [x] Server-side validation (email, phone, password)
- [x] Allowlist validation for roles, enums
- [x] File upload: MIME + extension + size checks

**Output Security:**
- [x] HTML escaping with `htmlspecialchars()`
- [x] CSRF tokens on all forms
- [x] JSON responses for API
- [x] Secure headers (X-Frame-Options, CSP, etc.)

**File Security:**
- [x] Random filenames for uploads
- [x] Token-based download URLs (short TTL)
- [x] Subscription verification on downloads
- [x] PDF/image only (no executables)

**Content Protection:**
- [x] Video watermarks (name + ID + timestamp)
- [x] Subscription checks on lessons
- [x] No direct video URLs exposed
- [x] Rate limiting on API endpoints

**Logging & Monitoring:**
- [x] Audit logs for sensitive actions
- [x] Login attempt tracking
- [x] Payment webhook logging
- [x] File download logging

### ⚠️ Known Limitations

**Cannot Be 100% Prevented:**
- Screen recording (OBS, phone camera)
- Content sharing between users
- VPN/proxy bypass
- Account sharing

**Mitigations:**
- Watermarks deter casual sharing
- Legal terms of service
- Account monitoring for suspicious activity

---

## 🧪 Testing Guide

### Smoke Test Checklist

```bash
# 1. Landing Page
✓ Visit homepage
✓ Switch language (EN ↔ AR)
✓ View featured teachers

# 2. Registration
✓ Register as student
✓ Register as teacher
✓ Verify redirects to dashboard

# 3. Student Flow
✓ Browse teachers
✓ Select teacher & plan
✓ Complete payment (test mode)
✓ Access lessons
✓ Take exam
✓ View results

# 4. Teacher Flow
✓ Create lesson
✓ Upload video ID
✓ Attach PDF file
✓ Create exam
✓ Add questions
✓ View students

# 5. Admin Panel
✓ View dashboard stats
✓ Approve teacher
✓ Approve manual payment
✓ View all transactions
✓ Check audit logs

# 6. Security Tests
✓ CSRF: Try form without token
✓ Rate limit: 6+ login attempts
✓ Access control: Student → /admin.php
✓ File download: No subscription
✓ SQL injection attempts
```

### Test Payment Flow

```bash
# Development Mode (webhook.php accepts ?success=1)
1. Select teacher & plan
2. Choose "Paymob" or "Stripe"
3. System auto-completes (dev only)
4. Subscription created
5. Access lessons

# Production Mode
1. Real payment gateway
2. Webhook verification
3. HMAC signature check
4. Subscription activation
```

---

## 🚀 Production Deployment

### Pre-Deployment

1. **Security:**
   ```php
   // config.php
   define('APP_ENV', 'production');
   error_reporting(0);
   ini_set('display_errors', '0');
   ```

2. **Move uploads outside webroot:**
   ```bash
   mv uploads /var/www/uploads
   # Update UPLOAD_PATH in config.php
   ```

3. **SSL Certificate:**
   ```bash
   certbot --apache -d yourdomain.com
   ```

4. **Change default passwords:**
   ```sql
   UPDATE users SET password_hash = ? WHERE email = 'admin@platform.com';
   ```

5. **Set real payment keys** in config.php

### Apache Configuration

```apache
<VirtualHost *:443>
    ServerName yourdomain.com
    DocumentRoot /var/www/html/learningplatform

    <Directory /var/www/html/learningplatform>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # Security headers
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-XSS-Protection "1; mode=block"

    # SSL
    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/yourdomain.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/yourdomain.com/privkey.pem
</VirtualHost>
```

### .htaccess (if using Apache)

```apache
# Redirect to HTTPS
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Block access to sensitive files
<FilesMatch "(config\.php|install\.sql|seed\.sql)$">
    Order allow,deny
    Deny from all
</FilesMatch>

# Prevent directory listing
Options -Indexes

# PHP settings
php_value upload_max_filesize 10M
php_value post_max_size 10M
php_value max_execution_time 300
```

### Cron Job Setup

```bash
crontab -e

# Expire subscriptions, cleanup, reminders (every hour)
0 * * * * curl -s https://yourdomain.com/cron.php?key=YOUR_SECRET_KEY

# Database backup (daily at 2 AM)
0 2 * * * mysqldump -u root -p'password' eduplatform | gzip > /backups/eduplatform_$(date +\%Y\%m\%d).sql.gz

# Cleanup old backups (weekly)
0 3 * * 0 find /backups -name "eduplatform_*.sql.gz" -mtime +30 -delete
```

### Performance Optimization

```bash
# 1. Enable OPcache (php.ini)
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=10000
opcache.revalidate_freq=60

# 2. MySQL optimization (my.cnf)
innodb_buffer_pool_size=1G
query_cache_size=64M
max_connections=200

# 3. Enable compression
# In .htaccess:
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/css text/javascript application/javascript
</IfModule>
```

---

## 📊 Monitoring

### Key Metrics to Track

```sql
-- Daily Active Users
SELECT COUNT(DISTINCT user_id) FROM audit_logs
WHERE DATE(created_at) = CURDATE() AND event_type = 'user_login';

-- Revenue Today
SELECT SUM(amount) FROM payments
WHERE DATE(created_at) = CURDATE() AND status = 'completed';

-- Active Subscriptions
SELECT COUNT(*) FROM subscriptions WHERE status = 'active';

-- Failed Login Attempts
SELECT COUNT(*) FROM audit_logs
WHERE event_type = 'login_failed' AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR);
```

### Log Monitoring

```bash
# Watch security logs
tail -f logs/db_errors_*.log

# Check cron execution
curl https://yourdomain.com/api.php?action=health
```

---

## 🎨 Customization

### Change Theme Colors

Edit CSS variables in each file:

```css
:root {
    --primary: #4F46E5;     /* Main brand color */
    --secondary: #06B6D4;   /* Accent color */
    --success: #10B981;     /* Success messages */
    --danger: #EF4444;      /* Errors */
}
```

### Add New Language

1. Edit `helpers.php` → `getTranslations()`
2. Add new language keys
3. Update `getCurrentLang()` to support new language
4. Add RTL support if needed

### Add New Payment Gateway

1. Add credentials to `config.php`
2. Add option in `pay.php`
3. Handle webhook in `webhook.php`
4. Log events to `payment_events` table

---

## 📞 Support & Troubleshooting

### Common Issues

**"Database connection error"**
- Check DB credentials in config.php
- Verify MySQL is running: `systemctl status mysql`
- Check MySQL user permissions

**"Invalid CSRF token"**
- Session issues: check `session.save_path`
- Clear browser cookies
- Verify HTTPS in production

**"File upload failed"**
- Check `uploads/` permissions: `chmod 755 uploads`
- Verify PHP upload limits: `upload_max_filesize`
- Check disk space: `df -h`

**"Payment webhook not working"**
- Verify webhook URL is publicly accessible
- Check HMAC signature validation
- Review `payment_events` table for logs

**"Cron not running"**
- Verify cron key matches: `CRON_SECRET_KEY`
- Check crontab: `crontab -l`
- Test manually: `curl https://yourdomain.com/cron.php?key=KEY`

### Get Help

1. Check logs: `/logs/` directory
2. Review `audit_logs` table
3. Enable debug mode (development only):
   ```php
   define('APP_ENV', 'development');
   error_reporting(E_ALL);
   ```

---

## ✅ Production Checklist

Before going live:

- [ ] Change all default passwords
- [ ] Set `APP_ENV = 'production'`
- [ ] Disable error display
- [ ] Configure real payment keys
- [ ] Setup SSL certificate
- [ ] Move uploads outside webroot
- [ ] Setup cron jobs
- [ ] Configure email (SMTP)
- [ ] Test all payment flows
- [ ] Setup database backups
- [ ] Configure security headers
- [ ] Test rate limiting
- [ ] Review audit logs
- [ ] Setup monitoring alerts
- [ ] Load test the platform
- [ ] Create admin documentation

---

## 🎉 You're All Set!

Your bilingual educational platform is ready for production!

**Next Steps:**
1. Customize branding and colors
2. Add your real teachers and content
3. Configure payment gateways
4. Market to students
5. Monitor performance and scale

**Need Help?** Check the inline code comments for detailed explanations.

---

**Built with ❤️ by Claude Code**
Session: https://claude.ai/code/session_01Dzu1sRtABCw3FsDN4wPVsB
