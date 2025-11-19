<?php
/**
 * P�gina Inicial
 * Redireciona para login ou dashboard dependendo do status de autentica��o
 */

require_once __DIR__ . '/../src/config/Config.php';
require_once __DIR__ . '/../src/middleware/Auth.php';

Config::load();
Auth::iniciarSessao();

// Se estiver autenticado, redireciona para o dashboard
if (Auth::check()) {
    header('Location: /dashboard.php');
    exit;
}

// Se n�o estiver autenticado, redireciona para login
header('Location: /login.php');
exit;
