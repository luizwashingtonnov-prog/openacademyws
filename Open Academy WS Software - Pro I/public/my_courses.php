<?php
require_once __DIR__ . '/../includes/auth.php';

require_roles(['aluno']);

$pageTitle = 'Meus cursos';
$db = get_db();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();
    $action = $_POST['action'] ?? '';

    if ($action === 'mark_read') {
        $courseId = (int) ($_POST['course_id'] ?? 0);
        if ($courseId <= 0) {
            flash('Curso invalido.', 'warning');
        } else {
            $stmtCourse = $db->prepare('SELECT completion_type FROM courses WHERE id = ? LIMIT 1');
            $stmtCourse->bind_param('i', $courseId);
            $stmtCourse->execute();
            $courseRow = $stmtCourse->get_result()->fetch_assoc();
            if (!$courseRow) {
                flash('Curso nao encontrado.', 'warning');
            } elseif (($courseRow['completion_type'] ?? COURSE_COMPLETION_ASSESSMENT) !== COURSE_COMPLETION_ACK) {
                flash('Este curso exige avaliacao final para liberar o certificado.', 'warning');
            } else {
                $stmtEnrollment = $db->prepare('SELECT 1 FROM enrollments WHERE course_id = ? AND user_id = ?');
                $stmtEnrollment->bind_param('ii', $courseId, $user['id']);
                $stmtEnrollment->execute();
                if (!$stmtEnrollment->get_result()->fetch_assoc()) {
                    flash('Voce nao esta matriculado neste curso.', 'danger');
                } else {
                    $score = 10.0;
                    $approved = 1;
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
                    flash('Curso marcado como lido. Seu certificado esta liberado.', 'success');
                }
            }
        }
    }

    redirect('my_courses.php');
}

