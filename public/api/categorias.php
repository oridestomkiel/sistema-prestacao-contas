<?php
/**
 * API de Categorias
 * CRUD completo de categorias de saídas
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../src/config/Config.php';
require_once __DIR__ . '/../../src/config/Database.php';
require_once __DIR__ . '/../../src/models/Categoria.php';
require_once __DIR__ . '/../../src/middleware/Auth.php';
require_once __DIR__ . '/../../src/helpers/functions.php';

// Inicializar
Config::load();
Auth::iniciarSessao();

// Verificar autenticação
Auth::requireAuth();

// Obter método e ID
$method = getRequestMethod();
$id = $_GET['id'] ?? null;

$categoriaModel = new Categoria();

try {
    switch ($method) {
        case 'GET':
            if ($id) {
                handleGetOne($categoriaModel, $id);
            } else {
                handleGetAll($categoriaModel);
            }
            break;

        case 'POST':
            Auth::requireAdmin(true);
            handleCreate($categoriaModel);
            break;

        case 'PUT':
            Auth::requireAdmin(true);
            if (!$id) {
                jsonError('ID é obrigatório para atualização', 400);
            }
            handleUpdate($categoriaModel, $id);
            break;

        case 'DELETE':
            Auth::requireAdmin(true);
            if (!$id) {
                jsonError('ID é obrigatório para exclusão', 400);
            }
            handleDelete($categoriaModel, $id);
            break;

        default:
            jsonError('Método não permitido', 405);
    }

} catch (Exception $e) {
    error_log("Erro na API de categorias: " . $e->getMessage());
    jsonError('Erro: ' . $e->getMessage(), 500);
}

/**
 * GET - Listar categorias
 */
function handleGetAll($model)
{
    $filtros = [];

    // Filtro para incluir inativas (apenas admin)
    if (isset($_GET['incluir_inativas']) && Auth::isAdmin()) {
        $filtros['incluir_inativas'] = true;
    }

    if (isset($_GET['busca'])) {
        $filtros['busca'] = $_GET['busca'];
    }

    $categorias = $model->listar($filtros);

    // Adicionar contagem de saídas
    foreach ($categorias as &$categoria) {
        $categoria['total_saidas'] = $model->contarSaidas($categoria['id']);
    }

    jsonSuccess([
        'categorias' => $categorias,
        'total' => count($categorias)
    ]);
}

/**
 * GET - Obter uma categoria
 */
function handleGetOne($model, $id)
{
    $categoria = $model->buscarPorId($id);

    if (!$categoria) {
        jsonError('Categoria não encontrada', 404);
    }

    $categoria['total_saidas'] = $model->contarSaidas($id);

    jsonSuccess(['categoria' => $categoria]);
}

/**
 * POST - Criar categoria
 */
function handleCreate($model)
{
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    if (!isset($data['nome']) || empty($data['nome'])) {
        jsonError('Nome é obrigatório', 400);
    }

    try {
        $id = $model->criar($data);

        if (!$id) {
            jsonError('Erro ao criar categoria', 500);
        }

        $categoria = $model->buscarPorId($id);

        jsonSuccess([
            'categoria' => $categoria,
            'id' => $id
        ], 'Categoria criada com sucesso');

    } catch (Exception $e) {
        jsonError($e->getMessage(), 400);
    }
}

/**
 * PUT - Atualizar categoria
 */
function handleUpdate($model, $id)
{
    $categoria = $model->buscarPorId($id);
    if (!$categoria) {
        jsonError('Categoria não encontrada', 404);
    }

    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    try {
        $sucesso = $model->atualizar($id, $data);

        if (!$sucesso) {
            jsonError('Nenhuma alteração foi feita', 400);
        }

        $categoriaAtualizada = $model->buscarPorId($id);

        jsonSuccess([
            'categoria' => $categoriaAtualizada
        ], 'Categoria atualizada com sucesso');

    } catch (Exception $e) {
        jsonError($e->getMessage(), 400);
    }
}

/**
 * DELETE - Excluir categoria
 */
function handleDelete($model, $id)
{
    $categoria = $model->buscarPorId($id);
    if (!$categoria) {
        jsonError('Categoria não encontrada', 404);
    }

    try {
        $sucesso = $model->excluir($id);

        if (!$sucesso) {
            jsonError('Erro ao excluir categoria', 500);
        }

        jsonSuccess(null, 'Categoria excluída com sucesso');

    } catch (Exception $e) {
        jsonError($e->getMessage(), 400);
    }
}
