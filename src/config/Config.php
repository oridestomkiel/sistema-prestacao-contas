<?php
/**
 * Classe Config
 * Gerencia as configurações da aplicação através do arquivo .env
 */

class Config
{
    private static $config = [];
    private static $loaded = false;

    /**
     * Carrega as configurações do arquivo .env
     */
    public static function load($envPath = null)
    {
        if (self::$loaded) {
            return;
        }

        if ($envPath === null) {
            $envPath = dirname(__DIR__, 2) . '/.env';
        }

        if (!file_exists($envPath)) {
            throw new Exception("Arquivo .env não encontrado em: {$envPath}");
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            // Ignorar comentários
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            // Parse da linha KEY=VALUE
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Remover aspas se existirem
                $value = trim($value, '"\'');

                self::$config[$key] = $value;

                // Também definir como variável de ambiente
                if (!getenv($key)) {
                    putenv("{$key}={$value}");
                }
            }
        }

        self::$loaded = true;
    }

    /**
     * Obtém um valor de configuração
     *
     * @param string $key Chave da configuração
     * @param mixed $default Valor padrão se não encontrar
     * @return mixed
     */
    public static function get($key, $default = null)
    {
        if (!self::$loaded) {
            self::load();
        }

        return self::$config[$key] ?? $default;
    }

    /**
     * Define um valor de configuração
     *
     * @param string $key
     * @param mixed $value
     */
    public static function set($key, $value)
    {
        self::$config[$key] = $value;
    }

    /**
     * Verifica se uma chave existe
     *
     * @param string $key
     * @return bool
     */
    public static function has($key)
    {
        if (!self::$loaded) {
            self::load();
        }

        return isset(self::$config[$key]);
    }

    /**
     * Retorna todas as configurações
     *
     * @return array
     */
    public static function all()
    {
        if (!self::$loaded) {
            self::load();
        }

        return self::$config;
    }

    /**
     * Obtém configuração do banco de dados
     *
     * @return array
     */
    public static function database()
    {
        return [
            'host' => self::get('DB_HOST', 'localhost'),
            'name' => self::get('DB_NAME', 'doacoes_mae'),
            'user' => self::get('DB_USER', 'root'),
            'pass' => self::get('DB_PASS', ''),
            'charset' => self::get('DB_CHARSET', 'utf8mb4'),
        ];
    }
}
