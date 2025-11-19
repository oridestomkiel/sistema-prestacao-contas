<?php
/**
 * API de Contribuições
 * Gerenciamento de doações via PIX
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../src/config/Config.php';
require_once __DIR__ . '/../../src/config/Database.php';
require_once __DIR__ . '/../../src/models/Contribuicao.php';
require_once __DIR__ . '/../../src/models/ContribuicaoPendente.php';
require_once __DIR__ . '/../../src/middleware/Auth.php';
require_once __DIR__ . '/../../src/helpers/functions.php';

// Inicializar
Config::load();
Auth::iniciarSessao();

// Debug inicial
error_log("=== API Contribuição ===");
error_log("Session ID: " . session_id());
error_log("Action: " . ($_GET['action'] ?? 'none'));

// Verificar autenticação via token
Auth::checkTokenAuth();

// Obter ação
$action = $_GET['action'] ?? '';
$method = getRequestMethod();

$contribuicaoModel = new Contribuicao();

try {
    switch ($action) {
        case 'gerar_qrcode':
            handleGerarQRCode($contribuicaoModel);
            break;

        case 'registrar_contribuicao':
            handleRegistrarContribuicao($contribuicaoModel);
            break;

        case 'listar_publicas':
            handleListarPublicas($contribuicaoModel);
            break;

        default:
            jsonError('Ação inválida', 400);
    }

} catch (Exception $e) {
    error_log("Erro na API de contribuições: " . $e->getMessage());
    jsonError('Erro: ' . $e->getMessage(), 500);
}

/**
 * Gera QR Code PIX
 */
function handleGerarQRCode($model)
{
    if (!isPost()) {
        jsonError('Método não permitido', 405);
    }

    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $valor = isset($data['valor']) && is_numeric($data['valor']) ? floatval($data['valor']) : null;
    $nomeContribuinte = isset($data['nome']) ? trim($data['nome']) : null;

    // Obter configurações PIX
    $pixChave = Config::get('PIX_CHAVE', 'suachave@email.com');
    $pixNome = Config::get('PIX_NOME', 'Cuidados Mae/Vo/Bisavo Maria');
    $pixCidade = Config::get('PIX_CIDADE', 'Sua Cidade');

    try {
        // Gerar txid único para rastreamento
        $txid = Contribuicao::gerarTxid();

        // Gerar payload PIX com txid e nome do contribuinte
        $payload = Contribuicao::gerarPixPayload(
            $pixChave,
            $pixNome,
            $pixCidade,
            $valor,
            $nomeContribuinte,
            $txid
        );

        // Gerar URL do QR Code usando API pública
        $qrcodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($payload);

        jsonSuccess([
            'pix_payload' => $payload,
            'pix_chave' => $pixChave,
            'qrcode_url' => $qrcodeUrl,
            'valor' => $valor,
            'txid' => $txid
        ]);

    } catch (Exception $e) {
        jsonError($e->getMessage(), 400);
    }
}

/**
 * Registra contribuição pendente de aprovação
 */
function handleRegistrarContribuicao($model)
{
    if (!isPost()) {
        jsonError('Método não permitido', 405);
    }

    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $nomeDoador = isset($data['nome']) ? trim($data['nome']) : null;
    $mostrarNome = isset($data['mostrar_nome']) ? boolval($data['mostrar_nome']) : false;
    $valor = isset($data['valor']) && is_numeric($data['valor']) ? floatval($data['valor']) : null;
    $pixPayload = isset($data['pix_payload']) ? trim($data['pix_payload']) : null;
    $txid = isset($data['txid']) ? trim($data['txid']) : null;

    // Nome da sessão (usuário logado)
    $nomeSessao = Auth::user('nome');

    // Se nome da sessão for "Convidado", não salvar
    if ($nomeSessao === 'Convidado') {
        $nomeSessao = null;
    }

    // Se não tem valor mínimo
    if (!$valor || $valor <= 0) {
        jsonError('Valor da contribuição deve ser informado e maior que zero', 400);
    }

    try {
        // Criar contribuição pendente
        $pendenteModel = new ContribuicaoPendente();

        $contribuicaoId = $pendenteModel->criar([
            'nome_doador' => $nomeDoador,
            'nome_sessao' => $nomeSessao,
            'exibir_anonimo' => !$mostrarNome, // Inverter: se NÃO mostrar nome = anônimo
            'valor' => $valor,
            'observacoes' => "TxID PIX: {$txid}"
        ]);

        if (!$contribuicaoId) {
            jsonError('Erro ao registrar contribuição pendente', 500);
        }

        // Também salvar na tabela antiga de contribuições para histórico
        $model->criar(
            Auth::visitanteId(),
            $nomeDoador ?: 'Convidado',
            $mostrarNome,
            $valor,
            Config::get('PIX_CHAVE', ''),
            $pixPayload,
            $txid
        );

        jsonSuccess([
            'id' => $contribuicaoId,
            'txid' => $txid,
            'nome_exibicao' => $mostrarNome ? ($nomeDoador ?? $nomeSessao ?? 'Anônimo') : 'Anônimo'
        ], 'Contribuição registrada com sucesso! Aguardando confirmação do administrador.');

    } catch (Exception $e) {
        error_log("Erro ao registrar contribuição: " . $e->getMessage());
        jsonError($e->getMessage(), 400);
    }
}

/**
 * Lista contribuições públicas
 */
function handleListarPublicas($model)
{
    try {
        $limite = isset($_GET['limite']) ? intval($_GET['limite']) : 20;
        $contribuicoes = $model->listarPublicas($limite);

        jsonSuccess([
            'contribuicoes' => $contribuicoes
        ]);

    } catch (Exception $e) {
        jsonError($e->getMessage(), 400);
    }
}
