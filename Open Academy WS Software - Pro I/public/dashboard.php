<?php

require_once __DIR__ . '/../includes/auth.php';



require_login();



$pageTitle = 'Dashboard';

$db = get_db();

$user = current_user();



$totalUsers = 0;

$totalCourses = 0;

$totalApproved = 0;

$studentCourses = [];



if (has_role('admin')) {

    $totalUsers = (int) ($db->query("SELECT COUNT(*) AS total FROM users")?->fetch_assoc()['total'] ?? 0);

    $totalCourses = (int) ($db->query("SELECT COUNT(*) AS total FROM courses")?->fetch_assoc()['total'] ?? 0);

    $totalApproved = (int) ($db->query("SELECT COUNT(*) AS total FROM assessment_results WHERE approved = 1")?->fetch_assoc()['total'] ?? 0);

} elseif (has_role('gestor')) {

    $totalCourses = (int) ($db->query("SELECT COUNT(*) AS total FROM courses")?->fetch_assoc()['total'] ?? 0);

    $totalApproved = (int) ($db->query("SELECT COUNT(*) AS total FROM assessment_results WHERE approved = 1")?->fetch_assoc()['total'] ?? 0);

} else {

    $stmt = $db->prepare('SELECT c.id, c.title, c.description, c.video_url, c.pdf_url, c.deadline, ar.score, ar.approved

        FROM enrollments e

        INNER JOIN courses c ON c.id = e.course_id

        LEFT JOIN assessment_results ar ON ar.user_id = e.user_id AND ar.course_id = e.course_id

        WHERE e.user_id = ?

        ORDER BY c.title');

    $stmt->bind_param('i', $user['id']);

    $stmt->execute();

    $studentCourses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

}



$completedCourses = 0;

$pendingCourses = 0;

$overdueCourses = 0;

if (!has_role('admin') && !has_role('gestor')) {

    $today = new DateTimeImmutable('today');

    foreach ($studentCourses as $course) {

        $approved = (int) ($course['approved'] ?? 0) === 1;

        if ($approved) {

            $completedCourses++;

            continue;

        }

        $deadlineRaw = $course['deadline'] ?? null;

        $isOverdue = false;

        if (!empty($deadlineRaw)) {

            try {

                $deadlineDate = new DateTimeImmutable($deadlineRaw);

                if ($deadlineDate < $today) {

                    $isOverdue = true;

                }

            } catch (Exception $exception) {

                $isOverdue = false;

            }

        }



        if ($isOverdue) {

            $overdueCourses++;

        } else {

            $pendingCourses++;

        }

    }

}



require_once __DIR__ . '/../includes/header.php';

require_once __DIR__ . '/../includes/navbar.php';

require_once __DIR__ . '/../includes/alerts.php';



$firstName = explode(' ', trim($user['name']))[0] ?? $user['name'];
$lastUpdateLabel = (new DateTimeImmutable())->format('d/m/Y');

if (has_role('admin')) {
    $heroStatValue = $totalUsers;
    $heroStatLabel = 'profissionais conectados';
    $heroChipSecondary = $totalApproved . ' certificados emitidos';
} elseif (has_role('gestor')) {
    $heroStatValue = $totalCourses;
    $heroStatLabel = 'trilhas disponíveis';
    $heroChipSecondary = $totalApproved . ' aprovações recentes';
} else {
    $heroStatValue = count($studentCourses);
    $heroStatLabel = 'cursos inscritos';
    $heroChipSecondary = $completedCourses . ' certificados conquistados';
}
?>

<div class="container-xxl py-4">

    <div class="laravel-card hero-banner p-4 p-lg-5 mb-4">

        <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-4">

            <div class="flex-grow-1">

                <span class="badge text-bg-light text-dark text-uppercase small" style="letter-spacing: .3em;">Open academy WS Software By Washington Oliveira</span>

                <h1 class="mt-3 fw-bold display-5">Bem-vindo, <?php echo htmlspecialchars($firstName); ?>!</h1>

                <p class="mb-0 fs-5">Monitoramos toda a jornada de aprendizagem com uma experiência premium da Open Academy WS Software.</p>

                <div class="d-flex flex-wrap gap-2 mt-4">

                    <span class="hero-chip"><i class="bi bi-calendar2-week"></i><?php echo htmlspecialchars($lastUpdateLabel); ?></span>

                    <span class="hero-chip"><i class="bi bi-award"></i><?php echo htmlspecialchars($heroChipSecondary); ?></span>

                </div>

            </div>

            <div class="text-lg-end">

                <p class="text-uppercase small fw-semibold mb-1" style="letter-spacing:.4em;">Visão executiva</p>

                <p class="display-4 fw-bold mb-0"><?php echo (int) $heroStatValue; ?>+</p>

                <p class="mb-0 opacity-75 text-white"><?php echo htmlspecialchars($heroStatLabel); ?></p>

            </div>

        </div>

    </div>



    <?php if (has_role('admin') || has_role('gestor')): ?>

        <div class="row g-4">

            <?php if (has_role('admin')): ?>

                <div class="col-md-6 col-lg-4">

                    <div class="laravel-card h-100 p-4 position-relative overflow-hidden text-white" style="background: linear-gradient(135deg, #b22222, #8b1a1a);">

                        <div class="text-uppercase small fw-semibold mb-3" style="letter-spacing:.3em;">Usuários ativos</div>

                        <p class="display-5 fw-bold mb-2"><?php echo $totalUsers; ?></p>

                        <p class="text-white-50 mb-0">Perfis cadastrados na plataforma.</p>

                        <span class="position-absolute top-0 end-0 display-1 fw-bold opacity-25 pe-3"></span>

                    </div>

                </div>

            <?php endif; ?>

            <div class="col-md-6 <?php echo has_role('admin') ? 'col-lg-4' : 'col-lg-6'; ?>">

                <div class="laravel-card h-100 p-4">

                    <div class="text-uppercase small fw-semibold text-secondary mb-3" style="letter-spacing:.3em;">Cursos</div>

                    <p class="display-5 fw-bold text-dark mb-2"><?php echo $totalCourses; ?></p>

                    <p class="text-muted mb-0">Turmas publicadas e gerenciadas.</p>

                </div>

            </div>

            <div class="col-md-6 col-lg-4">

                <div class="laravel-card h-100 p-4 text-white" style="background: linear-gradient(135deg, #5b1d1d, #b22222);">

                    <div class="text-uppercase small fw-semibold mb-3" style="letter-spacing:.3em;">Certificados</div>

                    <p class="display-5 fw-bold mb-2"><?php echo $totalApproved; ?></p>

                    <p class="text-white-50 mb-0">Avaliações aprovadas.</p>

                </div>

            </div>

        </div>

    <?php else: ?>

        <div class="mt-4">

            <div class="mb-4">

                <h2 class="h5 fw-semibold text-dark mb-1">Cursos em andamento</h2>

                <p class="text-muted mb-0">Acesse suas aulas, realize avaliações e acompanhe o progresso das notas.</p>

            </div>

            <div class="row g-3 mb-4">

                <div class="col-md-4">

                    <div class="laravel-card h-100 p-4 text-white" style="background: linear-gradient(135deg,#0f9d58,#34a853);">

                        <div class="text-uppercase small fw-semibold mb-2" style="letter-spacing:.35em;">Cursos realizados</div>

                        <p class="display-6 fw-bold mb-1"><?php echo $completedCourses; ?></p>

                        <p class="text-white-75 mb-0">Concluídos e certificados liberados.</p>

                    </div>

                </div>

                <div class="col-md-4">

                    <div class="laravel-card h-100 p-4">

                        <div class="text-uppercase small fw-semibold text-secondary mb-2" style="letter-spacing:.35em;">Cursos pendentes</div>

                        <p class="display-6 fw-bold text-dark mb-1"><?php echo $pendingCourses; ?></p>

                        <p class="text-muted mb-0">Em andamento ou aguardando avaliação.</p>

                    </div>

                </div>

                <div class="col-md-4">

                    <div class="laravel-card h-100 p-4 text-white" style="background: linear-gradient(135deg,#f97316,#ea580c);">

                        <div class="text-uppercase small fw-semibold mb-2" style="letter-spacing:.35em;">Cursos atrasados</div>

                        <p class="display-6 fw-bold mb-1"><?php echo $overdueCourses; ?></p>

                        <p class="text-white-75 mb-0">Prazos encerrados sem conclusão.</p>

                    </div>

                </div>

            </div>



            <?php if (empty($studentCourses)): ?>

                <div class="laravel-card p-5 text-center text-muted">

                    Você ainda nao possui cursos matriculados. Procure o gestor para iniciar sua jornada.

                </div>

            <?php else: ?>

                <div class="row g-4">

                    <?php foreach ($studentCourses as $course): ?>

                        <?php

                            $description = $course['description'] ?? '';

                            $preview = strlen($description) > 120 ? substr($description, 0, 120) . '...' : $description;



                            $moduleItems = [];

                            $hasModules = false;

                            $allModulesApproved = true;



                            $moduleStmt = $db->prepare('SELECT id, title, description, video_url, pdf_url, position FROM course_modules WHERE course_id = ? ORDER BY position, id');

                            if ($moduleStmt) {

                                $moduleStmt->bind_param('i', $course['id']);

                                $moduleStmt->execute();

                                $moduleRows = $moduleStmt->get_result()->fetch_all(MYSQLI_ASSOC);

                                $moduleStmt->close();

                                if (!empty($moduleRows)) {

                                    $hasModules = true;

                                    foreach ($moduleRows as $moduleRow) {

                                        $moduleResultStmt = $db->prepare('SELECT score, approved, updated_at FROM module_results WHERE module_id = ? AND user_id = ?');

                                        $moduleResultRow = null;

                                        if ($moduleResultStmt) {

                                            $moduleResultStmt->bind_param('ii', $moduleRow['id'], $user['id']);

                                            $moduleResultStmt->execute();

                                            $moduleResultRow = $moduleResultStmt->get_result()->fetch_assoc();

                                            $moduleResultStmt->close();

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

                        <div class="col-12 col-md-6">

                            <div class="laravel-card h-100 p-4 d-flex flex-column">

                                <div class="mb-3">

                                    <h3 class="h5 fw-semibold text-dark mb-2"><?php echo htmlspecialchars($course['title']); ?></h3>

                                    <p class="text-muted mb-0"><?php echo htmlspecialchars($preview); ?></p>

                                </div>

                                <div class="mb-3">

                                    <?php if ($course['score'] !== null): ?>

                                        <span class="badge rounded-pill text-bg-success px-3 py-2">

                                            Nota <?php echo number_format((float) $course['score'], 1); ?> â <?php echo $course['approved'] ? 'Aprovado' : 'Nao aprovado'; ?>

                                        </span>

                                    <?php else: ?>

                                        <span class="badge rounded-pill text-bg-warning text-dark px-3 py-2">Avaliação pendente</span>

                                    <?php endif; ?>

                                </div>

                                <?php if ($videoPlayer || $hasPdfUrl): ?>

                                    <div class="mb-3">

                                        <?php if ($videoPlayer): ?>

                                            <div class="ratio ratio-16x9 rounded-4 overflow-hidden border mb-3">

                                                <?php echo $videoPlayer; ?>

                                            </div>

                                        <?php endif; ?>

                                        <div class="d-flex flex-wrap gap-2">

                                            <?php if ($hasVideoUrl && $videoLink): ?>

                                                <a href="<?php echo htmlspecialchars($videoLink); ?>" target="_blank" class="btn btn-outline-danger btn-sm">

                                                    <?php echo $videoPlayer ? 'Abrir video em nova aba' : 'Video'; ?>

                                                </a>

                                            <?php endif; ?>

                                            <?php if ($hasPdfUrl): ?>

                                                <a href="material.php?course_id=<?php echo (int) $course['id']; ?>" class="btn btn-outline-secondary btn-sm">Ler PDF</a>

                                                <a href="<?php echo htmlspecialchars($pdfAssetUrl); ?>" download class="btn btn-outline-dark btn-sm">Baixar PDF</a>

                                            <?php endif; ?>

                                        </div>

                                    </div>

                                <?php endif; ?>

                                <?php if (!empty($moduleItems)): ?>

                                    <div class="mb-3">

                                        <div class="text-uppercase text-muted small fw-semibold mb-2" style="letter-spacing:.3em;">Módulos</div>

                                        <div class="d-flex flex-column gap-2">

                                            <?php foreach ($moduleItems as $item): ?>

                                                <?php

                                                    $moduleData = $item['data'];

                                                    $moduleResult = $item['result'] ?? null;

                                                    $isApproved = ($moduleResult && (int) $moduleResult['approved'] === 1);

                                                ?>

                                                <div class="border rounded-4 p-3 bg-light">

                                                    <div class="d-flex flex-column flex-lg-row gap-2 align-items-lg-center justify-content-between">

                                                        <div>

                                                            <div class="fw-semibold text-dark"><?php echo htmlspecialchars($moduleData['title']); ?></div>

                                                            <?php if ($moduleResult): ?>

                                                                <small class="text-muted">Nota <?php echo number_format((float) ($moduleResult['score'] ?? 0), 1); ?> â <?php echo $isApproved ? 'Aprovado' : 'Nao aprovado'; ?></small>

                                                            <?php else: ?>

                                                                <small class="text-muted">Questionário pendente</small>

                                                            <?php endif; ?>

                                                        </div>

                                                        <div class="d-flex flex-wrap gap-2">

                                                            <span class="badge <?php echo $isApproved ? 'text-bg-success' : 'text-bg-secondary'; ?>"><?php echo $isApproved ? 'Concluido' : 'Pendente'; ?></span>

                                                            <a href="module.php?module_id=<?php echo (int) $moduleData['id']; ?>" class="btn btn-sm btn-outline-danger"><?php echo $isApproved ? 'Revisar' : 'Iniciar'; ?></a>

                                                        </div>

                                                    </div>

                                                </div>

                                            <?php endforeach; ?>

                                        </div>

                                    </div>

                                <?php endif; ?>

                                <div class="mt-auto d-flex flex-wrap gap-2">

                                    <?php if ($course['approved']): ?>

                                        <span class="badge text-bg-success px-3 py-2 flex-grow-1 text-center">Avaliação concluida</span>

                                    <?php elseif ($hasModules && !$allModulesApproved): ?>

                                        <span class="badge text-bg-secondary px-3 py-2 flex-grow-1 text-center">Conclua os módulos</span>

                                    <?php else: ?>

                                        <a href="assessment.php?course_id=<?php echo (int) $course['id']; ?>" class="btn btn-danger flex-grow-1">Acessar avaliação</a>

                                    <?php endif; ?>

                                    <?php if ($course['approved']): ?>

                                        <a href="certificate.php?course_id=<?php echo (int) $course['id']; ?>" class="btn btn-outline-dark flex-grow-1">Certificado</a>

                                    <?php endif; ?>

                                </div>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </div>

    <?php endif; ?>

</div>



<?php require_once __DIR__ . '/../includes/footer.php'; ?>

