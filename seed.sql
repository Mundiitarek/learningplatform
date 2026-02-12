-- =====================================================
-- Seed Data - Sample accounts and content
-- Default passwords: Admin@123456, Teacher@123456, Student@123456
-- =====================================================

-- Insert academic years
INSERT INTO `academic_years` (`id`, `name_en`, `name_ar`, `order_num`) VALUES
(1, 'First Secondary', 'الصف الأول الثانوي', 1),
(2, 'Second Secondary', 'الصف الثاني الثانوي', 2),
(3, 'Third Secondary', 'الصف الثالث الثانوي', 3);

-- Insert subjects
INSERT INTO `subjects` (`id`, `name_en`, `name_ar`, `description_en`, `description_ar`, `icon`, `color`) VALUES
(1, 'Mathematics', 'الرياضيات', 'Advanced mathematics for secondary students', 'رياضيات متقدمة لطلاب الثانوية', '📐', '#3B82F6'),
(2, 'Physics', 'الفيزياء', 'Physics fundamentals and applications', 'أساسيات الفيزياء وتطبيقاتها', '⚛️', '#8B5CF6'),
(3, 'Chemistry', 'الكيمياء', 'Chemistry concepts and experiments', 'مفاهيم الكيمياء والتجارب', '🧪', '#10B981'),
(4, 'Biology', 'الأحياء', 'Life sciences and biology', 'علوم الحياة والأحياء', '🧬', '#F59E0B'),
(5, 'Arabic Language', 'اللغة العربية', 'Arabic language and literature', 'اللغة العربية والأدب', '📚', '#EF4444'),
(6, 'English Language', 'اللغة الإنجليزية', 'English language skills', 'مهارات اللغة الإنجليزية', '🌍', '#06B6D4'),
(7, 'History', 'التاريخ', 'World and Egyptian history', 'التاريخ العالمي والمصري', '🏛️', '#EC4899'),
(8, 'Geography', 'الجغرافيا', 'Physical and human geography', 'الجغرافيا الطبيعية والبشرية', '🗺️', '#14B8A6');

