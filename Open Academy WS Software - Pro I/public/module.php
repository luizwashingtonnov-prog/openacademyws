<?php
require_once __DIR__ . '/../includes/auth.php';

require_roles(['aluno']);

$db = get_db();
$user = current_user();

$moduleId = (int) ($_GET['module_id'] ?? 0);
if ($moduleId <= 0) {
    flash('Modulo invalido.', 'warning');
    redirect('my_courses.php');
}

$stmtModule = $db->prepare('SELECT m.id, m.title, m.description, m.position, c.id AS course_id, c.title AS course_title
    FROM course_modules m
    INNER JOIN courses c ON c.id = m.course_id
    WHERE m.id = ?');
$stmtModule->bind_param('i', $moduleId);
$stmtModule->execute();
$module = $stmtModule->get_result()->fetch_assoc();

if (!$module) {
    flash('Modulo nao encontrado.', 'warning');
    redirect('my_courses.php');
}

$courseId = (int) $module['course_id'];

$stmtEnrollment = $db->prepare('SELECT 1 FROM enrollments WHERE course_id = ? AND user_id = ?');
$stmtEnrollment->bind_param('ii', $courseId, $user['id']);
$stmtEnrollment->execute();
if (!$stmtEnrollment->get_result()->fetch_assoc()) {
    flash('Voce nao esta matriculado neste curso.', 'danger');
    redirect('my_courses.php');
}

$stmtTopics = $db->prepare('SELECT id, title, description, video_url, pdf_url, position FROM module_topics WHERE module_id = ? ORDER BY position, id');
$stmtTopics->bind_param('i', $moduleId);
$stmtTopics->execute();
$topics = $stmtTopics->get_result()->fetch_all(MYSQLI_ASSOC);

$topicIds = array_column($topics, 'id');
$completedTopicIds = [];
if (!empty($topicIds)) {
    $placeholders = implode(',', array_fill(0, count($topicIds), '?'));
    $types = 'i' . str_repeat('i', count($topicIds));
    $params = array_merge([(int) $user['id']], array_map('intval', $topicIds));
    $stmtProgress = $db->prepare('SELECT module_topic_id FROM module_topic_progress WHERE user_id = ? AND module_topic_id IN (' . $placeholders . ')');
    $stmtProgress->bind_param($types, ...$params);
    $stmtProgress->execute();
    $progressRows = $stmtProgress->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($progressRows as $row) {
        $completedTopicIds[] = (int) $row['module_topic_id'];
    }
}
$completedTopicIds = array_unique($completedTopicIds);
$allTopicsCompleted = empty($topics) || count($completedTopicIds) === count($topics);

$stmtQuestions = $db->prepare('SELECT id, prompt FROM module_questions WHERE module_id = ? ORDER BY id');
$stmtQuestions->bind_param('i', $moduleId);
$stmtQuestions->execute();
$questions = $stmtQuestions->get_result()->fetch_all(MYSQLI_ASSOC);

$questionIds = array_column($questions, 'id');
$optionsByQuestion = [];
if (!empty($questionIds)) {
    $placeholders = implode(',', array_fill(0, count($questionIds), '?'));
    $types = str_repeat('i', count($questionIds));
    $stmtOptions = $db->prepare('SELECT id, question_id, option_text, is_correct FROM module_question_options WHERE question_id IN (' . $placeholders . ') ORDER BY id');
    $stmtOptions->bind_param($types, ...$questionIds);
    $stmtOptions->execute();
    $resultOptions = $stmtOptions->get_result();
    while ($row = $resultOptions->fetch_assoc()) {
        $optionsByQuestion[$row['question_id']][] = $row;
    }
}

$stmtResult = $db->prepare('SELECT id, score, approved, attempts, updated_at FROM module_results WHERE module_id = ? AND user_id = ?');
$stmtResult->bind_param('ii', $moduleId, $user['id']);
$stmtResult->execute();
$existingResult = $stmtResult->get_result()->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();
    $action = $_POST['action'] ?? '';

    if ($action === 'complete_topic') {
        $topicId = (int) ($_POST['topic_id'] ?? 0);
        if ($topicId <= 0 || !in_array($topicId, $topicIds, true)) {
            flash('Topico invalido.', 'warning');
        } else {
            if (using_pgsql()) {
                $stmtProgressInsert = $db->prepare('INSERT INTO module_topic_progress (module_topic_id, user_id) VALUES (?, ?)
                ON CONFLICT (module_topic_id, user_id) DO UPDATE SET completed_at = CURRENT_TIMESTAMP');
            } else {
                $stmtProgressInsert = $db->prepare('INSERT INTO module_topic_progress (module_topic_id, user_id) VALUES (?, ?)
                ON DUPLICATE KEY UPDATE completed_at = CURRENT_TIMESTAMP');
            }
            $stmtProgressInsert->bind_param('ii', $topicId, $user['id']);
            $stmtProgressInsert->execute();
            flash('Topico marcado como concluido.', 'success');
        }
        redirect('module.php?module_id=' . $moduleId);
    }

    if ($action === 'submit_answers') {
        if (!$allTopicsCompleted) {
            flash('Conclua todos os topicos antes de responder a avaliacao do modulo.', 'warning');
            redirect('module.php?module_id=' . $moduleId);
        }

        if (count($questions) !== MODULE_QUESTION_COUNT) {
            flash('Questoes do modulo ainda nao foram configuradas.', 'warning');
            redirect('module.php?module_id=' . $moduleId);
        }

        foreach ($questions as $question) {
            if (empty($optionsByQuestion[$question['id']])) {
                flash('Questoes incompletas. Aguarde o gestor configurar o modulo.', 'warning');
                redirect('module.php?module_id=' . $moduleId);
            }
        }

        $answers = $_POST['answers'] ?? [];
        $correctCount = 0;

        foreach ($questions as $question) {
            $questionId = (int) $question['id'];
            $selectedOptionId = (int) ($answers[$questionId] ?? 0);
            if ($selectedOptionId <= 0) {
                continue;
            }
            $optionMap = [];
            foreach ($optionsByQuestion[$questionId] as $opt) {
                $optionMap[(int) $opt['id']] = (int) $opt['is_correct'];
            }
            if (($optionMap[$selectedOptionId] ?? 0) === 1) {
                $correctCount++;
            }
        }

        $score = round(($correctCount / MODULE_QUESTION_COUNT) * 10, 1);
        $approved = $score >= PASSING_GRADE ? 1 : 0;

        if (using_pgsql()) {
            $stmtUpsert = $db->prepare('INSERT INTO module_results (module_id, user_id, score, approved)
            VALUES (?, ?, ?, ?)
            ON CONFLICT (module_id, user_id)
            DO UPDATE SET score = EXCLUDED.score, approved = EXCLUDED.approved, attempts = module_results.attempts + 1, updated_at = CURRENT_TIMESTAMP
            RETURNING id');
        } else {
            $stmtUpsert = $db->prepare('INSERT INTO module_results (module_id, user_id, score, approved)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE score = VALUES(score), approved = VALUES(approved), attempts = attempts + 1, updated_at = CURRENT_TIMESTAMP, id = LAST_INSERT_ID(id)');
        }
        $stmtUpsert->bind_param('iidi', $moduleId, $user['id'], $score, $approved);
        $stmtUpsert->execute();
        $resultId = $stmtUpsert->insert_id;
        if ($resultId === 0 && $existingResult) {
            $resultId = (int) $existingResult['id'];
        }

        $stmtDeleteAnswers = $db->prepare('DELETE FROM module_answers WHERE result_id = ?');
        $stmtDeleteAnswers->bind_param('i', $resultId);
        $stmtDeleteAnswers->execute();

        $stmtInsertAnswer = $db->prepare('INSERT INTO module_answers (result_id, question_id, option_id, is_correct) VALUES (?, ?, ?, ?)');
        foreach ($questions as $question) {
            $questionId = (int) $question['id'];
            $selectedOptionId = (int) ($answers[$questionId] ?? 0);
            if ($selectedOptionId <= 0) {
                continue;
            }
            $isCorrect = 0;
            foreach ($optionsByQuestion[$questionId] as $option) {
                if ((int) $option['id'] === $selectedOptionId) {
                    $isCorrect = (int) $option['is_correct'];
                    break;
                }
            }
            $stmtInsertAnswer->bind_param('iiii', $resultId, $questionId, $selectedOptionId, $isCorrect);
            $stmtInsertAnswer->execute();
        }

        if ($approved) {
            flash('Parabens! Voce concluiu o modulo com nota ' . number_format($score, 1) . '.', 'success');
        } else {
            flash('Voce obteve nota ' . number_format($score, 1) . '. Revise o conteudo e tente novamente.', 'warning');
        }

        redirect('module.php?module_id=' . $moduleId);
    }
}

$stmtResult->close();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/alerts.php';
?>
<div class="mx-auto max-w-7xl space-y-8 px-4 pb-12">
    <div class="rounded-3xl border border-slate-200 bg-white/90 p-8 shadow shadow-slate-900/10">
        <span class="inline-flex items-center gap-2 rounded-full bg-brand-red/10 px-4 py-1 text-xs font-semibold uppercase tracking-[0.3em] text-brand-red">Modulo do curso</span>
        <h1 class="mt-4 text-2xl font-bold tracking-tight text-brand-gray"><?php echo htmlspecialchars($module['title']); ?></h1>
        <p class="mt-2 text-sm text-slate-500">Curso: <?php echo htmlspecialchars($module['course_title']); ?> — Ordem: <?php echo (int) ($module['position'] ?? 0); ?></p>
        <?php if (!empty($topics)): ?>
            <p class="mt-1 text-xs font-semibold uppercase tracking-wide text-slate-500">Topicos concluidos: <?php echo count($completedTopicIds); ?>/<?php echo count($topics); ?></p>
        <?php endif; ?>
        <?php if (!empty($module['description'])): ?>
            <div class="mt-4 rounded-2xl border border-slate-200 bg-white/80 p-4 text-sm text-slate-600 shadow">
                <?php echo nl2br(htmlspecialchars($module['description'])); ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]">
        <div class="space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow shadow-slate-900/10">
                <h2 class="text-lg font-semibold text-brand-gray">Resumo do modulo</h2>
                <p class="mt-2 text-sm text-slate-500">Responda todas as <?php echo MODULE_QUESTION_COUNT; ?> questoes para registrar o seu progresso.</p>
                <?php if ($existingResult): ?>
                    <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                        <p><span class="font-semibold text-brand-gray">Ultima nota:</span> <?php echo number_format((float) $existingResult['score'], 1); ?></p>
                        <p><span class="font-semibold text-brand-gray">Status:</span> <?php echo ((int) $existingResult['approved'] === 1) ? 'Aprovado' : 'Nao aprovado'; ?></p>
                        <p><span class="font-semibold text-brand-gray">Tentativas:</span> <?php echo (int) ($existingResult['attempts'] ?? 1); ?></p>
                    </div>
                <?php else: ?>
                    <div class="mt-4 rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">Voce ainda nao respondeu o questionario deste modulo.</div>
                <?php endif; ?>
                <div class="mt-5">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-brand-gray">Topicos do modulo</h2>
                    <?php if (empty($topics)): ?>
                        <div class="mt-3 rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">Nenhum topico cadastrado para este modulo ainda.</div>
                    <?php else: ?>
                        <div class="mt-3 space-y-3">
                            <?php foreach ($topics as $topic): ?>
                                <?php
                                    $topicId = (int) $topic['id'];
                                    $completed = in_array($topicId, $completedTopicIds, true);
                                    $videoRaw = $topic['video_url'] ?? '';
                                    $videoLink = '';
                                    $videoPlayer = null;
                                    if ($videoRaw !== '') {
                                        $videoLink = is_external_url($videoRaw) ? $videoRaw : asset_url($videoRaw);
                                        $videoPlayer = render_video_player($videoLink);
                                    }
                                    $pdfRaw = $topic['pdf_url'] ?? '';
                                    $pdfLink = $pdfRaw !== '' ? asset_url($pdfRaw) : '';
                                ?>
                                <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm">
                                    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                        <div class="md:flex-1 md:pr-6">
                                            <div class="flex flex-wrap items-center gap-2 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-1">Topico <?php echo (int) ($topic['position'] ?? 0); ?></span>
                                                <span class="inline-flex items-center rounded-full px-2 py-1 <?php echo $completed ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'; ?>">
                                                    <?php echo $completed ? 'Concluido' : 'Pendente'; ?>
                                                </span>
                                            </div>
                                            <p class="mt-2 text-sm font-semibold text-brand-gray"><?php echo htmlspecialchars($topic['title']); ?></p>
                                            <?php if (!empty($topic['description'])): ?>
                                                <p class="mt-1 text-sm text-slate-600 leading-relaxed"><?php echo nl2br(htmlspecialchars($topic['description'])); ?></p>
                                            <?php endif; ?>
                                            <?php if ($videoPlayer): ?>
                                                <div class="mt-3 overflow-hidden rounded-2xl border border-slate-200 bg-black" style="aspect-ratio: 16 / 9;">
                                                    <?php echo $videoPlayer; ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="mt-3 flex flex-wrap gap-2">
                                                <?php if ($videoLink): ?>
                                                    <a href="<?php echo htmlspecialchars($videoLink); ?>" target="_blank" class="inline-flex items-center rounded-full border border-brand-red/30 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-brand-red transition hover:bg-brand-red hover:text-white">
                                                        <?php echo $videoPlayer ? 'Abrir video em nova aba' : 'Assistir video'; ?>
                                                    </a>
                                                <?php endif; ?>
                                                <?php if ($pdfLink): ?>
                                                    <a href="<?php echo htmlspecialchars($pdfLink); ?>" target="_blank" class="inline-flex items-center rounded-full border border-brand-red/30 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-brand-red transition hover:bg-brand-red hover:text-white">Ler material</a>
                                                    <a href="<?php echo htmlspecialchars($pdfLink); ?>" download class="inline-flex items-center rounded-full border border-slate-300 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-600 transition hover:bg-slate-700 hover:text-white">Baixar PDF</a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="flex w-full flex-col items-stretch gap-2 md:w-auto md:items-end">
                                            <?php if ($completed): ?>
                                                <span class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-emerald-700">Topico concluido</span>
                                            <?php else: ?>
                                                <form method="post" class="inline">
                                                    <?php echo csrf_field(); ?>
                                                    <input type="hidden" name="action" value="complete_topic">
                                                    <input type="hidden" name="topic_id" value="<?php echo $topicId; ?>">
                                                    <button type="submit" class="inline-flex items-center rounded-full border border-brand-red/30 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-brand-red transition hover:bg-brand-red hover:text-white">
                                                        Marcar como concluido
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="mt-4 flex flex-wrap gap-2">
                    <a href="my_courses.php" class="inline-flex items-center rounded-full border border-slate-300 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-slate-600 transition hover:bg-slate-700 hover:text-white">Voltar para meus cursos</a>
                    <a href="course_detail.php?course_id=<?php echo $courseId; ?>" class="inline-flex items-center rounded-full border border-brand-red/30 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-brand-red transition hover:bg-brand-red hover:text-white">Ver detalhes do curso</a>
                </div>
            </div>
        </div>
        <div class="rounded-3xl border border-slate-200 bg-white shadow shadow-slate-900/10">
            <?php if (count($questions) !== MODULE_QUESTION_COUNT): ?>
                <div class="px-6 py-12 text-center text-sm text-slate-500">O gestor ainda nao configurou todas as questoes deste modulo. Tente novamente mais tarde.</div>
            <?php elseif (!$allTopicsCompleted): ?>
                <div class="px-6 py-12 text-center text-sm text-slate-500">
                    Conclua todos os topicos do modulo antes de liberar a avaliacao.<br>
                    Progresso: <?php echo count($completedTopicIds); ?>/<?php echo count($topics); ?> topicos finalizados.
                </div>
            <?php else: ?>
                <form method="post" class="space-y-6 px-6 py-6">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="submit_answers">
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
                    <button type="submit" class="inline-flex w-full items-center justify-center rounded-2xl bg-brand-red px-5 py-3 text-sm font-semibold text-white shadow-glow transition hover:bg-brand-redDark focus:outline-none focus:ring-4 focus:ring-brand-red/30">Enviar respostas</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>



