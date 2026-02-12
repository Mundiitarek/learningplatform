# 🎓 Bilingual Educational Platform - Egypt

[![PHP](https://img.shields.io/badge/PHP-8.0%2B-blue)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-8.0%2B-orange)](https://mysql.com)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

> Premium educational subscription platform for Egyptian secondary students with full Arabic/English bilingual support.

**Similar to:** Basattahalk (but more premium with enterprise features)

---

## ✨ Features

### 🌍 Bilingual Support
- **Arabic (RTL)** & **English (LTR)** interfaces
- Automatic language detection
- Persistent language preference
- All content in both languages

### 👥 Role-Based Access Control
- **Students**: Browse teachers, subscribe, watch lessons, take exams
- **Teachers**: Create content, manage students, track earnings
- **Admins**: Full platform control, analytics, payment management

### 🎬 Content Management
- Video lessons (YouTube/Vimeo/External streams)
- PDF attachments and study materials
- Organized by: Subject → Unit → Lesson
- Free preview lessons for marketing

### 📝 Exam System
- Multiple choice & True/False questions
- Timed exams with countdown
- Randomized questions (anti-cheat)
- Attempt limits and passing scores
- Instant results with detailed feedback

### 💳 Payment Integration
- **Paymob** (Card, Wallet, Installments)
- **Stripe** (International cards)
- **Manual** (Vodafone Cash, Instapay with receipt upload)
- Coupon codes (percentage/fixed discounts)

### 🔒 Security Features
- CSRF protection on all forms
- XSS prevention with output escaping
- SQL injection protection (PDO prepared statements)
- Rate limiting on login (5 attempts/15 min)
- Session security (HttpOnly, SameSite)
- File upload validation (MIME + extension)
- Audit logging for all sensitive actions
- Password hashing with bcrypt

### 🎨 Premium UI/UX
- Glassy cards with backdrop blur
- Smooth micro-animations
- Mobile-first responsive design
- Blue/white professional theme
- Tajawal (Arabic) + Inter (English) fonts
- WCAG AA accessibility compliant

### 📊 Analytics & Reporting
- Revenue tracking and charts
- Student progress monitoring
- Teacher performance metrics
- Subscription analytics
- Top teachers/subjects

---

## 🚀 Quick Start

### Installation (5 Minutes)

```bash
# 1. Database setup
mysql -u root -p
CREATE DATABASE eduplatform CHARACTER SET utf8mb4;
EXIT;

mysql -u root -p eduplatform < install.sql
mysql -u root -p eduplatform < seed.sql

# 2. Configure
# Edit config.php with your settings

# 3. Set permissions
mkdir uploads logs
chmod 755 uploads logs

# 4. Access platform
https://yourdomain.com
```

### Default Credentials

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@platform.com | Admin@123456 |
| Teacher | teacher@platform.com | Teacher@123456 |
| Student | student@platform.com | Student@123456 |

⚠️ **Change these immediately!**

---

## 📁 File Structure (17 Files)

```
├── config.php          # Configuration
├── db.php              # Database singleton
├── helpers.php         # Core functions (auth, CSRF, i18n, RBAC)
├── index.php           # Landing page
├── auth.php            # Login/Register
├── student.php         # Student dashboard
├── teacher.php         # Teacher dashboard
├── admin.php           # Admin panel
├── lesson.php          # Lesson viewer
├── exam.php            # Exam system
├── pay.php             # Payment processing
├── webhook.php         # Payment webhooks
├── download.php        # Secure file downloads
├── cron.php            # Background tasks
├── api.php             # AJAX endpoints
├── install.sql         # Database schema (35 tables)
└── seed.sql            # Sample data
```

---

## 🗄️ Database Schema (35 Tables)

**Users & Auth:** users, teacher_profiles, rate_limits, audit_logs
**Academic:** academic_years, subjects, teacher_subjects
**Content:** units, lessons, lesson_files, lesson_progress
**Exams:** exams, exam_questions, question_choices, exam_attempts, exam_answers
**Payments:** plans, payments, payment_events, subscriptions, coupons, coupon_uses
**Support:** tickets, ticket_messages, notifications, settings

---

## 💡 Key Features Explained

### Content Protection
- **Video Watermarks**: Student name + ID + timestamp overlay
- **Signed Download URLs**: Time-limited tokens (5 min TTL)
- **Subscription Checks**: Every lesson/file access verified
- **No Direct URLs**: All content served through PHP controllers

### Payment Flow
1. Student selects teacher + plan
2. Optional coupon code applied
3. Payment gateway redirect
4. Webhook confirms payment
5. Subscription auto-activated
6. Student gains access to content

### Exam Anti-Cheat
- Questions randomized per attempt
- Time limit enforced
- No answer review during exam
- Attempt tracking and limits
- Abandoned attempt detection

### Background Tasks (Cron)
- Expire subscriptions automatically
- Send renewal reminders (3 days before)
- Clean old logs and rate limits
- Update teacher statistics
- Database optimization (weekly)

---

## 🔧 Configuration

### Required Settings (config.php)

```php
// Database
define('DB_NAME', 'eduplatform');
define('DB_USER', 'root');
define('DB_PASS', 'your_password');

// Paymob
define('PAYMOB_API_KEY', 'your_key');
define('PAYMOB_INTEGRATION_ID', 'your_id');
define('PAYMOB_HMAC_SECRET', 'your_secret');

// Stripe
define('STRIPE_SECRET_KEY', 'sk_live_...');
define('STRIPE_WEBHOOK_SECRET', 'whsec_...');

// Cron
define('CRON_SECRET_KEY', 'random_32_char_key');
```

### Cron Job Setup

```bash
# Run every hour
0 * * * * curl https://yourdomain.com/cron.php?key=YOUR_KEY
```

---

## 🔒 Security Checklist

✅ PDO prepared statements (no SQL injection)
✅ Output escaping (no XSS)
✅ CSRF tokens on all forms
✅ Rate limiting (brute force protection)
✅ Secure sessions (HttpOnly, SameSite)
✅ File upload validation (MIME + extension)
✅ Password hashing (bcrypt)
✅ Audit logging (all sensitive actions)
✅ RBAC (role-based access control)
✅ Security headers (X-Frame-Options, CSP, etc.)

---

## 📊 Platform Statistics

| Metric | Count |
|--------|-------|
| Total Files | 17 PHP files |
| Database Tables | 35 tables |
| Supported Languages | 2 (Arabic, English) |
| Payment Methods | 3 (Paymob, Stripe, Manual) |
| User Roles | 4 (Admin, Teacher, Student, Support) |
| Exam Question Types | 2 (MCQ, True/False) |

---

## 🎯 Use Cases

### For Schools
- Teachers create courses
- Students subscribe and learn
- Track progress and grades
- Generate reports

### For Individual Teachers
- Build your brand
- Sell courses online
- Manage students
- Track earnings

### For Tutoring Centers
- Multiple teachers
- Centralized billing
- Student management
- Analytics dashboard

---

## 🛠️ Tech Stack

- **Backend**: PHP 8.0+ (vanilla, no frameworks)
- **Database**: MySQL 8.0+
- **Frontend**: Vanilla HTML/CSS/JS
- **Fonts**: Tajawal (Arabic), Inter (English)
- **Payments**: Paymob, Stripe
- **Video**: YouTube, Vimeo, External embeds

---

## 📈 Scaling Recommendations

### For 1K+ Students
- Enable OPcache
- Add Redis for sessions
- CDN for static assets
- Database read replicas

### For 10K+ Students
- Load balancer (Nginx)
- Separate file storage (S3)
- Dedicated video CDN
- Microservices architecture

---

## 🧪 Testing

### Smoke Test (5 Minutes)

```bash
✓ Register new student
✓ Subscribe to teacher
✓ Watch lesson with watermark
✓ Take exam and see results
✓ Teacher creates lesson
✓ Admin approves teacher
✓ Payment webhook processes
```

### Security Tests

```bash
✓ CSRF: Try form without token → Blocked
✓ SQL Injection: Try malicious input → Escaped
✓ XSS: Try script tags → Escaped
✓ Rate Limit: 6 login attempts → Blocked
✓ Unauthorized Access: Student → /admin.php → Denied
```

---

## 📞 Support

### Common Issues

**Database connection failed**
- Check DB credentials in config.php
- Verify MySQL is running

**Payment not working**
- Verify webhook URL is public
- Check payment gateway credentials
- Review `payment_events` table

**Cron not running**
- Verify cron key matches
- Test manually: `curl .../cron.php?key=KEY`

---

## 📝 Roadmap

- [ ] Mobile apps (iOS/Android)
- [ ] Live streaming classes
- [ ] AI-powered recommendations
- [ ] Gamification (badges, leaderboards)
- [ ] Social features (discussion forums)
- [ ] Video recording (teacher can record in-platform)
- [ ] Offline mode (PWA)

---

## 📄 License

This project is proprietary software. All rights reserved.

---

## 🙏 Credits

**Built with Claude Code**
Session: https://claude.ai/code/session_01Dzu1sRtABCw3FsDN4wPVsB

**Technologies:**
- PHP 8+ (no frameworks)
- MySQL 8+
- Vanilla HTML/CSS/JS
- Paymob & Stripe APIs

---

## 📚 Documentation

- [Setup Guide](SETUP_GUIDE.md) - Detailed installation & configuration
- [Security Guide](SETUP_GUIDE.md#security-checklist) - Security best practices
- [API Documentation](SETUP_GUIDE.md#monitoring) - API endpoints reference

---

## 🎉 Get Started

```bash
# Clone repository
git clone https://github.com/yourusername/learningplatform.git

# Follow setup guide
cat SETUP_GUIDE.md

# Start building!
```

**Questions?** Review the inline code comments for detailed explanations.

---

**Made with ❤️ in Egypt**