-- Insert users (passwords: Admin@123456, Teacher@123456, Student@123456)
-- Password hash for all: $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
INSERT INTO `users` (`id`, `role`, `name`, `email`, `phone`, `password_hash`, `lang`, `year_id`, `stream`, `status`) VALUES
(1, 'admin', 'Admin User', 'admin@platform.com', '+201000000001', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'en', NULL, NULL, 'active'),
(2, 'teacher', 'محمد أحمد', 'teacher@platform.com', '+201000000002', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ar', NULL, NULL, 'active'),
(3, 'teacher', 'Sara Hassan', 'sara@platform.com', '+201000000003', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'en', NULL, NULL, 'active'),
(4, 'student', 'علي محمود', 'student@platform.com', '+201000000004', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ar', 3, 'scientific', 'active'),
(5, 'student', 'Ahmed Hassan', 'ahmed@platform.com', '+201000000005', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'en', 3, 'scientific', 'active');

-- Insert teacher profiles
INSERT INTO `teacher_profiles` (`user_id`, `bio_en`, `bio_ar`, `subjects`, `years_experience`, `approved`, `approved_at`, `approved_by`, `commission_rate`, `rating`, `rating_count`) VALUES
(2, 'Experienced mathematics teacher with 10+ years', 'مدرس رياضيات ذو خبرة تزيد عن 10 سنوات', '[1]', 10, 'approved', NOW(), 1, 0.2000, 4.85, 127),
(3, 'Physics expert and passionate educator', 'خبيرة فيزياء ومعلمة شغوفة', '[2]', 8, 'approved', NOW(), 1, 0.2000, 4.92, 89);

-- Insert teacher subjects
INSERT INTO `teacher_subjects` (`teacher_id`, `subject_id`, `year_id`) VALUES
(2, 1, 1), (2, 1, 2), (2, 1, 3),
(3, 2, 2), (3, 2, 3);

-- Insert subscription plans
INSERT INTO `plans` (`teacher_id`, `type`, `title_en`, `title_ar`, `description_en`, `description_ar`, `price`, `currency`, `duration_days`) VALUES
(2, 'monthly', 'Monthly Subscription - Mathematics', 'اشتراك شهري - الرياضيات', 'Full access to all mathematics lessons and exams', 'وصول كامل لجميع دروس وامتحانات الرياضيات', 299.00, 'EGP', 30),
(2, 'course', 'Full Year Course - Mathematics', 'كورس كامل - الرياضيات', 'Complete mathematics course for the academic year', 'كورس رياضيات كامل للعام الدراسي', 2499.00, 'EGP', 365),
(3, 'monthly', 'Monthly Subscription - Physics', 'اشتراك شهري - الفيزياء', 'Full access to all physics lessons and exams', 'وصول كامل لجميع دروس وامتحانات الفيزياء', 349.00, 'EGP', 30);

-- Insert sample units
INSERT INTO `units` (`teacher_id`, `subject_id`, `year_id`, `title_en`, `title_ar`, `description_en`, `description_ar`, `order_num`) VALUES
(2, 1, 3, 'Algebra Fundamentals', 'أساسيات الجبر', 'Core algebraic concepts and equations', 'المفاهيم الجبرية الأساسية والمعادلات', 1),
(2, 1, 3, 'Trigonometry', 'المثلثات', 'Trigonometric functions and identities', 'الدوال المثلثية والمتطابقات', 2),
(3, 2, 3, 'Mechanics', 'الميكانيكا', 'Newton laws and motion', 'قوانين نيوتن والحركة', 1),
(3, 2, 3, 'Electricity', 'الكهرباء', 'Current, voltage, and circuits', 'التيار والجهد والدوائر', 2);

-- Insert sample lessons
INSERT INTO `lessons` (`unit_id`, `teacher_id`, `title_en`, `title_ar`, `description_en`, `description_ar`, `content_en`, `content_ar`, `video_provider`, `video_id`, `is_free_preview`, `order_num`, `status`) VALUES
(1, 2, 'Introduction to Algebra', 'مقدمة في الجبر', 'Learn the basics of algebra', 'تعلم أساسيات الجبر', 'In this lesson, we will cover the fundamental concepts of algebra...', 'في هذا الدرس، سنغطي المفاهيم الأساسية للجبر...', 'youtube', 'dQw4w9WgXcQ', TRUE, 1, 'published'),
(1, 2, 'Linear Equations', 'المعادلات الخطية', 'Solving linear equations step by step', 'حل المعادلات الخطية خطوة بخطوة', 'Linear equations are fundamental...', 'المعادلات الخطية أساسية...', 'youtube', 'dQw4w9WgXcQ', FALSE, 2, 'published'),
(3, 3, 'Newton First Law', 'قانون نيوتن الأول', 'Understanding inertia and motion', 'فهم القصور الذاتي والحركة', 'Newton first law states that...', 'ينص قانون نيوتن الأول على أن...', 'youtube', 'dQw4w9WgXcQ', TRUE, 1, 'published');

-- Insert sample exam
INSERT INTO `exams` (`teacher_id`, `unit_id`, `title_en`, `title_ar`, `description_en`, `description_ar`, `duration_minutes`, `passing_score`, `max_attempts`, `status`) VALUES
(2, 1, 'Algebra Fundamentals Exam', 'امتحان أساسيات الجبر', 'Test your knowledge of algebra', 'اختبر معرفتك بالجبر', 45, 60.00, 3, 'published');

-- Insert sample questions
INSERT INTO `exam_questions` (`exam_id`, `type`, `question_en`, `question_ar`, `points`, `order_num`) VALUES
(1, 'mcq', 'What is 2 + 2?', 'كم يساوي 2 + 2؟', 1.00, 1),
(1, 'mcq', 'Solve: 3x = 9', 'حل: 3س = 9', 2.00, 2),
(1, 'true_false', 'Algebra is a branch of mathematics', 'الجبر فرع من الرياضيات', 1.00, 3);

-- Insert sample choices
INSERT INTO `question_choices` (`question_id`, `choice_en`, `choice_ar`, `is_correct`, `order_num`) VALUES
(1, '3', '3', FALSE, 1),
(1, '4', '4', TRUE, 2),
(1, '5', '5', FALSE, 3),
(1, '6', '6', FALSE, 4),
(2, 'x = 2', 'س = 2', FALSE, 1),
(2, 'x = 3', 'س = 3', TRUE, 2),
(2, 'x = 6', 'س = 6', FALSE, 3),
(2, 'x = 9', 'س = 9', FALSE, 4),
(3, 'True', 'صح', TRUE, 1),
(3, 'False', 'خطأ', FALSE, 2);

-- Insert sample coupons
INSERT INTO `coupons` (`code`, `type`, `value`, `teacher_id`, `max_uses`, `valid_from`, `valid_until`, `active`, `created_by`) VALUES
('WELCOME20', 'percentage', 20.00, NULL, 100, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), TRUE, 1),
('MATH50', 'fixed', 50.00, 2, 50, NOW(), DATE_ADD(NOW(), INTERVAL 60 DAY), TRUE, 1);

-- Insert system settings
INSERT INTO `settings` (`key`, `value`, `type`, `description`) VALUES
('site_name_en', 'EduPlatform', 'string', 'Site name in English'),
('site_name_ar', 'منصة التعليم', 'string', 'Site name in Arabic'),
('commission_rate', '0.20', 'string', 'Default platform commission rate'),
('maintenance_mode', 'false', 'bool', 'Enable maintenance mode'),
('registration_enabled', 'true', 'bool', 'Allow new registrations'),
('email_verification_required', 'false', 'bool', 'Require email verification'),
('contact_email', 'support@platform.com', 'string', 'Contact email address'),
('contact_phone', '+201000000000', 'string', 'Contact phone number');

-- Note: All default passwords are: Admin@123456, Teacher@123456, Student@123456
-- IMPORTANT: Change these passwords immediately after installation!
