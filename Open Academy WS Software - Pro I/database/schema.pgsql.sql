-- Estrutura adaptada para PostgreSQL

CREATE TABLE IF NOT EXISTS users (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(120) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(16) NOT NULL DEFAULT 'aluno' CHECK (role IN ('admin', 'gestor', 'aluno')),
    created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS courses (
    id BIGSERIAL PRIMARY KEY,
    title VARCHAR(160) NOT NULL,
    description TEXT NOT NULL,
    video_url VARCHAR(255),
    pdf_url VARCHAR(255),
    workload INTEGER NOT NULL,
    deadline DATE,
    certificate_validity_months INTEGER,
    completion_type VARCHAR(32) NOT NULL DEFAULT 'assessment',
    created_by BIGINT REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_courses_author ON courses(created_by);

CREATE TABLE IF NOT EXISTS course_modules (
    id BIGSERIAL PRIMARY KEY,
    course_id BIGINT NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
    title VARCHAR(160) NOT NULL,
    description TEXT,
    video_url VARCHAR(255),
    pdf_url VARCHAR(255),
    position INTEGER NOT NULL DEFAULT 1,
    created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_modules_course ON course_modules(course_id);
CREATE INDEX IF NOT EXISTS idx_modules_order ON course_modules(course_id, position);

CREATE TABLE IF NOT EXISTS enrollments (
    course_id BIGINT NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (course_id, user_id)
);
CREATE INDEX IF NOT EXISTS idx_enrollments_user ON enrollments(user_id);

CREATE TABLE IF NOT EXISTS course_questions (
    id BIGSERIAL PRIMARY KEY,
    course_id BIGINT NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
    prompt TEXT NOT NULL,
    created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_course_questions_course ON course_questions(course_id);

CREATE TABLE IF NOT EXISTS question_options (
    id BIGSERIAL PRIMARY KEY,
    question_id BIGINT NOT NULL REFERENCES course_questions(id) ON DELETE CASCADE,
    option_text TEXT NOT NULL,
    is_correct BOOLEAN NOT NULL DEFAULT FALSE
);
CREATE INDEX IF NOT EXISTS idx_options_question ON question_options(question_id);

CREATE TABLE IF NOT EXISTS module_questions (
    id BIGSERIAL PRIMARY KEY,
    module_id BIGINT NOT NULL REFERENCES course_modules(id) ON DELETE CASCADE,
    prompt TEXT NOT NULL,
    created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_module_questions_module ON module_questions(module_id);

CREATE TABLE IF NOT EXISTS module_question_options (
    id BIGSERIAL PRIMARY KEY,
    question_id BIGINT NOT NULL REFERENCES module_questions(id) ON DELETE CASCADE,
    option_text TEXT NOT NULL,
    is_correct BOOLEAN NOT NULL DEFAULT FALSE
);
CREATE INDEX IF NOT EXISTS idx_module_options_question ON module_question_options(question_id);

CREATE TABLE IF NOT EXISTS module_topics (
    id BIGSERIAL PRIMARY KEY,
    module_id BIGINT NOT NULL REFERENCES course_modules(id) ON DELETE CASCADE,
    title VARCHAR(160) NOT NULL,
    description TEXT,
    video_url VARCHAR(255),
    pdf_url VARCHAR(255),
    position INTEGER NOT NULL DEFAULT 1,
    created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_topics_module ON module_topics(module_id);
CREATE INDEX IF NOT EXISTS idx_topics_order ON module_topics(module_id, position);

CREATE TABLE IF NOT EXISTS module_topic_progress (
    id BIGSERIAL PRIMARY KEY,
    module_topic_id BIGINT NOT NULL REFERENCES module_topics(id) ON DELETE CASCADE,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    completed_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (module_topic_id, user_id)
);
CREATE INDEX IF NOT EXISTS idx_progress_user ON module_topic_progress(user_id);

CREATE TABLE IF NOT EXISTS module_results (
    id BIGSERIAL PRIMARY KEY,
    module_id BIGINT NOT NULL REFERENCES course_modules(id) ON DELETE CASCADE,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    score NUMERIC(4,1) NOT NULL,
    approved BOOLEAN NOT NULL DEFAULT FALSE,
    attempts INTEGER NOT NULL DEFAULT 1,
    created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (module_id, user_id)
);
CREATE INDEX IF NOT EXISTS idx_module_results_user ON module_results(user_id);

CREATE TABLE IF NOT EXISTS module_answers (
    id BIGSERIAL PRIMARY KEY,
    result_id BIGINT NOT NULL REFERENCES module_results(id) ON DELETE CASCADE,
    question_id BIGINT NOT NULL REFERENCES module_questions(id) ON DELETE CASCADE,
    option_id BIGINT NOT NULL REFERENCES module_question_options(id) ON DELETE CASCADE,
    is_correct BOOLEAN NOT NULL,
    created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_module_answers_result ON module_answers(result_id);
CREATE INDEX IF NOT EXISTS idx_module_answers_question ON module_answers(question_id);

CREATE TABLE IF NOT EXISTS assessment_results (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    course_id BIGINT NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
    score NUMERIC(4,1) NOT NULL,
    approved BOOLEAN NOT NULL DEFAULT FALSE,
    certificate_code VARCHAR(32),
    issued_at TIMESTAMP WITHOUT TIME ZONE,
    created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id, course_id)
);
CREATE INDEX IF NOT EXISTS idx_results_course ON assessment_results(course_id);

CREATE TABLE IF NOT EXISTS assessment_answers (
    id BIGSERIAL PRIMARY KEY,
    result_id BIGINT NOT NULL REFERENCES assessment_results(id) ON DELETE CASCADE,
    question_id BIGINT NOT NULL REFERENCES course_questions(id) ON DELETE CASCADE,
    option_id BIGINT NOT NULL REFERENCES question_options(id) ON DELETE CASCADE,
    is_correct BOOLEAN NOT NULL,
    created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_answers_result ON assessment_answers(result_id);
CREATE INDEX IF NOT EXISTS idx_answers_question ON assessment_answers(question_id);
