<?php
require_once __DIR__ . '/../includes/auth.php';

require_roles(['admin', 'gestor']);

$db = get_db();

$resultModules = $db->query('SELECT m.id, m.title, m.position, c.id AS course_id, c.title AS course_title FROM course_modules m INNER JOIN courses c ON c.id = m.course_id ORDER BY c.title, m.position, m.id');
$modules = $resultModules ? $resultModules->fetch_all(MYSQLI_ASSOC) : [];

if (empty($modules)) {
    flash('Cadastre um modulo antes de gerenciar o questionario.', 'info');
    redirect('courses.php');
}

$moduleId = (int) ($_GET['module_id'] ?? $modules[0]['id']);
$moduleLookup = [];
foreach ($modules as $module) {
    $moduleLookup[(int) $module['id']] = $module;
}
if (!isset($moduleLookup[$moduleId])) {
    $moduleId = (int) $modules[0]['id'];
}

$currentModule = $moduleLookup[$moduleId];
$courseId = (int) $currentModule['course_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $prompt = trim($_POST['prompt'] ?? '');
        $optionsInput = $_POST['options'] ?? [];
        $correct = $_POST['correct_option'] ?? '';

        $cleanOptions = [];
        foreach ($optionsInput as $index => $text) {
            $text = trim($text);
            if ($text !== '') {
                $cleanOptions[$index] = $text;
            }
        }

        $stmtCount = $db->prepare('SELECT COUNT(*) AS total FROM module_questions WHERE module_id = ?');
        $stmtCount->bind_param('i', $moduleId);
        $stmtCount->execute();
        $questionTotal = (int) ($stmtCount->get_result()->fetch_assoc()['total'] ?? 0);

        if ($questionTotal >= MODULE_QUESTION_COUNT) {
            flash('O modulo ja possui o limite de ' . MODULE_QUESTION_COUNT . ' questoes.', 'warning');
        } elseif ($prompt === '' || count($cleanOptions) < 2) {
            flash('Informe o enunciado e pelo menos duas alternativas.', 'warning');
        } elseif (!array_key_exists($correct, $optionsInput) || trim($optionsInput[$correct]) === '') {
            flash('Selecione qual alternativa esta correta.', 'warning');
        } else {
            if (using_pgsql()) {
                $stmtQuestion = $db->prepare('INSERT INTO module_questions (module_id, prompt) VALUES (?, ?) RETURNING id');
            } else {
                $stmtQuestion = $db->prepare('INSERT INTO module_questions (module_id, prompt) VALUES (?, ?)');
            }
            $stmtQuestion->bind_param('is', $moduleId, $prompt);
            if ($stmtQuestion->execute()) {
                $questionId = $stmtQuestion->insert_id;
                $stmtOption = $db->prepare('INSERT INTO module_question_options (question_id, option_text, is_correct) VALUES (?, ?, ?)');
                foreach ($optionsInput as $index => $text) {
                    $text = trim($text);
                    if ($text === '') {
                        continue;
                    }
                    $isCorrect = ($index === (int) $correct) ? 1 : 0;
                    $stmtOption->bind_param('isi', $questionId, $text, $isCorrect);
                    $stmtOption->execute();
                }
                flash('Questao adicionada ao modulo.', 'success');
            } else {
                flash('Nao foi possivel adicionar a questao.', 'danger');
            }
        }
    }

    if ($action === 'delete') {
        $questionId = (int) ($_POST['question_id'] ?? 0);
        $stmtDelete = $db->prepare('DELETE FROM module_questions WHERE id = ? AND module_id = ?');
        $stmtDelete->bind_param('ii', $questionId, $moduleId);
        if ($stmtDelete->execute()) {
            flash('Questao removida.', 'success');
        } else {
            flash('Erro ao remover questao.', 'danger');
        }
    }

    redirect('module_questions.php?module_id=' . $moduleId);
}

$stmtQuestions = $db->prepare('SELECT id, prompt FROM module_questions WHERE module_id = ? ORDER BY id ASC');
$stmtQuestions->bind_param('i', $moduleId);
$stmtQuestions->execute();
$questions = $stmtQuestions->get_result()->fetch_all(MYSQLI_ASSOC);

