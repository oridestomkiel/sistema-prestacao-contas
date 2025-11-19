<?php
/**
 * Model TokenAcesso
 * Gerencia tokens de acesso direto para convidados
 */

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../helpers/functions.php';

class TokenAcesso
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Cria um novo token de acesso
     *
     * @param string $nomeConvidado
     * @param int $criadoPor ID do admin que criou
     * @param int $diasValidade Dias de validade (null = sem expiração)
     * @return string|false Token gerado ou false em erro
     */
    public function criar($nomeConvidado, $criadoPor, $diasValidade = null)
    {
        try {
            if (empty($nomeConvidado)) {
                throw new Exception('Nome do convidado é obrigatório');
            }

            // Gerar token único
            $token = bin2hex(random_bytes(32)); // 64 caracteres

            $expiraEm = null;
            if ($diasValidade !== null && $diasValidade > 0) {
                $expiraEm = date('Y-m-d H:i:s', strtotime("+{$diasValidade} days"));
            }

            $sql = "INSERT INTO tokens_acesso (token, nome_convidado, tipo, ativo, expira_em, criado_por)
                    VALUES (:token, :nome, 'convidado', 1, :expira, :criado_por)";

            $params = [
                ':token' => $token,
                ':nome' => sanitize($nomeConvidado),
                ':expira' => $expiraEm,
                ':criado_por' => $criadoPor
            ];

            $id = $this->db->insert($sql, $params);

            return $id ? $token : false;

        } catch (Exception $e) {
            error_log("Erro ao criar token: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Busca token por valor
     *
     * @param string $token
     * @return array|false
     */
    public function buscarPorToken($token)
    {
        $sql = "SELECT id, token, nome_convidado, tipo, ativo, expira_em, ultimo_acesso, criado_em
                FROM tokens_acesso
                WHERE token = :token";

        return $this->db->fetchOne($sql, [':token' => $token]);
    }

    /**
     * Valida se token é válido
     *
     * @param string $token
     * @return array|false Retorna dados do token se válido, false caso contrário
     */
    public function validar($token)
    {
        $tokenData = $this->buscarPorToken($token);

        if (!$tokenData) {
            return false;
        }

        // Verificar se está ativo
        if (!$tokenData['ativo']) {
            return false;
        }

        // Verificar se não expirou
        if ($tokenData['expira_em'] !== null) {
            $agora = new DateTime();
            $expiracao = new DateTime($tokenData['expira_em']);

            if ($agora > $expiracao) {
                return false;
            }
        }

        // Atualizar último acesso
        $this->atualizarUltimoAcesso($token);

        return $tokenData;
    }

    /**
     * Atualiza o timestamp de último acesso
     *
     * @param string $token
     * @return bool
     */
    public function atualizarUltimoAcesso($token)
    {
        $sql = "UPDATE tokens_acesso
                SET ultimo_acesso = NOW()
                WHERE token = :token";

        return $this->db->update($sql, [':token' => $token]) !== false;
    }

    /**
     * Lista todos os tokens de acesso
     *
     * @param bool $apenasAtivos
     * @return array
     */
    public function listar($apenasAtivos = true)
    {
        $sql = "SELECT t.id, t.token, t.nome_convidado, t.tipo, t.ativo,
                       t.expira_em, t.ultimo_acesso, t.criado_em,
                       u.nome as criado_por_nome
                FROM tokens_acesso t
                LEFT JOIN usuarios u ON t.criado_por = u.id";

        if ($apenasAtivos) {
            $sql .= " WHERE t.ativo = 1";
        }

        $sql .= " ORDER BY t.criado_em DESC";

        return $this->db->fetchAll($sql);
    }

    /**
     * Desativa um token
     *
     * @param int $id
     * @return bool
     */
    public function desativar($id)
    {
        $sql = "UPDATE tokens_acesso SET ativo = 0 WHERE id = :id";
        return $this->db->update($sql, [':id' => $id]) !== false;
    }

    /**
     * Ativa um token
     *
     * @param int $id
     * @return bool
     */
    public function ativar($id)
    {
        $sql = "UPDATE tokens_acesso SET ativo = 1 WHERE id = :id";
        return $this->db->update($sql, [':id' => $id]) !== false;
    }

    /**
     * Deleta um token
     *
     * @param int $id
     * @return bool
     */
    public function deletar($id)
    {
        $sql = "DELETE FROM tokens_acesso WHERE id = :id";
        return $this->db->delete($sql, [':id' => $id]) !== false;
    }

    /**
     * Remove tokens expirados
     *
     * @return int Número de tokens removidos
     */
    public function limparExpirados()
    {
        $sql = "DELETE FROM tokens_acesso
                WHERE expira_em IS NOT NULL
                AND expira_em < NOW()";

        return $this->db->delete($sql);
    }

    /**
     * Obtém estatísticas de tokens
     *
     * @return array
     */
    public function estatisticas()
    {
        $sql = "SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN ativo = 1 THEN 1 ELSE 0 END) as ativos,
                    SUM(CASE WHEN ativo = 0 THEN 1 ELSE 0 END) as inativos,
                    SUM(CASE WHEN expira_em IS NOT NULL AND expira_em < NOW() THEN 1 ELSE 0 END) as expirados,
                    SUM(CASE WHEN ultimo_acesso IS NOT NULL THEN 1 ELSE 0 END) as ja_acessados
                FROM tokens_acesso";

        return $this->db->fetchOne($sql);
    }
}
