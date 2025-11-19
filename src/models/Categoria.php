<?php
/**
 * Model Categoria
 * Gerencia operações relacionadas às categorias de saídas
 */

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../helpers/functions.php';

class Categoria
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Cria uma nova categoria
     *
     * @param array $dados
     * @return int|false ID da categoria criada ou false
     */
    public function criar($dados)
    {
        try {
            if (empty($dados['nome'])) {
                throw new Exception('Nome é obrigatório');
            }

            $sql = "INSERT INTO categorias (nome, descricao, cor, icone)
                    VALUES (:nome, :descricao, :cor, :icone)";

            $params = [
                ':nome' => sanitize($dados['nome']),
                ':descricao' => isset($dados['descricao']) ? sanitize($dados['descricao']) : null,
                ':cor' => isset($dados['cor']) ? $dados['cor'] : '#6B7280',
                ':icone' => isset($dados['icone']) ? $dados['icone'] : 'fa-folder'
            ];

            return $this->db->insert($sql, $params);

        } catch (Exception $e) {
            error_log("Erro ao criar categoria: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Busca categoria por ID
     *
     * @param int $id
     * @return array|false
     */
    public function buscarPorId($id)
    {
        $sql = "SELECT * FROM categorias WHERE id = :id";
        return $this->db->fetchOne($sql, [':id' => $id]);
    }

    /**
     * Lista categorias
     *
     * @param array $filtros
     * @return array
     */
    public function listar($filtros = [])
    {
        $where = [];
        $params = [];

        // Filtrar apenas ativas por padrão
        if (!isset($filtros['incluir_inativas']) || !$filtros['incluir_inativas']) {
            $where[] = "ativa = 1";
        }

        // Busca por nome
        if (!empty($filtros['busca'])) {
            $where[] = "(nome LIKE :busca OR descricao LIKE :busca)";
            $params[':busca'] = '%' . $filtros['busca'] . '%';
        }

        $whereClause = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

        $sql = "SELECT * FROM categorias {$whereClause} ORDER BY nome ASC";

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Atualiza uma categoria
     *
     * @param int $id
     * @param array $dados
     * @return bool
     */
    public function atualizar($id, $dados)
    {
        $campos = [];
        $params = [':id' => $id];

        if (isset($dados['nome'])) {
            $campos[] = "nome = :nome";
            $params[':nome'] = sanitize($dados['nome']);
        }

        if (isset($dados['descricao'])) {
            $campos[] = "descricao = :descricao";
            $params[':descricao'] = sanitize($dados['descricao']);
        }

        if (isset($dados['cor'])) {
            $campos[] = "cor = :cor";
            $params[':cor'] = $dados['cor'];
        }

        if (isset($dados['icone'])) {
            $campos[] = "icone = :icone";
            $params[':icone'] = $dados['icone'];
        }

        if (isset($dados['ativa'])) {
            $campos[] = "ativa = :ativa";
            $params[':ativa'] = $dados['ativa'] ? 1 : 0;
        }

        if (empty($campos)) {
            return false;
        }

        $sql = "UPDATE categorias SET " . implode(', ', $campos) . " WHERE id = :id";

        return $this->db->update($sql, $params) > 0;
    }

    /**
     * Exclui uma categoria
     *
     * @param int $id
     * @return bool
     */
    public function excluir($id)
    {
        // Verificar se há saídas usando esta categoria
        $sql = "SELECT COUNT(*) FROM saidas WHERE categoria_id = :id";
        $count = $this->db->fetchColumn($sql, [':id' => $id]);

        if ($count > 0) {
            throw new Exception("Não é possível excluir esta categoria pois existem {$count} saída(s) associada(s)");
        }

        $sql = "DELETE FROM categorias WHERE id = :id";
        return $this->db->delete($sql, [':id' => $id]) > 0;
    }

    /**
     * Desativa uma categoria
     *
     * @param int $id
     * @return bool
     */
    public function desativar($id)
    {
        $sql = "UPDATE categorias SET ativa = 0 WHERE id = :id";
        return $this->db->update($sql, [':id' => $id]) > 0;
    }

    /**
     * Ativa uma categoria
     *
     * @param int $id
     * @return bool
     */
    public function ativar($id)
    {
        $sql = "UPDATE categorias SET ativa = 1 WHERE id = :id";
        return $this->db->update($sql, [':id' => $id]) > 0;
    }

    /**
     * Conta saídas por categoria
     *
     * @param int $id
     * @return int
     */
    public function contarSaidas($id)
    {
        $sql = "SELECT COUNT(*) FROM saidas WHERE categoria_id = :id AND deleted_at IS NULL";
        return (int) $this->db->fetchColumn($sql, [':id' => $id]);
    }
}