$stmtCourses = $db->prepare('SELECT c.id, c.title, c.description, c.video_url, c.pdf_url, c.workload, c.completion_type, ar.score, ar.approved
    FROM enrollments e
    INNER JOIN courses c ON c.id = e.course_id
    LEFT JOIN assessment_results ar ON ar.course_id = e.course_id AND ar.user_id = e.user_id
    WHERE e.user_id = ?
    ORDER BY c.title');
$stmtCourses->bind_param('i', $user['id']);
$stmtCourses->execute();
$courses = $stmtCourses->get_result()->fetch_all(MYSQLI_ASSOC);

$stmtModules = $db->prepare('SELECT id, title, description, video_url, pdf_url, position FROM course_modules WHERE course_id = ? ORDER BY position, id');
$stmtModuleResult = $db->prepare('SELECT score, approved, updated_at FROM module_results WHERE module_id = ? AND user_id = ?');

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/alerts.php';
?>
<div class="mx-auto max-w-7xl space-y-8 px-4 pb-12">
    <div class="rounded-3xl border border-slate-200 bg-white/90 p-8 shadow shadow-slate-900/10">
        <h1 class="text-2xl font-bold tracking-tight text-brand-gray">Meus cursos</h1>
        <p class="mt-2 text-sm text-slate-500">Acompanhe sua jornada de aprendizado, realize os modulos e responda as avaliacoes ou marque o curso como lido para liberar o certificado.</p>
    </div>

    <?php if (empty($courses)): ?>
        <div class="rounded-3xl border border-slate-200 bg-white p-10 text-center text-sm text-slate-500 shadow shadow-slate-900/10">
            Nenhum curso matriculado ainda. Entre em contato com o gestor para iniciar um novo modulo.
        </div>
    <?php else: ?>
        <div class="grid gap-4 md:grid-cols-2">
            <?php foreach ($courses as $course): ?>
                <?php
                    $moduleItems = [];
                    $hasModules = false;
                    $allModulesApproved = true;

                    if ($stmtModules) {
                        $stmtModules->bind_param('i', $course['id']);
                        $stmtModules->execute();
                        $moduleRows = $stmtModules->get_result()->fetch_all(MYSQLI_ASSOC);
                        if (!empty($moduleRows)) {
                            $hasModules = true;
                            foreach ($moduleRows as $moduleRow) {
                                if ($stmtModuleResult) {
                                    $stmtModuleResult->bind_param('ii', $moduleRow['id'], $user['id']);
                                    $stmtModuleResult->execute();
                                    $moduleResultRow = $stmtModuleResult->get_result()->fetch_assoc();
                                } else {
                                    $moduleResultRow = null;
                                }
                                if (!$moduleResultRow || (int) ($moduleResultRow['approved'] ?? 0) !== 1) {
                                    $allModulesApproved = false;
                                }
                                $moduleItems[] = [
                                    'data' => $moduleRow,
                                    'result' => $moduleResultRow,
                                ];
                            }
                        }
                    }
                    $currentCompletionType = $course['completion_type'] ?? COURSE_COMPLETION_ASSESSMENT;
                    $isAssessmentCourse = $currentCompletionType === COURSE_COMPLETION_ASSESSMENT;
                    $isCourseApproved = (int) ($course['approved'] ?? 0) === 1;
                ?>
                <div class="flex h-full flex-col justify-between rounded-3xl border border-slate-200 bg-white p-6 shadow shadow-slate-900/10">
                    <div>
                        <div class="flex items-center justify-between text-xs font-semibold uppercase tracking-wider text-slate-400">
                            <span>Curso</span>
                            <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-100 px-3 py-1">Carga: <?php echo (int) ($course['workload'] ?? 0); ?>h</span>
                        </div>
                        <h3 class="mt-3 text-lg font-semibold text-brand-gray"><?php echo htmlspecialchars($course['title']); ?></h3>
                        <p class="mt-2 text-sm text-slate-500 leading-relaxed"><?php echo nl2br(htmlspecialchars($course['description'])); ?></p>
                    </div>
                    <div class="mt-4 flex flex-col gap-4">
                        <div>
                            <?php if ($isAssessmentCourse): ?>
                                <?php if ($course['score'] !== null): ?>
                                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-4 py-1 text-xs font-semibold uppercase tracking-wide text-emerald-700">
                                        Nota: <?php echo number_format((float) $course['score'], 1); ?>
                                        <?php echo $isCourseApproved ? ' - Aprovado' : ' - Nao aprovado'; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center rounded-full bg-amber-100 px-4 py-1 text-xs font-semibold uppercase tracking-wide text-amber-700">Avaliacao pendente</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php if ($isCourseApproved): ?>
                                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-4 py-1 text-xs font-semibold uppercase tracking-wide text-emerald-700">Curso marcado como lido</span>
                                <?php else: ?>
                                    <span class="inline-flex items-center rounded-full bg-slate-200 px-4 py-1 text-xs font-semibold uppercase tracking-wide text-slate-600">Confirme a leitura para liberar o certificado</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <?php
                            $rawVideoUrl = $course['video_url'] ?? '';
                            $hasVideoUrl = $rawVideoUrl !== '';
                            $videoLink = null;
                            if ($hasVideoUrl) {
                                $videoLink = is_external_url($rawVideoUrl) ? $rawVideoUrl : asset_url($rawVideoUrl);
                            }
                            $videoPlayer = $hasVideoUrl ? render_video_player($videoLink) : null;
                            $pdfPath = $course['pdf_url'] ?? '';
                            $hasPdfUrl = !empty($pdfPath);
                            $pdfAssetUrl = $hasPdfUrl ? asset_url($pdfPath) : '';
                            $hasPdfUrl = $hasPdfUrl && $pdfAssetUrl !== '';
                        ?>
                        <?php if ($videoPlayer || $hasVideoUrl || $hasPdfUrl): ?>
                            <div class="flex flex-col gap-3">
                                <?php if ($videoPlayer): ?>
                                    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-black" style="aspect-ratio: 16 / 9;">
                                        <?php echo $videoPlayer; ?>
                                    </div>
                                <?php endif; ?>
                                <div class="flex flex-wrap gap-2">
                                    <?php if ($hasVideoUrl && $videoLink): ?>
                                        <a href="<?php echo htmlspecialchars($videoLink); ?>" target="_blank" class="inline-flex items-center rounded-full border border-brand-red/30 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-brand-red transition hover:bg-brand-red hover:text-white">
                                            <?php echo $videoPlayer ? 'Abrir video em nova aba' : 'Assistir video'; ?>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($hasPdfUrl): ?>
                                        <a href="material.php?course_id=<?php echo (int) $course['id']; ?>" class="inline-flex items-center rounded-full border border-brand-red/30 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-brand-red transition hover:bg-brand-red hover:text-white">Ler PDF</a>
                                        <a href="<?php echo htmlspecialchars($pdfAssetUrl); ?>" download class="inline-flex items-center rounded-full border border-slate-300 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-600 transition hover:bg-slate-700 hover:text-white">Baixar PDF</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($moduleItems)): ?>
                            <div class="space-y-2">
                                <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Modulos do curso</h4>
                                <?php foreach ($moduleItems as $item): ?>
                                    <?php
                                        $moduleData = $item['data'];
                                        $moduleResult = $item['result'] ?? null;
                                        $isApproved = ($moduleResult && (int) $moduleResult['approved'] === 1);
                                        $statusBadgeClass = $isApproved ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600';
                                    ?>
                                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                            <div>
                                                <p class="font-semibold text-brand-gray"><?php echo htmlspecialchars($moduleData['title']); ?></p>
                                                <?php if (!empty($moduleData['description'])): ?>
                                                    <p class="mt-1 text-xs leading-relaxed text-slate-500"><?php echo htmlspecialchars($moduleData['description']); ?></p>
                                                <?php endif; ?>
                                                <?php if ($moduleResult): ?>
                                                    <p class="mt-1 text-xs text-slate-500">Nota: <?php echo number_format((float) ($moduleResult['score'] ?? 0), 1); ?> · <?php echo $isApproved ? 'Aprovado' : 'Nao aprovado'; ?></p>
                                                <?php else: ?>
                                                    <p class="mt-1 text-xs text-slate-500">Questionario pendente</p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="flex w-full flex-col items-stretch gap-2 sm:w-auto sm:items-end">
                                                <span class="inline-flex items-center justify-center rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide <?php echo $statusBadgeClass; ?>"><?php echo $isApproved ? 'Concluido' : 'Em andamento'; ?></span>
                                                <a href="module.php?module_id=<?php echo (int) $moduleData['id']; ?>" class="inline-flex w-full items-center justify-center rounded-full border border-brand-red/30 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-brand-red transition hover:bg-brand-red hover:text-white sm:w-auto"><?php echo $isApproved ? 'Revisar modulo' : 'Iniciar modulo'; ?></a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div class="flex flex-wrap gap-2">
                            <?php if ($isAssessmentCourse): ?>
                                <?php if ($isCourseApproved): ?>
                                    <span class="inline-flex w-full items-center justify-center rounded-2xl border border-emerald-300 bg-emerald-50 px-4 py-2 text-sm font-semibold uppercase tracking-wide text-emerald-700">Avaliacao concluida</span>
                                <?php elseif ($hasModules && !$allModulesApproved): ?>
                                    <span class="inline-flex w-full items-center justify-center rounded-2xl border border-slate-300 px-4 py-2 text-sm font-semibold uppercase tracking-wide text-slate-400">Conclua os modulos</span>
                                <?php else: ?>
                                    <a href="assessment.php?course_id=<?php echo (int) $course['id']; ?>" class="inline-flex w-full items-center justify-center rounded-2xl bg-brand-red px-4 py-2 text-sm font-semibold text-white shadow-glow transition hover:bg-brand-redDark focus:outline-none focus:ring-4 focus:ring-brand-red/30">
                                        Abrir avaliacao
                                    </a>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php if ($isCourseApproved): ?>
                                    <span class="inline-flex w-full items-center justify-center rounded-2xl border border-emerald-300 bg-emerald-50 px-4 py-2 text-sm font-semibold uppercase tracking-wide text-emerald-700">Certificado liberado</span>
                                <?php else: ?>
                                    <form method="post" class="flex w-full sm:w-auto">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="mark_read">
                                        <input type="hidden" name="course_id" value="<?php echo (int) $course['id']; ?>">
                                        <button type="submit" class="inline-flex w-full items-center justify-center rounded-2xl bg-brand-red px-4 py-2 text-sm font-semibold text-white shadow-glow transition hover:bg-brand-redDark focus:outline-none focus:ring-4 focus:ring-brand-red/30 sm:w-auto">
                                            Marcar como lido
                                        </button>
                                    </form>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php if ($isCourseApproved): ?>
                                <a href="certificate.php?course_id=<?php echo (int) $course['id']; ?>" class="inline-flex w-full items-center justify-center rounded-2xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-700 hover:text-white sm:w-auto">
                                    Gerar certificado
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
