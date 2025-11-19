<?php if (defined('LAYOUT_WITH_SIDEBAR')): ?>
        </main>
    </div>
</div>
<?php endif; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
        // Confirmação para ações destrutivas
        document.querySelectorAll('[data-confirm]').forEach((el) => {
            el.addEventListener('click', (event) => {
                const message = el.getAttribute('data-confirm') || 'Tem certeza que deseja realizar esta ação?';
                if (!confirm(message)) {
                    event.preventDefault();
                    return false;
                }
            });
        });

        // Loading states para formulários
        document.querySelectorAll('form').forEach((form) => {
            form.addEventListener('submit', function() {
                const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    const originalText = submitBtn.textContent || submitBtn.value;
                    submitBtn.textContent = submitBtn.textContent ? 'Processando...' : '';
                    submitBtn.value = submitBtn.value ? 'Processando...' : '';
                    submitBtn.classList.add('opacity-75', 'cursor-not-allowed');
                    
                    // Re-habilitar após 10 segundos (fallback)
                    setTimeout(() => {
                        submitBtn.disabled = false;
                        submitBtn.textContent = originalText;
                        submitBtn.value = originalText;
                        submitBtn.classList.remove('opacity-75', 'cursor-not-allowed');
                    }, 10000);
                }
            });
        });

        // Auto-dismiss alerts após 5 segundos
        document.querySelectorAll('[data-dismiss="alert"]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const alert = btn.closest('div');
                if (alert) {
                    alert.style.transition = 'opacity 0.3s ease-out';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 300);
                }
            });
        });

        // Auto-hide success messages após 5 segundos
        setTimeout(() => {
            document.querySelectorAll('.border-emerald-200').forEach((alert) => {
                const dismissBtn = alert.querySelector('[data-dismiss="alert"]');
                if (dismissBtn) {
                    dismissBtn.click();
                }
            });
        }, 5000);

        // Melhorar acessibilidade - foco em elementos interativos
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                document.querySelectorAll('[data-dismiss="alert"]').forEach((btn) => {
                    if (document.activeElement === btn || btn.closest('div').contains(document.activeElement)) {
                        btn.click();
                    }
                });
            }
        });

        // Validação de senha em tempo real
        document.querySelectorAll('input[type="password"][data-validate-password]').forEach((input) => {
            const feedback = document.createElement('div');
            feedback.className = 'mt-2 text-xs';
            input.parentElement.appendChild(feedback);

            input.addEventListener('input', function() {
                const password = this.value;
                const errors = [];
                
                if (password.length < 8) {
                    errors.push('Mínimo 8 caracteres');
                }
                if (!/[A-Z]/.test(password)) {
                    errors.push('Uma maiúscula');
                }
                if (!/[a-z]/.test(password)) {
                    errors.push('Uma minúscula');
                }
                if (!/[0-9]/.test(password)) {
                    errors.push('Um número');
                }
                if (!/[^A-Za-z0-9]/.test(password)) {
                    errors.push('Um caractere especial');
                }

                if (password.length === 0) {
                    feedback.textContent = '';
                    feedback.className = 'mt-2 text-xs';
                } else if (errors.length === 0) {
                    feedback.textContent = '✓ Senha válida';
                    feedback.className = 'mt-2 text-xs text-emerald-600';
                } else {
                    feedback.textContent = 'Requisitos: ' + errors.join(', ');
                    feedback.className = 'mt-2 text-xs text-amber-600';
                }
            });
        });

        // Menu lateral responsivo
        (function() {
            const sidebar = document.querySelector('[data-app-sidebar]');
            const toggles = document.querySelectorAll('[data-sidebar-toggle]');
            const backdrop = document.querySelector('[data-sidebar-backdrop]');

            if (!sidebar || toggles.length === 0) {
                return;
            }

            const openSidebar = () => {
                sidebar.classList.add('is-open');
                if (backdrop) {
                    backdrop.classList.add('is-visible');
                }
            };

            const closeSidebar = () => {
                sidebar.classList.remove('is-open');
                if (backdrop) {
                    backdrop.classList.remove('is-visible');
                }
            };

            toggles.forEach((btn) => {
                btn.addEventListener('click', () => {
                    if (sidebar.classList.contains('is-open')) {
                        closeSidebar();
                    } else {
                        openSidebar();
                    }
                });
            });

            if (backdrop) {
                backdrop.addEventListener('click', closeSidebar);
            }
        })();
    </script>
</body>
</html>
