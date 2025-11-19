<?php
/**
 * Classe CSRF
 * Proteção contra ataques Cross-Site Request Forgery
 */

require_once __DIR__ . '/../helpers/functions.php';

class CSRF
{
    /**
     * Gera um token CSRF e armazena na sessão
     *
     * @return string
     */
    public static function gerarToken()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['_csrf_token'])) {
            $length = Config::get('CSRF_TOKEN_LENGTH', 32);
            $_SESSION['_csrf_token'] = gerarToken($length);
        }

        return $_SESSION['_csrf_token'];
    }

    /**
     * Valida o token CSRF
     *
     * @param string|null $token
     * @return bool
     */
    public static function validarToken($token = null)
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Obter token da requisição se não foi passado
        if ($token === null) {
            $token = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        }

        // Validar
        if (empty($token) || !isset($_SESSION['_csrf_token'])) {
            return false;
        }

        return hash_equals($_SESSION['_csrf_token'], $token);
    }

    /**
     * Verifica o token e retorna erro se inválido
     *
     * @param bool $ajax
     */
    public static function verificar($ajax = false)
    {
        if (!self::validarToken()) {
            if ($ajax || isAjax()) {
                jsonError('Token CSRF inválido ou expirado.', 403);
            } else {
                http_response_code(403);
                die('Token CSRF inválido ou expirado. Por favor, recarregue a página e tente novamente.');
            }
        }
    }

    /**
     * Retorna um campo input hidden com o token CSRF
     *
     * @return string
     */
    public static function campoInput()
    {
        $token = self::gerarToken();
        return '<input type="hidden" name="_csrf_token" value="' . e($token) . '">';
    }

    /**
     * Retorna o token como meta tag para usar em requisições AJAX
     *
     * @return string
     */
    public static function metaTag()
    {
        $token = self::gerarToken();
        return '<meta name="csrf-token" content="' . e($token) . '">';
    }

    /**
     * Regenera o token CSRF
     */
    public static function regenerarToken()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $length = Config::get('CSRF_TOKEN_LENGTH', 32);
        $_SESSION['_csrf_token'] = gerarToken($length);
    }

    /**
     * Obtém o token atual sem gerar um novo
     *
     * @return string|null
     */
    public static function obterToken()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        return $_SESSION['_csrf_token'] ?? null;
    }
}
