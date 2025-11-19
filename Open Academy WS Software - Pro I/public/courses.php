<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/enrollments.php';
require_roles(['admin', 'gestor']);

$pageTitle = 'Cursos';
$db = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $videoUrl = trim($_POST['video_url'] ?? '');
        $workload = (int) ($_POST['workload'] ?? 0);
        $deadlineRaw = trim($_POST['deadline'] ?? '');
        $deadlineValue = null;
        $validityRaw = trim($_POST['certificate_validity_months'] ?? '');
        $validityMonths = null;
        $pdfPath = null;
        $uploadedVideoPath = null;
        $completionType = normalize_course_completion_type($_POST['completion_type'] ?? COURSE_COMPLETION_ASSESSMENT);

        $pdfFile = $_FILES['pdf_file'] ?? null;
        if ($pdfFile && ($pdfFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $upload = store_uploaded_pdf($pdfFile);
            if (!$upload['success']) {
                flash($upload['message'], 'warning');
                redirect('courses.php');
            }
            $pdfPath = $upload['path'];
        }

        $videoFile = $_FILES['video_file'] ?? null;
        if ($videoFile && ($videoFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $videoUpload = store_uploaded_video($videoFile);
            if (!$videoUpload['success']) {
                if ($pdfPath !== null) {
                    delete_uploaded_file($pdfPath);
                }
                flash($videoUpload['message'], 'warning');
                redirect('courses.php');
            }
            $uploadedVideoPath = $videoUpload['path'];
        }

        if ($deadlineRaw !== '') {
            $deadlineDate = DateTime::createFromFormat('Y-m-d', $deadlineRaw);
            if ($deadlineDate === false) {
                if ($pdfPath !== null) {
                    delete_uploaded_file($pdfPath);
                }
                if ($uploadedVideoPath !== null) {
                    delete_uploaded_file($uploadedVideoPath);
                }
                flash('Informe uma data limite valida.', 'warning');
                redirect('courses.php');
            }
            $deadlineValue = $deadlineDate->format('Y-m-d');
        }

        if ($validityRaw !== '') {
            if (!ctype_digit($validityRaw)) {
                if ($pdfPath !== null) {
                    delete_uploaded_file($pdfPath);
                }
                if ($uploadedVideoPath !== null) {
                    delete_uploaded_file($uploadedVideoPath);
                }
                flash('Informe a validade do certificado em meses utilizando apenas numeros inteiros.', 'warning');
                redirect('courses.php');
            }

            $validityParsed = (int) $validityRaw;
            if ($validityParsed > 0) {
                $validityMonths = $validityParsed;
            }
        }

        if ($uploadedVideoPath === null && $videoUrl !== '' && !filter_var($videoUrl, FILTER_VALIDATE_URL)) {
            if ($pdfPath !== null) {
                delete_uploaded_file($pdfPath);
            }
            flash('Informe uma URL valida para o video ou envie um arquivo.', 'warning');
        } elseif (!$title || !$description || $workload <= 0) {
            if ($pdfPath !== null) {
                delete_uploaded_file($pdfPath);
            }
            if ($uploadedVideoPath !== null) {
                delete_uploaded_file($uploadedVideoPath);
            }
            flash('Preencha titulo, descricao e carga horaria.', 'warning');
        } else {
            $videoValue = $uploadedVideoPath !== null ? $uploadedVideoPath : ($videoUrl !== '' ? $videoUrl : null);

            $stmt = $db->prepare('INSERT INTO courses (title, description, video_url, pdf_url, workload, deadline, certificate_validity_months, completion_type, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $creatorId = current_user()['id'];
            $stmt->bind_param('ssssisisi', $title, $description, $videoValue, $pdfPath, $workload, $deadlineValue, $validityMonths, $completionType, $creatorId);
            if ($stmt->execute()) {
                flash('Curso criado com sucesso.', 'success');
            } else {
                if ($pdfPath !== null) {
                    delete_uploaded_file($pdfPath);
                }
                if ($uploadedVideoPath !== null) {
                    delete_uploaded_file($uploadedVideoPath);
                }
                flash('Nao foi possivel criar o curso.', 'danger');
            }
        }
    }
    if ($action === 'delete') {
        $courseId = (int) ($_POST['course_id'] ?? 0);
        if ($courseId > 0) {
            $stmtMedia = $db->prepare('SELECT pdf_url, video_url FROM courses WHERE id = ? LIMIT 1');
            $stmtMedia->bind_param('i', $courseId);
            $stmtMedia->execute();
            $mediaRow = $stmtMedia->get_result()->fetch_assoc();
            $pdfPath = $mediaRow['pdf_url'] ?? null;
            $videoPath = $mediaRow['video_url'] ?? null;

            $stmt = $db->prepare('DELETE FROM courses WHERE id = ? LIMIT 1');
            $stmt->bind_param('i', $courseId);
            if ($stmt->execute()) {
                delete_uploaded_file($pdfPath ?? null);
                delete_uploaded_file($videoPath ?? null);
                flash('Curso removido.', 'success');
            } else {
                flash('Erro ao remover curso.', 'danger');
            }
        }
    }
    if ($action === 'assign_students') {
        $courseId = (int) ($_POST['course_id'] ?? 0);
        $studentIds = $_POST['students'] ?? [];
        if ($courseId <= 0) {
            flash('Curso inválido.', 'warning');
        } elseif (empty($studentIds)) {
            flash('Selecione pelo menos um aluno.', 'warning');
        } else {
            $stmtCourseCheck = $db->prepare('SELECT id FROM courses WHERE id = ? LIMIT 1');
            $stmtCourseCheck->bind_param('i', $courseId);
            $stmtCourseCheck->execute();
            $courseExists = $stmtCourseCheck->get_result()->fetch_assoc();
            if (!$courseExists) {
                flash('Curso não encontrado.', 'warning');
            } else {
                $added = enroll_users_in_course($db, $courseId, array_map('intval', $studentIds));
                if ($added > 0) {
                    flash("{$added} aluno(s) matriculado(s) no curso.", 'success');
                } else {
                    flash('Nenhuma matrícula realizada. Verifique se os alunos já estavam inscritos ou possuem perfil válido.', 'info');
                }
            }
        }
        redirect('courses.php');
    }
    redirect('courses.php');
}

$sql = 'SELECT c.id, c.title, c.description, c.video_url, c.pdf_url, c.workload, c.created_at, c.completion_type, u.name AS author
    FROM courses c
    LEFT JOIN users u ON u.id = c.created_by
    ORDER BY c.created_at DESC';
$result = $db->query($sql);
$courses = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
$completionModes = course_completion_modes();
$studentsQuery = $db->query('SELECT id, name, email FROM users WHERE role = "aluno" ORDER BY name');
$studentOptions = $studentsQuery ? $studentsQuery->fetch_all(MYSQLI_ASSOC) : [];
$hasStudents = !empty($studentOptions);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/alerts.php';
?>
<style>
body.theme-dark {
    background-color: #0b1120;
    color: #e2e8f0;
}
body.theme-dark .laravel-card,
body.theme-dark .rounded-3xl.bg-white,
body.theme-dark .rounded-3xl.bg-white\/90 {
    background: #111827 !important;
    border-color: #0f172a !important;
    color: #e2e8f0;
    box-shadow: 0 25px 60px -30px rgba(15, 23, 42, 0.8) !important;
}
body.theme-dark .text-slate-500,
body.theme-dark .text-brand-gray {
    color: #cbd5f5 !important;
}
body.theme-dark input,
body.theme-dark textarea,
body.theme-dark select {
    background: #0f172a !important;
    border-color: #1f2937 !important;
    color: #e2e8f0 !important;
}
body.theme-dark table thead {
    background: #1f2937 !important;
}
body.theme-dark table tbody {
    background: #0f172a !important;
    color: #e2e8f0;
}
body.theme-dark .btn-outline-secondary,
body.theme-dark .btn-outline-dark {
    border-color: #94a3b8 !important;
    color: #e2e8f0 !important;
}
#themeToggle {
    border: 1px solid rgba(15, 23, 42, 0.2);
    border-radius: 999px;
    padding: 0.5rem 1.25rem;
    font-size: 0.875rem;
    font-weight: 600;
    transition: all 0.2s ease;
}
body.theme-dark #themeToggle {
    background: #1d4ed8;
    color: #f8fafc;
    border-color: transparent;
}
#themeToggle:hover {
    transform: translateY(-1px);
    box-shadow: 0 15px 30px -20px rgba(15, 23, 42, 0.5);
}
.course-assign-row {
    display: none;
}
.course-assign-row.active {
    display: table-row;
}
.assign-course-btn[disabled] {
    opacity: 0.5;
    cursor: not-allowed;
}
</style>
<div class="mx-auto max-w-7xl space-y-8 px-4 pb-12">
    <div class="laravel-card hero-banner hero-banner--soft p-4 p-lg-5">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-4">
            <div>
                <span class="badge text-bg-light text-dark text-uppercase small" style="letter-spacing:.3em;">Catálogo institucional</span>
                <h1 class="mt-3 fw-bold display-6">Gestão de cursos</h1>
                <p class="mb-0 text-lg">Publique formações alinhadas a sua organização e mantenha todo o catálogo sob controle.</p>
            </div>
            <div class="text-md-end">
                <button type="button" id="themeToggle" class="bg-white text-slate-600">Tema escuro</button>
            </div>
        </div>
    </div>

    <div class="space-y-6">
        <div class="laravel-card rounded-3xl border border-slate-200 bg-white p-6 shadow shadow-slate-900/10">
            <h2 class="text-lg font-semibold text-brand-gray">Novo curso</h2>
            <p class="mt-1 text-sm text-slate-500">Defina um titulo atrativo, descreva o conteudo e adicione recursos multimidia (opcional).</p>
            <form method="post" enctype="multipart/form-data" class="mt-6 space-y-4" novalidate>
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="create">
                <div>
                    <label for="title" class="mb-1 block text-sm font-semibold text-slate-600">Titulo</label>
                    <input type="text" id="title" name="title" required placeholder="Nome do curso" class="block w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-brand-red focus:outline-none focus:ring-4 focus:ring-brand-red/20">
                </div>
                <div>
                    <label for="description" class="mb-1 block text-sm font-semibold text-slate-600">Descricao</label>
                    <textarea id="description" name="description" rows="4" required placeholder="Descreva o conteudo" class="block w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-brand-red focus:outline-none focus:ring-4 focus:ring-brand-red/20"></textarea>
                </div>
                <div>
                    <label for="video_url" class="mb-1 block text-sm font-semibold text-slate-600">Video (URL)</label>
                    <input type="url" id="video_url" name="video_url" placeholder="https://..." class="block w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-brand-red focus:outline-none focus:ring-4 focus:ring-brand-red/20">
                    <p class="mt-1 text-xs text-slate-500">Cole o link do video hospedado (YouTube, Vimeo, etc.).</p>
                    <div class="mt-3 space-y-2">
                        <label for="video_file" class="block text-sm font-semibold text-slate-600">Ou envie um video (MP4, WEBM, OGG)</label>
                        <input type="file" id="video_file" name="video_file" accept="video/mp4,video/webm,video/ogg,video/ogv,video/x-m4v" class="block w-full rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-brand-red focus:outline-none focus:ring-4 focus:ring-brand-red/20">
                        <p class="text-xs text-slate-500">Tamanho máximo 200 MB. O arquivo enviado substitui o link informado acima.</p>
                    </div>
                </div>
                <div>
                    <label for="pdf_file" class="mb-1 block text-sm font-semibold text-slate-600">Material PDF</label>
                    <input type="file" id="pdf_file" name="pdf_file" accept="application/pdf" class="block w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-brand-red focus:outline-none focus:ring-4 focus:ring-brand-red/20">
                    <p class="mt-1 text-xs text-slate-500">Envie um arquivo PDF de ate 10 MB.</p>
                </div>
                <div>
                    <label for="workload" class="mb-1 block text-sm font-semibold text-slate-600">Carga horária (h)</label>
                    <input type="number" min="1" id="workload" name="workload" required placeholder="Ex.: 40" class="block w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-brand-red focus:outline-none focus:ring-4 focus:ring-brand-red/20">
                </div>
                <div>
                    <label for="certificate_validity_months" class="mb-1 block text-sm font-semibold text-slate-600">Validade do certificado (meses)</label>
                    <input type="number" min="0" id="certificate_validity_months" name="certificate_validity_months" placeholder="Deixe em branco para validade indeterminada" class="block w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-brand-red focus:outline-none focus:ring-4 focus:ring-brand-red/20">
                    <p class="mt-1 text-xs text-slate-500">Informe em meses. Utilize 0 ou deixe em branco para certificados sem data de expiração.</p>
                </div>
                <div>
                    <span class="mb-1 block text-sm font-semibold text-slate-600">Forma de conclusão</span>
                    <div class="grid gap-3 md:grid-cols-2">
                        <?php foreach ($completionModes as $modeValue => $modeData): ?>
                            <label class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600 shadow-sm focus-within:border-brand-red focus-within:ring-4 focus-within:ring-brand-red/10">
                                <input type="radio" name="completion_type" value="<?php echo htmlspecialchars($modeValue); ?>" <?php echo $modeValue === COURSE_COMPLETION_ASSESSMENT ? 'checked' : ''; ?> class="mt-1 h-4 w-4 text-brand-red focus:ring-brand-red/60">
                                <span>
                                    <span class="block font-semibold text-brand-gray"><?php echo htmlspecialchars($modeData['label']); ?></span>
                                    <span class="block text-xs text-slate-500"><?php echo htmlspecialchars($modeData['description']); ?></span>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">Selecione se o curso terá avaliação final ou apenas confirmação de leitura.</p>
                </div>
                <div class="pt-2">
                    <button type="submit" class="inline-flex w-full items-center justify-center rounded-2xl bg-brand-red px-5 py-3 text-sm font-semibold text-white shadow-glow transition hover:bg-brand-redDark focus:outline-none focus:ring-4 focus:ring-brand-red/30">
                        Publicar curso
                    </button>
                </div>
            </form>
        </div>
        <div class="rounded-3xl border border-slate-200 bg-white shadow shadow-slate-900/10">
            <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-6 py-5">
                <div>
                    <h2 class="text-lg font-semibold text-brand-gray">Cursos publicados</h2>
                    <p class="text-sm text-slate-500"><?php echo count($courses); ?> curso(s) ativos na plataforma.</p>
                </div>
            </div>
            <div class="overflow-hidden">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-brand-gray text-left text-xs font-semibold uppercase tracking-[0.25em] text-white">
                        <tr>
                            <th class="px-6 py-3">Titulo</th>
                            <th class="px-6 py-3">Recursos</th>
                            <th class="px-6 py-3">Carga</th>
                            <th class="px-6 py-3">Conclusão</th>
                            <th class="px-6 py-3">Responsável</th>
                            <th class="px-6 py-3">Criado em</th>
                            <th class="px-6 py-3 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <?php foreach ($courses as $course): ?>
                            <tr class="transition hover:bg-slate-50">
                                <td class="px-6 py-4 font-semibold text-slate-700"><?php echo htmlspecialchars($course['title']); ?></td>
                                <td class="px-6 py-4 text-slate-500">
                                    <div class="flex flex-wrap gap-2">
                                        <?php
                                            $rawVideoUrl = $course['video_url'] ?? '';
                                            $videoLink = null;
                                            if (!empty($rawVideoUrl)) {
                                                $videoLink = is_external_url($rawVideoUrl) ? $rawVideoUrl : asset_url($rawVideoUrl);
                                            }
                                        ?>
                                        <?php if ($videoLink): ?>
                                            <a href="<?php echo htmlspecialchars($videoLink); ?>" target="_blank" class="inline-flex w-full items-center justify-center rounded-full border border-brand-red/30 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-brand-red transition hover:bg-brand-red hover:text-white sm:w-auto">Video</a>
                                        <?php endif; ?>
                                        <?php if (!empty($course['pdf_url'])): ?>
                                            <a href="material.php?course_id=<?php echo (int) $course['id']; ?>" class="inline-flex w-full items-center justify-center rounded-full border border-brand-red/30 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-brand-red transition hover:bg-brand-red hover:text-white sm:w-auto">Ler PDF</a>
                                            <a href="<?php echo htmlspecialchars(asset_url($course['pdf_url'])); ?>" download class="inline-flex w-full items-center justify-center rounded-full border border-slate-300 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-600 transition hover:bg-slate-700 hover:text-white sm:w-auto">Baixar PDF</a>
                                        <?php endif; ?>
                                        <?php if (!$videoLink && empty($course['pdf_url'])): ?>
                                            <span class="text-xs text-slate-400">Sem recursos</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-slate-500"><?php echo (int) $course['workload']; ?>h</td>
                                <td class="px-6 py-4 text-slate-500">
                                    <?php
                                        $modeValue = $course['completion_type'] ?? COURSE_COMPLETION_ASSESSMENT;
                                        $modeLabel = $completionModes[$modeValue]['label'] ?? $completionModes[COURSE_COMPLETION_ASSESSMENT]['label'];
                                        echo htmlspecialchars($modeLabel);
                                    ?>
                                </td>
                                <td class="px-6 py-4 text-slate-500"><?php echo htmlspecialchars($course['author'] ?? ''); ?></td>
                                <td class="px-6 py-4 text-slate-500"><?php echo $course['created_at'] ? date('d/m/Y', strtotime($course['created_at'])) : '-'; ?></td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex flex-wrap gap-2 sm:justify-end">
                                        <button type="button" class="inline-flex w-full items-center justify-center rounded-full border border-brand-red/30 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-brand-red transition hover:bg-brand-red hover:text-white disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto assign-course-btn" data-assign-target="assign-course-<?php echo (int) $course['id']; ?>" <?php echo $hasStudents ? '' : 'disabled'; ?>>
                                            Atribuir curso
                                        </button>
                                        <a href="course_detail.php?course_id=<?php echo (int) $course['id']; ?>" class="inline-flex w-full items-center justify-center rounded-full border border-slate-300 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-slate-600 transition hover:bg-slate-700 hover:text-white sm:w-auto">Editar</a>
                                        <form method="post" class="flex w-full sm:w-auto">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="course_id" value="<?php echo (int) $course['id']; ?>">
                                            <button type="submit" data-confirm="Excluir o curso e seus dados associados?" class="inline-flex w-full items-center justify-center rounded-full border border-rose-300 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-rose-600 transition hover:bg-rose-500 hover:text-white sm:w-auto">
                                                Excluir
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <tr class="course-assign-row" id="assign-course-<?php echo (int) $course['id']; ?>" aria-hidden="true">
                                <td colspan="7" class="bg-slate-50 px-6 py-4">
                                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-inner">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm font-semibold text-brand-gray">Atribuir alunos ao curso "<?php echo htmlspecialchars($course['title']); ?>"</p>
                                            <p class="text-xs text-slate-500">Selecione os alunos que receberão esse curso.</p>
                                        </div>
                                        <button type="button" class="text-xs font-semibold uppercase tracking-wide text-slate-400 hover:text-brand-red" data-close-assign="assign-course-<?php echo (int) $course['id']; ?>">Fechar</button>
                                    </div>
                                        <form method="post" class="mt-4 space-y-4">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="assign_students">
                                            <input type="hidden" name="course_id" value="<?php echo (int) $course['id']; ?>">
                                            <?php if ($hasStudents): ?>
                                                <select name="students[]" multiple size="6" class="block w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-brand-red focus:outline-none focus:ring-4 focus:ring-brand-red/20">
                                                    <?php foreach ($studentOptions as $student): ?>
                                                        <option value="<?php echo (int) $student['id']; ?>"><?php echo htmlspecialchars($student['name']); ?> (<?php echo htmlspecialchars($student['email']); ?>)</option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <p class="text-xs text-slate-500">Use Ctrl (Windows) ou Cmd (macOS) para escolher vários alunos.</p>
                                                <div class="flex flex-wrap gap-3">
                                                    <button type="submit" class="inline-flex items-center justify-center rounded-full bg-brand-red px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white shadow-glow transition hover:bg-brand-redDark focus:outline-none focus:ring-4 focus:ring-brand-red/30">
                                                        Confirmar atribuição
                                                    </button>
                                                    <button type="button" class="inline-flex items-center justify-center rounded-full border border-slate-300 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-slate-600 transition hover:bg-slate-700 hover:text-white" data-close-assign="assign-course-<?php echo (int) $course['id']; ?>">Cancelar</button>
                                                </div>
                                            <?php else: ?>
                                                <p class="text-sm text-slate-500">Nenhum aluno cadastrado para atribuir cursos.</p>
                                            <?php endif; ?>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($courses)): ?>
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-sm text-slate-500">Nenhum curso cadastrado ainda. Comece publicando sua primeira turma.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script>
(function() {
    const body = document.body;
    const toggle = document.getElementById('themeToggle');
    if (!toggle) return;
    const storageKey = 'courses-theme';
    const applyTheme = (theme) => {
        const isDark = theme === 'dark';
        body.classList.toggle('theme-dark', isDark);
        toggle.textContent = isDark ? 'Tema claro' : 'Tema escuro';
    };
    let saved = localStorage.getItem(storageKey) || 'light';
    applyTheme(saved);
    toggle.addEventListener('click', () => {
        saved = body.classList.contains('theme-dark') ? 'light' : 'dark';
        localStorage.setItem(storageKey, saved);
        applyTheme(saved);
    });
})();
(function() {
    const rows = document.querySelectorAll('.course-assign-row');
    const toggleRow = (targetId, forceClose = false) => {
        rows.forEach(row => {
            if (row.id !== targetId) {
                row.classList.remove('active');
                row.setAttribute('aria-hidden', 'true');
            }
        });
        const target = document.getElementById(targetId);
        if (!target) {
            return;
        }
        if (forceClose) {
            target.classList.remove('active');
            target.setAttribute('aria-hidden', 'true');
            return;
        }
        const willShow = !target.classList.contains('active');
        target.classList.toggle('active', willShow);
        target.setAttribute('aria-hidden', willShow ? 'false' : 'true');
    };
    document.querySelectorAll('[data-assign-target]').forEach(button => {
        button.addEventListener('click', () => {
            const targetId = button.getAttribute('data-assign-target');
            toggleRow(targetId);
        });
    });
    document.querySelectorAll('[data-close-assign]').forEach(button => {
        button.addEventListener('click', () => {
            const targetId = button.getAttribute('data-close-assign');
            toggleRow(targetId, true);
        });
    });
})();
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>















