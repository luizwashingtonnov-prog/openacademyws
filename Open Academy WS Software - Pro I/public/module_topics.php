<?php
require_once __DIR__ . '/../includes/auth.php';

require_roles(['admin', 'gestor']);

$db = get_db();
$moduleId = (int) ($_GET['module_id'] ?? 0);

if ($moduleId <= 0) {
    flash('Modulo invalido.', 'warning');
    redirect('courses.php');
}

$stmtModule = $db->prepare('SELECT m.id, m.title, m.position, c.id AS course_id, c.title AS course_title
    FROM course_modules m
    INNER JOIN courses c ON c.id = m.course_id
    WHERE m.id = ?');
$stmtModule->bind_param('i', $moduleId);
$stmtModule->execute();
$module = $stmtModule->get_result()->fetch_assoc();

if (!$module) {
    flash('Modulo nao encontrado.', 'warning');
    redirect('courses.php');
}

$courseId = (int) $module['course_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $title = trim($_POST['topic_title'] ?? '');
        $description = trim($_POST['topic_description'] ?? '');
        $videoUrlInput = trim($_POST['topic_video_url'] ?? '');
        $videoFile = $_FILES['topic_video_file'] ?? null;
        $pdfFile = $_FILES['topic_pdf_file'] ?? null;

        $uploadedVideoPath = null;
        $uploadedPdfPath = null;

        if ($title === '') {
            flash('Informe um titulo para o topico.', 'warning');
        } elseif ($videoUrlInput !== '' && !filter_var($videoUrlInput, FILTER_VALIDATE_URL)) {
            flash('Informe uma URL valida para o video do topico.', 'warning');
        } else {
            $errorMessage = null;

            if ($videoFile && ($videoFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $videoUpload = store_uploaded_video($videoFile);
                if (!$videoUpload['success']) {
                    $errorMessage = $videoUpload['message'];
                } else {
                    $uploadedVideoPath = $videoUpload['path'];
                }
            }

            if ($errorMessage === null && $pdfFile && ($pdfFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $pdfUpload = store_uploaded_pdf($pdfFile);
                if (!$pdfUpload['success']) {
                    $errorMessage = $pdfUpload['message'];
                } else {
                    $uploadedPdfPath = $pdfUpload['path'];
                }
            }

            if ($errorMessage !== null) {
                if ($uploadedVideoPath !== null) {
                    delete_uploaded_file($uploadedVideoPath);
                }
                if ($uploadedPdfPath !== null) {
                    delete_uploaded_file($uploadedPdfPath);
                }
                flash($errorMessage, 'warning');
            } else {
                $stmtPosition = $db->prepare('SELECT COALESCE(MAX(position), 0) + 1 AS next_pos FROM module_topics WHERE module_id = ?');
                $stmtPosition->bind_param('i', $moduleId);
                $stmtPosition->execute();
                $nextPos = (int) ($stmtPosition->get_result()->fetch_assoc()['next_pos'] ?? 1);

                $videoValue = $uploadedVideoPath ?? ($videoUrlInput !== '' ? $videoUrlInput : null);
                $pdfValue = $uploadedPdfPath ?? null;

                $stmtInsert = $db->prepare('INSERT INTO module_topics (module_id, title, description, video_url, pdf_url, position) VALUES (?, ?, ?, ?, ?, ?)');
                $stmtInsert->bind_param('issssi', $moduleId, $title, $description, $videoValue, $pdfValue, $nextPos);
                if ($stmtInsert->execute()) {
                    flash('Topico criado com sucesso.', 'success');
                } else {
                    if ($uploadedVideoPath !== null) {
                        delete_uploaded_file($uploadedVideoPath);
                    }
                    if ($uploadedPdfPath !== null) {
                        delete_uploaded_file($uploadedPdfPath);
                    }
                    flash('Nao foi possivel criar o topico.', 'danger');
                }
            }
        }
    }

    if ($action === 'update') {
        $topicId = (int) ($_POST['topic_id'] ?? 0);
        $title = trim($_POST['topic_title'] ?? '');
        $description = trim($_POST['topic_description'] ?? '');
        $videoUrlInput = trim($_POST['topic_video_url'] ?? '');
        $removeVideo = isset($_POST['topic_remove_video']);
        $removePdf = isset($_POST['topic_remove_pdf']);
        $videoFile = $_FILES['topic_video_file'] ?? null;
        $pdfFile = $_FILES['topic_pdf_file'] ?? null;

        if ($topicId <= 0) {
            flash('Topico invalido.', 'warning');
        } elseif ($title === '') {
            flash('Informe um titulo para o topico.', 'warning');
        } elseif ($videoUrlInput !== '' && !filter_var($videoUrlInput, FILTER_VALIDATE_URL)) {
            flash('Informe uma URL valida para o video do topico.', 'warning');
        } else {
            $stmtTopic = $db->prepare('SELECT id, video_url, pdf_url FROM module_topics WHERE id = ? AND module_id = ?');
            $stmtTopic->bind_param('ii', $topicId, $moduleId);
            $stmtTopic->execute();
            $topicRow = $stmtTopic->get_result()->fetch_assoc();

            if (!$topicRow) {
                flash('Topico nao encontrado.', 'warning');
            } else {
                $currentVideo = $topicRow['video_url'] ?? null;
                $currentPdf = $topicRow['pdf_url'] ?? null;
                $newVideoPath = $currentVideo;
                $newPdfPath = $currentPdf;
                $uploadedVideoPath = null;
                $uploadedPdfPath = null;
                $videoToDeleteAfterUpdate = null;
                $pdfToDeleteAfterUpdate = null;
                $errorMessage = null;

                if ($videoFile && ($videoFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $videoUpload = store_uploaded_video($videoFile);
                    if (!$videoUpload['success']) {
                        $errorMessage = $videoUpload['message'];
                    } else {
                        $uploadedVideoPath = $videoUpload['path'];
                    }
                }

                if ($errorMessage === null && $pdfFile && ($pdfFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $pdfUpload = store_uploaded_pdf($pdfFile);
                    if (!$pdfUpload['success']) {
                        $errorMessage = $pdfUpload['message'];
                    } else {
                        $uploadedPdfPath = $pdfUpload['path'];
                    }
                }

                if ($errorMessage !== null) {
                    if ($uploadedVideoPath !== null) {
                        delete_uploaded_file($uploadedVideoPath);
                    }
                    if ($uploadedPdfPath !== null) {
                        delete_uploaded_file($uploadedPdfPath);
                    }
                    flash($errorMessage, 'warning');
                } else {
                    if ($uploadedVideoPath !== null) {
                        if ($currentVideo && !is_external_url($currentVideo)) {
                            $videoToDeleteAfterUpdate = $currentVideo;
                        }
                        $newVideoPath = $uploadedVideoPath;
                    } elseif ($removeVideo) {
                        if ($currentVideo && !is_external_url($currentVideo)) {
                            $videoToDeleteAfterUpdate = $currentVideo;
                        }
                        $newVideoPath = null;
                    } elseif ($videoUrlInput !== '') {
                        if ($currentVideo && !is_external_url($currentVideo)) {
                            $videoToDeleteAfterUpdate = $currentVideo;
                        }
                        $newVideoPath = $videoUrlInput;
                    }

                    if ($uploadedPdfPath !== null) {
                        if ($currentPdf && !is_external_url($currentPdf)) {
                            $pdfToDeleteAfterUpdate = $currentPdf;
                        }
                        $newPdfPath = $uploadedPdfPath;
                    } elseif ($removePdf) {
                        if ($currentPdf && !is_external_url($currentPdf)) {
                            $pdfToDeleteAfterUpdate = $currentPdf;
                        }
                        $newPdfPath = null;
                    }

                    $stmtUpdate = $db->prepare('UPDATE module_topics SET title = ?, description = ?, video_url = ?, pdf_url = ? WHERE id = ?');
                    $stmtUpdate->bind_param('ssssi', $title, $description, $newVideoPath, $newPdfPath, $topicId);
                    if ($stmtUpdate->execute()) {
                        if ($videoToDeleteAfterUpdate !== null) {
                            delete_uploaded_file($videoToDeleteAfterUpdate);
                        }
                        if ($pdfToDeleteAfterUpdate !== null) {
                            delete_uploaded_file($pdfToDeleteAfterUpdate);
                        }
                        flash('Topico atualizado com sucesso.', 'success');
                    } else {
                        if ($uploadedVideoPath !== null) {
                            delete_uploaded_file($uploadedVideoPath);
                        }
                        if ($uploadedPdfPath !== null) {
                            delete_uploaded_file($uploadedPdfPath);
                        }
                        flash('Nao foi possivel atualizar o topico.', 'danger');
                    }
                }
            }
        }
    }

    if ($action === 'delete') {
        $topicId = (int) ($_POST['topic_id'] ?? 0);
        if ($topicId <= 0) {
            flash('Topico invalido.', 'warning');
        } else {
            $stmtTopic = $db->prepare('SELECT id, video_url, pdf_url FROM module_topics WHERE id = ? AND module_id = ?');
            $stmtTopic->bind_param('ii', $topicId, $moduleId);
            $stmtTopic->execute();
            $topicRow = $stmtTopic->get_result()->fetch_assoc();
            if (!$topicRow) {
                flash('Topico nao encontrado.', 'warning');
            } else {
                $stmtDelete = $db->prepare('DELETE FROM module_topics WHERE id = ?');
                $stmtDelete->bind_param('i', $topicId);
                if ($stmtDelete->execute()) {
                    delete_uploaded_file($topicRow['video_url'] ?? null);
                    delete_uploaded_file($topicRow['pdf_url'] ?? null);
                    flash('Topico removido.', 'success');
                } else {
                    flash('Erro ao remover topico.', 'danger');
                }
            }
        }
    }

    redirect('module_topics.php?module_id=' . $moduleId);
}

$stmtTopics = $db->prepare('SELECT id, title, description, video_url, pdf_url, position FROM module_topics WHERE module_id = ? ORDER BY position, id');
$stmtTopics->bind_param('i', $moduleId);
$stmtTopics->execute();
$topics = $stmtTopics->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Topicos do modulo';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/alerts.php';
?>
<div class="mx-auto max-w-7xl space-y-8 px-4 pb-12">
    <div class="rounded-3xl border border-slate-200 bg-white/90 p-8 shadow shadow-slate-900/10">
        <span class="inline-flex items-center gap-2 rounded-full bg-brand-red/10 px-4 py-1 text-xs font-semibold uppercase tracking-[0.3em] text-brand-red">Modulo: <?php echo htmlspecialchars($module['title']); ?></span>
        <h1 class="mt-4 text-2xl font-bold tracking-tight text-brand-gray">Tópicos cadastrados</h1>
        <p class="mt-2 text-sm text-slate-500">Organize o conteudo em tópicos. Cada topico pode receber videos e materiais em PDF.</p>
        <div class="mt-4 flex flex-wrap gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
            <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1">Curso: <?php echo htmlspecialchars($module['course_title']); ?></span>
            <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1">Ordem do modulo: <?php echo (int) ($module['position'] ?? 0); ?></span>
            <a href="course_detail.php?course_id=<?php echo $courseId; ?>" class="inline-flex items-center rounded-full border border-brand-red/30 px-3 py-1 text-brand-red transition hover:bg-brand-red hover:text-white">Voltar para o curso</a>
        </div>
    </div>

    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow shadow-slate-900/10">
        <?php if (empty($topics)): ?>
            <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-5 py-8 text-center text-sm text-slate-500">Nenhum tópico cadastrado até o momento. Utilize o formulário abaixo para criar o primeiro tópico.</div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($topics as $topic): ?>
                    <?php
                        $videoRaw = $topic['video_url'] ?? '';
                        $videoLink = $videoRaw !== '' ? (is_external_url($videoRaw) ? $videoRaw : asset_url($videoRaw)) : '';
                        $hasVideo = $videoLink !== '';
                        $pdfRaw = $topic['pdf_url'] ?? '';
                        $pdfLink = $pdfRaw !== '' ? asset_url($pdfRaw) : '';
                        $hasPdf = $pdfLink !== '';
                    ?>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-5 py-5">
                        <form method="post" class="space-y-3" novalidate enctype="multipart/form-data">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="topic_id" value="<?php echo (int) $topic['id']; ?>">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <span class="inline-flex items-center rounded-full bg-white px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-500">Topico <?php echo (int) $topic['position']; ?></span>
                                <button type="submit" class="inline-flex items-center rounded-full bg-brand-red px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white shadow-glow transition hover:bg-brand-redDark focus:outline-none focus:ring-4 focus:ring-brand-red/30">Salvar topico</button>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500" for="topic_title_<?php echo (int) $topic['id']; ?>">Titulo do topico</label>
                                <input type="text" id="topic_title_<?php echo (int) $topic['id']; ?>" name="topic_title" required value="<?php echo htmlspecialchars($topic['title']); ?>" class="block w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-brand-red focus:outline-none focus:ring-4 focus:ring-brand-red/20">
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500" for="topic_description_<?php echo (int) $topic['id']; ?>">Descricao</label>
                                <textarea id="topic_description_<?php echo (int) $topic['id']; ?>" name="topic_description" rows="3" class="block w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-brand-red focus:outline-none focus:ring-4 focus:ring-brand-red/20"><?php echo htmlspecialchars($topic['description'] ?? ''); ?></textarea>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500" for="topic_video_url_<?php echo (int) $topic['id']; ?>">Video do tópico (URL)</label>
                                    <input type="url" id="topic_video_url_<?php echo (int) $topic['id']; ?>" name="topic_video_url" value="<?php echo htmlspecialchars(is_external_url($videoRaw) ? $videoRaw : ''); ?>" placeholder="https://..." class="block w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-brand-red focus:outline-none focus:ring-4 focus:ring-brand-red/20">
                                    <p class="mt-1 text-xs text-slate-500">Opcional. Informe um link (YouTube, Vimeo, MP4) ou envie um arquivo.</p>
                                    <?php if ($hasVideo): ?>
                                        <div class="mt-2 rounded-2xl bg-white px-4 py-3 text-xs text-slate-600 shadow-sm">
                                            <span class="font-semibold text-brand-gray">Video atual:</span>
                                            <a href="<?php echo htmlspecialchars($videoLink); ?>" target="_blank" class="ml-2 underline transition hover:text-brand-red">Abrir</a>
                                        </div>
                                        <label class="mt-2 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                            <input type="checkbox" name="topic_remove_video" value="1" class="h-4 w-4 rounded border-slate-300 text-brand-red focus:ring-brand-red">
                                            Remover video atual
                                        </label>
                                    <?php endif; ?>
                                    <input type="file" name="topic_video_file" accept="video/mp4,video/webm,video/ogg,video/ogv,video/x-m4v" class="mt-3 block w-full rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-brand-red focus:outline-none focus:ring-4 focus:ring-brand-red/20">
                                    <p class="mt-1 text-xs text-slate-500">Limite de 200 MB.</p>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500" for="topic_pdf_file_<?php echo (int) $topic['id']; ?>">Material PDF</label>
                                    <?php if ($hasPdf): ?>
                                        <div class="mb-2 rounded-2xl bg-white px-4 py-3 text-xs text-slate-600 shadow-sm">
                                            <span class="font-semibold text-brand-gray">PDF atual:</span>
                                            <a href="<?php echo htmlspecialchars($pdfLink); ?>" target="_blank" class="ml-2 underline transition hover:text-brand-red">Abrir</a>
                                        </div>
                                        <label class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                            <input type="checkbox" name="topic_remove_pdf" value="1" class="h-4 w-4 rounded border-slate-300 text-brand-red focus:ring-brand-red">
                                            Remover PDF atual
                                        </label>
                                    <?php endif; ?>
                                    <input type="file" name="topic_pdf_file" accept="application/pdf" class="mt-2 block w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-brand-red focus:outline-none focus:ring-4 focus:ring-brand-red/20">
                                    <p class="mt-1 text-xs text-slate-500">Envie um PDF ate 10 MB.</p>
                                </div>
                            </div>
                        </form>
                        <form method="post" class="mt-3 flex justify-end" novalidate>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="topic_id" value="<?php echo (int) $topic['id']; ?>">
                            <button type="submit" data-confirm="Excluir este topico?" class="inline-flex items-center rounded-full border border-rose-300 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-rose-600 transition hover:bg-rose-500 hover:text-white">Remover topico</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow shadow-slate-900/10">
        <h2 class="text-lg font-semibold text-brand-gray">Adicionar novo topico</h2>
        <form method="post" class="mt-3 space-y-3" novalidate enctype="multipart/form-data">
            <input type="hidden" name="action" value="create">
            <div>
                <label for="topic_title_new" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Titulo</label>
                <input type="text" id="topic_title_new" name="topic_title" required placeholder="Ex.: Conceitos iniciais" class="block w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-brand-red focus:outline-none focus:ring-4 focus:ring-brand-red/20">
            </div>
            <div>
                <label for="topic_description_new" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Descricao</label>
                <textarea id="topic_description_new" name="topic_description" rows="3" placeholder="Inclua o resumo do topico" class="block w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-brand-red focus:outline-none focus:ring-4 focus:ring-brand-red/20"></textarea>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label for="topic_video_url_new" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Video (URL)</label>
                    <input type="url" id="topic_video_url_new" name="topic_video_url" placeholder="https://..." class="block w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-brand-red focus:outline-none focus:ring-4 focus:ring-brand-red/20">
                    <p class="mt-1 text-xs text-slate-500">Opcional. Informe um link ou envie um arquivo abaixo.</p>
                    <input type="file" name="topic_video_file" accept="video/mp4,video/webm,video/ogg,video/ogv,video/x-m4v" class="mt-3 block w-full rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-brand-red focus:outline-none focus:ring-4 focus:ring-brand-red/20">
                    <p class="mt-1 text-xs text-slate-500">Limite de 200 MB.</p>
                </div>
                <div>
                    <label for="topic_pdf_file_new" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Material PDF</label>
                    <input type="file" id="topic_pdf_file_new" name="topic_pdf_file" accept="application/pdf" class="block w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-brand-red focus:outline-none focus:ring-4 focus:ring-brand-red/20">
                    <p class="mt-1 text-xs text-slate-500">Opcional. Envie um PDF de apoio (limite 10 MB).</p>
                </div>
            </div>
            <button type="submit" class="inline-flex items-center rounded-full bg-brand-red px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white shadow-glow transition hover:bg-brand-redDark focus:outline-none focus:ring-4 focus:ring-brand-red/30">Adicionar topico</button>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
