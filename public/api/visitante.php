<?php
/**
 * API de Visitantes
 * Gerenciamento de identificação de visitantes
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../src/config/Config.php';
require_once __DIR__ . '/../../src/config/Database.php';
require_once __DIR__ . '/../../src/models/Visitante.php';
require_once __DIR__ . '/../../src/middleware/Auth.php';
require_once __DIR__ . '/../../src/helpers/functions.php';

// Inicializar
Config::load();
Auth::iniciarSessao();

// Debug inicial
error_log("=== API Visitante ===");
error_log("Session ID: " . session_id());
error_log("USER_ID na sessão: " . ($_SESSION['USER_ID'] ?? 'none'));
error_log("VISITANTE_ID na sessão: " . ($_SESSION['VISITANTE_ID'] ?? 'none'));
error_log("AUTH_METHOD: " . ($_SESSION['AUTH_METHOD'] ?? 'none'));

// Verificar autenticação via token
Auth::checkTokenAuth();

if (!Auth::isTokenAuth()) {
    error_log("Não autenticado via token!");
    jsonError('Acesso não autorizado', 401);
}

error_log("Autenticado! Visitante ID: " . Auth::visitanteId());

// Obter ação
$action = $_GET['action'] ?? '';
$method = getRequestMethod();

$visitanteModel = new Visitante();

try {
    switch ($action) {
        case 'salvar_identificacao':
            handleSalvarIdentificacao($visitanteModel);
            break;

        case 'verificar_modal':
            handleVerificarModal();
            break;

        default:
            jsonError('Ação inválida', 400);
    }

} catch (Exception $e) {
    error_log("Erro na API de visitantes: " . $e->getMessage());
    jsonError('Erro: ' . $e->getMessage(), 500);
}

/**
 * Salva identificação do visitante
 */
function handleSalvarIdentificacao($model)
{
    if (!isPost()) {
        jsonError('Método não permitido', 405);
    }

    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $nome = isset($data['nome']) ? trim($data['nome']) : null;
    $responder = $data['responder'] ?? true; // Se false, usuário escolheu "Não quero informar"

    $visitanteId = Auth::visitanteId();

    if (!$visitanteId) {
        // Debug para entender o problema
        error_log("Visitante ID null. AUTH_METHOD: " . ($_SESSION['AUTH_METHOD'] ?? 'none'));
        error_log("USER_ID: " . ($_SESSION['USER_ID'] ?? 'none'));
        error_log("VISITANTE_ID: " . ($_SESSION['VISITANTE_ID'] ?? 'none'));
        jsonError('Visitante não identificado. Sessão perdida.', 400);
    }

    try {
        // Salvar identificação (pode ser null se não quis informar)
        $sucesso = $model->salvarIdentificacao($visitanteId, $nome);

        if (!$sucesso) {
            jsonError('Erro ao salvar identificação', 500);
        }

        // Marcar que respondeu modal na sessão
        Auth::marcarModalRespondido();

        // Atualizar nome na sessão se foi informado
        if ($nome) {
            $_SESSION['USER_NAME'] = $nome;
        }

        jsonSuccess([
            'nome' => $nome,
            'visitante_hash' => Auth::visitanteHash()
        ], $nome ? 'Obrigado por se identificar!' : 'Preferências salvas');

    } catch (Exception $e) {
        jsonError($e->getMessage(), 400);
    }
}

/**
 * Verifica se deve mostrar modal
 */
function handleVerificarModal()
{
    $mostrarModal = !Auth::visitanteRespondeuModal();

    jsonSuccess([
        'mostrar_modal' => $mostrarModal,
        'visitante_hash' => Auth::visitanteHash(),
        'nome_atual' => Auth::user('nome')
    ]);
}
