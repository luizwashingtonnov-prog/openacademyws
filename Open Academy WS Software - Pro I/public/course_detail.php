<?php
require_once __DIR__ . '/../includes/auth.php';

require_roles(['admin', 'gestor']);
require_once __DIR__ . '/../includes/enrollments.php';

$courseId = (int) ($_GET['course_id'] ?? 0);
if ($courseId <= 0) {
    flash('Curso nao encontrado.', 'warning');
    redirect('courses.php');
}

$db = get_db();

$stmtCourse = $db->prepare('SELECT c.id, c.title, c.description, c.video_url, c.pdf_url, c.workload, c.deadline, c.certificate_validity_months, c.completion_type, c.created_at, u.name AS author
    FROM courses c
    LEFT JOIN users u ON u.id = c.created_by
    WHERE c.id = ?');
$stmtCourse->bind_param('i', $courseId);
$stmtCourse->execute();
$course = $stmtCourse->get_result()->fetch_assoc();

if (!$course) {
    flash('Curso nao encontrado.', 'warning');
    redirect('courses.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $videoUrl = trim($_POST['video_url'] ?? '');
        $workload = (int) ($_POST['workload'] ?? 0);
        $validityRaw = trim($_POST['certificate_validity_months'] ?? '');
        $validityMonths = null;
        $validityParseError = false;
        $completionType = normalize_course_completion_type($_POST['completion_type'] ?? ($course['completion_type'] ?? COURSE_COMPLETION_ASSESSMENT));

        $currentPdf = $course['pdf_url'] ?? null;
        $newPdfPath = $currentPdf;
        $uploadedPdfPath = null;
        $pdfToDeleteAfterUpdate = null;

        $currentVideo = $course['video_url'] ?? null;
        $newVideoPath = $currentVideo;
        $uploadedVideoPath = null;
        $videoToDeleteAfterUpdate = null;

        $removePdf = isset($_POST['remove_pdf']);
        $pdfFile = $_FILES['pdf_file'] ?? null;
        $removeVideo = isset($_POST['remove_video']);
        $videoFile = $_FILES['video_file'] ?? null;

        if ($pdfFile && ($pdfFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $upload = store_uploaded_pdf($pdfFile);
            if (!$upload['success']) {
                flash($upload['message'], 'warning');
                redirect('course_detail.php?course_id=' . $courseId);
            }
            $uploadedPdfPath = $upload['path'];
            $newPdfPath = $uploadedPdfPath;
            if ($currentPdf && !is_external_url($currentPdf)) {
                $pdfToDeleteAfterUpdate = $currentPdf;
            }
        } elseif ($removePdf) {
            $newPdfPath = null;
            if ($currentPdf && !is_external_url($currentPdf)) {
                $pdfToDeleteAfterUpdate = $currentPdf;
            }
        }

        if ($videoFile && ($videoFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $videoUpload = store_uploaded_video($videoFile);
            if (!$videoUpload['success']) {
                if ($uploadedPdfPath !== null) {
                    delete_uploaded_file($uploadedPdfPath);
                }
                flash($videoUpload['message'], 'warning');
                redirect('course_detail.php?course_id=' . $courseId);
            }
            $uploadedVideoPath = $videoUpload['path'];
            $newVideoPath = $uploadedVideoPath;
            if ($currentVideo && !is_external_url($currentVideo)) {
                $videoToDeleteAfterUpdate = $currentVideo;
            }
        } elseif ($removeVideo) {
            $newVideoPath = null;
            if ($currentVideo && !is_external_url($currentVideo)) {
                $videoToDeleteAfterUpdate = $currentVideo;
            }
        }

        if ($validityRaw !== '') {
            if (!ctype_digit($validityRaw)) {
                $validityParseError = true;
            } else {
                $validityParsed = (int) $validityRaw;
                if ($validityParsed > 0) {
                    $validityMonths = $validityParsed;
                }
            }
        }

        if ($title === '' || $description === '' || $workload <= 0) {
            if ($uploadedPdfPath !== null) {
                delete_uploaded_file($uploadedPdfPath);
            }
            if ($uploadedVideoPath !== null) {
                delete_uploaded_file($uploadedVideoPath);
            }
            flash('Preencha titulo, descricao e carga horaria valida.', 'warning');
        } elseif ($validityParseError) {
            if ($uploadedPdfPath !== null) {
                delete_uploaded_file($uploadedPdfPath);
            }
            if ($uploadedVideoPath !== null) {
                delete_uploaded_file($uploadedVideoPath);
            }
            flash('Informe a validade do certificado em meses utilizando apenas numeros inteiros.', 'warning');
        } elseif ($uploadedVideoPath === null && !$removeVideo && $videoUrl !== '' && !filter_var($videoUrl, FILTER_VALIDATE_URL)) {
            if ($uploadedPdfPath !== null) {
                delete_uploaded_file($uploadedPdfPath);
            }
            if ($uploadedVideoPath !== null) {
                delete_uploaded_file($uploadedVideoPath);
            }
            flash('Informe uma URL valida para o video.', 'warning');
        } else {
            if ($uploadedVideoPath !== null) {
                $videoUrl = '';
                $newVideoPath = $uploadedVideoPath;
            } elseif ($removeVideo) {
                $videoUrl = '';
                $newVideoPath = null;
            } elseif ($videoUrl !== '') {
                if ($currentVideo && !is_external_url($currentVideo)) {
                    $videoToDeleteAfterUpdate = $currentVideo;
                }
                $newVideoPath = $videoUrl;
            }

            $stmtUpdate = $db->prepare('UPDATE courses SET title = ?, description = ?, video_url = ?, pdf_url = ?, workload = ?, certificate_validity_months = ?, completion_type = ? WHERE id = ?');
            $videoValue = $newVideoPath !== '' ? $newVideoPath : null;
            $stmtUpdate->bind_param('ssssiisi', $title, $description, $videoValue, $newPdfPath, $workload, $validityMonths, $completionType, $courseId);
            if ($stmtUpdate->execute()) {
                if ($pdfToDeleteAfterUpdate !== null) {
                    delete_uploaded_file($pdfToDeleteAfterUpdate);
                }
                if ($videoToDeleteAfterUpdate !== null) {
                    delete_uploaded_file($videoToDeleteAfterUpdate);
                }
                flash('Curso atualizado com sucesso.', 'success');
            } else {
                if ($uploadedPdfPath !== null) {
                    delete_uploaded_file($uploadedPdfPath);
                }
                if ($uploadedVideoPath !== null) {
                    delete_uploaded_file($uploadedVideoPath);
                }
                flash('Nao foi possivel atualizar o curso.', 'danger');
            }
        }

        redirect('course_detail.php?course_id=' . $courseId);
    }
    if ($action === 'module_create') {
        $moduleTitle = trim($_POST['module_title'] ?? '');
        $moduleDescription = trim($_POST['module_description'] ?? '');

        if ($moduleTitle === '') {
            flash('Informe um titulo para o modulo.', 'warning');
        } else {
            $stmtPosition = $db->prepare('SELECT COALESCE(MAX(position), 0) + 1 AS next_pos FROM course_modules WHERE course_id = ?');
            $stmtPosition->bind_param('i', $courseId);
            $stmtPosition->execute();
            $nextPos = (int) ($stmtPosition->get_result()->fetch_assoc()['next_pos'] ?? 1);

            $stmtCreateModule = $db->prepare('INSERT INTO course_modules (course_id, title, description, position) VALUES (?, ?, ?, ?)');
            $stmtCreateModule->bind_param('issi', $courseId, $moduleTitle, $moduleDescription, $nextPos);
            if ($stmtCreateModule->execute()) {
                flash('Modulo criado com sucesso.', 'success');
            } else {
                flash('Nao foi possivel criar o modulo.', 'danger');
            }
        }
    }

    if ($action === 'module_update') {
        $moduleId = (int) ($_POST['module_id'] ?? 0);
        $moduleTitle = trim($_POST['module_title'] ?? '');
        $moduleDescription = trim($_POST['module_description'] ?? '');

        if ($moduleId <= 0) {
            flash('Modulo invalido.', 'warning');
        } else {
            $stmtCheckModule = $db->prepare('SELECT id FROM course_modules WHERE id = ? AND course_id = ?');
            $stmtCheckModule->bind_param('ii', $moduleId, $courseId);
            $stmtCheckModule->execute();
            if (!$stmtCheckModule->get_result()->fetch_assoc()) {
                flash('Modulo nao encontrado.', 'warning');
            } elseif ($moduleTitle === '') {
                flash('Informe um titulo para o modulo.', 'warning');
            } else {
                $stmtUpdateModule = $db->prepare('UPDATE course_modules SET title = ?, description = ? WHERE id = ?');
                $stmtUpdateModule->bind_param('ssi', $moduleTitle, $moduleDescription, $moduleId);
                if ($stmtUpdateModule->execute()) {
                    flash('Modulo atualizado com sucesso.', 'success');
                } else {
                    flash('Nao foi possivel atualizar o modulo.', 'danger');
                }
            }
        }
    }

    if ($action === 'module_delete') {
        $moduleId = (int) ($_POST['module_id'] ?? 0);
        if ($moduleId <= 0) {
            flash('Modulo invalido.', 'warning');
        } else {
            $stmtCheckModule = $db->prepare('SELECT id, video_url, pdf_url FROM course_modules WHERE id = ? AND course_id = ?');
            $stmtCheckModule->bind_param('ii', $moduleId, $courseId);
            $stmtCheckModule->execute();
            $moduleRow = $stmtCheckModule->get_result()->fetch_assoc();
            if (!$moduleRow) {
                flash('Modulo nao encontrado.', 'warning');
            } else {
                $stmtDeleteModule = $db->prepare('DELETE FROM course_modules WHERE id = ?');
                $stmtDeleteModule->bind_param('i', $moduleId);
                if ($stmtDeleteModule->execute()) {
                    delete_uploaded_file($moduleRow['video_url'] ?? null);
                    delete_uploaded_file($moduleRow['pdf_url'] ?? null);
                    flash('Modulo removido.', 'success');
                } else {
                    flash('Erro ao remover modulo.', 'danger');
                }
            }
        }
    }

    if ($action === 'enroll') {
        $students = $_POST['students'] ?? [];
        if (empty($students)) {
            flash('Selecione pelo menos um aluno.', 'warning');
        } else {
            $added = enroll_users_in_course($db, $courseId, array_map('intval', $students));
            if ($added > 0) {
                flash('Alunos matriculados no curso.', 'success');
            } else {
                flash('Nenhuma matricula realizada (talvez ja matriculados).', 'info');
            }
        }
    }

    if ($action === 'unenroll') {
        $studentId = (int) ($_POST['user_id'] ?? 0);
        if ($studentId > 0) {
            unenroll_user_from_course($db, $studentId, $courseId);
            flash('Matricula removida.', 'success');
        }
    }

    redirect('course_detail.php?course_id=' . $courseId);
}

$stmtModules = $db->prepare('SELECT id, title, description, position FROM course_modules WHERE course_id = ? ORDER BY position, id');
$stmtModules->bind_param('i', $courseId);
$stmtModules->execute();
$modules = $stmtModules->get_result()->fetch_all(MYSQLI_ASSOC);

$moduleQuestionCounts = [];
if (!empty($modules)) {
    $moduleIds = array_column($modules, 'id');
    $placeholders = implode(',', array_fill(0, count($moduleIds), '?'));
    $types = str_repeat('i', count($moduleIds));
    $stmtModuleCounts = $db->prepare('SELECT module_id, COUNT(*) AS total FROM module_questions WHERE module_id IN (' . $placeholders . ') GROUP BY module_id');
    $stmtModuleCounts->bind_param($types, ...$moduleIds);
    $stmtModuleCounts->execute();
    $resultCounts = $stmtModuleCounts->get_result();
    while ($row = $resultCounts->fetch_assoc()) {
        $moduleQuestionCounts[(int) $row['module_id']] = (int) ($row['total'] ?? 0);
    }
}

$moduleTopicCounts = [];
if (!empty($modules)) {
    $moduleIds = array_column($modules, 'id');
    $placeholders = implode(',', array_fill(0, count($moduleIds), '?'));
    $types = str_repeat('i', count($moduleIds));
    $stmtTopicCounts = $db->prepare('SELECT module_id, COUNT(*) AS total FROM module_topics WHERE module_id IN (' . $placeholders . ') GROUP BY module_id');
    $stmtTopicCounts->bind_param($types, ...$moduleIds);
    $stmtTopicCounts->execute();
    $resultTopics = $stmtTopicCounts->get_result();
    while ($row = $resultTopics->fetch_assoc()) {
        $moduleTopicCounts[(int) $row['module_id']] = (int) ($row['total'] ?? 0);
    }
}

$stmtEnrollments = $db->prepare('SELECT u.id, u.name, u.email, ar.score, ar.approved, ar.created_at
    FROM enrollments e
    INNER JOIN users u ON u.id = e.user_id
    LEFT JOIN assessment_results ar ON ar.course_id = e.course_id AND ar.user_id = e.user_id
    WHERE e.course_id = ?
    ORDER BY u.name');
$stmtEnrollments->bind_param('i', $courseId);
$stmtEnrollments->execute();
$enrollments = $stmtEnrollments->get_result()->fetch_all(MYSQLI_ASSOC);

$stmtAvailable = $db->prepare('SELECT id, name, email FROM users WHERE role = "aluno" AND id NOT IN (
    SELECT user_id FROM enrollments WHERE course_id = ?
) ORDER BY name');
$stmtAvailable->bind_param('i', $courseId);
$stmtAvailable->execute();
$availableStudents = $stmtAvailable->get_result()->fetch_all(MYSQLI_ASSOC);

$completionModes = course_completion_modes();
$currentCompletionType = $course['completion_type'] ?? COURSE_COMPLETION_ASSESSMENT;
$isAssessmentCourse = $currentCompletionType === COURSE_COMPLETION_ASSESSMENT;
$questionCount = 0;
if ($isAssessmentCourse) {
    $stmtQuestionCount = $db->prepare('SELECT COUNT(*) AS total FROM course_questions WHERE course_id = ?');
    $stmtQuestionCount->bind_param('i', $courseId);
    $stmtQuestionCount->execute();
    $questionCount = (int) ($stmtQuestionCount->get_result()->fetch_assoc()['total'] ?? 0);
}
$courseVideoUrlRaw = $course['video_url'] ?? null;
$courseVideoLink = null;
if (!empty($courseVideoUrlRaw)) {
    $courseVideoLink = is_external_url($courseVideoUrlRaw) ? $courseVideoUrlRaw : asset_url($courseVideoUrlRaw);
}
$videoPlayer = render_video_player($courseVideoLink);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/alerts.php';
?>
<div class="mx-auto max-w-7xl space-y-8 px-4 pb-12">
    <div class="rounded-3xl border border-slate-200 bg-white/90 p-8 shadow shadow-slate-900/10">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-brand-gray"><?php echo htmlspecialchars($course['title']); ?></h1>
                <p class="mt-2 text-sm text-slate-500 leading-relaxed"><?php echo htmlspecialchars($course['description']); ?></p>
                <div class="mt-4 flex flex-wrap gap-3 text-xs font-semibold uppercase tracking-wider text-slate-500">
                    <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-100 px-4 py-2">Carga: <?php echo (int) $course['workload']; ?>h</span>
                    <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-100 px-4 py-2">Conclusão: <?php echo htmlspecialchars($completionModes[$currentCompletionType]['label'] ?? 'Avaliacao final'); ?></span>
                    <?php if ($isAssessmentCourse): ?>
                        <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-100 px-4 py-2">Questoes: <?php echo $questionCount; ?>/<?php echo QUESTION_COUNT; ?></span>
                    <?php else: ?>
                        <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-100 px-4 py-2">Sem avaliação final</span>
                    <?php endif; ?>
                    <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-100 px-4 py-2">Criado por: <?php echo htmlspecialchars($course['author'] ?? '-'); ?></span>
                </div>
            </div>
            <div class="flex flex-col gap-3">
                <?php if ($videoPlayer): ?>
                    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-black" style="aspect-ratio: 16 / 9; min-width: 280px;">
                        <?php echo $videoPlayer; ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($courseVideoLink)): ?>
                    <a href="<?php echo htmlspecialchars($courseVideoLink); ?>" target="_blank" class="inline-flex items-center justify-center rounded-2xl bg-brand-red px-4 py-3 text-xs font-semibold uppercase tracking-[0.3em] text-white shadow-glow transition hover:bg-brand-redDark focus:outline-none focus:ring-4 focus:ring-brand-red/30">
                        <?php echo $videoPlayer ? 'Abrir video em nova aba' : 'Assistir video'; ?>
                    </a>
                <?php endif; ?>
                <?php if (!empty($course['pdf_url'])): ?>
                    <a href="material.php?course_id=<?php echo (int) $course['id']; ?>" class="inline-flex items-center justify-center rounded-2xl border border-brand-red/30 px-4 py-3 text-xs font-semibold uppercase tracking-[0.3em] text-brand-red transition hover:bg-brand-red hover:text-white">Ler PDF</a>
                    <a href="<?php echo htmlspecialchars(asset_url($course['pdf_url'])); ?>" download class="inline-flex items-center justify-center rounded-2xl border border-slate-300 px-4 py-3 text-xs font-semibold uppercase tracking-[0.3em] text-slate-600 transition hover:bg-slate-700 hover:text-white">Baixar PDF</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow shadow-slate-900/10">
        <h2 class="text-lg font-semibold text-brand-gray">Editar curso</h2>
        <p class="mt-1 text-sm text-slate-500">Atualize as informações exibidas para os alunos.</p>
        <form method="post" enctype="multipart/form-data" class="mt-5 space-y-4" novalidate>
            <input type="hidden" name="action" value="update">
            <div>
                <label for="title" class="mb-1 block text-sm font-semibold text-slate-600">Titulo</label>
                <input type="text" id="title" name="title" required value="<?php echo htmlspecialchars($course['title']); ?>" class="block w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-brand-red focus:outline-none focus:ring-4 focus:ring-brand-red/20">
            </div>
            <div>
                <label for="description" class="mb-1 block text-sm font-semibold text-slate-600">Descricao</label>
                <textarea id="description" name="description" rows="4" required class="block w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-brand-red focus:outline-none focus:ring-4 focus:ring-brand-red/20"><?php echo htmlspecialchars($course['description']); ?></textarea>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label for="video_url" class="mb-1 block text-sm font-semibold text-slate-600">Video (URL)</label>
                    <input type="url" id="video_url" name="video_url" value="<?php echo htmlspecialchars($course['video_url'] ?? ''); ?>" placeholder="https://..." class="block w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-brand-red focus:outline-none focus:ring-4 focus:ring-brand-red/20">
                    <p class="mt-1 text-xs text-slate-500">Opcional. Aceita links do YouTube, Vimeo ou arquivos MP4.</p>
                    <?php if (!empty($course['video_url'])): ?>
                        <?php $currentVideoLink = is_external_url($course['video_url']) ? $course['video_url'] : asset_url($course['video_url']); ?>
                        <div class="mt-3 rounded-2xl bg-slate-100 px-4 py-3 text-xs text-slate-600">
                            <span class="font-semibold text-brand-gray">Video atual:</span>
                            <a href="<?php echo htmlspecialchars($currentVideoLink); ?>" target="_blank" class="ml-2 underline transition hover:text-brand-red">Abrir</a>
                        </div>
                        <label class="mt-2 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <input type="checkbox" name="remove_video" value="1" class="h-4 w-4 rounded border-slate-300 text-brand-red focus:ring-brand-red">
                            Remover video atual
                        </label>
                    <?php endif; ?>
                    <input type="file" id="video_file" name="video_file" accept="video/mp4,video/webm,video/ogg,video/ogv,video/x-m4v" class="mt-3 block w-full rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-brand-red focus:outline-none focus:ring-4 focus:ring-brand-red/20">
                    <p class="mt-1 text-xs text-slate-500">Enviar um novo video substitui o material atual. Tamanho maximo 200 MB.</p>
                </div>
                <div>
                    <label for="pdf_file" class="mb-1 block text-sm font-semibold text-slate-600">Material PDF</label>
                    <?php if (!empty($course['pdf_url'])): ?>
                        <div class="mb-2 rounded-2xl bg-slate-100 px-4 py-3 text-xs text-slate-600">
                            <span class="font-semibold text-brand-gray">Arquivo atual:</span>
                            <a href="<?php echo htmlspecialchars(asset_url($course['pdf_url'])); ?>" target="_blank" class="ml-2 underline transition hover:text-brand-red">Abrir</a>
                        </div>
                        <label class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <input type="checkbox" name="remove_pdf" value="1" class="h-4 w-4 rounded border-slate-300 text-brand-red focus:ring-brand-red">
                            Remover PDF atual
                        </label>
                    <?php endif; ?>
                    <input type="file" id="pdf_file" name="pdf_file" accept="application/pdf" class="mt-2 block w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-brand-red focus:outline-none focus:ring-4 focus:ring-brand-red/20">
                    <p class="mt-1 text-xs text-slate-500">Enviar um novo PDF substitui o material atual. Tamanho maximo: 10 MB.</p>
                </div>
            </div>
                <div>
                    <label for="workload" class="mb-1 block text-sm font-semibold text-slate-600">Carga horaria (h)</label>
                    <input type="number" id="workload" name="workload" min="1" required value="<?php echo (int) $course['workload']; ?>" class="block w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-brand-red focus:outline-none focus:ring-4 focus:ring-brand-red/20">
                </div>
                <div>
                    <label for="certificate_validity_months" class="mb-1 block text-sm font-semibold text-slate-600">Validade do certificado (meses)</label>
                    <input type="number" id="certificate_validity_months" name="certificate_validity_months" min="0" value="<?php echo htmlspecialchars((string) ($course['certificate_validity_months'] ?? '')); ?>" placeholder="Deixe em branco para validade indeterminada" class="block w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-brand-red focus:outline-none focus:ring-4 focus:ring-brand-red/20">
                    <p class="mt-1 text-xs text-slate-500">Informe em meses. Utilize 0 ou deixe em branco para certificados sem data de expiracao.</p>
                </div>
                <div>
                    <span class="mb-1 block text-sm font-semibold text-slate-600">Forma de conclusao</span>
                    <div class="grid gap-3 md:grid-cols-2">
                        <?php foreach ($completionModes as $modeValue => $modeData): ?>
                            <label class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600 shadow-sm focus-within:border-brand-red focus-within:ring-4 focus-within:ring-brand-red/10">
                                <input type="radio" name="completion_type" value="<?php echo htmlspecialchars($modeValue); ?>" <?php echo $modeValue === $currentCompletionType ? 'checked' : ''; ?> class="mt-1 h-4 w-4 text-brand-red focus:ring-brand-red/60">
                                <span>
                                    <span class="block font-semibold text-brand-gray"><?php echo htmlspecialchars($modeData['label']); ?></span>
                                    <span class="block text-xs text-slate-500"><?php echo htmlspecialchars($modeData['description']); ?></span>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">Defina se o curso exige avaliacao final ou apenas confirmacao manual de leitura.</p>
                </div>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <span class="text-xs text-slate-500">Ultima atualizacao: <?php echo $course['created_at'] ? date('d/m/Y', strtotime($course['created_at'])) : 'Indisponivel'; ?></span>
                <button type="submit" class="inline-flex items-center justify-center rounded-2xl bg-brand-red px-5 py-3 text-sm font-semibold text-white shadow-glow transition hover:bg-brand-redDark focus:outline-none focus:ring-4 focus:ring-brand-red/30">
                    Salvar alteracoes
                </button>
            </div>
        </form>
    </div>

    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow shadow-slate-900/10">
        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-brand-gray">Modulos do curso</h2>
                <p class="mt-1 text-sm text-slate-500">Estruture o conteudo em topicos. Cada modulo precisa de <?php echo MODULE_QUESTION_COUNT; ?> questoes para liberar o acesso aos alunos.</p>
            </div>
            <?php if ($isAssessmentCourse): ?>
                <div class="flex flex-col items-start gap-2 text-xs font-semibold uppercase tracking-[0.3em] text-slate-500 md:items-end">
                    <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-4 py-2">Avaliacao final: <?php echo $questionCount; ?>/<?php echo QUESTION_COUNT; ?> questoes</span>
                    <a href="questions.php?course_id=<?php echo (int) $course['id']; ?>" class="inline-flex items-center gap-2 rounded-full border border-brand-red/30 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-brand-red transition hover:bg-brand-red hover:text-white">Gerenciar avaliacao final</a>
                </div>
            <?php else: ?>
                <div class="flex items-center rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs font-semibold uppercase tracking-[0.3em] text-amber-700">
                    <span>Curso sem avaliacao final. Certificados liberados ao marcar como lido.</span>
                </div>
            <?php endif; ?>
        </div>
        <div class="mt-6 space-y-4">
            <?php if (empty($modules)): ?>
                <div class="rounded-2xl border border-slate-200 border-dashed bg-slate-50 px-5 py-8 text-center text-sm text-slate-500">Nenhum modulo cadastrado ainda. Utilize o formulario abaixo para criar o primeiro modulo.</div>
            <?php else: ?>
                <?php foreach ($modules as $index => $module): ?>
                    <?php
                        $moduleTotal = $moduleQuestionCounts[$module['id']] ?? 0;
                        $topicTotal = $moduleTopicCounts[$module['id']] ?? 0;
                    ?>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-5 py-5">
                        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                            <div class="flex-1 md:pr-6">
                                <form method="post" class="space-y-3" novalidate>
                                    <input type="hidden" name="action" value="module_update">
                                    <input type="hidden" name="module_id" value="<?php echo (int) $module['id']; ?>">
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500" for="module_title_<?php echo (int) $module['id']; ?>">Titulo do modulo</label>
                                        <input type="text" id="module_title_<?php echo (int) $module['id']; ?>" name="module_title" required value="<?php echo htmlspecialchars($module['title']); ?>" class="block w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-brand-red focus:outline-none focus:ring-4 focus:ring-brand-red/20">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500" for="module_description_<?php echo (int) $module['id']; ?>">Resumo ou objetivos</label>
                                        <textarea id="module_description_<?php echo (int) $module['id']; ?>" name="module_description" rows="3" class="block w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-brand-red focus:outline-none focus:ring-4 focus:ring-brand-red/20"><?php echo htmlspecialchars($module['description'] ?? ''); ?></textarea>
                                    </div>
                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <div class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                            <span class="inline-flex items-center rounded-full bg-white px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-500">Topicos: <?php echo $topicTotal; ?></span>
                                            <span>Questionario: <?php echo $moduleTotal; ?>/<?php echo MODULE_QUESTION_COUNT; ?> questoes</span>
                                        </div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <a href="module_topics.php?module_id=<?php echo (int) $module['id']; ?>" class="inline-flex items-center rounded-full border border-brand-red/30 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-brand-red transition hover:bg-brand-red hover:text-white">Gerenciar topicos</a>
                                            <a href="module_questions.php?module_id=<?php echo (int) $module['id']; ?>" class="inline-flex items-center rounded-full border border-brand-red/30 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-brand-red transition hover:bg-brand-red hover:text-white">Gerenciar questionario</a>
                                            <button type="submit" class="inline-flex items-center rounded-full bg-brand-red px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white shadow-glow transition hover:bg-brand-redDark focus:outline-none focus:ring-4 focus:ring-brand-red/30">Salvar modulo</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                            <div class="flex flex-col items-start gap-2 md:items-end">
                                <span class="inline-flex items-center rounded-full bg-slate-200 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-600">Ordem: <?php echo (int) $module['position']; ?></span>
                                <form method="post" class="mt-auto">
                                    <input type="hidden" name="action" value="module_delete">
                                    <input type="hidden" name="module_id" value="<?php echo (int) $module['id']; ?>">
                                    <button type="submit" data-confirm="Excluir este modulo e todo o conteudo associado?" class="inline-flex items-center rounded-full border border-rose-300 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-rose-600 transition hover:bg-rose-500 hover:text-white">Excluir modulo</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="mt-6 border-t border-slate-200 pt-6">
            <h3 class="text-sm font-semibold text-brand-gray">Adicionar novo modulo</h3>
            <form method="post" class="mt-3 space-y-3" novalidate>
                <input type="hidden" name="action" value="module_create">
                <div>
                    <label for="module_title_new" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Titulo</label>
                    <input type="text" id="module_title_new" name="module_title" required placeholder="Ex.: Introducao ao tema" class="block w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-brand-red focus:outline-none focus:ring-4 focus:ring-brand-red/20">
                </div>
                <div>
                    <label for="module_description_new" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Resumo</label>
                    <textarea id="module_description_new" name="module_description" rows="3" placeholder="Defina os objetivos e conteudos deste modulo" class="block w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-brand-red focus:outline-none focus:ring-4 focus:ring-brand-red/20"></textarea>
                </div>
                <button type="submit" class="inline-flex items-center rounded-full bg-brand-red px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white shadow-glow transition hover:bg-brand-redDark focus:outline-none focus:ring-4 focus:ring-brand-red/30">Adicionar modulo</button>
            </form>
        </div>
    </div>
    <div class="space-y-6">
        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow shadow-slate-900/10">
            <h2 class="text-lg font-semibold text-brand-gray">Matricular alunos</h2>
            <p class="mt-1 text-sm text-slate-500">Selecione um ou mais estudantes para ingressar neste curso.</p>
            <form method="post" class="mt-5 space-y-4" novalidate>
                <input type="hidden" name="action" value="enroll">
                <div>
                    <label for="students" class="mb-1 block text-sm font-semibold text-slate-600">Alunos disponiveis</label>
                    <select id="students" name="students[]" multiple size="6" class="block w-full min-h-[200px] rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-brand-red focus:outline-none focus:ring-4 focus:ring-brand-red/20">
                        <?php foreach ($availableStudents as $student): ?>
                            <option value="<?php echo (int) $student['id']; ?>"><?php echo htmlspecialchars($student['name']); ?> - <?php echo htmlspecialchars($student['email']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="mt-1 text-xs text-slate-500">Use CTRL ou SHIFT para selecionar varios alunos.</p>
                </div>
                <button type="submit" class="inline-flex w-full items-center justify-center rounded-2xl bg-brand-red px-5 py-3 text-sm font-semibold text-white shadow-glow transition hover:bg-brand-redDark focus:outline-none focus:ring-4 focus:ring-brand-red/30">
                    Adicionar ao curso
                </button>
            </form>
            <?php if (empty($availableStudents)): ?>
                <p class="mt-4 text-sm text-slate-500">Todos os alunos ja estao matriculados neste curso.</p>
            <?php endif; ?>
        </div>
        <?php if (!empty($courseVideoLink) || !empty($course['pdf_url'])): ?>
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow shadow-slate-900/10">
                <h2 class="text-lg font-semibold text-brand-gray">Recursos do curso</h2>
                <div class="mt-4 space-y-4">
                    <?php if ($videoPlayer): ?>
                        <div class="space-y-3 rounded-2xl border border-brand-red/30 bg-brand-red/5 p-4">
                            <h3 class="text-sm font-semibold text-brand-red">Video aula</h3>
                            <div class="overflow-hidden rounded-xl border border-brand-red/40 bg-black" style="aspect-ratio: 16 / 9;">
                                <?php echo $videoPlayer; ?>
                            </div>
                            <a href="<?php echo htmlspecialchars($courseVideoLink); ?>" target="_blank" class="inline-flex items-center gap-2 text-sm font-semibold text-brand-red underline-offset-4 hover:underline">Abrir video em nova aba</a>
                        </div>
                    <?php elseif (!empty($courseVideoLink)): ?>
                        <div class="rounded-2xl border border-brand-red/30 bg-brand-red/5 p-4">
                            <h3 class="mb-2 text-sm font-semibold text-brand-red">Video aula</h3>
                            <a href="<?php echo htmlspecialchars($courseVideoLink); ?>" target="_blank" class="inline-flex items-center gap-2 text-sm font-semibold text-brand-red underline-offset-4 hover:underline">Assistir video</a>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($course['pdf_url'])): ?>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <h3 class="mb-2 text-sm font-semibold text-slate-600">Material em PDF</h3>
                            <div class="flex flex-wrap gap-2">
                                <a href="material.php?course_id=<?php echo (int) $course['id']; ?>" class="inline-flex items-center gap-2 text-sm font-semibold text-brand-red underline-offset-4 hover:underline">Ler material</a>
                                <a href="<?php echo htmlspecialchars(asset_url($course['pdf_url'])); ?>" download class="inline-flex items-center gap-2 text-sm font-semibold text-slate-600 underline-offset-4 hover:underline">Baixar material</a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        <div class="rounded-3xl border border-slate-200 bg-white shadow shadow-slate-900/10">
            <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-6 py-5">
                <div>
                    <h2 class="text-lg font-semibold text-brand-gray">Alunos matriculados</h2>
                    <p class="text-sm text-slate-500"><?php echo count($enrollments); ?> aluno(s) acompanhando esta trilha.</p>
                </div>
            </div>
            <div class="overflow-hidden">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-brand-gray text-left text-xs font-semibold uppercase tracking-[0.25em] text-white">
                        <tr>
                            <th class="px-6 py-3">Aluno</th>
                            <th class="px-6 py-3">Email</th>
                            <th class="px-6 py-3">Avaliacao</th>
                            <th class="px-6 py-3">Nota</th>
                            <th class="px-6 py-3 text-right">Acoes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <?php foreach ($enrollments as $student): ?>
                            <tr class="transition hover:bg-slate-50">
                                <td class="px-6 py-4 font-semibold text-slate-700"><?php echo htmlspecialchars($student['name']); ?></td>
                                <td class="px-6 py-4 text-slate-500"><?php echo htmlspecialchars($student['email']); ?></td>
                                <td class="px-6 py-4 text-slate-500">
                                    <?php if ($student['created_at']): ?>
                                        <?php echo date('d/m/Y H:i', strtotime($student['created_at'])); ?>
                                    <?php else: ?>
                                        <span class="inline-flex items-center rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-amber-700">Pendente</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-slate-500">
                                    <?php if ($student['score'] !== null): ?>
                                        <span class="font-semibold text-brand-red"><?php echo number_format((float) $student['score'], 1); ?></span>
                                        <?php echo $student['approved'] ? '<span class="ml-2 inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-emerald-700">Aprovado</span>' : '<span class="ml-2 inline-flex items-center rounded-full bg-slate-200 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-600">Reprovado</span>'; ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex flex-wrap items-center justify-end gap-2">
                                        <?php if ((int) ($student['approved'] ?? 0) === 1): ?>
                                            <a href="certificate.php?course_id=<?php echo (int) $course['id']; ?>&user_id=<?php echo (int) $student['id']; ?>" target="_blank" rel="noopener" class="inline-flex items-center rounded-full border border-brand-red/30 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-brand-red transition hover:bg-brand-red hover:text-white">
                                                Ver certificado
                                            </a>
                                        <?php endif; ?>
                                        <form method="post" class="inline">
                                            <input type="hidden" name="action" value="unenroll">
                                            <input type="hidden" name="user_id" value="<?php echo (int) $student['id']; ?>">
                                            <button type="submit" data-confirm="Remover matricula deste aluno?" class="inline-flex items-center rounded-full border border-rose-300 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-rose-600 transition hover:bg-rose-500 hover:text-white">
                                                Remover
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($enrollments)): ?>
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center text-sm text-slate-500">Nenhum aluno matriculado ate o momento.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>























