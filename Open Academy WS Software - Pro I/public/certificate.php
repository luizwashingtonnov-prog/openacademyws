<?php
require_once __DIR__ . '/../includes/auth.php';

require_roles(['aluno', 'admin', 'gestor']);

$db = get_db();
$viewer = current_user();
$isManager = in_array($viewer['role'], ['admin', 'gestor'], true);
$courseId = (int) ($_GET['course_id'] ?? 0);
$targetUserId = $isManager ? (int) ($_GET['user_id'] ?? 0) : (int) $viewer['id'];
$downloadRequested = isset($_GET['download']) && $_GET['download'] === '1';

if (!$isManager) {
    $targetUserId = (int) $viewer['id'];
}

$fallbackBase = $isManager ? 'courses.php' : 'my_courses.php';

if ($courseId <= 0) {
    flash('Curso invalido.', 'warning');
    redirect($fallbackBase);
}

$stmtCourse = $db->prepare('SELECT id, title, workload, certificate_validity_months, created_by FROM courses WHERE id = ?');
$stmtCourse->bind_param('i', $courseId);
$stmtCourse->execute();
$course = $stmtCourse->get_result()->fetch_assoc();

if (!$course) {
    flash('Curso nao encontrado.', 'warning');
    redirect($fallbackBase);
}

$courseDetailUrl = $isManager ? 'course_detail.php?course_id=' . $courseId : 'my_courses.php';

if ($isManager && $targetUserId <= 0) {
    flash('Selecione um aluno para visualizar o certificado.', 'warning');
    redirect($courseDetailUrl);
}

$student = $viewer;
if ($isManager) {
    $stmtStudent = $db->prepare('SELECT id, name, role, photo_path, signature_name, signature_title, signature_path FROM users WHERE id = ?');
    $stmtStudent->bind_param('i', $targetUserId);
    $stmtStudent->execute();
    $student = $stmtStudent->get_result()->fetch_assoc() ?: null;

    if (!$student || $student['role'] !== 'aluno') {
        flash('Aluno não encontrado.', 'warning');
        redirect($courseDetailUrl);
    }
} else {
    if ($targetUserId !== (int) $viewer['id']) {
        flash('Acesso não autorizado.', 'danger');
        redirect($courseDetailUrl);
    }

    $stmtStudent = $db->prepare('SELECT id, name, role, photo_path, signature_name, signature_title, signature_path FROM users WHERE id = ?');
    $stmtStudent->bind_param('i', $targetUserId);
    $stmtStudent->execute();
    $studentRow = $stmtStudent->get_result()->fetch_assoc();
    if ($studentRow) {
        $student = $studentRow;
    } else {
        $student['photo_path'] = $viewer['photo_path'] ?? null;
        $student['signature_name'] = $viewer['signature_name'] ?? null;
        $student['signature_title'] = $viewer['signature_title'] ?? null;
        $student['signature_path'] = $viewer['signature_path'] ?? null;
    }
}

$allowDownload = $isManager || $targetUserId === (int) $viewer['id'];
$shouldDownload = $downloadRequested && $allowDownload;

$stmtResult = $db->prepare('SELECT id, score, approved, certificate_code, issued_at, created_at FROM assessment_results WHERE course_id = ? AND user_id = ?');
$stmtResult->bind_param('ii', $courseId, $student['id']);
$stmtResult->execute();
$result = $stmtResult->get_result()->fetch_assoc();

if (!$result || !(int) $result['approved']) {
    $warningMessage = $isManager ? 'Aluno ainda não foi aprovado neste curso.' : 'Certificado disponivel apenas para alunos aprovados.';
    flash($warningMessage, 'warning');
    redirect($courseDetailUrl);
}

$studentPhotoUrl = asset_url($student['photo_path'] ?? ($viewer['photo_path'] ?? null));
$signatureName = trim((string) ($student['signature_name'] ?? ''));
if ($signatureName === '') {
    $signatureName = $student['name'];
}
$signatureTitle = trim((string) ($student['signature_title'] ?? ''));
$signatureImageUrl = asset_url($student['signature_path'] ?? null);

