<?php
/**
 * Página de Acesso Negado para Convidados
 * Exibida quando alguém tenta acessar sem um link válido
 */

define('SISTEMA_MAE', true);

require_once __DIR__ . '/../src/helpers/functions.php';
require_once __DIR__ . '/../src/config/Config.php';

$page_title = 'Acesso Restrito - ' . Config::get('ORGANIZATION_NAME', 'Sistema de Prestação de Contas');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($page_title); ?></title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Estilos customizados -->
    <link rel="stylesheet" href="<?php echo asset('/assets/css/styles.css'); ?>">

    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>
<body>
    <div class="container mx-auto px-4">
        <div class="max-w-2xl mx-auto">
            <!-- Card principal -->
            <div class="bg-white rounded-2xl shadow-2xl p-8 md:p-12 text-center">
                <!-- Ícone -->
                <div class="mb-6">
                    <div class="w-24 h-24 bg-purple-100 rounded-full flex items-center justify-center mx-auto">
                        <i class="fas fa-lock text-5xl text-purple-600"></i>
                    </div>
                </div>

                <!-- Título -->
                <h1 class="text-3xl md:text-4xl font-bold text-gray-800 mb-4">
                    Acesso Restrito
                </h1>

                <!-- Mensagem principal -->
                <div class="mb-8">
                    <p class="text-lg text-gray-600 mb-4">
                        Este é um sistema privado para acompanhamento dos cuidados com <strong class="text-purple-600"><?php echo Config::get('PATIENT_NAME', 'pessoa assistida'); ?></strong>.
                    </p>
                    <p class="text-gray-600">
                        Para acessar, você precisa de um <strong>link de acesso especial</strong> compartilhado pela família.
                    </p>
                </div>

                <!-- Ícone coração -->
                <div class="mb-8">
                    <i class="fas fa-heart text-6xl text-red-400"></i>
                </div>

                <!-- Informações -->
                <div class="bg-blue-50 rounded-lg p-6 mb-6 border border-blue-200">
                    <h3 class="font-semibold text-gray-800 mb-3 flex items-center justify-center">
                        <i class="fas fa-info-circle text-blue-500 mr-2"></i>
                        Como obter acesso?
                    </h3>
                    <ul class="text-left text-gray-700 space-y-2 max-w-md mx-auto">
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i>
                            <span>Entre em contato com um membro da família</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i>
                            <span>Solicite o link de acesso personalizado</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mr-2 mt-1"></i>
                            <span>Use o link compartilhado para entrar no sistema</span>
                        </li>
                    </ul>
                </div>

                <!-- Mensagem afetuosa -->
                <p class="text-sm text-gray-500 italic">
                    <i class="fas fa-heart text-red-300 mr-1"></i>
                    Com amor e transparência
                    <i class="fas fa-heart text-red-300 ml-1"></i>
                </p>
            </div>

            <!-- Link voltar -->
            <div class="text-center mt-6">
                <a href="/" class="text-white hover:text-gray-200 transition-colors">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Voltar para a página inicial
                </a>
            </div>
        </div>
    </div>
</body>
</html>
