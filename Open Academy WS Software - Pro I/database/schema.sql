-- Estrutura de tabelas utilizada pelo sistema EAD.
-- O banco remoto mtiser88_sistemaead ja contem esta estrutura.

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(120) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','gestor','aluno') NOT NULL DEFAULT 'aluno',
  photo_path VARCHAR(255) DEFAULT NULL,
  signature_name VARCHAR(160) DEFAULT NULL,
  signature_title VARCHAR(160) DEFAULT NULL,
  signature_path VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY idx_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS courses (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(160) NOT NULL,
  description TEXT NOT NULL,
  video_url VARCHAR(255) DEFAULT NULL,
  pdf_url VARCHAR(255) DEFAULT NULL,
  workload INT UNSIGNED NOT NULL,
  deadline DATE DEFAULT NULL,
  certificate_validity_months INT UNSIGNED DEFAULT NULL,
  completion_type VARCHAR(32) NOT NULL DEFAULT 'assessment',
  created_by INT UNSIGNED DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_courses_author (created_by),
  CONSTRAINT fk_courses_users FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS course_modules (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  course_id INT UNSIGNED NOT NULL,
  title VARCHAR(160) NOT NULL,
  description TEXT,
  video_url VARCHAR(255) DEFAULT NULL,
  pdf_url VARCHAR(255) DEFAULT NULL,
  position INT UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_modules_course (course_id),
  KEY idx_modules_order (course_id, position),
  CONSTRAINT fk_modules_course FOREIGN KEY (course_id) REFERENCES courses (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS enrollments (
  course_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (course_id, user_id),
  KEY idx_enrollments_user (user_id),
  CONSTRAINT fk_enrollments_course FOREIGN KEY (course_id) REFERENCES courses (id) ON DELETE CASCADE,
  CONSTRAINT fk_enrollments_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS course_questions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  course_id INT UNSIGNED NOT NULL,
  prompt TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_course_questions_course (course_id),
  CONSTRAINT fk_questions_course FOREIGN KEY (course_id) REFERENCES courses (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS question_options (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  question_id INT UNSIGNED NOT NULL,
  option_text TEXT NOT NULL,
  is_correct TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_options_question (question_id),
  CONSTRAINT fk_options_question FOREIGN KEY (question_id) REFERENCES course_questions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS module_questions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  module_id INT UNSIGNED NOT NULL,
  prompt TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_module_questions_module (module_id),
  CONSTRAINT fk_module_questions_module FOREIGN KEY (module_id) REFERENCES course_modules (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS module_question_options (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  question_id INT UNSIGNED NOT NULL,
  option_text TEXT NOT NULL,
  is_correct TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_module_options_question (question_id),
  CONSTRAINT fk_module_options_question FOREIGN KEY (question_id) REFERENCES module_questions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS module_topics (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  module_id INT UNSIGNED NOT NULL,
  title VARCHAR(160) NOT NULL,
  description TEXT,
  video_url VARCHAR(255) DEFAULT NULL,
  pdf_url VARCHAR(255) DEFAULT NULL,
  position INT UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_topics_module (module_id),
  KEY idx_topics_order (module_id, position),
  CONSTRAINT fk_topics_module FOREIGN KEY (module_id) REFERENCES course_modules (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS module_topic_progress (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  module_topic_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  completed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_topic_user (module_topic_id, user_id),
  KEY idx_progress_user (user_id),
  CONSTRAINT fk_progress_topic FOREIGN KEY (module_topic_id) REFERENCES module_topics (id) ON DELETE CASCADE,
  CONSTRAINT fk_progress_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS module_results (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  module_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  score DECIMAL(4,1) NOT NULL,
  approved TINYINT(1) NOT NULL DEFAULT 0,
  attempts INT UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_module_user (module_id, user_id),
  KEY idx_module_results_user (user_id),
  CONSTRAINT fk_module_results_module FOREIGN KEY (module_id) REFERENCES course_modules (id) ON DELETE CASCADE,
  CONSTRAINT fk_module_results_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS module_answers (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  result_id INT UNSIGNED NOT NULL,
  question_id INT UNSIGNED NOT NULL,
  option_id INT UNSIGNED NOT NULL,
  is_correct TINYINT(1) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_module_answers_result (result_id),
  KEY idx_module_answers_question (question_id),
  CONSTRAINT fk_module_answers_result FOREIGN KEY (result_id) REFERENCES module_results (id) ON DELETE CASCADE,
  CONSTRAINT fk_module_answers_question FOREIGN KEY (question_id) REFERENCES module_questions (id) ON DELETE CASCADE,
  CONSTRAINT fk_module_answers_option FOREIGN KEY (option_id) REFERENCES module_question_options (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS assessment_results (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  course_id INT UNSIGNED NOT NULL,
  score DECIMAL(4,1) NOT NULL,
  approved TINYINT(1) NOT NULL DEFAULT 0,
  certificate_code VARCHAR(32) DEFAULT NULL,
  issued_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_result_user_course (user_id, course_id),
  KEY idx_results_course (course_id),
  CONSTRAINT fk_results_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_results_course FOREIGN KEY (course_id) REFERENCES courses (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS assessment_answers (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  result_id INT UNSIGNED NOT NULL,
  question_id INT UNSIGNED NOT NULL,
  option_id INT UNSIGNED NOT NULL,
  is_correct TINYINT(1) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_answers_result (result_id),
  KEY idx_answers_question (question_id),
  CONSTRAINT fk_answers_result FOREIGN KEY (result_id) REFERENCES assessment_results (id) ON DELETE CASCADE,
  CONSTRAINT fk_answers_question FOREIGN KEY (question_id) REFERENCES course_questions (id) ON DELETE CASCADE,
  CONSTRAINT fk_answers_option FOREIGN KEY (option_id) REFERENCES question_options (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS course_modules (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  course_id INT UNSIGNED NOT NULL,
  title VARCHAR(160) NOT NULL,
  description TEXT,
  video_url VARCHAR(255) DEFAULT NULL,
  pdf_url VARCHAR(255) DEFAULT NULL,
  position INT UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_modules_course (course_id),
  KEY idx_modules_order (course_id, position),
  CONSTRAINT fk_modules_course FOREIGN KEY (course_id) REFERENCES courses (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS module_questions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  module_id INT UNSIGNED NOT NULL,
  prompt TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_module_questions_module (module_id),
  CONSTRAINT fk_module_questions_module FOREIGN KEY (module_id) REFERENCES course_modules (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS module_question_options (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  question_id INT UNSIGNED NOT NULL,
  option_text TEXT NOT NULL,
  is_correct TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_module_options_question (question_id),
  CONSTRAINT fk_module_options_question FOREIGN KEY (question_id) REFERENCES module_questions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS module_topics (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  module_id INT UNSIGNED NOT NULL,
  title VARCHAR(160) NOT NULL,
  description TEXT,
  video_url VARCHAR(255) DEFAULT NULL,
  pdf_url VARCHAR(255) DEFAULT NULL,
  position INT UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_topics_module (module_id),
  KEY idx_topics_order (module_id, position),
  CONSTRAINT fk_topics_module FOREIGN KEY (module_id) REFERENCES course_modules (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS module_topic_progress (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  module_topic_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  completed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_topic_user (module_topic_id, user_id),
  KEY idx_progress_user (user_id),
  CONSTRAINT fk_progress_topic FOREIGN KEY (module_topic_id) REFERENCES module_topics (id) ON DELETE CASCADE,
  CONSTRAINT fk_progress_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS module_results (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  module_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  score DECIMAL(4,1) NOT NULL,
  approved TINYINT(1) NOT NULL DEFAULT 0,
  attempts INT UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_module_user (module_id, user_id),
  KEY idx_module_results_user (user_id),
  CONSTRAINT fk_module_results_module FOREIGN KEY (module_id) REFERENCES course_modules (id) ON DELETE CASCADE,
  CONSTRAINT fk_module_results_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS module_answers (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  result_id INT UNSIGNED NOT NULL,
  question_id INT UNSIGNED NOT NULL,
  option_id INT UNSIGNED NOT NULL,
  is_correct TINYINT(1) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_module_answers_result (result_id),
  KEY idx_module_answers_question (question_id),
  CONSTRAINT fk_module_answers_result FOREIGN KEY (result_id) REFERENCES module_results (id) ON DELETE CASCADE,
  CONSTRAINT fk_module_answers_question FOREIGN KEY (question_id) REFERENCES module_questions (id) ON DELETE CASCADE,
  CONSTRAINT fk_module_answers_option FOREIGN KEY (option_id) REFERENCES module_question_options (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


