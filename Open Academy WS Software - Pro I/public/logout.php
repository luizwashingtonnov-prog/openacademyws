<?php
require_once __DIR__ . '/../includes/auth.php';
logout();
flash('Sessao encerrada com sucesso.', 'success');
redirect('index.php');