$creatorSignatureName = null;
$creatorSignatureTitle = null;
$creatorSignatureImageUrl = null;
if (!empty($course['created_by'])) {
    $stmtCreator = $db->prepare('SELECT name, signature_name, signature_title, signature_path FROM users WHERE id = ? LIMIT 1');
    if ($stmtCreator) {
        $stmtCreator->bind_param('i', $course['created_by']);
        $stmtCreator->execute();
        if ($creatorRow = $stmtCreator->get_result()->fetch_assoc()) {
            $creatorSignatureName = trim((string) ($creatorRow['signature_name'] ?? ''));
            if ($creatorSignatureName === '') {
                $creatorSignatureName = $creatorRow['name'] ?? null;
            }
            $creatorSignatureTitle = trim((string) ($creatorRow['signature_title'] ?? ''));
            $creatorSignatureImageUrl = asset_url($creatorRow['signature_path'] ?? null);
        }
        $stmtCreator->close();
    }
}

if ($shouldDownload) {
    $safeName = preg_replace('/[^a-z0-9]+/i', '-', strtolower($student['name']));
    $safeName = trim($safeName, '-');
    if ($safeName === '') {
        $safeName = 'aluno';
    }
    $filename = 'certificado-' . $safeName . '-curso-' . $courseId . '.html';
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
}

if (empty($result['certificate_code'])) {
    $code = strtoupper(bin2hex(random_bytes(4)));
    $stmtUpdate = $db->prepare('UPDATE assessment_results SET certificate_code = ?, issued_at = NOW() WHERE id = ?');
    $stmtUpdate->bind_param('si', $code, $result['id']);
    $stmtUpdate->execute();
    $result['certificate_code'] = $code;
    $result['issued_at'] = date('Y-m-d H:i:s');
}

$certificateValidityMonths = $course['certificate_validity_months'] ?? null;
if ($certificateValidityMonths !== null) {
    $certificateValidityMonths = (int) $certificateValidityMonths;
    if ($certificateValidityMonths <= 0) {
        $certificateValidityMonths = null;
    }
}

$issuedAtRaw = $result['issued_at'] ?? $result['created_at'] ?? date('Y-m-d H:i:s');
try {
    $issuedAtDate = new DateTime($issuedAtRaw);
} catch (Exception $exception) {
    $issuedAtDate = new DateTime();
}
$issuedAtDisplay = $issuedAtDate->format('d/m/Y');

$certificateExpiryDisplay = null;
if ($certificateValidityMonths !== null) {
    try {
        $expiryDate = clone $issuedAtDate;
        $expiryDate->add(new DateInterval('P' . $certificateValidityMonths . 'M'));
        $certificateExpiryDisplay = $expiryDate->format('d/m/Y');
    } catch (Exception $exception) {
        $certificateValidityMonths = null;
    }
}
$stmtModules = $db->prepare('SELECT id, title, description, position FROM course_modules WHERE course_id = ? ORDER BY position, id');
$stmtModules->bind_param('i', $courseId);
$stmtModules->execute();
$modules = $stmtModules->get_result()->fetch_all(MYSQLI_ASSOC);
$hasProgrammaticContent = !empty($modules);
$pageTitle = 'Certificado - ' . $course['title'];
$assetBase = BASE_URL === '/' ? '' : BASE_URL;
require_once __DIR__ . '/../includes/header.php';
// Intentionally não incluímos o menu lateral para manter o certificado limpo/imprimível
echo '<div class="print-hidden">';
require_once __DIR__ . '/../includes/alerts.php';
echo '</div>';
?>
<style>
.certificate-page {
    position: relative;
}

.certificate-container {
    display: internal;
    flex-direction: column;
    gap: 2.5rem;
    align-items: center;
}

.certificate-sheet {
    width: min(297mm, 90%);
    min-height: 210mm;
    height: auto;
    background: #fff;
}

.certificate-photo {
    width: 96px;
    height: 128px;
    border: 4px solid #b22222;
    border-radius: 1rem;
    overflow: hidden;
    background: #fff;
    box-shadow: 0 10px 16px -8px rgba(178, 34, 34, 0.4);
}

