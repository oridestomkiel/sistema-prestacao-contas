<?php
/**
 * Model Visitante
 * Gerencia visitantes que acessam via links compartilhados
 */

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../helpers/functions.php';

class Visitante
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Gera hash único para visitante baseado em dados do navegador
     *
     * @return string
     */
    public static function gerarHash()
    {
        // Usar vários fatores para criar um "fingerprint" único
        $dados = [
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
            $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '',
        ];

        // Gerar hash SHA256
        return hash('sha256', implode('|', $dados) . time());
    }

    /**
     * Busca ou cria visitante
     *
     * @param int $tokenId
     * @param string $visitanteHash
     * @return array
     */
    public function buscarOuCriar($tokenId, $visitanteHash)
    {
        // Tentar buscar visitante existente
        $visitante = $this->buscarPorHash($visitanteHash);

        if ($visitante) {
            // Atualizar último acesso e incrementar contador
            $this->atualizarAcesso($visitante['id']);
            return $visitante;
        }

        // Criar novo visitante
        $sql = "INSERT INTO visitantes (token_id, visitante_hash, primeiro_acesso, ultimo_acesso, user_agent)
                VALUES (:token_id, :hash, NOW(), NOW(), :user_agent)";

        $params = [
            ':token_id' => $tokenId,
            ':hash' => $visitanteHash,
            ':user_agent' => getUserAgent()
        ];

        $id = $this->db->insert($sql, $params);

        return $this->buscarPorId($id);
    }

    /**
     * Busca visitante por ID
     *
     * @param int $id
     * @return array|false
     */
    public function buscarPorId($id)
    {
        $sql = "SELECT * FROM visitantes WHERE id = :id";
        return $this->db->fetchOne($sql, [':id' => $id]);
    }

    /**
     * Busca visitante por hash
     *
     * @param string $hash
     * @return array|false
     */
    public function buscarPorHash($hash)
    {
        $sql = "SELECT * FROM visitantes WHERE visitante_hash = :hash";
        return $this->db->fetchOne($sql, [':hash' => $hash]);
    }

    /**
     * Atualiza último acesso e incrementa contador
     *
     * @param int $id
     * @return bool
     */
    public function atualizarAcesso($id)
    {
        $sql = "UPDATE visitantes
                SET ultimo_acesso = NOW(),
                    total_acessos = total_acessos + 1
                WHERE id = :id";

        return $this->db->update($sql, [':id' => $id]) !== false;
    }

    /**
     * Salva identificação do visitante (nome)
     *
     * @param int $id
     * @param string $nome
     * @return bool
     */
    public function salvarIdentificacao($id, $nome = null)
    {
        $sql = "UPDATE visitantes
                SET nome = :nome,
                    respondeu_modal = 1
                WHERE id = :id";

        return $this->db->update($sql, [
            ':id' => $id,
            ':nome' => $nome ? sanitize($nome) : null
        ]) !== false;
    }

    /**
     * Verifica se visitante já respondeu modal
     *
     * @param string $hash
     * @return bool
     */
    public function jaRespondeu($hash)
    {
        $visitante = $this->buscarPorHash($hash);
        return $visitante && $visitante['respondeu_modal'];
    }

    /**
     * Lista visitantes por token
     *
     * @param int $tokenId
     * @return array
     */
    public function listarPorToken($tokenId)
    {
        $sql = "SELECT * FROM visitantes
                WHERE token_id = :token_id
                ORDER BY primeiro_acesso DESC";

        return $this->db->fetchAll($sql, [':token_id' => $tokenId]);
    }

    /**
     * Obtém estatísticas de visitantes por token
     *
     * @param int $tokenId
     * @return array
     */
    public function estatisticasPorToken($tokenId)
    {
        $sql = "SELECT
                    COUNT(*) as total_visitantes,
                    SUM(CASE WHEN nome IS NOT NULL THEN 1 ELSE 0 END) as identificados,
                    SUM(total_acessos) as total_acessos,
                    MAX(ultimo_acesso) as ultimo_acesso_geral
                FROM visitantes
                WHERE token_id = :token_id";

        return $this->db->fetchOne($sql, [':token_id' => $tokenId]);
    }
}
