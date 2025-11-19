<?php
/**
 * API de Tokens de Acesso
 * Endpoints para administradores gerenciarem links de acesso direto
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../src/config/Config.php';
require_once __DIR__ . '/../../src/config/Database.php';
require_once __DIR__ . '/../../src/models/TokenAcesso.php';
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

$tokenModel = new TokenAcesso();

try {
    switch ($action) {
        case 'criar':
            handleCriarToken($tokenModel);
            break;

        case 'listar':
            handleListarTokens($tokenModel);
            break;

        case 'desativar':
            handleDesativarToken($tokenModel);
            break;

        case 'ativar':
            handleAtivarToken($tokenModel);
            break;

        case 'deletar':
            handleDeletarToken($tokenModel);
            break;

        case 'estatisticas':
            handleEstatisticas($tokenModel);
            break;

        case 'limpar_expirados':
            handleLimparExpirados($tokenModel);
            break;

        default:
            jsonError('Ação inválida', 400);
    }

} catch (Exception $e) {
    error_log("Erro na API de tokens: " . $e->getMessage());
    jsonError('Erro: ' . $e->getMessage(), 500);
}

/**
 * Criar novo token de acesso
 */
function handleCriarToken($model)
{
    if (!isPost()) {
        jsonError('Método não permitido', 405);
    }

    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $nomeConvidado = $data['nome'] ?? '';
    $diasValidade = isset($data['dias']) ? (int) $data['dias'] : null;

    // Validações
    if (empty($nomeConvidado)) {
        jsonError('Nome do convidado é obrigatório', 400);
    }

    if ($diasValidade !== null && ($diasValidade < 1 || $diasValidade > 365)) {
        jsonError('Dias de validade deve estar entre 1 e 365 (ou deixe vazio para sem expiração)', 400);
    }

    try {
        $token = $model->criar($nomeConvidado, Auth::id(), $diasValidade);

        if (!$token) {
            jsonError('Erro ao criar token de acesso', 500);
        }

        $expiraEm = null;
        if ($diasValidade !== null) {
            $expiraEm = formatarDataHora(date('Y-m-d H:i:s', strtotime("+{$diasValidade} days")));
        }

        $linkAcesso = Config::get('APP_URL') . '/acesso.php?token=' . urlencode($token);

        jsonSuccess([
            'token' => $token,
            'nome_convidado' => $nomeConvidado,
            'dias_validade' => $diasValidade,
            'expira_em' => $expiraEm,
            'link' => $linkAcesso
        ], 'Link de acesso criado com sucesso');

    } catch (Exception $e) {
        jsonError($e->getMessage(), 400);
    }
}

/**
 * Listar todos os tokens
 */
function handleListarTokens($model)
{
    $apenasAtivos = isset($_GET['ativos']) && $_GET['ativos'] === '1';

    $tokens = $model->listar($apenasAtivos);

    // Formatar dados
    foreach ($tokens as &$token) {
        $token['criado_em_formatado'] = formatarDataHora($token['criado_em']);
        $token['expira_em_formatado'] = $token['expira_em'] ? formatarDataHora($token['expira_em']) : 'Sem expiração';
        $token['ultimo_acesso_formatado'] = $token['ultimo_acesso'] ? formatarDataHora($token['ultimo_acesso']) : 'Nunca acessado';
        $token['ativo_texto'] = $token['ativo'] ? 'Ativo' : 'Inativo';
        $token['link'] = Config::get('APP_URL') . '/acesso.php?token=' . urlencode($token['token']);

        // Verificar se expirou
        $token['expirado'] = false;
        if ($token['expira_em'] !== null) {
            $agora = new DateTime();
            $expiracao = new DateTime($token['expira_em']);
            $token['expirado'] = $agora > $expiracao;
        }
    }

    jsonSuccess([
        'tokens' => $tokens,
        'total' => count($tokens)
    ]);
}

/**
 * Desativar token
 */
function handleDesativarToken($model)
{
    if (!isPost()) {
        jsonError('Método não permitido', 405);
    }

    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $id = $data['id'] ?? null;

    if (!$id) {
        jsonError('ID do token é obrigatório', 400);
    }

    $sucesso = $model->desativar($id);

    if (!$sucesso) {
        jsonError('Erro ao desativar token', 500);
    }

    jsonSuccess(null, 'Token desativado com sucesso');
}

/**
 * Ativar token
 */
function handleAtivarToken($model)
{
    if (!isPost()) {
        jsonError('Método não permitido', 405);
    }

    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $id = $data['id'] ?? null;

    if (!$id) {
        jsonError('ID do token é obrigatório', 400);
    }

    $sucesso = $model->ativar($id);

    if (!$sucesso) {
        jsonError('Erro ao ativar token', 500);
    }

    jsonSuccess(null, 'Token ativado com sucesso');
}

/**
 * Deletar token
 */
function handleDeletarToken($model)
{
    if (!isPost()) {
        jsonError('Método não permitido', 405);
    }

    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $id = $data['id'] ?? null;

    if (!$id) {
        jsonError('ID do token é obrigatório', 400);
    }

    $sucesso = $model->deletar($id);

    if (!$sucesso) {
        jsonError('Erro ao deletar token', 500);
    }

    jsonSuccess(null, 'Token deletado com sucesso');
}

/**
 * Obter estatísticas de tokens
 */
function handleEstatisticas($model)
{
    $stats = $model->estatisticas();
    jsonSuccess($stats);
}

/**
 * Limpar tokens expirados
 */
function handleLimparExpirados($model)
{
    if (!isPost()) {
        jsonError('Método não permitido', 405);
    }

    $removidos = $model->limparExpirados();

    jsonSuccess([
        'removidos' => $removidos
    ], "{$removidos} token(s) expirado(s) removido(s)");
}
