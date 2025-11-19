<?php
/**
 * Header do sistema
 */

if (!defined('SISTEMA_MAE')) {
    die('Acesso negado');
}

$pagina_atual = basename($_SERVER['PHP_SELF'], '.php');
$usuario = Auth::user();
$is_admin = Auth::isAdmin();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sistema de Prestação de Contas - Gerenciamento transparente de cuidados">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <?php echo CSRF::metaTag(); ?>
    <title><?php echo $page_title ?? 'Sistema de Prestação de Contas'; ?></title>

    <!-- Tailwind CSS Compilado -->
    <link rel="stylesheet" href="<?php echo asset('/assets/css/tailwind.css'); ?>">

    <!-- CSS Customizado -->
    <link rel="stylesheet" href="<?php echo asset('/assets/css/styles.css'); ?>">

    <!-- Alpine.js CDN -->
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/mask@3.x.x/dist/cdn.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Flatpickr CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="bg-gray-50">

    <!-- Toast de Notificações -->
    <div x-data x-show="$store.toast.show"
         x-transition
         class="toast"
         :class="{
             'border-l-4 border-green-500': $store.toast.type === 'success',
             'border-l-4 border-red-500': $store.toast.type === 'error',
             'border-l-4 border-blue-500': $store.toast.type === 'info'
         }"
         style="display: none;">
        <div class="flex items-center gap-3">
            <i class="fas" :class="{
                'fa-check-circle text-green-500': $store.toast.type === 'success',
                'fa-times-circle text-red-500': $store.toast.type === 'error',
                'fa-info-circle text-blue-500': $store.toast.type === 'info'
            }"></i>
            <span x-text="$store.toast.message"></span>
        </div>
    </div>

    <div class="min-h-screen flex flex-col">
        <!-- Header -->
        <header class="bg-white shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Primeira linha: Logo e Usuário -->
                <div class="flex justify-between items-center py-4">
                    <!-- Logo/Título -->
                    <div class="flex items-center gap-3">
                        <i class="fas fa-heart text-3xl" style="color: #A5C9E5;"></i>
                        <div>
                            <h1 class="text-2xl font-bold text-gray-800"><?php echo Config::get('ORGANIZATION_NAME', 'Sistema de Prestação de Contas'); ?></h1>
                            <p class="text-sm text-gray-500"><?php echo Config::get('APP_TAGLINE', 'Transparência e amor em cada gesto'); ?></p>
                        </div>
                    </div>

                    <!-- User Menu -->
                    <div class="flex items-center gap-4" x-data="{ open: false }">
                        <div class="relative">
                            <button @click="open = !open"
                                    class="flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-gray-100 transition">
                                <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-user text-blue-600"></i>
                                </div>
                                <div class="hidden md:block text-left">
                                    <p class="text-sm font-medium text-gray-700"><?php echo e($usuario['nome']); ?></p>
                                    <p class="text-xs text-gray-500"><?php echo $is_admin ? 'Administrador' : 'Convidado'; ?></p>
                                </div>
                                <i class="fas fa-chevron-down text-gray-400 text-sm"></i>
                            </button>

                            <!-- Dropdown -->
                            <div x-show="open"
                                 @click.away="open = false"
                                 x-transition
                                 class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg py-2 z-50"
                                 style="display: none;">
                                <?php if (Auth::isTokenAuth() && $usuario['nome'] === 'Convidado'): ?>
                                <button @click="$store.visitante.abrir()"
                                        class="block w-full text-left px-4 py-2 text-blue-600 hover:bg-blue-50">
                                    <i class="fas fa-user-edit mr-2"></i>Identificar-se
                                </button>
                                <hr class="my-2">
                                <?php endif; ?>
                                <button onclick="logout()" class="block w-full text-left px-4 py-2 text-red-600 hover:bg-red-50">
                                    <i class="fas fa-sign-out-alt mr-2"></i>Sair
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Segunda linha: Menu de Navegação -->
                <div class="border-t border-gray-200">
                    <nav class="hidden md:flex items-center justify-center gap-6 py-3">
                        <a href="/dashboard.php"
                           class="<?php echo $pagina_atual === 'dashboard' ? 'text-blue-600 font-semibold' : 'text-gray-600 hover:text-blue-600'; ?> transition">
                            <i class="fas fa-home mr-2"></i>Início
                        </a>
                        <a href="/entradas.php"
                           class="<?php echo $pagina_atual === 'entradas' ? 'text-green-600 font-semibold' : 'text-gray-600 hover:text-green-600'; ?> transition">
                            <i class="fas fa-arrow-up mr-2"></i>Entradas
                        </a>
                        <a href="/saidas.php"
                           class="<?php echo $pagina_atual === 'saidas' ? 'text-red-600 font-semibold' : 'text-gray-600 hover:text-red-600'; ?> transition">
                            <i class="fas fa-arrow-down mr-2"></i>Saídas
                        </a>
                        <a href="/relatorios.php"
                           class="<?php echo $pagina_atual === 'relatorios' ? 'text-purple-600 font-semibold' : 'text-gray-600 hover:text-purple-600'; ?> transition">
                            <i class="fas fa-chart-bar mr-2"></i>Relatórios
                        </a>
                        <?php if ($is_admin): ?>
                        <a href="/categorias.php"
                           class="<?php echo $pagina_atual === 'categorias' ? 'text-purple-600 font-semibold' : 'text-gray-600 hover:text-purple-600'; ?> transition">
                            <i class="fas fa-tags mr-2"></i>Categorias
                        </a>
                        <a href="/contribuicoes-pendentes.php"
                           class="<?php echo $pagina_atual === 'contribuicoes-pendentes' ? 'text-red-600 font-semibold' : 'text-gray-600 hover:text-red-600'; ?> transition">
                            <i class="fas fa-hand-holding-heart mr-2"></i>Contribuições
                        </a>
                        <a href="/config.php"
                           class="<?php echo $pagina_atual === 'config' ? 'text-orange-600 font-semibold' : 'text-gray-600 hover:text-orange-600'; ?> transition">
                            <i class="fas fa-cog mr-2"></i>Configurações
                        </a>
                        <?php endif; ?>
                    </nav>
                </div>

                <!-- Menu Mobile -->
                <div class="md:hidden pb-4" x-data="{ mobileMenuOpen: false }">
                    <button @click="mobileMenuOpen = !mobileMenuOpen"
                            class="flex items-center justify-center w-full py-2 text-gray-600 hover:text-gray-900">
                        <i class="fas fa-bars"></i>
                        <span class="ml-2">Menu</span>
                    </button>

                    <nav x-show="mobileMenuOpen"
                         x-transition
                         class="mt-2 flex flex-col gap-2"
                         style="display: none;">
                        <a href="/dashboard.php" class="block py-2 px-4 rounded <?php echo $pagina_atual === 'dashboard' ? 'bg-blue-50 text-blue-600' : 'text-gray-600'; ?>">
                            <i class="fas fa-home mr-2"></i>Início
                        </a>
                        <a href="/entradas.php" class="block py-2 px-4 rounded <?php echo $pagina_atual === 'entradas' ? 'bg-green-50 text-green-600' : 'text-gray-600'; ?>">
                            <i class="fas fa-arrow-up mr-2"></i>Entradas
                        </a>
                        <a href="/saidas.php" class="block py-2 px-4 rounded <?php echo $pagina_atual === 'saidas' ? 'bg-red-50 text-red-600' : 'text-gray-600'; ?>">
                            <i class="fas fa-arrow-down mr-2"></i>Saídas
                        </a>
                        <a href="/relatorios.php" class="block py-2 px-4 rounded <?php echo $pagina_atual === 'relatorios' ? 'bg-purple-50 text-purple-600' : 'text-gray-600'; ?>">
                            <i class="fas fa-chart-bar mr-2"></i>Relatórios
                        </a>
                        <?php if ($is_admin): ?>
                        <a href="/categorias.php" class="block py-2 px-4 rounded <?php echo $pagina_atual === 'categorias' ? 'bg-purple-50 text-purple-600' : 'text-gray-600'; ?>">
                            <i class="fas fa-tags mr-2"></i>Categorias
                        </a>
                        <a href="/contribuicoes-pendentes.php" class="block py-2 px-4 rounded <?php echo $pagina_atual === 'contribuicoes-pendentes' ? 'bg-red-50 text-red-600' : 'text-gray-600'; ?>">
                            <i class="fas fa-hand-holding-heart mr-2"></i>Contribuições
                        </a>
                        <a href="/config.php" class="block py-2 px-4 rounded <?php echo $pagina_atual === 'config' ? 'bg-orange-50 text-orange-600' : 'text-gray-600'; ?>">
                            <i class="fas fa-cog mr-2"></i>Configurações
                        </a>
                        <?php endif; ?>
                    </nav>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
