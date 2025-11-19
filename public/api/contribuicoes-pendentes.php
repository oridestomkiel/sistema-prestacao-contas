<?php
/**
 * API de Contribuições Pendentes
 * Gerenciamento de aprovação/rejeição de contribuições
 * Apenas para administradores
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../src/config/Config.php';
require_once __DIR__ . '/../../src/config/Database.php';
require_once __DIR__ . '/../../src/models/ContribuicaoPendente.php';
require_once __DIR__ . '/../../src/middleware/Auth.php';
require_once __DIR__ . '/../../src/helpers/functions.php';

// Inicializar
Config::load();
Auth::iniciarSessao();
Auth::requireAuth();
Auth::requireAdmin(); // Apenas admin pode gerenciar

// Obter ação
$action = $_GET['action'] ?? 'listar';
$method = getRequestMethod();

$model = new ContribuicaoPendente();

try {
    switch ($action) {
        case 'listar':
            handleListar($model);
            break;

        case 'aprovar':
            handleAprovar($model);
            break;

        case 'rejeitar':
            handleRejeitar($model);
            break;

        default:
            jsonError('Ação inválida', 400);
    }

} catch (Exception $e) {
    error_log("Erro na API de contribuições pendentes: " . $e->getMessage());
    jsonError('Erro: ' . $e->getMessage(), 500);
}

/**
 * Lista contribuições pendentes com filtros
 */
function handleListar($model)
{
    try {
        $filtros = [];

        if (!empty($_GET['status'])) {
            $filtros['status'] = $_GET['status'];
        }

        if (!empty($_GET['data_inicio'])) {
            $filtros['data_inicio'] = $_GET['data_inicio'];
        }

        if (!empty($_GET['data_fim'])) {
            $filtros['data_fim'] = $_GET['data_fim'];
        }

        $contribuicoes = $model->listar($filtros);

        // Formatar dados para exibição
        $contribuicoesFormatadas = array_map(function($contrib) {
            return [
                'id' => (int) $contrib['id'],
                'nome_doador' => $contrib['nome_doador'],
                'nome_sessao' => $contrib['nome_sessao'],
                'exibir_anonimo' => (bool) $contrib['exibir_anonimo'],
                'valor' => (float) $contrib['valor'],
                'status' => $contrib['status'],
                'status_formatado' => ucfirst($contrib['status']),
                'observacoes' => $contrib['observacoes'],
                'criado_em' => $contrib['criado_em'],
                'criado_em_formatado' => dataHoraBRComHora($contrib['criado_em']),
                'aprovado_por' => $contrib['aprovado_por'],
                'aprovador_nome' => $contrib['aprovador_nome'],
                'aprovado_em' => $contrib['aprovado_em'],
                'aprovado_em_formatado' => $contrib['aprovado_em'] ? dataHoraBRComHora($contrib['aprovado_em']) : null,
                'entrada_id' => $contrib['entrada_id']
            ];
        }, $contribuicoes);

        jsonSuccess([
            'contribuicoes' => $contribuicoesFormatadas
        ]);

    } catch (Exception $e) {
        jsonError($e->getMessage(), 400);
    }
}

/**
 * Aprova uma contribuição e cria entrada automaticamente
 */
function handleAprovar($model)
{
    if (!isPost()) {
        jsonError('Método não permitido', 405);
    }

    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

    if (!$id) {
        jsonError('ID da contribuição não informado', 400);
    }

    try {
        $usuarioId = Auth::id();

        $sucesso = $model->aprovar($id, $usuarioId);

        if (!$sucesso) {
            jsonError('Erro ao aprovar contribuição', 500);
        }

        jsonSuccess([], 'Contribuição aprovada com sucesso! Entrada criada automaticamente.');

    } catch (Exception $e) {
        jsonError($e->getMessage(), 400);
    }
}

/**
 * Rejeita uma contribuição
 */
function handleRejeitar($model)
{
    if (!isPost()) {
        jsonError('Método não permitido', 405);
    }

    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

    if (!$id) {
        jsonError('ID da contribuição não informado', 400);
    }

    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $motivo = isset($data['motivo']) ? trim($data['motivo']) : null;

    try {
        $usuarioId = Auth::id();

        $sucesso = $model->rejeitar($id, $usuarioId, $motivo);

        if (!$sucesso) {
            jsonError('Erro ao rejeitar contribuição', 500);
        }

        jsonSuccess([], 'Contribuição rejeitada.');

    } catch (Exception $e) {
        jsonError($e->getMessage(), 400);
    }
}

/**
 * Formata data/hora para exibição em português brasileiro com hora
 */
function dataHoraBRComHora($dataHora)
{
    if (empty($dataHora)) {
        return null;
    }

    $timestamp = strtotime($dataHora);
    return date('d/m/Y H:i', $timestamp);
}
