<?php
/**
 * Página de Erro 404 - Página não encontrada
 */

define('SISTEMA_MAE', true);

require_once __DIR__ . '/../src/config/Config.php';
require_once __DIR__ . '/../src/helpers/functions.php';

Config::load();

// Define status HTTP 404
http_response_code(404);

$page_title = 'Página Não Encontrada - 404';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>

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

            <!-- Ícone e Código de Erro -->
            <div class="mb-8">
                <i class="fas fa-heart-broken text-8xl mb-4" style="color: #F4A5A5;"></i>
                <h1 class="text-6xl font-bold text-gray-800 mb-2">404</h1>
                <p class="text-2xl text-gray-600">Página não encontrada</p>
            </div>

            <!-- Mensagem -->
            <div class="mb-8">
                <p class="text-gray-600 text-lg">
                    Ops! A página que você está procurando não existe ou foi movida.
                </p>
            </div>

            <!-- Informação Adicional -->
            <div class="mt-8 pt-8 border-t border-gray-200">
                <p class="text-sm text-gray-500">
                    <i class="fas fa-question-circle mr-2"></i>
                    Se você acha que isso é um erro, entre em contato com o administrador.
                </p>
            </div>

        </div>

        <!-- Mensagem Emotiva -->
        <div class="mt-6 text-gray-600">
            <i class="fas fa-heart text-red-400 mr-2"></i>
            <span class="italic"><?php echo Config::get('APP_TAGLINE', 'Transparência e amor em cada gesto'); ?></span>
        </div>
    </div>

</body>
</html>