.certificate-photo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.certificate-signatures {
    display: grid;
    gap: 2rem;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    align-items: flex-end;
}

.certificate-signature-card {
    text-align: center;
}

.certificate-signature-label {
    font-size: 0.65rem;
    letter-spacing: 0.35em;
    text-transform: uppercase;
    color: #64748b;
    display: block;
    margin-bottom: 0.75rem;
}

.certificate-back {
    margin-top: 3rem;
}

.certificate-program {
    display: inside;
    gap: 2rem;
}

.certificate-program-topic span {
    min-width: 1.5rem;
}

@media screen and (max-width: 1200px) {
    .certificate-sheet {
        width: 100%;
        min-height: auto;
    }
}

@media print {
    @page {
        size: A4 landscape;
        margin: 12mm;
    }

    html {
        background: #fff !important;
        margin: 0 !important;
    }

    body {
        background: #fff !important;
        margin: 0 !important;
    }

    nav,
    footer,
    .certificate-print-button,
    .alerts-container,
    .print-hidden {
        display: none !important;
    }

    .certificate-page {
        padding: 0 !important;
        background: #fff !important;
    }

    .certificate-container {
        width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    .certificate-sheet {
        border-radius: 0 !important;
        border: 1px solid #d1d5db !important;
        box-shadow: none !important;
        padding: 18mm !important;
        background: #fff !important;
        position: relative !important;
        width: calc(297mm - 24mm) !important;
        min-height: calc(210mm - 24mm) !important;
        height: calc(210mm - 24mm) !important;
        margin: 0 auto !important;
        page-break-inside: avoid !important;
        page-break-after: always;
        overflow: hidden !important;
    }

    .certificate-photo {
        box-shadow: none !important;
        border: 4px solid #b22222 !important;
    }

    .certificate-signature img {
        max-height: 240px !important;
        width: auto !important;
    }

    .certificate-signatures {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .certificate-back {
        page-break-before: always !important;
        page-break-after: avoid !important;
    }
}
</style>
<div class="relative py-16 certificate-page">
    <div class="pointer-events-none absolute inset-0 bg-gradient-to-br from-brand-red/10 via-transparent to-brand-gray/10 print-hidden"></div>
    <div class="relative mx-auto max-w-5xl px-4 certificate-container">
        <div class="relative rounded-[2.5rem] border border-brand-red/40 bg-white/95 px-10 py-12 shadow-2xl shadow-brand-red/20 backdrop-blur certificate-sheet">
            <div class="absolute -top-4 right-6 certificate-print-button">
                <button onclick="window.print()" class="inline-flex items-center rounded-full bg-brand-red px-4 py-2 text-xs font-semibold uppercase tracking-wider text-white shadow hover:bg-brand-redDark focus:outline-none focus:ring-4 focus:ring-brand-red/30">
                    Imprimir
                </button>
            </div>
            <div class="flex flex-col items-center gap-6 md:flex-row md:items-start md:justify-between">
                <?php if ($studentPhotoUrl): ?>
                    <div class="certificate-photo shadow shadow-brand-red/20">
                        <img src="<?php echo htmlspecialchars($studentPhotoUrl); ?>" alt="Foto 3x4 do aluno <?php echo htmlspecialchars($student['name']); ?>">
                    </div>
                <?php endif; ?>
                <div class="text-center flex-1">
                    <img src="<?php echo htmlspecialchars($assetBase); ?>/assets/nov-national-oilwell-varco5792.png" alt="Logo NOV" class="mx-auto mb-6 h-16 w-auto md:h-20" loading="lazy">
                    <h1 class="text-3xl font-bold uppercase tracking-[0.3em] text-brand-red">Certificado de conclusão</h1>
                    <p class="mt-2 text-sm uppercase tracking-[0.4em] text-slate-400">Reconhecimento de desempenho acadêmico</p>
                </div>
            </div>
            <div class="mt-8 space-y-4 text-slate-600">
                <p class="text-lg leading-relaxed">Certificamos que <span class="font-semibold text-brand-red"><?php echo htmlspecialchars($student['name']); ?></span> concluiu com aproveitamento o curso <span class="font-semibold text-brand-gray"><?php echo htmlspecialchars($course['title']); ?></span>.</p>
                <p class="text-sm leading-relaxed">A carga horária total foi de <span class="font-semibold text-brand-gray"><?php echo (int) ($course['workload'] ?? 0); ?> horas</span> e o(a) aluno(a) obteve nota final <span class="font-semibold text-brand-red"><?php echo number_format((float) $result['score'], 1); ?></span>, cumprindo os requisitos minimos de aprovacão.</p>
            </div>
            <div class="mt-10 grid gap-4 border-y border-dashed border-slate-200 py-6 text-sm text-slate-500 md:grid-cols-2 lg:grid-cols-3">
                <div>
                    <p class="text-xs uppercase tracking-[0.4em] text-slate-400">Data de emissão</p>
                    <p class="mt-2 text-lg font-semibold text-brand-gray"><?php echo htmlspecialchars($issuedAtDisplay); ?></p>
                </div>
                <div class="md:text-right">
                    <p class="text-xs uppercase tracking-[0.4em] text-slate-400">Código de validacão</p>
                    <p class="mt-2 text-lg font-semibold text-brand-red"><?php echo htmlspecialchars($result['certificate_code']); ?></p>
                </div>
                <?php if ($certificateValidityMonths !== null && $certificateExpiryDisplay !== null): ?>
                    <div class="md:text-right">
                        <p class="text-xs uppercase tracking-[0.4em] text-slate-400">Válido até</p>
                        <p class="mt-2 text-lg font-semibold text-brand-gray"><?php echo htmlspecialchars($certificateExpiryDisplay); ?></p>
                        <p class="text-xs text-slate-500">Validade: <?php echo (int) $certificateValidityMonths; ?> mês(es)</p>
                    </div>
                <?php endif; ?>
            </div>
            <div class="mt-10 certificate-signatures">
                <?php if ($creatorSignatureName !== null): ?>
                    <div class="certificate-signature-card">
                        <span class="certificate-signature-label">Responsável pelo curso</span>
                        <?php if ($creatorSignatureImageUrl): ?>
                            <img src="<?php echo htmlspecialchars($creatorSignatureImageUrl); ?>" alt="Assinatura do responsável" class="mx-auto h-24 w-auto object-contain">
                        <?php else: ?>
                            <p class="text-2xl text-brand-gray">______________________________</p>
                        <?php endif; ?>
                        <p class="mt-4 text-sm font-semibold uppercase tracking-wide text-brand-gray"><?php echo htmlspecialchars($creatorSignatureName); ?></p>
                        <?php if ($creatorSignatureTitle !== null && $creatorSignatureTitle !== ''): ?>
                            <p class="text-xs uppercase tracking-[0.3em] text-slate-400"><?php echo htmlspecialchars($creatorSignatureTitle); ?></p>
                        <?php endif; ?>
                        <p class="mt-2 text-[8px] uppercase tracking-[0.2em] text-slate-400">Responsável pela emissão</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="relative rounded-[2.5rem] border border-brand-red/30 bg-white/95 px-10 py-12 shadow-xl shadow-brand-red/15 backdrop-blur certificate-sheet certificate-back">
            <div class="mb-8 text-center">
                <h2 class="text-2xl font-bold uppercase tracking-[0.3em] text-brand-gray">Conteúdo programático</h2>
                <p class="mt-2 text-sm uppercase tracking-[0.35em] text-slate-400">Verso do certificado</p>
            </div>
            <?php if ($hasProgrammaticContent): ?>
                <div class="certificate-program">
                    <?php foreach ($modules as $moduleIndex => $module): ?>
                        <div class="certificate-program-section rounded-2xl border border-slate-200 bg-white/80 p-6 shadow-sm">
                            <h3 class="text-lg font-semibold uppercase tracking-wide text-brand-red">
                                Módulo <?php echo $moduleIndex + 1; ?>: <?php echo htmlspecialchars($module['title']); ?>
                            </h3>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-center text-sm text-slate-500">Conteúdo programático em atualização.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
