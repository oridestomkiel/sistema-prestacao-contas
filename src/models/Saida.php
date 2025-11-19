<?php
/**
 * Model Saida
 * Gerencia operações relacionadas às saídas financeiras
 */

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../helpers/functions.php';

class Saida
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Cria uma nova saída
     *
     * @param array $dados
     * @param int $usuarioId
     * @return int|false ID da saída criada ou false
     */
    public function criar($dados, $usuarioId)
    {
        try {
            // Validações
            if (empty($dados['data']) || !validarData($dados['data'])) {
                throw new Exception('Data inválida');
            }

            if (empty($dados['tipo']) || !in_array($dados['tipo'], ['compra', 'pagamento'])) {
                throw new Exception('Tipo inválido');
            }

            if (empty($dados['categoria_id'])) {
                throw new Exception('Categoria é obrigatória');
            }

            if (empty($dados['item'])) {
                throw new Exception('Item é obrigatório');
            }

            if (!isset($dados['valor']) || $dados['valor'] <= 0) {
                throw new Exception('Valor deve ser maior que zero');
            }

            $sql = "INSERT INTO saidas (data, tipo, categoria_id, item, valor, fornecedor, observacoes, nao_contabilizar, criado_por)
                    VALUES (:data, :tipo, :categoria_id, :item, :valor, :fornecedor, :observacoes, :nao_contabilizar, :criado_por)";

            $params = [
                ':data' => dataParaMySQL($dados['data']),
                ':tipo' => $dados['tipo'],
                ':categoria_id' => (int) $dados['categoria_id'],
                ':item' => sanitize($dados['item']),
                ':valor' => (float) $dados['valor'],
                ':fornecedor' => isset($dados['fornecedor']) ? sanitize($dados['fornecedor']) : null,
                ':observacoes' => isset($dados['observacoes']) ? sanitize($dados['observacoes']) : null,
                ':nao_contabilizar' => isset($dados['nao_contabilizar']) ? (int) $dados['nao_contabilizar'] : 0,
                ':criado_por' => $usuarioId
            ];

            return $this->db->insert($sql, $params);

        } catch (Exception $e) {
            error_log("Erro ao criar saída: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Busca saída por ID
     *
     * @param int $id
     * @return array|false
     */
    public function buscarPorId($id)
    {
        $sql = "SELECT s.*, u.nome as criador_nome, c.nome as categoria, c.icone as categoria_icone
                FROM saidas s
                LEFT JOIN usuarios u ON s.criado_por = u.id
                LEFT JOIN categorias c ON s.categoria_id = c.id
                WHERE s.id = :id";

        return $this->db->fetchOne($sql, [':id' => $id]);
    }

    /**
     * Lista saídas com filtros
     *
     * @param array $filtros
     * @return array
     */
    public function listar($filtros = [])
    {
        $where = [];
        $params = [];

        // Filtrar apenas não deletados (a menos que explicitamente solicitado)
        if (!isset($filtros['incluir_deletados']) || !$filtros['incluir_deletados']) {
            $where[] = "s.deleted_at IS NULL";
        }

        // Filtro por tipo
        if (!empty($filtros['tipo'])) {
            $where[] = "s.tipo = :tipo";
            $params[':tipo'] = $filtros['tipo'];
        }

        // Filtro por categoria (suporta tanto categoria_id quanto categoria para compatibilidade)
        if (!empty($filtros['categoria_id'])) {
            $where[] = "s.categoria_id = :categoria_id";
            $params[':categoria_id'] = (int) $filtros['categoria_id'];
        } elseif (!empty($filtros['categoria'])) {
            $where[] = "c.nome = :categoria";
            $params[':categoria'] = $filtros['categoria'];
        }

        // Filtro por período
        if (!empty($filtros['data_inicio'])) {
            $where[] = "s.data >= :data_inicio";
            $params[':data_inicio'] = dataParaMySQL($filtros['data_inicio']);
        }

        if (!empty($filtros['data_fim'])) {
            $where[] = "s.data <= :data_fim";
            $params[':data_fim'] = dataParaMySQL($filtros['data_fim']);
        }

        // Filtro por mês/ano
        if (!empty($filtros['mes']) && !empty($filtros['ano'])) {
            $where[] = "YEAR(s.data) = :ano AND MONTH(s.data) = :mes";
            $params[':ano'] = (int) $filtros['ano'];
            $params[':mes'] = (int) $filtros['mes'];
        }

        // Busca por item ou fornecedor
        if (!empty($filtros['busca'])) {
            $where[] = "(s.item LIKE :busca OR s.fornecedor LIKE :busca OR s.observacoes LIKE :busca)";
            $params[':busca'] = '%' . $filtros['busca'] . '%';
        }

        $whereClause = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

        // Ordenação
        $orderBy = 's.data DESC, s.id DESC';
        if (!empty($filtros['ordenar'])) {
            switch ($filtros['ordenar']) {
                case 'data_asc':
                    $orderBy = 's.data ASC, s.id ASC';
                    break;
                case 'valor_desc':
                    $orderBy = 's.valor DESC';
                    break;
                case 'valor_asc':
                    $orderBy = 's.valor ASC';
                    break;
                case 'categoria':
                    $orderBy = 's.categoria ASC, s.data DESC';
                    break;
            }
        }

        // Paginação
        $limit = '';
        if (isset($filtros['limite'])) {
            $limite = (int) $filtros['limite'];
            $offset = isset($filtros['offset']) ? (int) $filtros['offset'] : 0;
            $limit = " LIMIT {$limite} OFFSET {$offset}";
        }

        $sql = "SELECT s.*, u.nome as criador_nome, c.nome as categoria, c.icone as categoria_icone
                FROM saidas s
                LEFT JOIN usuarios u ON s.criado_por = u.id
                LEFT JOIN categorias c ON s.categoria_id = c.id
                {$whereClause}
                ORDER BY {$orderBy}
                {$limit}";

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Atualiza uma saída
     *
     * @param int $id
     * @param array $dados
     * @return bool
     */
    public function atualizar($id, $dados)
    {
        $campos = [];
        $params = [':id' => $id];

        if (isset($dados['data']) && validarData($dados['data'])) {
            $campos[] = "data = :data";
            $params[':data'] = dataParaMySQL($dados['data']);
        }

        if (isset($dados['tipo']) && in_array($dados['tipo'], ['compra', 'pagamento'])) {
            $campos[] = "tipo = :tipo";
            $params[':tipo'] = $dados['tipo'];
        }

        if (isset($dados['categoria_id'])) {
            $campos[] = "categoria_id = :categoria_id";
            $params[':categoria_id'] = (int) $dados['categoria_id'];
        }

        if (isset($dados['item'])) {
            $campos[] = "item = :item";
            $params[':item'] = sanitize($dados['item']);
        }

        if (isset($dados['valor']) && is_numeric($dados['valor'])) {
            $campos[] = "valor = :valor";
            $params[':valor'] = (float) $dados['valor'];
        }

        if (isset($dados['fornecedor'])) {
            $campos[] = "fornecedor = :fornecedor";
            $params[':fornecedor'] = sanitize($dados['fornecedor']);
        }

        if (isset($dados['observacoes'])) {
            $campos[] = "observacoes = :observacoes";
            $params[':observacoes'] = sanitize($dados['observacoes']);
        }

        if (isset($dados['nao_contabilizar'])) {
            $campos[] = "nao_contabilizar = :nao_contabilizar";
            $params[':nao_contabilizar'] = (int) $dados['nao_contabilizar'];
        }

        if (empty($campos)) {
            return false;
        }

        $sql = "UPDATE saidas SET " . implode(', ', $campos) . " WHERE id = :id";

        return $this->db->update($sql, $params) > 0;
    }

    /**
     * Exclui uma saída (hard delete)
     *
     * @param int $id
     * @return bool
     */
    public function excluir($id)
    {
        $sql = "DELETE FROM saidas WHERE id = :id";
        return $this->db->delete($sql, [':id' => $id]) > 0;
    }

    /**
     * Soft delete - marca saída como deletada
     *
     * @param int $id
     * @return bool
     */
    public function softDelete($id)
    {
        $sql = "UPDATE saidas SET deleted_at = NOW() WHERE id = :id AND deleted_at IS NULL";
        return $this->db->update($sql, [':id' => $id]) > 0;
    }

    /**
     * Restaura uma saída deletada
     *
     * @param int $id
     * @return bool
     */
    public function restaurar($id)
    {
        $sql = "UPDATE saidas SET deleted_at = NULL WHERE id = :id";
        return $this->db->update($sql, [':id' => $id]) > 0;
    }

    /**
     * Calcula o total de saídas por período
     *
     * @param string $dataInicio
     * @param string $dataFim
     * @param string|null $tipo
     * @param string|null $categoria
     * @return float
     */
    public function totalPorPeriodo($dataInicio, $dataFim, $tipo = null, $categoria = null)
    {
        $params = [
            ':data_inicio' => dataParaMySQL($dataInicio),
            ':data_fim' => dataParaMySQL($dataFim)
        ];

        $whereExtra = ' AND deleted_at IS NULL AND nao_contabilizar = 0';

        if ($tipo !== null) {
            $whereExtra .= " AND tipo = :tipo";
            $params[':tipo'] = $tipo;
        }

        if ($categoria !== null) {
            $whereExtra .= " AND categoria_id = :categoria";
            $params[':categoria'] = $categoria;
        }

        $sql = "SELECT COALESCE(SUM(valor), 0) as total
                FROM saidas
                WHERE data BETWEEN :data_inicio AND :data_fim
                {$whereExtra}";

        $resultado = $this->db->fetchOne($sql, $params);
        return (float) ($resultado['total'] ?? 0);
    }

    /**
     * Calcula o total geral de saídas
     *
     * @return float
     */
    public function totalGeral()
    {
        $sql = "SELECT COALESCE(SUM(valor), 0) as total FROM saidas WHERE deleted_at IS NULL AND nao_contabilizar = 0";
        $resultado = $this->db->fetchOne($sql);
        return (float) ($resultado['total'] ?? 0);
    }

    /**
     * Obtém totais por categoria
     *
     * @param string $dataInicio
     * @param string $dataFim
     * @return array
     */
    public function totaisPorCategoria($dataInicio, $dataFim)
    {
        $sql = "SELECT c.nome as categoria, c.icone as categoria_icone, COALESCE(SUM(s.valor), 0) as total, COUNT(*) as quantidade
                FROM saidas s
                LEFT JOIN categorias c ON s.categoria_id = c.id
                WHERE s.data BETWEEN :data_inicio AND :data_fim
                  AND s.deleted_at IS NULL
                  AND s.nao_contabilizar = 0
                GROUP BY s.categoria_id, c.nome, c.icone
                ORDER BY total DESC";

        $params = [
            ':data_inicio' => dataParaMySQL($dataInicio),
            ':data_fim' => dataParaMySQL($dataFim)
        ];

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Obtém totais por tipo
     *
     * @param string $dataInicio
     * @param string $dataFim
     * @return array
     */
    public function totaisPorTipo($dataInicio, $dataFim)
    {
        $sql = "SELECT tipo, COALESCE(SUM(valor), 0) as total, COUNT(*) as quantidade
                FROM saidas
                WHERE data BETWEEN :data_inicio AND :data_fim
                  AND deleted_at IS NULL
                  AND nao_contabilizar = 0
                GROUP BY tipo";

        $params = [
            ':data_inicio' => dataParaMySQL($dataInicio),
            ':data_fim' => dataParaMySQL($dataFim)
        ];

        return $this->db->fetchAll($sql, $params);
    }


    /**
     * Obtém resumo mensal de saídas
     *
     * @param int $mes
     * @param int $ano
     * @return array
     */
    public function resumoMensal($mes, $ano)
    {
        $periodo = periodoMes($mes, $ano);

        return [
            'total' => $this->totalPorPeriodo($periodo['inicio'], $periodo['fim']),
            'por_tipo' => $this->totaisPorTipo($periodo['inicio'], $periodo['fim']),
            'por_categoria' => $this->totaisPorCategoria($periodo['inicio'], $periodo['fim']),
            'quantidade' => $this->contar([
                'mes' => $mes,
                'ano' => $ano
            ])
        ];
    }

    /**
     * Obtém últimas saídas
     *
     * @param int $limite
     * @return array
     */
    public function ultimas($limite = 5)
    {
        return $this->listar(['limite' => $limite]);
    }

    /**
     * Conta o número de saídas
     *
     * @param array $filtros
     * @return int
     */
    public function contar($filtros = [])
    {
        $where = [];
        $params = [];

        // Filtrar apenas não deletados por padrão
        if (!isset($filtros['incluir_deletados']) || !$filtros['incluir_deletados']) {
            $where[] = "deleted_at IS NULL";
        }

        if (!empty($filtros['tipo'])) {
            $where[] = "tipo = :tipo";
            $params[':tipo'] = $filtros['tipo'];
        }

        if (!empty($filtros['categoria_id'])) {
            $where[] = "categoria_id = :categoria_id";
            $params[':categoria_id'] = (int) $filtros['categoria_id'];
        }

        if (!empty($filtros['mes']) && !empty($filtros['ano'])) {
            $where[] = "YEAR(data) = :ano AND MONTH(data) = :mes";
            $params[':ano'] = (int) $filtros['ano'];
            $params[':mes'] = (int) $filtros['mes'];
        }

        if (!empty($filtros['data_inicio'])) {
            $where[] = "data >= :data_inicio";
            $params[':data_inicio'] = dataParaMySQL($filtros['data_inicio']);
        }

        if (!empty($filtros['data_fim'])) {
            $where[] = "data <= :data_fim";
            $params[':data_fim'] = dataParaMySQL($filtros['data_fim']);
        }

        $whereClause = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

        $sql = "SELECT COUNT(*) FROM saidas {$whereClause}";

        return (int) $this->db->fetchColumn($sql, $params);
    }

    /**
     * Obtém saídas agrupadas por mês
     *
     * @param int $ano
     * @return array
     */
    public function porMes($ano)
    {
        $sql = "SELECT
                    MONTH(data) as mes,
                    COALESCE(SUM(valor), 0) as total,
                    COUNT(*) as quantidade
                FROM saidas
                WHERE YEAR(data) = :ano
                  AND deleted_at IS NULL
                  AND nao_contabilizar = 0
                GROUP BY MONTH(data)
                ORDER BY mes";

        return $this->db->fetchAll($sql, [':ano' => $ano]);
    }
}
