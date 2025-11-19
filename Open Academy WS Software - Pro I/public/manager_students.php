<?php
require_once __DIR__ . '/../includes/auth.php';

require_roles(['gestor', 'admin']);

$db = get_db();

$studentsResult = $db->query('SELECT id, name, email FROM users WHERE role = "aluno" ORDER BY name');
$students = $studentsResult ? $studentsResult->fetch_all(MYSQLI_ASSOC) : [];

$courseDataByStudent = [];

if (!empty($students)) {
    $studentIds = array_column($students, 'id');
    $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
    $types = str_repeat('i', count($studentIds));

    $stmtEnrollments = $db->prepare(
        'SELECT e.user_id, c.id AS course_id, c.title AS course_title, c.workload,
                ar.score, ar.approved, ar.certificate_code, ar.issued_at, ar.created_at
         FROM enrollments e
         INNER JOIN courses c ON c.id = e.course_id
         LEFT JOIN assessment_results ar ON ar.user_id = e.user_id AND ar.course_id = e.course_id
         WHERE e.user_id IN (' . $placeholders . ')
         ORDER BY c.title'
    );
    $stmtEnrollments->bind_param($types, ...$studentIds);
    $stmtEnrollments->execute();
    $enrollments = $stmtEnrollments->get_result();
    while ($row = $enrollments->fetch_assoc()) {
        $courseDataByStudent[(int) $row['user_id']][] = $row;
    }
}

$pageTitle = 'Alunos cadastrados';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/alerts.php';
?>
<div class="mx-auto max-w-7xl space-y-8 px-4 pb-12">
    <div class="rounded-3xl border border-slate-200 bg-white/90 p-8 shadow shadow-slate-900/10">
        <h1 class="text-2xl font-bold tracking-tight text-brand-gray">Alunos cadastrados</h1>
        <p class="mt-2 text-sm text-slate-500">Visualize matr&iacute;culas, notas e acesse rapidamente os certificados gerados para impress&atilde;o ou download.</p>
    </div>

    <?php if (empty($students)): ?>
        <div class="rounded-3xl border border-slate-200 bg-white p-10 text-center text-sm text-slate-500 shadow shadow-slate-900/10">
            Nenhum aluno cadastrado at&eacute; o momento.
        </div>
    <?php else: ?>
        <div class="grid gap-4 lg:grid-cols-2">
            <?php foreach ($students as $student): ?>
                <?php
                    $studentId = (int) $student['id'];
                    $enrolledCourses = $courseDataByStudent[$studentId] ?? [];
                ?>
                <div class="flex h-full flex-col justify-between rounded-3xl border border-slate-200 bg-white p-6 shadow shadow-slate-900/10">
                    <div>
                        <div class="flex items-center justify-between text-xs font-semibold uppercase tracking-wider text-slate-400">
                            <span>Aluno</span>
                            <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-100 px-3 py-1"><?php echo count($enrolledCourses); ?> curso(s)</span>
                        </div>
                        <h2 class="mt-3 text-lg font-semibold text-brand-gray"><?php echo htmlspecialchars($student['name']); ?></h2>
                        <p class="mt-1 text-sm text-slate-500"><?php echo htmlspecialchars($student['email']); ?></p>
                    </div>
                    <div class="mt-5 space-y-3">
                        <?php if (empty($enrolledCourses)): ?>
                            <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-500">
                                Nenhum curso matriculado.
                            </div>
                        <?php else: ?>
                            <?php foreach ($enrolledCourses as $course): ?>
                                <?php
                                    $approved = (int) ($course['approved'] ?? 0) === 1;
                                    $certificateCode = $course['certificate_code'] ?? '';
                                    $issuedAtRaw = $course['issued_at'] ?? $course['created_at'] ?? null;
                                    $issuedAtDisplay = '-';
                                    if ($issuedAtRaw !== null) {
                                        try {
                                            $issuedDate = new DateTime($issuedAtRaw);
                                            $issuedAtDisplay = $issuedDate->format('d/m/Y');
                                        } catch (Exception $exception) {
                                            $issuedAtDisplay = '-';
                                        }
                                    }
                                    $scoreDisplay = $course['score'] !== null ? number_format((float) $course['score'], 1) : '-';
                                ?>
                                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                        <div>
                                            <p class="font-semibold text-brand-gray"><?php echo htmlspecialchars($course['course_title']); ?></p>
                                            <p class="mt-1 text-xs text-slate-500">Carga hor&aacute;ria: <?php echo (int) ($course['workload'] ?? 0); ?>h</p>
                                            <p class="mt-1 text-xs text-slate-500">Nota: <?php echo $scoreDisplay; ?> - <?php echo $approved ? 'Aprovado' : 'Nao aprovado'; ?></p>
                                            <?php if ($certificateCode !== ''): ?>
                                                <p class="mt-1 text-xs text-slate-500">Certificado em: <?php echo htmlspecialchars($issuedAtDisplay); ?> (<?php echo htmlspecialchars($certificateCode); ?>)</p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex w-full flex-col items-stretch gap-2 sm:w-auto sm:items-end">
                                            <?php if ($approved): ?>
                                                <a href="certificate.php?course_id=<?php echo (int) $course['course_id']; ?>&user_id=<?php echo $studentId; ?>" target="_blank" rel="noopener" class="inline-flex items-center justify-center rounded-full border border-brand-red/30 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-brand-red transition hover:bg-brand-red hover:text-white">
                                                    Imprimir certificado
                                                </a>
                                                <a href="certificate.php?course_id=<?php echo (int) $course['course_id']; ?>&user_id=<?php echo $studentId; ?>&download=1" class="inline-flex items-center justify-center rounded-full border border-slate-300 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-600 transition hover:bg-slate-700 hover:text-white">
                                                    Baixar certificado
                                                </a>
                                            <?php else: ?>
                                                <span class="inline-flex items-center justify-center rounded-full border border-slate-300 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-400">
                                                    Certificado indispon&iacute;vel
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

