<?php
/**
 * Model Usuario
 * Gerencia operações relacionadas aos usuários
 */

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../helpers/functions.php';

class Usuario
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Cria um novo usuário
     *
     * @param array $dados
     * @return int|false ID do usuário criado ou false em caso de erro
     */
    public function criar($dados)
    {
        try {
            // Validações
            if (empty($dados['email']) || !validarEmail($dados['email'])) {
                throw new Exception('Email inválido');
            }

            if (empty($dados['senha']) || strlen($dados['senha']) < 8) {
                throw new Exception('Senha deve ter no mínimo 8 caracteres');
            }

            if (empty($dados['nome'])) {
                throw new Exception('Nome é obrigatório');
            }

            // Verificar se email já existe
            if ($this->buscarPorEmail($dados['email'])) {
                throw new Exception('Email já cadastrado');
            }

            $sql = "INSERT INTO usuarios (nome, email, senha, tipo, codigo_acesso, data_expiracao_codigo, ativo)
                    VALUES (:nome, :email, :senha, :tipo, :codigo_acesso, :data_expiracao, :ativo)";

            $params = [
                ':nome' => sanitize($dados['nome']),
                ':email' => sanitize($dados['email']),
                ':senha' => password_hash($dados['senha'], PASSWORD_DEFAULT),
                ':tipo' => $dados['tipo'] ?? 'convidado',
                ':codigo_acesso' => $dados['codigo_acesso'] ?? null,
                ':data_expiracao' => $dados['data_expiracao_codigo'] ?? null,
                ':ativo' => $dados['ativo'] ?? true
            ];

            return $this->db->insert($sql, $params);

        } catch (Exception $e) {
            error_log("Erro ao criar usuário: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Busca usuário por ID
     *
     * @param int $id
     * @return array|false
     */
    public function buscarPorId($id)
    {
        $sql = "SELECT id, nome, email, tipo, codigo_acesso, data_expiracao_codigo, ativo, criado_em
                FROM usuarios
                WHERE id = :id";

        return $this->db->fetchOne($sql, [':id' => $id]);
    }

    /**
     * Busca usuário por email
     *
     * @param string $email
     * @return array|false
     */
    public function buscarPorEmail($email)
    {
        $sql = "SELECT id, nome, email, senha, tipo, ativo, criado_em
                FROM usuarios
                WHERE email = :email";

        return $this->db->fetchOne($sql, [':email' => $email]);
    }

    /**
     * Busca usuário por código de acesso
     *
     * @param string $codigo
     * @return array|false
     */
    public function buscarPorCodigo($codigo)
    {
        $sql = "SELECT id, nome, email, tipo, codigo_acesso, data_expiracao_codigo, ativo
                FROM usuarios
                WHERE codigo_acesso = :codigo
                AND data_expiracao_codigo > NOW()
                AND ativo = 1";

        return $this->db->fetchOne($sql, [':codigo' => $codigo]);
    }

    /**
     * Valida credenciais de login
     *
     * @param string $email
     * @param string $senha
     * @return array|false Dados do usuário ou false
     */
    public function validarCredenciais($email, $senha)
    {
        $usuario = $this->buscarPorEmail($email);

        if (!$usuario) {
            return false;
        }

        if (!$usuario['ativo']) {
            return false;
        }

        if (password_verify($senha, $usuario['senha'])) {
            // Remover senha do array de retorno
            unset($usuario['senha']);
            return $usuario;
        }

        return false;
    }

    /**
     * Atualiza a senha do usuário
     *
     * @param int $id
     * @param string $novaSenha
     * @return bool
     */
    public function atualizarSenha($id, $novaSenha)
    {
        if (strlen($novaSenha) < 6) {
            return false;
        }

        $sql = "UPDATE usuarios SET senha = :senha WHERE id = :id";

        $params = [
            ':senha' => password_hash($novaSenha, PASSWORD_DEFAULT),
            ':id' => $id
        ];

        return $this->db->update($sql, $params) > 0;
    }

    /**
     * Atualiza dados do usuário
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

        if (isset($dados['email']) && validarEmail($dados['email'])) {
            // Verificar se email já existe em outro usuário
            $usuarioExistente = $this->buscarPorEmail($dados['email']);
            if ($usuarioExistente && $usuarioExistente['id'] != $id) {
                throw new Exception('Email já está em uso');
            }

            $campos[] = "email = :email";
            $params[':email'] = sanitize($dados['email']);
        }

        if (isset($dados['tipo'])) {
            $campos[] = "tipo = :tipo";
            $params[':tipo'] = $dados['tipo'];
        }

        if (isset($dados['ativo'])) {
            $campos[] = "ativo = :ativo";
            $params[':ativo'] = $dados['ativo'];
        }

        if (empty($campos)) {
            return false;
        }

        $sql = "UPDATE usuarios SET " . implode(', ', $campos) . " WHERE id = :id";

        return $this->db->update($sql, $params) > 0;
    }

    /**
     * Desativa um usuário
     *
     * @param int $id
     * @return bool
     */
    public function desativar($id)
    {
        $sql = "UPDATE usuarios SET ativo = 0 WHERE id = :id";
        return $this->db->update($sql, [':id' => $id]) > 0;
    }

    /**
     * Ativa um usuário
     *
     * @param int $id
     * @return bool
     */
    public function ativar($id)
    {
        $sql = "UPDATE usuarios SET ativo = 1 WHERE id = :id";
        return $this->db->update($sql, [':id' => $id]) > 0;
    }

    /**
     * Lista todos os convidados
     *
     * @return array
     */
    public function listarConvidados()
    {
        $sql = "SELECT id, nome, email, ativo, criado_em
                FROM usuarios
                WHERE tipo = 'convidado'
                ORDER BY criado_em DESC";

        return $this->db->fetchAll($sql);
    }

    /**
     * Lista todos os usuários
     *
     * @param array $filtros
     * @return array
     */
    public function listar($filtros = [])
    {
        $where = [];
        $params = [];

        if (isset($filtros['tipo'])) {
            $where[] = "tipo = :tipo";
            $params[':tipo'] = $filtros['tipo'];
        }

        if (isset($filtros['ativo'])) {
            $where[] = "ativo = :ativo";
            $params[':ativo'] = $filtros['ativo'];
        }

        $whereClause = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

        $sql = "SELECT id, nome, email, tipo, ativo, criado_em
                FROM usuarios
                {$whereClause}
                ORDER BY criado_em DESC";

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Gera um código de convite único
     *
     * @param int $diasValidade
     * @return string
     */
    public function gerarCodigoConvite($diasValidade = 7)
    {
        do {
            $codigo = gerarCodigoConvite(12);
            $existe = $this->buscarPorCodigo($codigo);
        } while ($existe);

        return $codigo;
    }

    /**
     * Cria um convite para novo usuário
     *
     * @param int $diasValidade
     * @return string Código do convite
     */
    public function criarConvite($diasValidade = 7)
    {
        $codigo = $this->gerarCodigoConvite($diasValidade);
        $dataExpiracao = date('Y-m-d H:i:s', strtotime("+{$diasValidade} days"));

        // Criar usuário temporário com o código
        $sql = "INSERT INTO usuarios (nome, email, senha, tipo, codigo_acesso, data_expiracao_codigo, ativo)
                VALUES (:nome, :email, :senha, 'convidado', :codigo, :data_expiracao, 0)";

        $params = [
            ':nome' => 'Convite Pendente',
            ':email' => 'convite_' . time() . '@temp.com',
            ':senha' => password_hash(gerarToken(), PASSWORD_DEFAULT),
            ':codigo' => $codigo,
            ':data_expiracao' => $dataExpiracao
        ];

        $this->db->insert($sql, $params);

        return $codigo;
    }

    /**
     * Ativa convite e atualiza dados do usuário
     *
     * @param string $codigo
     * @param array $dados
     * @return bool
     */
    public function ativarConvite($codigo, $dados)
    {
        $convite = $this->buscarPorCodigo($codigo);

        if (!$convite) {
            throw new Exception('Código de convite inválido ou expirado');
        }

        // Validações
        if (empty($dados['email']) || !validarEmail($dados['email'])) {
            throw new Exception('Email inválido');
        }

        if (empty($dados['senha']) || strlen($dados['senha']) < 8) {
            throw new Exception('Senha deve ter no mínimo 8 caracteres');
        }

        if (empty($dados['nome'])) {
            throw new Exception('Nome é obrigatório');
        }

        // Verificar se email já existe
        $usuarioExistente = $this->buscarPorEmail($dados['email']);
        if ($usuarioExistente && $usuarioExistente['id'] != $convite['id']) {
            throw new Exception('Email já cadastrado');
        }

        $sql = "UPDATE usuarios
                SET nome = :nome,
                    email = :email,
                    senha = :senha,
                    ativo = 1,
                    codigo_acesso = NULL,
                    data_expiracao_codigo = NULL
                WHERE id = :id";

        $params = [
            ':nome' => sanitize($dados['nome']),
            ':email' => sanitize($dados['email']),
            ':senha' => password_hash($dados['senha'], PASSWORD_DEFAULT),
            ':id' => $convite['id']
        ];

        return $this->db->update($sql, $params) > 0;
    }

    /**
     * Remove convites expirados
     *
     * @return int Número de convites removidos
     */
    public function limparConvitesExpirados()
    {
        $sql = "DELETE FROM usuarios
                WHERE ativo = 0
                AND data_expiracao_codigo < NOW()
                AND codigo_acesso IS NOT NULL";

        return $this->db->delete($sql);
    }

    /**
     * Conta total de usuários
     *
     * @param string|null $tipo
     * @return int
     */
    public function contar($tipo = null)
    {
        if ($tipo) {
            $sql = "SELECT COUNT(*) FROM usuarios WHERE tipo = :tipo AND ativo = 1";
            return (int) $this->db->fetchColumn($sql, [':tipo' => $tipo]);
        }

        $sql = "SELECT COUNT(*) FROM usuarios WHERE ativo = 1";
        return (int) $this->db->fetchColumn($sql);
    }
}
