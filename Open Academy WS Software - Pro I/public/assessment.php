<?php
require_once __DIR__ . '/../includes/auth.php';

require_roles(['aluno']);

$db = get_db();
$user = current_user();
$courseId = (int) ($_GET['course_id'] ?? 0);

if ($courseId <= 0) {
    flash('Curso invalido.', 'warning');
    redirect('my_courses.php');
}

$stmtCourse = $db->prepare('SELECT id, title, completion_type FROM courses WHERE id = ?');
$stmtCourse->bind_param('i', $courseId);
$stmtCourse->execute();
$course = $stmtCourse->get_result()->fetch_assoc();
if (!$course) {
    flash('Curso nao encontrado.', 'warning');
    redirect('my_courses.php');
}

if (($course['completion_type'] ?? COURSE_COMPLETION_ASSESSMENT) !== COURSE_COMPLETION_ASSESSMENT) {
    flash('Este curso nao exige avaliacao final.', 'info');
    redirect('my_courses.php');
}

$stmtEnrollment = $db->prepare('SELECT 1 FROM enrollments WHERE course_id = ? AND user_id = ?');
$stmtEnrollment->bind_param('ii', $courseId, $user['id']);
$stmtEnrollment->execute();
if (!$stmtEnrollment->get_result()->fetch_assoc()) {
    flash('Voce nao esta matriculado neste curso.', 'danger');
    redirect('my_courses.php');
}

$stmtModules = $db->prepare('SELECT id FROM course_modules WHERE course_id = ? ORDER BY position, id');
$stmtModules->bind_param('i', $courseId);
$stmtModules->execute();
$modules = $stmtModules->get_result()->fetch_all(MYSQLI_ASSOC);

if (!empty($modules)) {
    $stmtModuleResult = $db->prepare('SELECT approved FROM module_results WHERE module_id = ? AND user_id = ?');
    $pendingModuleId = null;
    foreach ($modules as $moduleRow) {
        $moduleCheckId = (int) $moduleRow['id'];
        $stmtModuleResult->bind_param('ii', $moduleCheckId, $user['id']);
        $stmtModuleResult->execute();
        $moduleResult = $stmtModuleResult->get_result()->fetch_assoc();
        if (!$moduleResult || (int) ($moduleResult['approved'] ?? 0) !== 1) {
            $pendingModuleId = $moduleCheckId;
            break;
        }
    }
    if ($pendingModuleId !== null) {
        flash('Conclua os questionarios de todos os modulos antes da avaliação final.', 'warning');
        redirect('module.php?module_id=' . $pendingModuleId);
    }
}

$stmtAssessmentResult = $db->prepare('SELECT approved FROM assessment_results WHERE course_id = ? AND user_id = ? LIMIT 1');
$stmtAssessmentResult->bind_param('ii', $courseId, $user['id']);
$stmtAssessmentResult->execute();
$existingAssessment = $stmtAssessmentResult->get_result()->fetch_assoc();
if ($existingAssessment && (int) ($existingAssessment['approved'] ?? 0) === 1) {
    flash('Voce ja foi aprovado neste curso. A avaliacao final esta bloqueada.', 'info');
    redirect('my_courses.php');
}

$stmtQuestions = $db->prepare('SELECT id, prompt FROM course_questions WHERE course_id = ? ORDER BY id');
$stmtQuestions->bind_param('i', $courseId);
$stmtQuestions->execute();
$questions = $stmtQuestions->get_result()->fetch_all(MYSQLI_ASSOC);

if (count($questions) < QUESTION_COUNT) {
    flash('A avaliacao ainda nao esta disponivel. Aguarde o gestor concluir as questoes.', 'info');
    redirect('my_courses.php');
}

$questionIds = array_column($questions, 'id');
$placeholders = implode(',', array_fill(0, count($questionIds), '?'));
$types = str_repeat('i', count($questionIds));

$stmtOptions = $db->prepare('SELECT id, question_id, option_text, is_correct FROM question_options WHERE question_id IN (' . $placeholders . ') ORDER BY id');
$stmtOptions->bind_param($types, ...$questionIds);
$stmtOptions->execute();
$optionsByQuestion = [];
$optionsById = [];
$resultOptions = $stmtOptions->get_result();
while ($option = $resultOptions->fetch_assoc()) {
    $optionsByQuestion[$option['question_id']][] = $option;
    $optionsById[$option['id']] = $option;
}