$optionsByQuestion = [];
if (!empty($questions)) {
    $questionIds = array_column($questions, 'id');
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

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/alerts.php';
?>
<div class="mx-auto max-w-7xl space-y-8 px-4 pb-12">
    <div class="rounded-3xl border border-slate-200 bg-white/90 p-8 shadow shadow-slate-900/10">
        <h1 class="text-2xl font-bold tracking-tight text-brand-gray">Questionario do modulo</h1>
        <p class="mt-2 text-sm text-slate-500">Cadastre <?php echo MODULE_QUESTION_COUNT; ?> questoes objetivas para reforcar o aprendizado ao final de cada modulo.</p>
        <div class="mt-4 flex flex-wrap items-center gap-3 text-xs font-semibold uppercase tracking-[0.3em] text-slate-500">
            <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-100 px-4 py-2">Curso: <?php echo htmlspecialchars($currentModule['course_title']); ?></span>
            <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-100 px-4 py-2">Modulo: <?php echo htmlspecialchars($currentModule['title']); ?></span>
            <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-100 px-4 py-2">Questoes: <?php echo count($questions); ?>/<?php echo MODULE_QUESTION_COUNT; ?></span>
        </div>
    </div>

    <form class="rounded-3xl border border-slate-200 bg-white p-6 shadow shadow-slate-900/10" method="get">
        <label for="module_id" class="mb-2 block text-sm font-semibold text-slate-600">Selecione o modulo</label>
        <div class="flex flex-col gap-3 sm:flex-row">
            <select id="module_id" name="module_id" class="flex-1 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-brand-red focus:outline-none focus:ring-4 focus:ring-brand-red/20">
                <?php foreach ($modules as $module): ?>
                    <option value="<?php echo (int) $module['id']; ?>" <?php echo (int) $module['id'] === $moduleId ? 'selected' : ''; ?>><?php echo htmlspecialchars($module['course_title'] . ' — ' . $module['title']); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="inline-flex items-center justify-center rounded-2xl bg-brand-red px-5 py-3 text-sm font-semibold text-white shadow-glow transition hover:bg-brand-redDark focus:outline-none focus:ring-4 focus:ring-brand-red/30">Abrir modulo</button>
        </div>
    </form>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]">
        <div class="space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow shadow-slate-900/10">
                <h2 class="text-lg font-semibold text-brand-gray">Nova questao</h2>
                <p class="mt-1 text-sm text-slate-500">Defina o enunciado e marque qual alternativa esta correta.</p>
                <p class="mt-2 text-xs text-slate-500">Este modulo permite ate <?php echo MODULE_QUESTION_COUNT; ?> questoes. Questões adicionais nao serao aceitas.</p>
                <form method="post" class="mt-5 space-y-4" novalidate>
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="create">
                    <div>
                        <label for="prompt" class="mb-1 block text-sm font-semibold text-slate-600">Enunciado</label>
                        <textarea id="prompt" name="prompt" rows="3" required placeholder="Descreva a questao" class="block w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-brand-red focus:outline-none focus:ring-4 focus:ring-brand-red/20"></textarea>
                    </div>
                    <div class="space-y-2">
                        <span class="block text-sm font-semibold text-slate-600">Alternativas</span>
                        <?php for ($i = 0; $i < 4; $i++): ?>
                            <label class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-600 shadow-sm focus-within:border-brand-red focus-within:ring-4 focus-within:ring-brand-red/10">
                                <input class="h-4 w-4 text-brand-red focus:ring-brand-red/60" type="radio" name="correct_option" value="<?php echo $i; ?>" required>
                                <input type="text" class="flex-1 border-0 bg-transparent text-sm text-slate-700 placeholder:text-slate-400 focus:outline-none" name="options[<?php echo $i; ?>]" placeholder="Alternativa <?php echo chr(65 + $i); ?>">
                            </label>
                        <?php endfor; ?>
                        <p class="text-xs text-slate-500">Marque qual alternativa esta correta antes de salvar.</p>
                    </div>
                    <button type="submit" class="inline-flex w-full items-center justify-center rounded-2xl bg-brand-red px-5 py-3 text-sm font-semibold text-white shadow-glow transition hover:bg-brand-redDark focus:outline-none focus:ring-4 focus:ring-brand-red/30">Adicionar questao</button>
                </form>
            </div>
        </div>
        <div class="rounded-3xl border border-slate-200 bg-white shadow shadow-slate-900/10">
            <?php if (empty($questions)): ?>
                <div class="px-6 py-12 text-center text-sm text-slate-500">Nenhuma questao cadastrada para este modulo. Comece adicionando o primeiro enunciado.</div>
            <?php else: ?>
                <div class="divide-y divide-slate-200">
                    <?php foreach ($questions as $index => $question): ?>
                        <div class="border-slate-200">
                            <div class="flex items-center justify-between px-6 py-4">
                                <span class="text-xs font-semibold uppercase tracking-[0.4em] text-brand-red/70">Questao <?php echo $index + 1; ?></span>
                            </div>
                            <div class="px-6 pb-6 text-sm text-slate-600">
                                <p class="font-semibold text-slate-700"><?php echo nl2br(htmlspecialchars($question['prompt'])); ?></p>
                                <ul class="mt-4 space-y-2">
                                    <?php foreach ($optionsByQuestion[$question['id']] ?? [] as $option): ?>
                                        <li class="flex items-center justify-between rounded-2xl border border-slate-100 bg-slate-50 px-3 py-2">
                                            <span><?php echo htmlspecialchars($option['option_text']); ?></span>
                                            <?php if ((int) $option['is_correct'] === 1): ?>
                                                <span class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-emerald-700">Correta</span>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                                <form method="post" class="mt-4 inline">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="question_id" value="<?php echo (int) $question['id']; ?>">
                                    <button type="submit" data-confirm="Excluir esta questao?" class="inline-flex items-center rounded-full border border-rose-300 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-rose-600 transition hover:bg-rose-500 hover:text-white">
                                        Remover questao
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
