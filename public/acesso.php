<?php
/**
 * Página de Acesso via Token
 * Permite acesso direto ao sistema via link com token
 */

define('SISTEMA_MAE', true);

require_once __DIR__ . '/../src/config/Config.php';
require_once __DIR__ . '/../src/middleware/Auth.php';
require_once __DIR__ . '/../src/helpers/functions.php';

Config::load();
Auth::iniciarSessao();

// Verificar se há token na URL
$token = $_GET['token'] ?? null;

if (!$token) {
    // Sem token, redirecionar para login
    header('Location: /login.php');
    exit;
}

// Tentar autenticar via token
if (Auth::loginViaToken($token)) {
    // Sucesso! Redirecionar para dashboard
    header('Location: /dashboard.php');
    exit;
} else {
    // Token inválido ou expirado
    $erro = 'Link de acesso inválido ou expirado';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acesso via Link - Sistema de Prestação de Contas</title>

    <!-- Tailwind CSS Compilado -->
    <link rel="stylesheet" href="<?php echo asset('/assets/css/tailwind.css'); ?>">

    <!-- CSS Customizado -->
    <link rel="stylesheet" href="<?php echo asset('/assets/css/styles.css'); ?>">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="bg-gradient-to-br from-blue-50 to-green-50 min-h-screen flex items-center justify-center p-4">

    <div class="text-center max-w-2xl mx-auto">
        <!-- Card Principal -->
        <div class="bg-white rounded-2xl shadow-xl p-8 md:p-12">

            <!-- Ícone e Mensagem de Erro -->
            <div class="mb-8">
                <i class="fas fa-exclamation-triangle text-8xl mb-4 text-yellow-500"></i>
                <h1 class="text-3xl font-bold text-gray-800 mb-2">Link Inválido</h1>
                <p class="text-xl text-gray-600"><?php echo e($erro); ?></p>
            </div>

            <!-- Detalhes -->
            <div class="mb-8 bg-gray-50 rounded-lg p-6">
                <p class="text-gray-600 mb-4">
                    O link de acesso que você está tentando usar pode estar:
                </p>
                <ul class="text-left text-gray-700 space-y-2">
                    <li class="flex items-start gap-2">
                        <i class="fas fa-clock text-yellow-500 mt-1"></i>
                        <span><strong>Expirado:</strong> Links de acesso têm prazo de validade</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <i class="fas fa-ban text-red-500 mt-1"></i>
                        <span><strong>Desativado:</strong> O administrador pode ter desativado este acesso</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <i class="fas fa-link-slash text-gray-500 mt-1"></i>
                        <span><strong>Incorreto:</strong> Verifique se o link está completo</span>
                    </li>
                </ul>
            </div>

            <!-- Ações -->
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="/login.php" class="btn btn-primary justify-center">
                    <i class="fas fa-sign-in-alt"></i>
                    Fazer Login
                </a>
                <a href="/" class="btn btn-outline justify-center">
                    <i class="fas fa-home"></i>
                    Página Inicial
                </a>
            </div>

            <!-- Informação Adicional -->
            <div class="mt-8 pt-8 border-t border-gray-200">
                <p class="text-sm text-gray-500">
                    <i class="fas fa-question-circle mr-2"></i>
                    Se você acredita que deveria ter acesso, entre em contato com o administrador para solicitar um novo link.
                </p>
            </div>

        </div>

        <!-- Mensagem Emotiva -->
        <div class="mt-6 text-gray-600">
            <i class="fas fa-heart text-red-400 mr-2"></i>
            <span class="italic">Cada cuidado é um ato de amor</span>
        </div>
    </div>

</body>
</html>