foreach ($questions as $question) {
    if (empty($optionsByQuestion[$question['id']])) {
        flash('A avaliação esta incompleta. Entre em contato com o gestor.', 'warning');
        redirect('my_courses.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();
    $answers = $_POST['answers'] ?? [];
    $correctCount = 0;

    foreach ($questions as $question) {
        $questionId = (int) $question['id'];
        $selectedOptionId = (int) ($answers[$questionId] ?? 0);
        if ($selectedOptionId <= 0) {
            continue;
        }
        $option = $optionsById[$selectedOptionId] ?? null;
        if ($option && (int) $option['is_correct'] === 1) {
            $correctCount++;
        }
    }

    $score = round(($correctCount / QUESTION_COUNT) * 10, 1);
    $approved = $score >= PASSING_GRADE ? 1 : 0;

    if (using_pgsql()) {
        $stmtResult = $db->prepare('INSERT INTO assessment_results (user_id, course_id, score, approved)
        VALUES (?, ?, ?, ?)
        ON CONFLICT (user_id, course_id)
        DO UPDATE SET score = EXCLUDED.score, approved = EXCLUDED.approved, created_at = CURRENT_TIMESTAMP
        RETURNING id');
    } else {
        $stmtResult = $db->prepare('INSERT INTO assessment_results (user_id, course_id, score, approved)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE score = VALUES(score), approved = VALUES(approved), created_at = CURRENT_TIMESTAMP, id = LAST_INSERT_ID(id)');
    }
    $stmtResult->bind_param('iidi', $user['id'], $courseId, $score, $approved);
    $stmtResult->execute();
    $resultId = $stmtResult->insert_id;

    $stmtDeleteAnswers = $db->prepare('DELETE FROM assessment_answers WHERE result_id = ?');
    $stmtDeleteAnswers->bind_param('i', $resultId);
    $stmtDeleteAnswers->execute();

    $stmtInsertAnswer = $db->prepare('INSERT INTO assessment_answers (result_id, question_id, option_id, is_correct) VALUES (?, ?, ?, ?)');
    foreach ($questions as $question) {
        $questionId = (int) $question['id'];
        $selectedOptionId = (int) ($answers[$questionId] ?? 0);
        if ($selectedOptionId <= 0) {
            continue;
        }
        $isCorrect = isset($optionsById[$selectedOptionId]) ? (int) $optionsById[$selectedOptionId]['is_correct'] : 0;
        $stmtInsertAnswer->bind_param('iiii', $resultId, $questionId, $selectedOptionId, $isCorrect);
        $stmtInsertAnswer->execute();
    }

    if ($approved) {
        flash('Parabens! Voce foi aprovado com nota ' . number_format($score, 1) . '.', 'success');
    } else {
        flash('Voce obteve nota ' . number_format($score, 1) . '. Continue estudando e tente novamente.', 'warning');
    }

    redirect('my_courses.php');
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/alerts.php';
?>
<div class="mx-auto max-w-7xl space-y-8 px-4 pb-12">
    <div class="rounded-3xl border border-slate-200 bg-white/90 p-8 shadow shadow-slate-900/10">
        <h1 class="text-2xl font-bold tracking-tight text-brand-gray">Avaliação do curso: <?php echo htmlspecialchars($course['title']); ?></h1>
        <p class="mt-2 text-sm text-slate-500">Responda as <?php echo QUESTION_COUNT; ?> questoes abaixo. Voce precisa atingir nota minima <?php echo number_format(PASSING_GRADE, 1); ?> para ser aprovado.</p>
    </div>

    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow shadow-slate-900/10">
        <form method="post" class="space-y-6">
            <?php echo csrf_field(); ?>
            <?php foreach ($questions as $index => $question): ?>
                <div class="rounded-2xl border border-slate-200 bg-slate-50/60 p-5">
                    <span class="text-xs font-semibold uppercase tracking-[0.4em] text-brand-red/70">Questao <?php echo $index + 1; ?></span>
                    <p class="mt-3 text-sm font-semibold text-brand-gray leading-relaxed"><?php echo nl2br(htmlspecialchars($question['prompt'])); ?></p>
                    <div class="mt-4 space-y-2">
                        <?php foreach ($optionsByQuestion[$question['id']] ?? [] as $option): ?>
                            <label class="flex items-center gap-3 rounded-2xl border border-transparent bg-white px-4 py-2 text-sm text-slate-600 shadow-sm transition focus-within:border-brand-red focus-within:ring-4 focus-within:ring-brand-red/15 hover:border-brand-red/30">
                                <input class="h-4 w-4 text-brand-red focus:ring-brand-red/60" type="radio" name="answers[<?php echo (int) $question['id']; ?>]" value="<?php echo (int) $option['id']; ?>" required>
                                <span><?php echo htmlspecialchars($option['option_text']); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <div class="pt-2">
                <button type="submit" class="inline-flex w-full items-center justify-center rounded-2xl bg-brand-red px-5 py-3 text-sm font-semibold text-white shadow-glow transition hover:bg-brand-redDark focus:outline-none focus:ring-4 focus:ring-brand-red/30">
                    Enviar avaliacao
                </button>
            </div>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
