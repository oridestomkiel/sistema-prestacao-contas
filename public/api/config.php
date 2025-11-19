<?php
/**
 * API de Configurações
 * Endpoints para administradores gerenciarem convites e usuários
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../src/config/Config.php';
require_once __DIR__ . '/../../src/config/Database.php';
require_once __DIR__ . '/../../src/models/Usuario.php';
require_once __DIR__ . '/../../src/middleware/Auth.php';
require_once __DIR__ . '/../../src/helpers/functions.php';

// Inicializar
Config::load();
Auth::iniciarSessao();

// Verificar se é admin
Auth::requireAdmin(true);

// Obter ação
$action = $_GET['action'] ?? '';
$method = getRequestMethod();

$usuarioModel = new Usuario();

try {
    switch ($action) {
        case 'gerar_convite':
            handleGerarConvite($usuarioModel);
            break;

        case 'listar_convidados':
            handleListarConvidados($usuarioModel);
            break;

        case 'desativar_usuario':
            handleDesativarUsuario($usuarioModel);
            break;

        case 'ativar_usuario':
            handleAtivarUsuario($usuarioModel);
            break;

        case 'alterar_senha':
            handleAlterarSenha($usuarioModel);
            break;

        case 'limpar_convites':
            handleLimparConvites($usuarioModel);
            break;

        default:
            jsonError('Ação inválida', 400);
    }

} catch (Exception $e) {
    error_log("Erro na API de configurações: " . $e->getMessage());
    jsonError('Erro: ' . $e->getMessage(), 500);
}

/**
 * Gerar código de convite
 */
function handleGerarConvite($model)
{
    if (!isPost()) {
        jsonError('Método não permitido', 405);
    }

    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $diasValidade = isset($data['dias']) ? (int) $data['dias'] : 7;

    if ($diasValidade < 1 || $diasValidade > 30) {
        jsonError('Dias de validade deve estar entre 1 e 30', 400);
    }

    try {
        $codigo = $model->criarConvite($diasValidade);

        if (!$codigo) {
            jsonError('Erro ao gerar convite', 500);
        }

        $dataExpiracao = date('d/m/Y H:i', strtotime("+{$diasValidade} days"));
        $linkConvite = Config::get('APP_URL') . '/login.php?codigo=' . urlencode($codigo);

        jsonSuccess([
            'codigo' => $codigo,
            'dias_validade' => $diasValidade,
            'expira_em' => $dataExpiracao,
            'link' => $linkConvite
        ], 'Código de convite gerado com sucesso');

    } catch (Exception $e) {
        jsonError($e->getMessage(), 400);
    }
}

/**
 * Listar todos os convidados
 */
function handleListarConvidados($model)
{
    $convidados = $model->listarConvidados();

    // Formatar datas
    foreach ($convidados as &$convidado) {
        $convidado['criado_em_formatado'] = formatarDataHora($convidado['criado_em']);
        $convidado['ativo_texto'] = $convidado['ativo'] ? 'Ativo' : 'Inativo';
    }

    jsonSuccess([
        'convidados' => $convidados,
        'total' => count($convidados)
    ]);
}

/**
 * Desativar usuário
 */
function handleDesativarUsuario($model)
{
    if (!isPost()) {
        jsonError('Método não permitido', 405);
    }

    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $id = $data['id'] ?? null;

    if (!$id) {
        jsonError('ID do usuário é obrigatório', 400);
    }

    // Verificar se não está tentando desativar a si mesmo
    if ($id == Auth::id()) {
        jsonError('Você não pode desativar sua própria conta', 400);
    }

    // Verificar se usuário existe
    $usuario = $model->buscarPorId($id);
    if (!$usuario) {
        jsonError('Usuário não encontrado', 404);
    }

    // Não permitir desativar outro admin
    if ($usuario['tipo'] === 'admin') {
        jsonError('Não é possível desativar um administrador', 403);
    }

    $sucesso = $model->desativar($id);

    if (!$sucesso) {
        jsonError('Erro ao desativar usuário', 500);
    }

    jsonSuccess(null, 'Usuário desativado com sucesso');
}

/**
 * Ativar usuário
 */
function handleAtivarUsuario($model)
{
    if (!isPost()) {
        jsonError('Método não permitido', 405);
    }

    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $id = $data['id'] ?? null;

    if (!$id) {
        jsonError('ID do usuário é obrigatório', 400);
    }

    // Verificar se usuário existe
    $usuario = $model->buscarPorId($id);
    if (!$usuario) {
        jsonError('Usuário não encontrado', 404);
    }

    $sucesso = $model->ativar($id);

    if (!$sucesso) {
        jsonError('Erro ao ativar usuário', 500);
    }

    jsonSuccess(null, 'Usuário ativado com sucesso');
}

/**
 * Alterar senha do usuário logado
 */
function handleAlterarSenha($model)
{
    if (!isPost()) {
        jsonError('Método não permitido', 405);
    }

    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $senhaAtual = $data['senha_atual'] ?? '';
    $novaSenha = $data['nova_senha'] ?? '';
    $confirmarSenha = $data['confirmar_senha'] ?? '';

    // Validações
    if (empty($senhaAtual) || empty($novaSenha) || empty($confirmarSenha)) {
        jsonError('Todos os campos são obrigatórios', 400);
    }

    if (strlen($novaSenha) < 8) {
        jsonError('A nova senha deve ter no mínimo 8 caracteres', 400);
    }

    if ($novaSenha !== $confirmarSenha) {
        jsonError('As senhas não coincidem', 400);
    }

    // Verificar senha atual
    $usuario = $model->buscarPorId(Auth::id());

    if (!password_verify($senhaAtual, $usuario['senha'])) {
        jsonError('Senha atual incorreta', 401);
    }

    // Atualizar senha
    $sucesso = $model->atualizarSenha(Auth::id(), $novaSenha);

    if (!$sucesso) {
        jsonError('Erro ao alterar senha', 500);
    }

    jsonSuccess(null, 'Senha alterada com sucesso');
}

/**
 * Limpar convites expirados
 */
function handleLimparConvites($model)
{
    if (!isPost()) {
        jsonError('Método não permitido', 405);
    }

    $removidos = $model->limparConvitesExpirados();

    jsonSuccess([
        'removidos' => $removidos
    ], "{$removidos} convite(s) expirado(s) removido(s)");
}
