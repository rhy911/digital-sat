/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `answer_choices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `answer_choices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `question_id` bigint unsigned NOT NULL,
  `label` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'A | B | C | D',
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'HTML — hỗ trợ MathJax/KaTeX cho công thức',
  `is_correct` tinyint(1) NOT NULL DEFAULT '0',
  `order` int NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `answer_choices_question_id_foreign` (`question_id`),
  CONSTRAINT `answer_choices_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `assignment_recipients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `assignment_recipients` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `assignment_id` bigint unsigned NOT NULL,
  `student_id` bigint unsigned NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `assigned_at` timestamp NOT NULL,
  `withdrawn_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `assignment_recipients_assignment_id_student_id_unique` (`assignment_id`,`student_id`),
  KEY `assignment_recipients_student_id_status_index` (`student_id`,`status`),
  KEY `assignment_recipients_status_index` (`status`),
  CONSTRAINT `assignment_recipients_assignment_id_foreign` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `assignment_recipients_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `assignments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ulid` char(26) COLLATE utf8mb4_unicode_ci NOT NULL,
  `classroom_id` bigint unsigned NOT NULL,
  `teacher_id` bigint unsigned NOT NULL,
  `test_id` bigint unsigned NOT NULL,
  `assign_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'full',
  `section_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `instructions` text COLLATE utf8mb4_unicode_ci,
  `available_at` timestamp NULL DEFAULT NULL,
  `due_at` timestamp NULL DEFAULT NULL,
  `attempt_limit` tinyint unsigned NOT NULL DEFAULT '1',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'published',
  `published_at` timestamp NULL DEFAULT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `assignments_ulid_unique` (`ulid`),
  KEY `assignments_teacher_id_foreign` (`teacher_id`),
  KEY `assignments_test_id_foreign` (`test_id`),
  KEY `assignments_classroom_id_status_index` (`classroom_id`,`status`),
  KEY `assignments_available_at_index` (`available_at`),
  KEY `assignments_due_at_index` (`due_at`),
  KEY `assignments_status_index` (`status`),
  CONSTRAINT `assignments_classroom_id_foreign` FOREIGN KEY (`classroom_id`) REFERENCES `classrooms` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `assignments_teacher_id_foreign` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `assignments_test_id_foreign` FOREIGN KEY (`test_id`) REFERENCES `tests` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `blog_posts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `blog_posts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ulid` char(26) COLLATE utf8mb4_unicode_ci NOT NULL,
  `teacher_id` bigint unsigned NOT NULL,
  `title` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `excerpt` text COLLATE utf8mb4_unicode_ci,
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `published_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `blog_posts_ulid_unique` (`ulid`),
  KEY `blog_posts_teacher_id_foreign` (`teacher_id`),
  CONSTRAINT `blog_posts_teacher_id_foreign` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `classroom_announcement_comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `classroom_announcement_comments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ulid` char(26) COLLATE utf8mb4_unicode_ci NOT NULL,
  `announcement_id` bigint unsigned NOT NULL,
  `author_id` bigint unsigned NOT NULL,
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `classroom_announcement_comments_ulid_unique` (`ulid`),
  KEY `classroom_announcement_comments_author_id_foreign` (`author_id`),
  KEY `classroom_announcement_comments_announcement_id_index` (`announcement_id`),
  CONSTRAINT `classroom_announcement_comments_announcement_id_foreign` FOREIGN KEY (`announcement_id`) REFERENCES `classroom_announcements` (`id`) ON DELETE CASCADE,
  CONSTRAINT `classroom_announcement_comments_author_id_foreign` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `classroom_announcements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `classroom_announcements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ulid` char(26) COLLATE utf8mb4_unicode_ci NOT NULL,
  `classroom_id` bigint unsigned NOT NULL,
  `author_id` bigint unsigned NOT NULL,
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `pinned` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `classroom_announcements_ulid_unique` (`ulid`),
  KEY `classroom_announcements_author_id_foreign` (`author_id`),
  KEY `classroom_announcements_classroom_id_pinned_index` (`classroom_id`,`pinned`),
  CONSTRAINT `classroom_announcements_author_id_foreign` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `classroom_announcements_classroom_id_foreign` FOREIGN KEY (`classroom_id`) REFERENCES `classrooms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `classroom_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `classroom_documents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ulid` char(26) COLLATE utf8mb4_unicode_ci NOT NULL,
  `classroom_id` bigint unsigned NOT NULL,
  `created_by` bigint unsigned NOT NULL,
  `title` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `source_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `disk` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `path` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `original_name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mime_type` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `size_bytes` bigint unsigned DEFAULT NULL,
  `external_url` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `classroom_documents_ulid_unique` (`ulid`),
  KEY `classroom_documents_created_by_foreign` (`created_by`),
  KEY `classroom_documents_classroom_id_source_type_index` (`classroom_id`,`source_type`),
  KEY `classroom_documents_source_type_index` (`source_type`),
  CONSTRAINT `classroom_documents_classroom_id_foreign` FOREIGN KEY (`classroom_id`) REFERENCES `classrooms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `classroom_documents_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `classroom_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `classroom_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ulid` char(26) COLLATE utf8mb4_unicode_ci NOT NULL,
  `classroom_id` bigint unsigned NOT NULL,
  `created_by` bigint unsigned NOT NULL,
  `title` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `starts_at` datetime NOT NULL,
  `ends_at` datetime DEFAULT NULL,
  `location` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `all_day` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `classroom_events_ulid_unique` (`ulid`),
  KEY `classroom_events_created_by_foreign` (`created_by`),
  KEY `classroom_events_classroom_id_starts_at_index` (`classroom_id`,`starts_at`),
  CONSTRAINT `classroom_events_classroom_id_foreign` FOREIGN KEY (`classroom_id`) REFERENCES `classrooms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `classroom_events_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `classroom_memberships`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `classroom_memberships` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `classroom_id` bigint unsigned NOT NULL,
  `student_id` bigint unsigned NOT NULL,
  `display_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `requested_at` timestamp NULL DEFAULT NULL,
  `decided_at` timestamp NULL DEFAULT NULL,
  `ended_at` timestamp NULL DEFAULT NULL,
  `decided_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `classroom_memberships_classroom_id_student_id_unique` (`classroom_id`,`student_id`),
  KEY `classroom_memberships_student_id_foreign` (`student_id`),
  KEY `classroom_memberships_decided_by_foreign` (`decided_by`),
  KEY `classroom_memberships_classroom_id_status_index` (`classroom_id`,`status`),
  KEY `classroom_memberships_status_index` (`status`),
  CONSTRAINT `classroom_memberships_classroom_id_foreign` FOREIGN KEY (`classroom_id`) REFERENCES `classrooms` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `classroom_memberships_decided_by_foreign` FOREIGN KEY (`decided_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `classroom_memberships_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `classroom_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `classroom_notes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `classroom_id` bigint unsigned NOT NULL,
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `classroom_notes_classroom_id_unique` (`classroom_id`),
  KEY `classroom_notes_created_by_foreign` (`created_by`),
  CONSTRAINT `classroom_notes_classroom_id_foreign` FOREIGN KEY (`classroom_id`) REFERENCES `classrooms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `classroom_notes_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `classroom_teachers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `classroom_teachers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `classroom_id` bigint unsigned NOT NULL,
  `teacher_id` bigint unsigned NOT NULL,
  `added_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `classroom_teachers_classroom_id_teacher_id_unique` (`classroom_id`,`teacher_id`),
  KEY `classroom_teachers_added_by_foreign` (`added_by`),
  KEY `classroom_teachers_teacher_id_classroom_id_index` (`teacher_id`,`classroom_id`),
  CONSTRAINT `classroom_teachers_added_by_foreign` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `classroom_teachers_classroom_id_foreign` FOREIGN KEY (`classroom_id`) REFERENCES `classrooms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `classroom_teachers_teacher_id_foreign` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `classrooms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `classrooms` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ulid` char(26) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner_id` bigint unsigned NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `join_code` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL,
  `join_code_rotated_at` timestamp NULL DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `classrooms_ulid_unique` (`ulid`),
  UNIQUE KEY `classrooms_join_code_unique` (`join_code`),
  KEY `classrooms_owner_id_status_index` (`owner_id`,`status`),
  KEY `classrooms_status_index` (`status`),
  CONSTRAINT `classrooms_owner_id_foreign` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `forum_replies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `forum_replies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ulid` char(26) COLLATE utf8mb4_unicode_ci NOT NULL,
  `thread_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `forum_replies_ulid_unique` (`ulid`),
  KEY `forum_replies_thread_id_foreign` (`thread_id`),
  KEY `forum_replies_user_id_foreign` (`user_id`),
  CONSTRAINT `forum_replies_thread_id_foreign` FOREIGN KEY (`thread_id`) REFERENCES `forum_threads` (`id`) ON DELETE CASCADE,
  CONSTRAINT `forum_replies_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `forum_threads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `forum_threads` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ulid` char(26) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `title` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `forum_threads_ulid_unique` (`ulid`),
  KEY `forum_threads_user_id_foreign` (`user_id`),
  CONSTRAINT `forum_threads_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `module_blueprints`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `module_blueprints` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `section_type` enum('reading_writing','math') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'reading_writing | math',
  `module_number` int NOT NULL COMMENT '1 | 2',
  `difficulty_level` enum('standard','easy','hard') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'standard | easy | hard',
  `skill_domain` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `min_questions` int NOT NULL COMMENT 'Số câu tối thiểu domain này',
  `max_questions` int NOT NULL COMMENT 'Số câu tối đa',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `module_questions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `module_questions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `module_id` bigint unsigned NOT NULL,
  `question_id` bigint unsigned NOT NULL,
  `position` int NOT NULL COMMENT 'Thứ tự hiển thị trong module này',
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_module_question` (`module_id`,`question_id`),
  UNIQUE KEY `uq_module_position` (`module_id`,`position`),
  KEY `module_questions_question_id_foreign` (`question_id`),
  CONSTRAINT `module_questions_module_id_foreign` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE,
  CONSTRAINT `module_questions_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `module_routing`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `module_routing` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `from_module_id` bigint unsigned NOT NULL,
  `to_module_id` bigint unsigned NOT NULL,
  `condition` enum('score_above','score_below_equal') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'score_above | score_below_equal',
  `threshold_score` int NOT NULL COMMENT 'Số câu đúng tối thiểu để route',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `module_routing_from_module_id_foreign` (`from_module_id`),
  KEY `module_routing_to_module_id_foreign` (`to_module_id`),
  CONSTRAINT `module_routing_from_module_id_foreign` FOREIGN KEY (`from_module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE,
  CONSTRAINT `module_routing_to_module_id_foreign` FOREIGN KEY (`to_module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `modules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `modules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ulid` char(26) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `section_id` bigint unsigned DEFAULT NULL,
  `module_number` int NOT NULL COMMENT '1 = Module 1, 2 = Module 2',
  `difficulty_level` enum('standard','easy','hard') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'standard' COMMENT 'standard (M1) | easy | hard (M2)',
  `duration_minutes` int NOT NULL DEFAULT '32',
  `total_questions` int NOT NULL DEFAULT '27',
  `order` int NOT NULL DEFAULT '1',
  `created_by` bigint unsigned DEFAULT NULL,
  `is_public` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `modules_ulid_unique` (`ulid`),
  UNIQUE KEY `modules_key_unique` (`key`),
  KEY `modules_section_id_foreign` (`section_id`),
  KEY `modules_created_by_foreign` (`created_by`),
  CONSTRAINT `modules_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `modules_section_id_foreign` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_type` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_id` bigint unsigned NOT NULL,
  `data` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `paired_passages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `paired_passages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `passage_a_id` bigint unsigned NOT NULL,
  `passage_b_id` bigint unsigned NOT NULL,
  `relationship` enum('contrasting','complementary','cause_effect') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'contrasting | complementary | cause_effect',
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `paired_passages_passage_a_id_foreign` (`passage_a_id`),
  KEY `paired_passages_passage_b_id_foreign` (`passage_b_id`),
  CONSTRAINT `paired_passages_passage_a_id_foreign` FOREIGN KEY (`passage_a_id`) REFERENCES `passages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `paired_passages_passage_b_id_foreign` FOREIGN KEY (`passage_b_id`) REFERENCES `passages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `passages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `passages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nội dung đoạn văn (HTML/Markdown)',
  `passage_type` enum('single','paired') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'single' COMMENT 'single | paired',
  `word_count` int DEFAULT NULL COMMENT 'Để cân đối độ dài khi chọn passage',
  `source_title` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Tiêu đề tác phẩm gốc',
  `source_author` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_year` int DEFAULT NULL,
  `genre` enum('literary_narrative','social_science','natural_science','humanities') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'literary_narrative | social_science | natural_science | humanities',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,
  `name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  KEY `personal_access_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `question_explanations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `question_explanations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `question_id` bigint unsigned NOT NULL,
  `explanation` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Tại sao đáp án đúng là đúng (HTML)',
  `rationale_a` text COLLATE utf8mb4_unicode_ci COMMENT 'Giải thích cho choice A',
  `rationale_b` text COLLATE utf8mb4_unicode_ci COMMENT 'Giải thích cho choice B',
  `rationale_c` text COLLATE utf8mb4_unicode_ci COMMENT 'Giải thích cho choice C',
  `rationale_d` text COLLATE utf8mb4_unicode_ci COMMENT 'Giải thích cho choice D',
  `strategy_tip` text COLLATE utf8mb4_unicode_ci COMMENT 'Mẹo giải nhanh hoặc phương pháp tiếp cận',
  `common_mistakes` text COLLATE utf8mb4_unicode_ci COMMENT 'Lỗi sai thường gặp',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `question_explanations_question_id_unique` (`question_id`),
  CONSTRAINT `question_explanations_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `question_media`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `question_media` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `question_id` bigint unsigned NOT NULL,
  `media_type` enum('image','graph','chart','table','formula','equation') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'image | graph | chart | table | formula | equation',
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `alt_text` text COLLATE utf8mb4_unicode_ci COMMENT 'Mô tả cho accessibility — Bluebook hỗ trợ screen reader',
  `position` enum('passage','stem','choice') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'passage | stem | choice',
  `order` int NOT NULL DEFAULT '1',
  `width` int DEFAULT NULL COMMENT 'Pixel width để render đúng kích thước',
  `height` int DEFAULT NULL COMMENT 'Pixel height',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `question_media_question_id_foreign` (`question_id`),
  CONSTRAINT `question_media_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `questions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `questions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `passage_id` bigint unsigned DEFAULT NULL,
  `paired_passage_id` bigint unsigned DEFAULT NULL,
  `stem` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nội dung câu hỏi (HTML)',
  `question_type` enum('multiple_choice','student_produced_response') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'multiple_choice' COMMENT 'multiple_choice | student_produced_response',
  `difficulty` enum('easy','medium','hard') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium' COMMENT 'easy | medium | hard',
  `is_pretest` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Nếu true, câu này không được tính điểm (unscored experimental)',
  `is_complete` tinyint(1) NOT NULL DEFAULT '1',
  `expected_time` int unsigned DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `section_type` enum('reading_writing','math') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Thuộc section nào',
  `skill_domain` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'VD: information_and_ideas, algebra, advanced_math, ...',
  `skill_subdomain` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Chi tiết hơn domain, VD: linear_equations_in_one_variable',
  `spr_hint` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Gợi ý cho SPR, VD: Enter a fraction or decimal',
  `calculator_allowed` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Math M1: một số câu không cho dùng calc',
  `irt_a` decimal(4,2) NOT NULL DEFAULT '0.90' COMMENT 'Discrimination',
  `irt_b` decimal(4,2) NOT NULL DEFAULT '0.00' COMMENT 'Difficulty',
  `irt_c` decimal(4,2) NOT NULL DEFAULT '0.25' COMMENT 'Guessing',
  `irt_calibration_status` enum('provisional','calibrated') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'provisional',
  `irt_calibration_version` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `external_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'CollegeBoard question ID nếu có',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `questions_passage_id_foreign` (`passage_id`),
  KEY `questions_paired_passage_id_foreign` (`paired_passage_id`),
  KEY `questions_is_complete_index` (`is_complete`),
  KEY `questions_created_by_foreign` (`created_by`),
  CONSTRAINT `questions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `questions_paired_passage_id_foreign` FOREIGN KEY (`paired_passage_id`) REFERENCES `paired_passages` (`id`) ON DELETE SET NULL,
  CONSTRAINT `questions_passage_id_foreign` FOREIGN KEY (`passage_id`) REFERENCES `passages` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `score_conversion_sets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `score_conversion_sets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `test_id` bigint unsigned NOT NULL,
  `version` int unsigned NOT NULL,
  `status` enum('draft','approved','retired') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `source_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_url` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `checksum` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `form_checksum` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `score_conversion_sets_test_id_version_unique` (`test_id`,`version`),
  KEY `score_conversion_sets_approved_by_foreign` (`approved_by`),
  CONSTRAINT `score_conversion_sets_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `score_conversion_sets_test_id_foreign` FOREIGN KEY (`test_id`) REFERENCES `tests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `score_conversions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `score_conversions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `score_conversion_set_id` bigint unsigned DEFAULT NULL,
  `test_id` bigint unsigned DEFAULT NULL,
  `section_type` enum('reading_writing','math') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'reading_writing | math',
  `m2_difficulty` enum('standard','easy','hard') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'standard',
  `raw_score` int NOT NULL COMMENT 'Tổng câu đúng M1 + M2',
  `scaled_score` int NOT NULL COMMENT '200–800',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_score_conversion` (`test_id`,`section_type`,`m2_difficulty`,`raw_score`),
  UNIQUE KEY `uq_score_conversion_version` (`score_conversion_set_id`,`section_type`,`m2_difficulty`,`raw_score`),
  CONSTRAINT `score_conversions_score_conversion_set_id_foreign` FOREIGN KEY (`score_conversion_set_id`) REFERENCES `score_conversion_sets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `score_conversions_test_id_foreign` FOREIGN KEY (`test_id`) REFERENCES `tests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `section_modules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `section_modules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `section_id` bigint unsigned NOT NULL,
  `module_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `section_modules_section_id_module_id_unique` (`section_id`,`module_id`),
  KEY `section_modules_module_id_foreign` (`module_id`),
  CONSTRAINT `section_modules_module_id_foreign` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE,
  CONSTRAINT `section_modules_section_id_foreign` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sections` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `test_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'VD: Reading and Writing / Math',
  `type` enum('reading_writing','math') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'reading_writing | math',
  `order` int NOT NULL DEFAULT '1' COMMENT '1 = R&W, 2 = Math',
  `created_by` bigint unsigned DEFAULT NULL,
  `is_public` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sections_test_id_foreign` (`test_id`),
  KEY `sections_created_by_foreign` (`created_by`),
  CONSTRAINT `sections_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sections_test_id_foreign` FOREIGN KEY (`test_id`) REFERENCES `tests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `spr_correct_answers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `spr_correct_answers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `question_id` bigint unsigned NOT NULL,
  `answer` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Một dạng đáp án hợp lệ',
  `answer_type` enum('exact','range','fraction_equivalent') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'exact' COMMENT 'exact | range | fraction_equivalent',
  `tolerance` decimal(10,4) DEFAULT NULL COMMENT 'Sai số cho phép nếu answer_type = range',
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `spr_correct_answers_question_id_foreign` (`question_id`),
  CONSTRAINT `spr_correct_answers_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `test_shares`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `test_shares` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `test_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `shared_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `test_shares_test_id_user_id_unique` (`test_id`,`user_id`),
  KEY `test_shares_shared_by_foreign` (`shared_by`),
  KEY `test_shares_user_id_test_id_index` (`user_id`,`test_id`),
  CONSTRAINT `test_shares_shared_by_foreign` FOREIGN KEY (`shared_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `test_shares_test_id_foreign` FOREIGN KEY (`test_id`) REFERENCES `tests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `test_shares_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ulid` char(26) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'VD: Digital SAT Practice Test 1',
  `description` text COLLATE utf8mb4_unicode_ci,
  `test_type` enum('full_length','adaptive_full_length','section_only','module_only','short_test','custom_test') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'full_length',
  `total_duration_minutes` int NOT NULL DEFAULT '134',
  `break_duration_minutes` int NOT NULL DEFAULT '10' COMMENT 'Break giữa Section 1 và 2',
  `status` enum('draft','active','archived') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active' COMMENT 'draft | active | archived',
  `created_by` bigint unsigned DEFAULT NULL,
  `is_public` tinyint(1) NOT NULL DEFAULT '0',
  `content_locked_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tests_ulid_unique` (`ulid`),
  KEY `tests_created_by_foreign` (`created_by`),
  KEY `tests_content_locked_at_index` (`content_locked_at`),
  CONSTRAINT `tests_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_test_answers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_test_answers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_test_id` bigint unsigned NOT NULL,
  `module_id` bigint unsigned NOT NULL,
  `question_id` bigint unsigned NOT NULL,
  `selected_answer` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Label A/B/C/D or SPR value',
  `time_spent` int unsigned NOT NULL DEFAULT '0',
  `is_correct` tinyint(1) NOT NULL DEFAULT '0',
  `question_snapshot` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_test_module_question` (`user_test_id`,`module_id`,`question_id`),
  KEY `user_test_answers_question_id_foreign` (`question_id`),
  KEY `user_test_answers_module_id_foreign` (`module_id`),
  CONSTRAINT `user_test_answers_module_id_foreign` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_test_answers_question_id_foreign` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `user_test_answers_user_test_id_foreign` FOREIGN KEY (`user_test_id`) REFERENCES `user_tests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_test_module_submissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_test_module_submissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_test_id` bigint unsigned NOT NULL,
  `module_id` bigint unsigned NOT NULL,
  `issued_next_module_id` bigint unsigned DEFAULT NULL,
  `result` json NOT NULL,
  `submitted_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `attempt_module_submission_unique` (`user_test_id`,`module_id`),
  KEY `user_test_module_submissions_module_id_foreign` (`module_id`),
  KEY `user_test_module_submissions_issued_next_module_id_foreign` (`issued_next_module_id`),
  CONSTRAINT `user_test_module_submissions_issued_next_module_id_foreign` FOREIGN KEY (`issued_next_module_id`) REFERENCES `modules` (`id`) ON DELETE SET NULL,
  CONSTRAINT `user_test_module_submissions_module_id_foreign` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `user_test_module_submissions_user_test_id_foreign` FOREIGN KEY (`user_test_id`) REFERENCES `user_tests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_test_score_revisions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_test_score_revisions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_test_id` bigint unsigned NOT NULL,
  `run_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `previous_score` json NOT NULL,
  `revised_score` json NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_test_score_revisions_user_test_id_run_id_unique` (`user_test_id`,`run_id`),
  CONSTRAINT `user_test_score_revisions_user_test_id_foreign` FOREIGN KEY (`user_test_id`) REFERENCES `user_tests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_tests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_tests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ulid` char(26) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` bigint unsigned NOT NULL,
  `test_id` bigint unsigned NOT NULL,
  `assignment_id` bigint unsigned DEFAULT NULL,
  `attempt_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'full',
  `section_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attempt_number` tinyint unsigned DEFAULT NULL,
  `score_reading_writing` int DEFAULT NULL,
  `score_reading_writing_lower` smallint unsigned DEFAULT NULL,
  `score_reading_writing_upper` smallint unsigned DEFAULT NULL,
  `rw_m2_path` enum('easy','hard') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rw_theta` decimal(5,3) DEFAULT NULL,
  `rw_theta_se` decimal(5,3) DEFAULT NULL,
  `score_math` int DEFAULT NULL,
  `score_math_lower` smallint unsigned DEFAULT NULL,
  `score_math_upper` smallint unsigned DEFAULT NULL,
  `math_m2_path` enum('easy','hard') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `math_theta` decimal(5,3) DEFAULT NULL,
  `math_theta_se` decimal(5,3) DEFAULT NULL,
  `scoring_method` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_score` int DEFAULT NULL,
  `total_score_lower` smallint unsigned DEFAULT NULL,
  `total_score_upper` smallint unsigned DEFAULT NULL,
  `score_conversion_set_id` bigint unsigned DEFAULT NULL,
  `score_conversion_version` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `score_estimate_kind` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'completed',
  `active_practice_attempt` bigint unsigned GENERATED ALWAYS AS ((case when ((`status` = _utf8mb4'in_progress') and (`assignment_id` is null)) then `user_id` else NULL end)) VIRTUAL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `current_module_id` bigint unsigned DEFAULT NULL,
  `current_module_started_at` timestamp NULL DEFAULT NULL,
  `current_module_elapsed_seconds` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_tests_ulid_unique` (`ulid`),
  UNIQUE KEY `assignment_student_attempt_unique` (`assignment_id`,`user_id`,`attempt_number`),
  UNIQUE KEY `uq_user_test_active_practice` (`test_id`,`active_practice_attempt`),
  KEY `user_tests_current_module_id_foreign` (`current_module_id`),
  KEY `user_tests_score_conversion_set_id_foreign` (`score_conversion_set_id`),
  KEY `idx_user_tests_composite` (`user_id`,`test_id`,`assignment_id`,`status`,`updated_at`),
  CONSTRAINT `user_tests_assignment_id_foreign` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `user_tests_current_module_id_foreign` FOREIGN KEY (`current_module_id`) REFERENCES `modules` (`id`) ON DELETE SET NULL,
  CONSTRAINT `user_tests_score_conversion_set_id_foreign` FOREIGN KEY (`score_conversion_set_id`) REFERENCES `score_conversion_sets` (`id`) ON DELETE SET NULL,
  CONSTRAINT `user_tests_test_id_foreign` FOREIGN KEY (`test_id`) REFERENCES `tests` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `user_tests_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('student','teacher','admin') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'student',
  `share_independent_practice` tinyint(1) NOT NULL DEFAULT '0',
  `teacher_approval_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `teacher_reviewed_by` bigint unsigned DEFAULT NULL,
  `teacher_reviewed_at` timestamp NULL DEFAULT NULL,
  `teacher_rejection_reason` text COLLATE utf8mb4_unicode_ci,
  `is_2FA_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `two_factor_code` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `two_factor_expired_at` datetime DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1 la active, 0 la banned',
  `avatar` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_username_unique` (`username`),
  KEY `users_teacher_reviewed_by_foreign` (`teacher_reviewed_by`),
  KEY `users_teacher_approval_status_index` (`teacher_approval_status`),
  CONSTRAINT `users_teacher_reviewed_by_foreign` FOREIGN KEY (`teacher_reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'0001_01_01_000000_create_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'0001_01_01_000001_create_cache_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'2026_02_24_181509_create_personal_access_tokens_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (5,'2026_03_31_100001_create_tests_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (6,'2026_03_31_100002_create_sections_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (7,'2026_03_31_100003_create_modules_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (8,'2026_03_31_100004_create_module_routing_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (9,'2026_03_31_100005_create_passages_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (10,'2026_03_31_100006_create_paired_passages_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (11,'2026_03_31_100007_create_questions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (12,'2026_03_31_100008_create_module_questions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (13,'2026_03_31_100009_create_question_media_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (14,'2026_03_31_100010_create_answer_choices_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (15,'2026_03_31_100011_create_spr_correct_answers_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (16,'2026_03_31_100012_create_question_explanations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (17,'2026_03_31_100013_create_module_blueprints_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (18,'2026_03_31_100014_create_score_conversions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (19,'2026_04_06_111749_add_deleted_at_to_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (20,'2026_04_10_162757_add_is_pretest_to_questions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (21,'2026_04_28_093442_remove_question_number_from_questions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (22,'2026_05_04_154345_add_is_complete_to_questions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (23,'2026_05_11_132718_create_user_tests_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (24,'2026_05_11_150943_add_irt_params_to_questions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (25,'2026_05_11_151119_create_user_test_answers_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (26,'2026_05_11_151133_add_routing_paths_to_user_tests_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (27,'2026_05_19_121500_create_section_modules_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (28,'2026_05_23_005945_update_test_types_in_tests_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (29,'2026_05_29_112208_add_ownership_and_visibility_fields',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (30,'2026_06_02_144106_add_module_started_at_to_user_tests_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (31,'2026_06_03_170635_add_elapsed_seconds_to_user_tests_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (32,'2026_06_03_235900_add_ulid_to_user_tests_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (33,'2026_06_04_000000_add_custom_test_type_to_tests_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (34,'2026_06_04_010000_add_module_id_to_user_test_answers_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (35,'2026_06_19_105842_backfill_questions_created_by',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (36,'2026_06_20_000001_create_teacher_class_management_tables',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (37,'2026_06_21_220210_add_soft_deletes_to_assignments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (38,'2026_06_22_000001_create_user_test_module_submissions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (39,'2026_06_22_133614_harden_historical_data_integrity',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (40,'2026_06_22_200000_version_score_conversions',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (41,'2026_06_22_210000_add_score_estimate_metadata_to_user_tests',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (42,'2026_06_22_220000_split_adaptive_and_normal_full_tests',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (43,'2026_06_22_230000_retire_adaptive_raw_conversion_sets',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (44,'2026_06_22_240000_reconcile_adaptive_content_locks',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (45,'2026_06_23_074324_add_active_practice_attempt_unique_index_to_user_tests',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (46,'2026_06_23_080601_make_module_id_not_null_in_user_test_answers',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (47,'2026_06_23_080611_add_user_tests_composite_indexes',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (48,'2026_06_24_000001_create_test_shares_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (49,'2026_06_25_000001_add_classroom_teachers_and_documents',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (50,'2026_07_02_104020_add_expected_time_and_time_spent_columns',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (51,'2026_07_07_174906_add_section_only_columns_to_assignments_and_attempts',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (52,'2026_07_17_000003_create_classroom_notes_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (53,'2026_07_17_000004_add_display_name_to_classroom_memberships_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (54,'2026_07_18_000001_add_share_independent_practice_to_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (55,'2026_07_19_000001_create_blog_posts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (56,'2026_07_19_000002_create_forum_threads_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (57,'2026_07_19_000003_create_forum_replies_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (58,'2026_07_20_130300_change_assignments_default_status_to_published',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (59,'2026_07_24_235451_create_notifications_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (60,'2026_07_25_000001_create_classroom_announcements_tables',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (61,'2026_07_25_000002_create_classroom_events_table',1);
