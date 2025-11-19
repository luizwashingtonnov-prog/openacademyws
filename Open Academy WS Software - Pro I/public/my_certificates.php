<?php
require_once __DIR__ . '/../includes/auth.php';

require_roles(['aluno']);

$db = get_db();
$user = current_user();

$stmt = $db->prepare('SELECT c.id, c.title, c.workload, ar.score, ar.certificate_code, ar.issued_at, ar.created_at
    FROM assessment_results ar
    INNER JOIN courses c ON c.id = ar.course_id
    WHERE ar.user_id = ? AND ar.approved = 1
    ORDER BY COALESCE(ar.issued_at, ar.created_at) DESC, c.title');
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$certificates = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Meus certificados';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/alerts.php';
?>
<div class="mx-auto max-w-7xl space-y-8 px-4 pb-12">
    <div class="rounded-3xl border border-slate-200 bg-white/90 p-8 shadow shadow-slate-900/10">
        <h1 class="text-2xl font-bold tracking-tight text-brand-gray">Meus certificados</h1>
        <p class="mt-2 text-sm text-slate-500">Acompanhe os certificados gerados e realize o download quando desejar.</p>
    </div>

    <?php if (empty($certificates)): ?>
        <div class="rounded-3xl border border-slate-200 bg-white p-10 text-center text-sm text-slate-500 shadow shadow-slate-900/10">
            Nenhum certificado liberado ainda. Conclua os cursos para gerar seus certificados.
        </div>
    <?php else: ?>
        <div class="space-y-4">
            <?php foreach ($certificates as $certificate): ?>
                <?php
                    $issuedRaw = $certificate['issued_at'] ?? $certificate['created_at'] ?? null;
                    $issuedDisplay = '-';
                    if ($issuedRaw !== null) {
                        try {
                            $issuedDate = new DateTime($issuedRaw);
                            $issuedDisplay = $issuedDate->format('d/m/Y');
                        } catch (Exception $exception) {
                            $issuedDisplay = '-';
                        }
                    }
                    $scoreDisplay = $certificate['score'] !== null ? number_format((float) $certificate['score'], 1) : '-';
                    $certificateCode = $certificate['certificate_code'] ?? 'Gerado ao abrir';
                    $courseId = (int) $certificate['id'];
                ?>
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow shadow-slate-900/10">
                    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                        <div class="flex-1">
                            <div class="text-xs font-semibold uppercase tracking-wider text-slate-400">Curso</div>
                            <h2 class="mt-2 text-lg font-semibold text-brand-gray"><?php echo htmlspecialchars($certificate['title']); ?></h2>
                            <p class="mt-2 text-sm text-slate-500">Carga hor&aacute;ria: <?php echo (int) ($certificate['workload'] ?? 0); ?>h</p>
                            <p class="mt-1 text-sm text-slate-500">Nota final: <?php echo $scoreDisplay; ?></p>
                            <p class="mt-1 text-sm text-slate-500">Emitido em: <?php echo htmlspecialchars($issuedDisplay); ?></p>
                            <p class="mt-1 text-xs uppercase tracking-wide text-slate-400">C&oacute;digo: <?php echo htmlspecialchars($certificateCode); ?></p>
                        </div>
                        <div class="flex w-full flex-col gap-2 md:w-auto md:items-end">
                            <a href="certificate.php?course_id=<?php echo $courseId; ?>" target="_blank" rel="noopener" class="inline-flex items-center justify-center rounded-full border border-brand-red/30 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-brand-red transition hover:bg-brand-red hover:text-white">
                                Visualizar certificado
                            </a>
                            <a href="certificate.php?course_id=<?php echo $courseId; ?>&download=1" class="inline-flex items-center justify-center rounded-full border border-slate-300 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-slate-600 transition hover:bg-slate-700 hover:text-white">
                                Baixar certificado
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

