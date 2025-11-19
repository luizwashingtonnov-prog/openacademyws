<?php
require_once __DIR__ . '/../includes/auth.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();
    $email = sanitize_email($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email && $password && login($email, $password)) {
        flash('Bem-vindo de volta!', 'success');
        redirect('dashboard.php');
    }

    flash('Credenciais inválidas. Tente novamente.', 'danger');
}

$pageTitle = 'Login - Training';
$bodyClass = 'bg-slate-900/5';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/alerts.php';
?>
<section class="relative py-16">
    <div class="absolute inset-0 overflow-hidden">
        <div class="pointer-events-none absolute -top-32 -right-32 h-72 w-72 rounded-full bg-brand-red/20 blur-3xl"></div>
        <div class="pointer-events-none absolute bottom-0 left-1/2 h-96 w-96 -translate-x-1/2 rounded-full bg-brand-gray/10 blur-3xl"></div>
    </div>
    <div class="relative mx-auto max-w-6xl px-4">
        <div class="grid items-center gap-10 lg:grid-cols-2">
            <div class="hidden rounded-3xl bg-gradient-to-br from-brand-red to-brand-redDark p-10 text-white shadow-2xl shadow-brand-red/40 lg:block">
                <span class="inline-flex items-center gap-2 rounded-full bg-white/15 px-4 py-1 text-xs font-semibold uppercase tracking-[0.3em]">Open Academy WS Software</span>
                <h1 class="mt-6 text-3xl font-bold tracking-tight">Educação inteligente para um mundo em movimento. WS Software.</h1>
                <p class="mt-4 text-base text-white/80">Gerencie alunos, monitore cursos, aplique avaliações e emita certificados em um ambiente intuitivo e responsivo.</p>
                <ul class="mt-8 space-y-4 text-sm text-white/85">
                    <li class="flex items-start gap-3"><span class="mt-1 inline-block h-2 w-2 rounded-full bg-white"></span> Painel completo para administradores e gestores acompanharem a performance dos alunos.</li>
                    <li class="flex items-start gap-3"><span class="mt-1 inline-block h-2 w-2 rounded-full bg-white"></span> Avaliações com nota minima configurada e resultado imediato.</li>
                    <li class="flex items-start gap-3"><span class="mt-1 inline-block h-2 w-2 rounded-full bg-white"></span> Emissão automatica de certificados e registro historico do desempenho.</li>
                    <li class="flex items-start gap-3"><span class="mt-1 inline-block h-2 w-2 rounded-full bg-white"></span> Sistema criado por Washington Oliveira.</li>
                </ul>
            </div>
            <div class="mx-auto w-full max-w-md">
                <div class="rounded-3xl border border-slate-100 bg-white/95 p-8 shadow-xl shadow-slate-900/10 backdrop-blur">
                    <div class="mb-6 text-center">
                        <h2 class="text-2xl font-semibold tracking-tight text-brand-gray">Seja bem-vindo</h2>
                        <p class="mt-2 text-sm text-slate-500">Use as credenciais fornecidas para acessar o painel</p>
                    </div>
                    <form method="post" class="space-y-4" novalidate>
                        <?php echo csrf_field(); ?>
                        <div>
                            <label for="email" class="mb-1 block text-sm font-semibold text-slate-600">Email</label>
                            <input type="email" id="email" name="email" required placeholder="voce@escola.com" class="block w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-brand-red focus:outline-none focus:ring-4 focus:ring-brand-red/20">
                        </div>
                        <div>
                            <label for="password" class="mb-1 block text-sm font-semibold text-slate-600">Senha</label>
                            <input type="password" id="password" name="password" required placeholder="Digite sua senha" class="block w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm focus:border-brand-red focus:outline-none focus:ring-4 focus:ring-brand-red/20">
                        </div>
                        <div class="pt-2">
                            <button type="submit" class="inline-flex w-full items-center justify-center rounded-2xl bg-brand-red px-5 py-3 text-sm font-semibold text-white shadow-glow transition hover:bg-brand-redDark focus:outline-none focus:ring-4 focus:ring-brand-red/30">
                                Entrar na plataforma
                            </button>
                        </div>
                        <p class="text-center text-xs text-slate-500">Problemas com o acesso? Procure a coordenação para redefinir sua senha.</p>
                    </form>
                    <script>
                        document.addEventListener('DOMContentLoaded', function () {
                            var emailField = document.getElementById('email');
                            var passwordField = document.getElementById('password');
                            if (emailField) {
                                emailField.value = '';
                            }
                            if (passwordField) {
                                passwordField.value = '';
                            }
                        });
                    </script>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

