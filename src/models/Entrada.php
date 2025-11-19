<?php
/**
 * Model Entrada
 * Gerencia operações relacionadas às entradas financeiras
 */

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../helpers/functions.php';

class Entrada
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Cria uma nova entrada
     *
     * @param array $dados
     * @param int $usuarioId
     * @return int|false ID da entrada criada ou false
     */
    public function criar($dados, $usuarioId)
    {
        try {
            // Validações
            if (empty($dados['data']) || !validarData($dados['data'])) {
                throw new Exception('Data inválida');
            }

            if (empty($dados['tipo']) || !in_array($dados['tipo'], ['doacao', 'aposentadoria', 'contribuicao'])) {
                throw new Exception('Tipo inválido');
            }

            if (empty($dados['descricao'])) {
                throw new Exception('Descrição é obrigatória');
            }

            if (!isset($dados['valor']) || $dados['valor'] <= 0) {
                throw new Exception('Valor deve ser maior que zero');
            }

            $sql = "INSERT INTO entradas (data, tipo, descricao, pessoa, valor, observacoes, criado_por)
                    VALUES (:data, :tipo, :descricao, :pessoa, :valor, :observacoes, :criado_por)";

            $params = [
                ':data' => dataParaMySQL($dados['data']),
                ':tipo' => $dados['tipo'],
                ':descricao' => sanitize($dados['descricao']),
                ':pessoa' => isset($dados['pessoa']) ? sanitize($dados['pessoa']) : null,
                ':valor' => (float) $dados['valor'],
                ':observacoes' => isset($dados['observacoes']) ? sanitize($dados['observacoes']) : null,
                ':criado_por' => $usuarioId
            ];

            return $this->db->insert($sql, $params);

        } catch (Exception $e) {
            error_log("Erro ao criar entrada: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Busca entrada por ID
     *
     * @param int $id
     * @return array|false
     */
    public function buscarPorId($id)
    {
        $sql = "SELECT e.*, u.nome as criador_nome
                FROM entradas e
                LEFT JOIN usuarios u ON e.criado_por = u.id
                WHERE e.id = :id";

        return $this->db->fetchOne($sql, [':id' => $id]);
    }

    /**
     * Lista entradas com filtros
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
            $where[] = "e.deleted_at IS NULL";
        }

        // Filtro por tipo
        if (!empty($filtros['tipo'])) {
            $where[] = "e.tipo = :tipo";
            $params[':tipo'] = $filtros['tipo'];
        }

        // Filtro por período
        if (!empty($filtros['data_inicio'])) {
            $where[] = "e.data >= :data_inicio";
            $params[':data_inicio'] = dataParaMySQL($filtros['data_inicio']);
        }

        if (!empty($filtros['data_fim'])) {
            $where[] = "e.data <= :data_fim";
            $params[':data_fim'] = dataParaMySQL($filtros['data_fim']);
        }

        // Filtro por mês/ano
        if (!empty($filtros['mes']) && !empty($filtros['ano'])) {
            $where[] = "YEAR(e.data) = :ano AND MONTH(e.data) = :mes";
            $params[':ano'] = (int) $filtros['ano'];
            $params[':mes'] = (int) $filtros['mes'];
        }

        // Busca por descrição
        if (!empty($filtros['busca'])) {
            $where[] = "(e.descricao LIKE :busca OR e.observacoes LIKE :busca)";
            $params[':busca'] = '%' . $filtros['busca'] . '%';
        }

        $whereClause = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

        // Ordenação
        $orderBy = 'e.data DESC, e.id DESC';
        if (!empty($filtros['ordenar'])) {
            switch ($filtros['ordenar']) {
                case 'data_asc':
                    $orderBy = 'e.data ASC, e.id ASC';
                    break;
                case 'valor_desc':
                    $orderBy = 'e.valor DESC';
                    break;
                case 'valor_asc':
                    $orderBy = 'e.valor ASC';
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

        $sql = "SELECT e.*, u.nome as criador_nome
                FROM entradas e
                LEFT JOIN usuarios u ON e.criado_por = u.id
                {$whereClause}
                ORDER BY {$orderBy}
                {$limit}";

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Atualiza uma entrada
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

        if (isset($dados['tipo']) && in_array($dados['tipo'], ['doacao', 'aposentadoria', 'contribuicao'])) {
            $campos[] = "tipo = :tipo";
            $params[':tipo'] = $dados['tipo'];
        }

        if (isset($dados['descricao'])) {
            $campos[] = "descricao = :descricao";
            $params[':descricao'] = sanitize($dados['descricao']);
        }

        if (isset($dados['pessoa'])) {
            $campos[] = "pessoa = :pessoa";
            $params[':pessoa'] = sanitize($dados['pessoa']);
        }

        if (isset($dados['valor']) && is_numeric($dados['valor'])) {
            $campos[] = "valor = :valor";
            $params[':valor'] = (float) $dados['valor'];
        }

        if (isset($dados['observacoes'])) {
            $campos[] = "observacoes = :observacoes";
            $params[':observacoes'] = sanitize($dados['observacoes']);
        }

        if (empty($campos)) {
            return false;
        }

        $sql = "UPDATE entradas SET " . implode(', ', $campos) . " WHERE id = :id";

        return $this->db->update($sql, $params) > 0;
    }

    /**
     * Exclui uma entrada (hard delete)
     *
     * @param int $id
     * @return bool
     */
    public function excluir($id)
    {
        $sql = "DELETE FROM entradas WHERE id = :id";
        return $this->db->delete($sql, [':id' => $id]) > 0;
    }

    /**
     * Soft delete - marca entrada como deletada
     *
     * @param int $id
     * @return bool
     */
    public function softDelete($id)
    {
        $sql = "UPDATE entradas SET deleted_at = NOW() WHERE id = :id AND deleted_at IS NULL";
        return $this->db->update($sql, [':id' => $id]) > 0;
    }

    /**
     * Restaura uma entrada deletada
     *
     * @param int $id
     * @return bool
     */
    public function restaurar($id)
    {
        $sql = "UPDATE entradas SET deleted_at = NULL WHERE id = :id";
        return $this->db->update($sql, [':id' => $id]) > 0;
    }

    /**
     * Calcula o total de entradas por período
     *
     * @param string $dataInicio
     * @param string $dataFim
     * @param string|null $tipo
     * @return float
     */
    public function totalPorPeriodo($dataInicio, $dataFim, $tipo = null)
    {
        $params = [
            ':data_inicio' => dataParaMySQL($dataInicio),
            ':data_fim' => dataParaMySQL($dataFim)
        ];

        $whereExtra = '';
        if ($tipo !== null) {
            $whereExtra = " AND tipo = :tipo";
            $params[':tipo'] = $tipo;
        }

        $sql = "SELECT COALESCE(SUM(valor), 0) as total
                FROM entradas
                WHERE data BETWEEN :data_inicio AND :data_fim
                AND deleted_at IS NULL
                {$whereExtra}";

        $resultado = $this->db->fetchOne($sql, $params);
        return (float) ($resultado['total'] ?? 0);
    }

    /**
     * Calcula o total geral de entradas
     *
     * @return float
     */
    public function totalGeral()
    {
        $sql = "SELECT COALESCE(SUM(valor), 0) as total FROM entradas WHERE deleted_at IS NULL";
        $resultado = $this->db->fetchOne($sql);
        return (float) ($resultado['total'] ?? 0);
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
                FROM entradas
                WHERE data BETWEEN :data_inicio AND :data_fim
                AND deleted_at IS NULL
                GROUP BY tipo";

        $params = [
            ':data_inicio' => dataParaMySQL($dataInicio),
            ':data_fim' => dataParaMySQL($dataFim)
        ];

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Obtém resumo mensal de entradas
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
            'quantidade' => $this->contar([
                'mes' => $mes,
                'ano' => $ano
            ])
        ];
    }

    /**
     * Obtém últimas entradas
     *
     * @param int $limite
     * @return array
     */
    public function ultimas($limite = 5)
    {
        return $this->listar(['limite' => $limite]);
    }

    /**
     * Conta o número de entradas
     *
     * @param array $filtros
     * @return int
     */
    public function contar($filtros = [])
    {
        $where = [];
        $params = [];

        if (!empty($filtros['tipo'])) {
            $where[] = "tipo = :tipo";
            $params[':tipo'] = $filtros['tipo'];
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

        $sql = "SELECT COUNT(*) FROM entradas {$whereClause}";

        return (int) $this->db->fetchColumn($sql, $params);
    }

    /**
     * Obtém entradas agrupadas por mês
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
                FROM entradas
                WHERE YEAR(data) = :ano
                  AND deleted_at IS NULL
                GROUP BY MONTH(data)
                ORDER BY mes";

        return $this->db->fetchAll($sql, [':ano' => $ano]);
    }
}
