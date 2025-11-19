<?php if (!empty($flashMessages)): ?>
    <?php
    $styles = [
        'success' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
        'danger' => 'border-rose-200 bg-rose-50 text-rose-800',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-800',
        'info' => 'border-sky-200 bg-sky-50 text-sky-800',
    ];
    ?>
    <div class="mx-auto max-w-7xl px-4 pt-4">
        <?php foreach ($flashMessages as $alert): ?>
            <?php $tone = $styles[$alert['type']] ?? 'border-slate-200 bg-white text-slate-800'; ?>
            <div class="mb-4 flex items-start justify-between gap-4 rounded-2xl border px-4 py-3 text-sm shadow shadow-slate-900/5 <?php echo $tone; ?>">
                <span class="font-medium"><?php echo htmlspecialchars($alert['message']); ?></span>
                <button type="button" class="rounded-full px-2 py-1 text-xs font-semibold uppercase tracking-wide text-black/40 transition hover:bg-black/5" data-dismiss="alert">
                    fechar
                </button>
            </div>
        <?php endforeach; ?>
    </div>
    <script>
        document.querySelectorAll('[data-dismiss="alert"]').forEach((btn) => {
            btn.addEventListener('click', () => btn.closest('div').remove());
        });
    </script>
<?php endif; ?>
