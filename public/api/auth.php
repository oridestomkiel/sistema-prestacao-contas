<?php
/**
 * API de Autenticação
 * Endpoints: login, logout, register, verify
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../src/config/Config.php';
require_once __DIR__ . '/../../src/config/Database.php';
require_once __DIR__ . '/../../src/models/Usuario.php';
require_once __DIR__ . '/../../src/middleware/Auth.php';
require_once __DIR__ . '/../../src/middleware/CSRF.php';
require_once __DIR__ . '/../../src/helpers/functions.php';

// Inicializar configurações
Config::load();

// Iniciar sessão
Auth::iniciarSessao();

// Obter método e ação
$method = getRequestMethod();
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'login':
            handleLogin();
            break;

        case 'logout':
            handleLogout();
            break;

        case 'register':
            handleRegister();
            break;

        case 'verify':
            handleVerify();
            break;

        default:
            jsonError('Ação inválida', 400);
    }

} catch (Exception $e) {
    error_log("Erro na API de autenticação: " . $e->getMessage());
    jsonError('Erro no servidor: ' . $e->getMessage(), 500);
}

/**
 * Handle Login
 */
function handleLogin()
{
    if (!isPost()) {
        jsonError('Método não permitido', 405);
    }

    // Obter dados do POST
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $email = $data['email'] ?? '';
    $senha = $data['senha'] ?? '';

    // Validações
    if (empty($email) || empty($senha)) {
        jsonError('Email e senha são obrigatórios', 400);
    }

    if (!validarEmail($email)) {
        jsonError('Email inválido', 400);
    }

    // Tentar autenticar
    $usuarioModel = new Usuario();
    $usuario = $usuarioModel->validarCredenciais($email, $senha);

    if (!$usuario) {
        jsonError('Email ou senha incorretos', 401);
    }

    // Fazer login
    Auth::login($usuario['id'], $usuario['tipo'], $usuario);

    // Retornar sucesso
    jsonSuccess([
        'usuario' => [
            'id' => $usuario['id'],
            'nome' => $usuario['nome'],
            'email' => $usuario['email'],
            'tipo' => $usuario['tipo']
        ],
        'redirect' => '/dashboard.php'
    ], 'Login realizado com sucesso');
}

/**
 * Handle Logout
 */
function handleLogout()
{
    if (!isPost()) {
        jsonError('Método não permitido', 405);
    }

    Auth::logout();

    jsonSuccess(null, 'Logout realizado com sucesso');
}

/**
 * Handle Register (cadastro com código de convite)
 */
function handleRegister()
{
    if (!isPost()) {
        jsonError('Método não permitido', 405);
    }

    // Obter dados do POST
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $codigo = $data['codigo'] ?? '';
    $nome = $data['nome'] ?? '';
    $email = $data['email'] ?? '';
    $senha = $data['senha'] ?? '';

    // Validações
    if (empty($codigo)) {
        jsonError('Código de convite é obrigatório', 400);
    }

    if (empty($nome)) {
        jsonError('Nome é obrigatório', 400);
    }

    if (empty($email) || !validarEmail($email)) {
        jsonError('Email inválido', 400);
    }

    if (empty($senha) || strlen($senha) < 8) {
        jsonError('Senha deve ter no mínimo 8 caracteres', 400);
    }

    try {
        $usuarioModel = new Usuario();

        // Ativar convite
        $sucesso = $usuarioModel->ativarConvite($codigo, [
            'nome' => $nome,
            'email' => $email,
            'senha' => $senha
        ]);

        if (!$sucesso) {
            jsonError('Erro ao ativar convite', 500);
        }

        // Buscar usuário criado
        $usuario = $usuarioModel->buscarPorEmail($email);

        if (!$usuario) {
            jsonError('Erro ao buscar usuário', 500);
        }

        // Fazer login automaticamente
        Auth::login($usuario['id'], $usuario['tipo'], $usuario);

        jsonSuccess([
            'usuario' => [
                'id' => $usuario['id'],
                'nome' => $usuario['nome'],
                'email' => $usuario['email'],
                'tipo' => $usuario['tipo']
            ],
            'redirect' => '/dashboard.php'
        ], 'Cadastro realizado com sucesso');

    } catch (Exception $e) {
        jsonError($e->getMessage(), 400);
    }
}

/**
 * Handle Verify (verifica se está autenticado)
 */
function handleVerify()
{
    if (!Auth::check()) {
        jsonError('Não autenticado', 401);
    }

    $usuario = Auth::user();

    jsonSuccess([
        'autenticado' => true,
        'usuario' => $usuario
    ]);
}
