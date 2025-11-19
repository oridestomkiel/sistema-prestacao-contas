<?php
/**
 * API de Saídas
 * CRUD completo de saídas financeiras
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../src/config/Config.php';
require_once __DIR__ . '/../../src/config/Database.php';
require_once __DIR__ . '/../../src/models/Saida.php';
require_once __DIR__ . '/../../src/middleware/Auth.php';
require_once __DIR__ . '/../../src/middleware/CSRF.php';
require_once __DIR__ . '/../../src/helpers/functions.php';

// Inicializar
Config::load();
Auth::iniciarSessao();

// Verificar autenticação
Auth::requireAuth();

// Obter método e ID
$method = getRequestMethod();
$id = $_GET['id'] ?? null;

$saidaModel = new Saida();

try {
    switch ($method) {
        case 'GET':
            if ($id) {
                handleGetOne($saidaModel, $id);
            } else {
                handleGetAll($saidaModel);
            }
            break;

        case 'POST':
            Auth::requireAdmin(true);
            handleCreate($saidaModel);
            break;

        case 'PUT':
            Auth::requireAdmin(true);
            if (!$id) {
                jsonError('ID é obrigatório para atualização', 400);
            }
            handleUpdate($saidaModel, $id);
            break;

        case 'DELETE':
            Auth::requireAdmin(true);
            if (!$id) {
                jsonError('ID é obrigatório para exclusão', 400);
            }
            handleDelete($saidaModel, $id);
            break;

        default:
            jsonError('Método não permitido', 405);
    }

} catch (Exception $e) {
    error_log("Erro na API de saídas: " . $e->getMessage());
    jsonError('Erro: ' . $e->getMessage(), 500);
}

/**
 * GET - Listar saídas
 */
function handleGetAll($model)
{
    $filtros = [];

    // Filtros
    if (isset($_GET['tipo'])) {
        $filtros['tipo'] = $_GET['tipo'];
    }

    if (isset($_GET['categoria_id'])) {
        $filtros['categoria_id'] = $_GET['categoria_id'];
    }

    if (isset($_GET['mes']) && isset($_GET['ano'])) {
        $filtros['mes'] = (int) $_GET['mes'];
        $filtros['ano'] = (int) $_GET['ano'];
    }

    if (isset($_GET['data_inicio'])) {
        $filtros['data_inicio'] = $_GET['data_inicio'];
    }

    if (isset($_GET['data_fim'])) {
        $filtros['data_fim'] = $_GET['data_fim'];
    }

    if (isset($_GET['busca'])) {
        $filtros['busca'] = $_GET['busca'];
    }

    // Paginação
    if (isset($_GET['limite'])) {
        $filtros['limite'] = (int) $_GET['limite'];
        $filtros['offset'] = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;
    }

    // Ordenação
    if (isset($_GET['ordenar'])) {
        $filtros['ordenar'] = $_GET['ordenar'];
    }

    $saidas = $model->listar($filtros);

    // Formatar valores para exibição
    foreach ($saidas as &$saida) {
        $saida['data_formatada'] = formatarData($saida['data']);
        $saida['valor_formatado'] = formatarValor($saida['valor']);
    }

    // Contar total (sem paginação)
    $total = $model->contar(array_diff_key($filtros, ['limite' => '', 'offset' => '', 'ordenar' => '']));

    jsonSuccess([
        'saidas' => $saidas,
        'total' => $total,
        'pagina_atual' => isset($filtros['offset']) && isset($filtros['limite'])
            ? floor($filtros['offset'] / $filtros['limite']) + 1
            : 1
    ]);
}

/**
 * GET - Obter uma saída
 */
function handleGetOne($model, $id)
{
    $saida = $model->buscarPorId($id);

    if (!$saida) {
        jsonError('Saída não encontrada', 404);
    }

    $saida['data_formatada'] = formatarData($saida['data']);
    $saida['valor_formatado'] = formatarValor($saida['valor']);

    jsonSuccess(['saida' => $saida]);
}

/**
 * POST - Criar saída
 */
function handleCreate($model)
{
    // Obter dados do POST
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    // Validar dados obrigatórios
    $required = ['data', 'tipo', 'categoria_id', 'item', 'valor'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            jsonError("Campo '{$field}' é obrigatório", 400);
        }
    }

    // Garantir que valor seja numérico
    $data['valor'] = (float) $data['valor'];

    // Converter checkbox para inteiro
    $data['nao_contabilizar'] = isset($data['nao_contabilizar']) && $data['nao_contabilizar'] ? 1 : 0;

    try {
        $id = $model->criar($data, Auth::id());

        if (!$id) {
            jsonError('Erro ao criar saída', 500);
        }

        $saida = $model->buscarPorId($id);

        jsonSuccess([
            'saida' => $saida,
            'id' => $id
        ], 'Saída criada com sucesso');

    } catch (Exception $e) {
        jsonError($e->getMessage(), 400);
    }
}

/**
 * PUT - Atualizar saída
 */
function handleUpdate($model, $id)
{
    // Verificar se saída existe
    $saida = $model->buscarPorId($id);
    if (!$saida) {
        jsonError('Saída não encontrada', 404);
    }

    // Obter dados do PUT
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    // Garantir que valor seja numérico se enviado
    if (isset($data['valor'])) {
        $data['valor'] = (float) $data['valor'];
    }

    // Converter checkbox para inteiro se enviado
    if (isset($data['nao_contabilizar'])) {
        $data['nao_contabilizar'] = $data['nao_contabilizar'] ? 1 : 0;
    }

    try {
        $sucesso = $model->atualizar($id, $data);

        if (!$sucesso) {
            jsonError('Nenhuma alteração foi feita', 400);
        }

        $saidaAtualizada = $model->buscarPorId($id);

        jsonSuccess([
            'saida' => $saidaAtualizada
        ], 'Saída atualizada com sucesso');

    } catch (Exception $e) {
        jsonError($e->getMessage(), 400);
    }
}

/**
 * DELETE - Excluir saída
 */
function handleDelete($model, $id)
{
    // Verificar se saída existe
    $saida = $model->buscarPorId($id);
    if (!$saida) {
        jsonError('Saída não encontrada', 404);
    }

    $sucesso = $model->excluir($id);

    if (!$sucesso) {
        jsonError('Erro ao excluir saída', 500);
    }

    jsonSuccess(null, 'Saída excluída com sucesso');
}
