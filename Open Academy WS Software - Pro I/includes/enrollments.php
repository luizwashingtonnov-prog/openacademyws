<?php
require_once __DIR__ . '/../config.php';

function is_user_student(DatabaseConnection $db, int $userId): bool
{
    if ($userId <= 0) {
        return false;
    }
    $stmt = $db->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row && ($row['role'] === 'aluno');
}

function enroll_user_in_courses(DatabaseConnection $db, int $userId, array $courseIds): int
{
    $inserted = 0;
    if (empty($courseIds)) {
        return 0;
    }
    // Validacao de perfil
    if (!is_user_student($db, $userId)) {
        return 0;
    }
    $sql = using_mysql()
        ? 'INSERT IGNORE INTO enrollments (course_id, user_id) VALUES (?, ?)'
        : 'INSERT INTO enrollments (course_id, user_id) VALUES (?, ?) ON CONFLICT (course_id, user_id) DO NOTHING';
    $stmt = $db->prepare($sql);
    foreach ($courseIds as $cid) {
        $courseId = (int) $cid;
        if ($courseId <= 0) {
            continue;
        }
        $stmt->bind_param('ii', $courseId, $userId);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $inserted++;
        }
    }
    return $inserted;
}

function enroll_users_in_course(DatabaseConnection $db, int $courseId, array $userIds): int
{
    $inserted = 0;
    if (empty($userIds)) {
        return 0;
    }
    $sql = using_mysql()
        ? 'INSERT IGNORE INTO enrollments (course_id, user_id) VALUES (?, ?)'
        : 'INSERT INTO enrollments (course_id, user_id) VALUES (?, ?) ON CONFLICT (course_id, user_id) DO NOTHING';
    $stmt = $db->prepare($sql);
    foreach ($userIds as $uid) {
        $userId = (int) $uid;
        if ($userId <= 0) {
            continue;
        }
        // Validacao de perfil por usuario
        if (!is_user_student($db, $userId)) {
            continue;
        }
        $stmt->bind_param('ii', $courseId, $userId);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $inserted++;
        }
    }
    return $inserted;
}

function unenroll_user_from_course(DatabaseConnection $db, int $userId, int $courseId): void
{
    // Limpa resultados de avaliacao vinculados ao par user/curso
    $stmtResult = $db->prepare('SELECT id FROM assessment_results WHERE course_id = ? AND user_id = ? LIMIT 1');
    $stmtResult->bind_param('ii', $courseId, $userId);
    $stmtResult->execute();
    if ($row = $stmtResult->get_result()->fetch_assoc()) {
        $resultId = (int) $row['id'];
        $stmtDeleteAnswers = $db->prepare('DELETE FROM assessment_answers WHERE result_id = ?');
        $stmtDeleteAnswers->bind_param('i', $resultId);
        $stmtDeleteAnswers->execute();

        $stmtDeleteResult = $db->prepare('DELETE FROM assessment_results WHERE id = ?');
        $stmtDeleteResult->bind_param('i', $resultId);
        $stmtDeleteResult->execute();
    }
    // Remove matricula
    $stmtUnenroll = $db->prepare('DELETE FROM enrollments WHERE course_id = ? AND user_id = ?');
    $stmtUnenroll->bind_param('ii', $courseId, $userId);
    $stmtUnenroll->execute();
}
