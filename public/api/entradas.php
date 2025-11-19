<?php
/**
 * API de Entradas
 * CRUD completo de entradas financeiras
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../src/config/Config.php';
require_once __DIR__ . '/../../src/config/Database.php';
require_once __DIR__ . '/../../src/models/Entrada.php';
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

$entradaModel = new Entrada();

try {
    switch ($method) {
        case 'GET':
            if ($id) {
                handleGetOne($entradaModel, $id);
            } else {
                handleGetAll($entradaModel);
            }
            break;

        case 'POST':
            Auth::requireAdmin(true);
            handleCreate($entradaModel);
            break;

        case 'PUT':
            Auth::requireAdmin(true);
            if (!$id) {
                jsonError('ID é obrigatório para atualização', 400);
            }
            handleUpdate($entradaModel, $id);
            break;

        case 'DELETE':
            Auth::requireAdmin(true);
            if (!$id) {
                jsonError('ID é obrigatório para exclusão', 400);
            }
            handleDelete($entradaModel, $id);
            break;

        default:
            jsonError('Método não permitido', 405);
    }

} catch (Exception $e) {
    error_log("Erro na API de entradas: " . $e->getMessage());
    jsonError('Erro: ' . $e->getMessage(), 500);
}

/**
 * GET - Listar entradas
 */
function handleGetAll($model)
{
    $filtros = [];

    // Filtros
    if (isset($_GET['tipo'])) {
        $filtros['tipo'] = $_GET['tipo'];
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

    $entradas = $model->listar($filtros);

    // Formatar valores para exibição
    foreach ($entradas as &$entrada) {
        $entrada['data_formatada'] = formatarData($entrada['data']);
        $entrada['valor_formatado'] = formatarValor($entrada['valor']);
    }

    // Contar total (sem paginação)
    $total = $model->contar(array_diff_key($filtros, ['limite' => '', 'offset' => '', 'ordenar' => '']));

    jsonSuccess([
        'entradas' => $entradas,
        'total' => $total,
        'pagina_atual' => isset($filtros['offset']) && isset($filtros['limite'])
            ? floor($filtros['offset'] / $filtros['limite']) + 1
            : 1
    ]);
}

/**
 * GET - Obter uma entrada
 */
function handleGetOne($model, $id)
{
    $entrada = $model->buscarPorId($id);

    if (!$entrada) {
        jsonError('Entrada não encontrada', 404);
    }

    $entrada['data_formatada'] = formatarData($entrada['data']);
    $entrada['valor_formatado'] = formatarValor($entrada['valor']);

    jsonSuccess(['entrada' => $entrada]);
}

/**
 * POST - Criar entrada
 */
function handleCreate($model)
{
    // Obter dados do POST
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    // Validar dados obrigatórios
    $required = ['data', 'tipo', 'descricao', 'valor'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            jsonError("Campo '{$field}' é obrigatório", 400);
        }
    }

    // Garantir que valor seja numérico
    $data['valor'] = (float) $data['valor'];

    try {
        $id = $model->criar($data, Auth::id());

        if (!$id) {
            jsonError('Erro ao criar entrada', 500);
        }

        $entrada = $model->buscarPorId($id);

        jsonSuccess([
            'entrada' => $entrada,
            'id' => $id
        ], 'Entrada criada com sucesso');

    } catch (Exception $e) {
        jsonError($e->getMessage(), 400);
    }
}

/**
 * PUT - Atualizar entrada
 */
function handleUpdate($model, $id)
{
    // Verificar se entrada existe
    $entrada = $model->buscarPorId($id);
    if (!$entrada) {
        jsonError('Entrada não encontrada', 404);
    }

    // Obter dados do PUT
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    // Garantir que valor seja numérico se enviado
    if (isset($data['valor'])) {
        $data['valor'] = (float) $data['valor'];
    }

    try {
        $sucesso = $model->atualizar($id, $data);

        if (!$sucesso) {
            jsonError('Nenhuma alteração foi feita', 400);
        }

        $entradaAtualizada = $model->buscarPorId($id);

        jsonSuccess([
            'entrada' => $entradaAtualizada
        ], 'Entrada atualizada com sucesso');

    } catch (Exception $e) {
        jsonError($e->getMessage(), 400);
    }
}

/**
 * DELETE - Excluir entrada
 */
function handleDelete($model, $id)
{
    // Verificar se entrada existe
    $entrada = $model->buscarPorId($id);
    if (!$entrada) {
        jsonError('Entrada não encontrada', 404);
    }

    $sucesso = $model->excluir($id);

    if (!$sucesso) {
        jsonError('Erro ao excluir entrada', 500);
    }

    jsonSuccess(null, 'Entrada excluída com sucesso');
}
