<?php
require_once __DIR__ . '/../includes/auth.php';

require_login();

$courseId = (int) ($_GET['course_id'] ?? 0);
if ($courseId <= 0) {
    flash('Curso nao encontrado.', 'warning');
    redirect('dashboard.php');
}

$db = get_db();
$user = current_user();

$pdfCourse = null;

if (has_role('aluno')) {
    $stmt = $db->prepare('SELECT c.id, c.title, c.pdf_url FROM courses c
        INNER JOIN enrollments e ON e.course_id = c.id
        WHERE c.id = ? AND e.user_id = ?
        LIMIT 1');
    $stmt->bind_param('ii', $courseId, $user['id']);
    $stmt->execute();
    $pdfCourse = $stmt->get_result()->fetch_assoc();
} elseif (has_role('admin') || has_role('gestor')) {
    $stmt = $db->prepare('SELECT c.id, c.title, c.pdf_url FROM courses c WHERE c.id = ? LIMIT 1');
    $stmt->bind_param('i', $courseId);
    $stmt->execute();
    $pdfCourse = $stmt->get_result()->fetch_assoc();
} else {
    flash('Você não tem permissao para acessar este material.', 'warning');
    redirect('dashboard.php');
}

if (!$pdfCourse) {
    flash('Material não encontrado ou você nao possui acesso.', 'warning');
    if (has_role('aluno')) {
        redirect('my_courses.php');
    }
    redirect('courses.php');
}

$pdfPath = $pdfCourse['pdf_url'] ?? '';
if ($pdfPath === '') {
    flash('O curso ainda não possui material em PDF disponivel.', 'warning');
    if (has_role('aluno')) {
        redirect('my_courses.php');
    }
    redirect('course_detail.php?course_id=' . $courseId);
}

$pdfUrl = asset_url($pdfPath);
if ($pdfUrl === '') {
    flash('O curso ainda não possui material em PDF disponivel.', 'warning');
    if (has_role('aluno')) {
        redirect('my_courses.php');
    }
    redirect('course_detail.php?course_id=' . $courseId);
}

$downloadName = 'material.pdf';
if (!is_external_url($pdfPath)) {
    $pathOnDisk = __DIR__ . '/' . ltrim($pdfPath, '/');
    if (!is_file($pathOnDisk)) {
        flash('O material em PDF não esta disponivel no momento.', 'warning');
        if (has_role('aluno')) {
            redirect('my_courses.php');
        }
        redirect('course_detail.php?course_id=' . $courseId);
    }
    $downloadName = basename($pathOnDisk) ?: $downloadName;
} else {
    $parsed = parse_url($pdfUrl, PHP_URL_PATH);
    if (is_string($parsed) && $parsed !== '') {
        $downloadName = basename($parsed);
    }
}
$pageTitle = 'Material do curso: ' . ($pdfCourse['title'] ?? '');

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/alerts.php';
?>
<div class="mx-auto max-w-6xl space-y-6 px-4 pb-12">
    <div class="flex flex-col gap-4 rounded-3xl border border-slate-200 bg-white/90 p-8 shadow shadow-slate-900/10">
        <div>
            <span class="inline-flex items-center gap-2 rounded-full bg-brand-red/10 px-4 py-1 text-xs font-semibold uppercase tracking-[0.3em] text-brand-red">Material em PDF</span>
            <h1 class="mt-4 text-2xl font-bold tracking-tight text-brand-gray"><?php echo htmlspecialchars($pdfCourse['title']); ?></h1>
            <p class="mt-2 text-sm text-slate-500">Leia o material diretamente na plataforma ou baixe o arquivo para estudar offline.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="<?php echo htmlspecialchars($pdfUrl); ?>" target="_blank" class="inline-flex items-center rounded-2xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-700 hover:text-white">Abrir em nova aba</a>
            <a href="<?php echo htmlspecialchars($pdfUrl); ?>" download="<?php echo htmlspecialchars($downloadName); ?>" class="inline-flex items-center rounded-2xl bg-brand-red px-4 py-2 text-sm font-semibold text-white shadow-glow transition hover:bg-brand-redDark">Baixar PDF</a>
        </div>
    </div>
    <div class="rounded-3xl border border-slate-200 bg-white shadow shadow-slate-900/10">
        <iframe
            src="<?php echo htmlspecialchars($pdfUrl); ?>#toolbar=1"
            title="Visualizador de PDF"
            class="h-[80vh] w-full rounded-3xl"
            loading="lazy"
        ></iframe>
        <div class="px-6 py-4 text-xs text-slate-500">
            Caso o PDF não seja exibido corretamente, utilize as opções acima para abrir o arquivo em nova aba ou realizar o download.
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

