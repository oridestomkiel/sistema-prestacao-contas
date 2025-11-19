<?php
/**
 * Model ContribuicaoPendente
 * Gerencia operações relacionadas às contribuições pendentes de aprovação
 */

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../helpers/functions.php';

class ContribuicaoPendente
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Cria uma nova contribuição pendente
     *
     * @param array $dados
     * @return int|false ID da contribuição criada ou false
     */
    public function criar($dados)
    {
        try {
            // Validações
            if (!isset($dados['valor']) || $dados['valor'] <= 0) {
                throw new Exception('Valor deve ser maior que zero');
            }

            $sql = "INSERT INTO contribuicoes_pendentes
                    (nome_doador, nome_sessao, exibir_anonimo, valor, comprovante_path, observacoes)
                    VALUES (:nome_doador, :nome_sessao, :exibir_anonimo, :valor, :comprovante_path, :observacoes)";

            $params = [
                ':nome_doador' => isset($dados['nome_doador']) ? sanitize($dados['nome_doador']) : null,
                ':nome_sessao' => isset($dados['nome_sessao']) ? sanitize($dados['nome_sessao']) : null,
                ':exibir_anonimo' => isset($dados['exibir_anonimo']) ? (int) $dados['exibir_anonimo'] : 0,
                ':valor' => (float) $dados['valor'],
                ':comprovante_path' => isset($dados['comprovante_path']) ? sanitize($dados['comprovante_path']) : null,
                ':observacoes' => isset($dados['observacoes']) ? sanitize($dados['observacoes']) : null
            ];

            return $this->db->insert($sql, $params);

        } catch (Exception $e) {
            error_log("Erro ao criar contribuição pendente: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Busca contribuição por ID
     *
     * @param int $id
     * @return array|false
     */
    public function buscarPorId($id)
    {
        $sql = "SELECT cp.*,
                       ua.nome as aprovador_nome
                FROM contribuicoes_pendentes cp
                LEFT JOIN usuarios ua ON cp.aprovado_por = ua.id
                WHERE cp.id = :id";

        return $this->db->fetchOne($sql, [':id' => $id]);
    }

    /**
     * Lista contribuições com filtros
     *
     * @param array $filtros
     * @return array
     */
    public function listar($filtros = [])
    {
        $where = [];
        $params = [];

        // Filtro por status
        if (!empty($filtros['status'])) {
            $where[] = "cp.status = :status";
            $params[':status'] = $filtros['status'];
        }

        // Filtro por data de criação
        if (!empty($filtros['data_inicio'])) {
            $where[] = "DATE(cp.criado_em) >= :data_inicio";
            $params[':data_inicio'] = $filtros['data_inicio'];
        }

        if (!empty($filtros['data_fim'])) {
            $where[] = "DATE(cp.criado_em) <= :data_fim";
            $params[':data_fim'] = $filtros['data_fim'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT cp.*,
                       ua.nome as aprovador_nome,
                       e.id as entrada_existe
                FROM contribuicoes_pendentes cp
                LEFT JOIN usuarios ua ON cp.aprovado_por = ua.id
                LEFT JOIN entradas e ON cp.entrada_id = e.id
                {$whereClause}
                ORDER BY cp.criado_em DESC";

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Conta contribuições pendentes
     *
     * @return int
     */
    public function contarPendentes()
    {
        $sql = "SELECT COUNT(*) as total FROM contribuicoes_pendentes WHERE status = 'pendente'";
        $result = $this->db->fetchOne($sql);
        return $result ? (int) $result['total'] : 0;
    }

    /**
     * Aprova uma contribuição e cria entrada automaticamente
     *
     * @param int $id ID da contribuição
     * @param int $usuarioId ID do admin que está aprovando
     * @return bool
     */
    public function aprovar($id, $usuarioId)
    {
        try {
            $this->db->beginTransaction();

            // Buscar contribuição
            $contribuicao = $this->buscarPorId($id);
            if (!$contribuicao) {
                throw new Exception('Contribuição não encontrada');
            }

            if ($contribuicao['status'] !== 'pendente') {
                throw new Exception('Contribuição já foi processada');
            }

            // Preparar dados para entrada
            $nomeExibicao = $contribuicao['exibir_anonimo'] ? 'Anônimo' :
                           ($contribuicao['nome_doador'] ?: $contribuicao['nome_sessao'] ?: 'Anônimo');

            // Nome real da pessoa (sempre salvar, visível apenas para admin)
            $nomePessoa = $contribuicao['nome_sessao'] ?: $contribuicao['nome_doador'];

            // Preparar observações (sem TxID PIX)
            $observacoes = 'Feito por: ';
            if ($contribuicao['exibir_anonimo']) {
                $observacoes .= 'Anônimo';
            } else {
                $observacoes .= $nomeExibicao;
            }

            // Criar entrada
            require_once __DIR__ . '/Entrada.php';
            $entradaModel = new Entrada();

            $entradaId = $entradaModel->criar([
                'data' => date('Y-m-d'),
                'tipo' => 'contribuicao',
                'descricao' => 'Contribuição',
                'pessoa' => $nomePessoa, // Nome real, visível apenas para admin
                'valor' => $contribuicao['valor'],
                'observacoes' => $observacoes
            ], $usuarioId);

            if (!$entradaId) {
                throw new Exception('Erro ao criar entrada');
            }

            // Atualizar contribuição
            $sql = "UPDATE contribuicoes_pendentes
                    SET status = 'aprovada',
                        aprovado_por = :aprovado_por,
                        aprovado_em = NOW(),
                        entrada_id = :entrada_id
                    WHERE id = :id";

            $this->db->update($sql, [
                ':aprovado_por' => $usuarioId,
                ':entrada_id' => $entradaId,
                ':id' => $id
            ]);

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Erro ao aprovar contribuição: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Rejeita uma contribuição
     *
     * @param int $id ID da contribuição
     * @param int $usuarioId ID do admin que está rejeitando
     * @param string $motivo Motivo da rejeição
     * @return bool
     */
    public function rejeitar($id, $usuarioId, $motivo = null)
    {
        try {
            // Buscar contribuição
            $contribuicao = $this->buscarPorId($id);
            if (!$contribuicao) {
                throw new Exception('Contribuição não encontrada');
            }

            if ($contribuicao['status'] !== 'pendente') {
                throw new Exception('Contribuição já foi processada');
            }

            // Atualizar observações com motivo da rejeição
            $observacoes = $contribuicao['observacoes'] ?: '';
            if ($motivo) {
                $observacoes .= "\nMotivo da rejeição: " . $motivo;
            }

            $sql = "UPDATE contribuicoes_pendentes
                    SET status = 'rejeitada',
                        aprovado_por = :aprovado_por,
                        aprovado_em = NOW(),
                        observacoes = :observacoes
                    WHERE id = :id";

            return $this->db->update($sql, [
                ':aprovado_por' => $usuarioId,
                ':observacoes' => $observacoes,
                ':id' => $id
            ]) > 0;

        } catch (Exception $e) {
            error_log("Erro ao rejeitar contribuição: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Atualiza uma contribuição pendente
     *
     * @param int $id
     * @param array $dados
     * @return bool
     */
    public function atualizar($id, $dados)
    {
        try {
            $campos = [];
            $params = [':id' => $id];

            if (isset($dados['nome_doador'])) {
                $campos[] = "nome_doador = :nome_doador";
                $params[':nome_doador'] = sanitize($dados['nome_doador']);
            }

            if (isset($dados['exibir_anonimo'])) {
                $campos[] = "exibir_anonimo = :exibir_anonimo";
                $params[':exibir_anonimo'] = (int) $dados['exibir_anonimo'];
            }

            if (isset($dados['valor'])) {
                $campos[] = "valor = :valor";
                $params[':valor'] = (float) $dados['valor'];
            }

            if (isset($dados['observacoes'])) {
                $campos[] = "observacoes = :observacoes";
                $params[':observacoes'] = sanitize($dados['observacoes']);
            }

            if (empty($campos)) {
                return true;
            }

            $sql = "UPDATE contribuicoes_pendentes SET " . implode(', ', $campos) . " WHERE id = :id";
            return $this->db->execute($sql, $params);

        } catch (Exception $e) {
            error_log("Erro ao atualizar contribuição: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Exclui uma contribuição
     *
     * @param int $id
     * @return bool
     */
    public function excluir($id)
    {
        try {
            $sql = "DELETE FROM contribuicoes_pendentes WHERE id = :id AND status = 'pendente'";
            return $this->db->execute($sql, [':id' => $id]);
        } catch (Exception $e) {
            error_log("Erro ao excluir contribuição: " . $e->getMessage());
            throw $e;
        }
    }
}
