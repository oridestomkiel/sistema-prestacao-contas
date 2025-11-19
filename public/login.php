<?php
/**
 * Página de Login - Acesso Restrito com Parâmetros
 * Apenas acessível com user e hash na URL
 */

require_once __DIR__ . '/../src/config/Config.php';
require_once __DIR__ . '/../src/middleware/Auth.php';
require_once __DIR__ . '/../src/helpers/functions.php';

Config::load();
Auth::iniciarSessao();

// Verificar se os parâmetros obrigatórios estão presentes
$user = $_GET['user'] ?? null;
$hash = $_GET['hash'] ?? null;

if (!$user || !$hash) {
    // Redirecionar para 404 se não tiver os parâmetros
    redirect('/404.php');
    exit;
}

// Validar hash (segurança básica - você pode implementar validação mais robusta)
if (strlen($hash) < 20) {
    redirect('/404.php');
    exit;
}

// Redirecionar se já estiver autenticado
Auth::requireGuest();

$codigo_convite = $_GET['codigo'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema de Prestação de Contas</title>

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- CSS Customizado -->
    <link rel="stylesheet" href="<?php echo asset('/assets/css/styles.css'); ?>">

    <!-- Alpine.js CDN -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="bg-gradient-to-br from-blue-50 to-green-50 min-h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-md" x-data="{ showRegister: <?php echo !empty($codigo_convite) ? 'true' : 'false'; ?> }">

        <!-- Card de Login/Registro -->
        <div class="bg-white rounded-2xl shadow-xl p-8">

            <!-- Logo/Header -->
            <div class="text-center mb-8">
                <i class="fas fa-heart text-6xl mb-4" style="color: #A5C9E5;"></i>
                <h1 class="text-3xl font-bold text-gray-800 mb-2">Prestação de Contas</h1>
                <p class="text-gray-600">Transparência e amor no cuidado</p>
            </div>

            <!-- Formulário de Login -->
            <div x-show="!showRegister" x-data="loginForm()">
                <h2 class="text-2xl font-semibold text-gray-800 mb-6">Entrar</h2>

                <!-- Alerta de erro -->
                <div x-show="error" x-transition class="alert alert-error mb-4" style="display: none;">
                    <i class="fas fa-exclamation-circle"></i>
                    <span x-text="error"></span>
                </div>

                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label class="label">Email</label>
                        <input type="email"
                               x-model="email"
                               required
                               class="input"
                               placeholder="seu@email.com">
                    </div>

                    <div>
                        <label class="label">Senha</label>
                        <input type="password"
                               x-model="senha"
                               required
                               class="input"
                               placeholder="••••••••">
                    </div>

                    <button type="submit"
                            :disabled="loading"
                            class="btn btn-primary w-full justify-center">
                        <span x-show="!loading">Entrar</span>
                        <span x-show="loading" class="flex items-center gap-2">
                            <span class="loading"></span>
                            Entrando...
                        </span>
                    </button>
                </form>

                <div class="mt-6 text-center">
                    <p class="text-gray-600">
                        Tem um código de convite?
                        <button @click="showRegister = true" class="text-blue-600 hover:underline font-medium">
                            Cadastre-se aqui
                        </button>
                    </p>
                </div>
            </div>

            <!-- Formulário de Registro -->
            <div x-show="showRegister" x-data="registerForm()" style="display: none;">
                <h2 class="text-2xl font-semibold text-gray-800 mb-6">Cadastro</h2>

                <!-- Alerta de erro -->
                <div x-show="error" x-transition class="alert alert-error mb-4" style="display: none;">
                    <i class="fas fa-exclamation-circle"></i>
                    <span x-text="error"></span>
                </div>

                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label class="label">Código de Convite</label>
                        <input type="text"
                               x-model="codigo"
                               required
                               class="input"
                               placeholder="XXXX-XXXX-XXXX"
                               value="<?php echo e($codigo_convite); ?>">
                        <p class="text-xs text-gray-500 mt-1">
                            Código recebido do administrador
                        </p>
                    </div>

                    <div>
                        <label class="label">Nome Completo</label>
                        <input type="text"
                               x-model="nome"
                               required
                               class="input"
                               placeholder="Seu nome">
                    </div>

                    <div>
                        <label class="label">Email</label>
                        <input type="email"
                               x-model="email"
                               required
                               class="input"
                               placeholder="seu@email.com">
                    </div>

                    <div>
                        <label class="label">Senha</label>
                        <input type="password"
                               x-model="senha"
                               required
                               minlength="8"
                               class="input"
                               placeholder="Mínimo 8 caracteres">
                    </div>

                    <button type="submit"
                            :disabled="loading"
                            class="btn btn-success w-full justify-center">
                        <span x-show="!loading">Cadastrar</span>
                        <span x-show="loading" class="flex items-center gap-2">
                            <span class="loading"></span>
                            Cadastrando...
                        </span>
                    </button>
                </form>

                <div class="mt-6 text-center">
                    <p class="text-gray-600">
                        Já tem uma conta?
                        <button @click="showRegister = false" class="text-blue-600 hover:underline font-medium">
                            Fazer login
                        </button>
                    </p>
                </div>
            </div>

        </div>

        <!-- Mensagem emotiva -->
        <div class="mt-6 text-center">
            <p class="text-gray-600 italic">
                "Um sistema feito com amor para facilitar o cuidado de quem amamos"
            </p>
        </div>
    </div>

    <!-- JavaScript Principal -->
    <script src="/assets/js/app.js"></script>

</body>
</html>
