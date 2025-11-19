<?php
/**
 * Classe Database
 * Gerencia a conexão com o banco de dados MySQL usando PDO
 * Implementa o padrão Singleton
 */

require_once __DIR__ . '/Config.php';

class Database
{
    private static $instance = null;
    private $connection = null;

    /**
     * Construtor privado (Singleton)
     */
    private function __construct()
    {
        $this->connect();
    }

    /**
     * Previne clonagem do objeto (Singleton)
     */
    private function __clone() {}

    /**
     * Previne deserialização (Singleton)
     */
    public function __wakeup()
    {
        throw new Exception("Não é possível deserializar um singleton.");
    }

    /**
     * Obtém a instância única da classe
     *
     * @return Database
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Estabelece a conexão com o banco de dados
     */
    private function connect()
    {
        try {
            $config = Config::database();

            $dsn = "mysql:host={$config['host']};dbname={$config['name']};charset={$config['charset']}";

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$config['charset']}"
            ];

            $this->connection = new PDO(
                $dsn,
                $config['user'],
                $config['pass'],
                $options
            );

            // Configurar timezone (removido - causava erro em alguns servidores)
            // $timezone = Config::get('TIMEZONE', 'America/Sao_Paulo');
            // $this->connection->exec("SET time_zone = '{$timezone}'");

        } catch (PDOException $e) {
            $this->handleConnectionError($e);
        }
    }

    /**
     * Trata erros de conexão
     *
     * @param PDOException $e
     */
    private function handleConnectionError(PDOException $e)
    {
        error_log("Erro de conexão com banco de dados: " . $e->getMessage());

        if (Config::get('APP_ENV') === 'production') {
            die("Erro ao conectar com o banco de dados. Por favor, tente novamente mais tarde.");
        } else {
            die("Erro de conexão: " . $e->getMessage());
        }
    }

    /**
     * Retorna a conexão PDO
     *
     * @return PDO
     */
    public function getConnection()
    {
        if ($this->connection === null) {
            $this->connect();
        }

        return $this->connection;
    }

    /**
     * Executa uma query e retorna o statement
     *
     * @param string $sql
     * @param array $params
     * @return PDOStatement
     */
    public function query($sql, $params = [])
    {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Erro na query: " . $e->getMessage() . " | SQL: " . $sql);
            throw $e;
        }
    }

    /**
     * Executa uma query e retorna todos os resultados
     *
     * @param string $sql
     * @param array $params
     * @return array
     */
    public function fetchAll($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Executa uma query e retorna um único resultado
     *
     * @param string $sql
     * @param array $params
     * @return mixed
     */
    public function fetchOne($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }

    /**
     * Executa uma query e retorna uma única coluna
     *
     * @param string $sql
     * @param array $params
     * @return mixed
     */
    public function fetchColumn($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchColumn();
    }

    /**
     * Insere um registro e retorna o ID inserido
     *
     * @param string $sql
     * @param array $params
     * @return string
     */
    public function insert($sql, $params = [])
    {
        $this->query($sql, $params);
        return $this->connection->lastInsertId();
    }

    /**
     * Atualiza registros e retorna o número de linhas afetadas
     *
     * @param string $sql
     * @param array $params
     * @return int
     */
    public function update($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Deleta registros e retorna o número de linhas afetadas
     *
     * @param string $sql
     * @param array $params
     * @return int
     */
    public function delete($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Inicia uma transação
     */
    public function beginTransaction()
    {
        return $this->connection->beginTransaction();
    }

    /**
     * Confirma uma transação
     */
    public function commit()
    {
        return $this->connection->commit();
    }

    /**
     * Reverte uma transação
     */
    public function rollback()
    {
        return $this->connection->rollBack();
    }

    /**
     * Verifica se está em uma transação
     *
     * @return bool
     */
    public function inTransaction()
    {
        return $this->connection->inTransaction();
    }
}
