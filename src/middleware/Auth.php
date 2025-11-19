<?php

/**
 * Classe Auth
 * Gerencia autenticação e sessões de usuários
 */

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../models/TokenAcesso.php';
require_once __DIR__ . '/../models/Visitante.php';

class Auth
{
    private static $sessionStarted = false;
    private static $currentUser = null;
    private static $tokenAuth = false;

    /**
     * Inicia a sessão se ainda não foi iniciada
     */
    public static function iniciarSessao()
    {
        if (self::$sessionStarted) {
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            // Configurações de segurança da sessão
            ini_set('session.cookie_httponly', 1);
            ini_set('session.use_only_cookies', 1);
            ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
            ini_set('session.cookie_samesite', 'Lax');

            $sessionName = Config::get('SESSION_NAME', 'mae_session');
            session_name($sessionName);

            $lifetime = Config::get('SESSION_LIFETIME', 7200);
            session_set_cookie_params($lifetime);

            session_start();
            self::$sessionStarted = true;

            // Regenerar ID da sessão periodicamente
            if (!isset($_SESSION['LAST_REGENERATE'])) {
                self::regenerarSessao();
            } elseif (time() - $_SESSION['LAST_REGENERATE'] > 300) { // 5 minutos
                self::regenerarSessao();
            }

            // Validar sessão
            self::validarSessao();
        }
    }

    /**
     * Regenera o ID da sessão (proteção contra session fixation)
     */
    private static function regenerarSessao()
    {
        session_regenerate_id(true);
        $_SESSION['LAST_REGENERATE'] = time();
    }

    /**
     * Valida a sessão atual
     */
    private static function validarSessao()
    {
        // Validar IP (opcional - pode causar problemas com proxies)
        if (isset($_SESSION['USER_IP'])) {
            if ($_SESSION['USER_IP'] !== getClientIP()) {
                self::logout();
                return;
            }
        }

        // Validar User Agent
        if (isset($_SESSION['USER_AGENT'])) {
            if ($_SESSION['USER_AGENT'] !== getUserAgent()) {
                self::logout();
                return;
            }
        }
    }

    /**
     * Realiza o login do usuário
     *
     * @param int $usuarioId
     * @param string $tipo
     * @param array $dadosUsuario
     * @return bool
     */
    public static function login($usuarioId, $tipo, $dadosUsuario = [])
    {
        self::iniciarSessao();

        $_SESSION['USER_ID'] = $usuarioId;
        $_SESSION['USER_TYPE'] = $tipo;
        $_SESSION['USER_EMAIL'] = $dadosUsuario['email'] ?? '';
        $_SESSION['USER_NAME'] = $dadosUsuario['nome'] ?? '';
        $_SESSION['USER_IP'] = getClientIP();
        $_SESSION['USER_AGENT'] = getUserAgent();
        $_SESSION['LOGIN_TIME'] = time();

        self::regenerarSessao();

        return true;
    }

    /**
     * Realiza o logout do usuário
     */
    public static function logout()
    {
        self::iniciarSessao();

        // Limpar variáveis de sessão
        $_SESSION = [];

        // Destruir cookie de sessão
        if (isset($_COOKIE[session_name()])) {
            setcookie(
                session_name(),
                '',
                time() - 3600,
                '/',
                '',
                isset($_SERVER['HTTPS']),
                true
            );
        }

        // Destruir sessão
        session_destroy();
        self::$sessionStarted = false;
        self::$currentUser = null;
    }

    /**
     * Verifica se o usuário está autenticado
     *
     * @return bool
     */
    public static function check()
    {
        self::iniciarSessao();
        return isset($_SESSION['USER_ID']) && !empty($_SESSION['USER_ID']);
    }

    /**
     * Verifica se o usuário NÃO está autenticado
     *
     * @return bool
     */
    public static function guest()
    {
        return !self::check();
    }

    /**
     * Verifica se o usuário é admin
     *
     * @return bool
     */
    public static function isAdmin()
    {
        self::iniciarSessao();
        return self::check() && $_SESSION['USER_TYPE'] === 'admin';
    }

    /**
     * Verifica se o usuário é convidado
     *
     * @return bool
     */
    public static function isConvidado()
    {
        self::iniciarSessao();
        return self::check() && $_SESSION['USER_TYPE'] === 'convidado';
    }

    /**
     * Obtém o ID do usuário atual
     *
     * @return int|null
     */
    public static function id()
    {
        self::iniciarSessao();
        return $_SESSION['USER_ID'] ?? null;
    }

    /**
     * Obtém dados do usuário atual
     *
     * @param string|null $key
     * @return mixed
     */
    public static function user($key = null)
    {
        self::iniciarSessao();

        if (!self::check()) {
            return null;
        }

        // Cache do usuário
        if (self::$currentUser === null) {
            self::$currentUser = [
                'id' => $_SESSION['USER_ID'] ?? null,
                'tipo' => $_SESSION['USER_TYPE'] ?? null,
                'email' => $_SESSION['USER_EMAIL'] ?? null,
                'nome' => $_SESSION['USER_NAME'] ?? null,
            ];
        }

        if ($key !== null) {
            return self::$currentUser[$key] ?? null;
        }

        return self::$currentUser;
    }

    /**
     * Requer autenticação - redireciona se não autenticado
     *
     * @param string $redirectTo
     */
    public static function requireAuth($redirectTo = '/acesso-negado.php')
    {
        if (self::guest()) {
            // Tentar restaurar sessão via cookie antes de redirecionar
            if (self::restaurarSessaoVisitante()) {
                return; // Sessão restaurada com sucesso
            }

            $_SESSION['REDIRECT_AFTER_LOGIN'] = $_SERVER['REQUEST_URI'] ?? '';
            redirect($redirectTo);
        }
    }

    /**
     * Requer privilégios de admin - retorna erro se não for admin
     *
     * @param bool $ajax
     */
    public static function requireAdmin($ajax = false)
    {
        self::requireAuth();

        if (!self::isAdmin()) {
            if ($ajax || isAjax()) {
                jsonError('Acesso negado. Apenas administradores podem realizar esta ação.', 403);
            } else {
                http_response_code(403);
                die('Acesso negado. Apenas administradores podem acessar esta página.');
            }
        }
    }

    /**
     * Redireciona usuário autenticado
     *
     * @param string $redirectTo
     */
    public static function requireGuest($redirectTo = '/dashboard.php')
    {
        if (self::check()) {
            redirect($redirectTo);
        }
    }

    /**
     * Obtém o tempo de login
     *
     * @return int|null
     */
    public static function loginTime()
    {
        self::iniciarSessao();
        return $_SESSION['LOGIN_TIME'] ?? null;
    }

    /**
     * Verifica se a sessão expirou
     *
     * @return bool
     */
    public static function isExpired()
    {
        self::iniciarSessao();

        if (!self::check()) {
            return true;
        }

        $lifetime = Config::get('SESSION_LIFETIME', 7200);
        $loginTime = self::loginTime();

        if ($loginTime && (time() - $loginTime) > $lifetime) {
            self::logout();
            return true;
        }

        return false;
    }

    /**
     * Atualiza o tempo de atividade da sessão
     */
    public static function touch()
    {
        self::iniciarSessao();

        if (self::check()) {
            $_SESSION['LOGIN_TIME'] = time();
        }
    }

    /**
     * Define uma mensagem flash
     *
     * @param string $key
     * @param mixed $value
     */
    public static function flash($key, $value)
    {
        self::iniciarSessao();
        $_SESSION['_flash'][$key] = $value;
    }

    /**
     * Obtém e remove uma mensagem flash
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function getFlash($key, $default = null)
    {
        self::iniciarSessao();

        if (isset($_SESSION['_flash'][$key])) {
            $value = $_SESSION['_flash'][$key];
            unset($_SESSION['_flash'][$key]);
            return $value;
        }

        return $default;
    }

    /**
     * Verifica se existe uma mensagem flash
     *
     * @param string $key
     * @return bool
     */
    public static function hasFlash($key)
    {
        self::iniciarSessao();
        return isset($_SESSION['_flash'][$key]);
    }

    /**
     * Autentica usuário via token de acesso
     *
     * @param string $token
     * @param string|null $visitanteHash Hash do visitante do localStorage
     * @return bool
     */
    public static function loginViaToken($token, $visitanteHash = null)
    {
        $tokenModel = new TokenAcesso();
        $tokenData = $tokenModel->validar($token);

        if (!$tokenData) {
            return false;
        }

        // Gerenciar visitante
        $visitanteModel = new Visitante();

        // Se não tem hash, gerar novo
        if (!$visitanteHash) {
            $visitanteHash = Visitante::gerarHash();
        }

        // Buscar ou criar visitante
        $visitante = $visitanteModel->buscarOuCriar($tokenData['id'], $visitanteHash);

        self::iniciarSessao();

        // Marcar como autenticação por token
        $_SESSION['AUTH_METHOD'] = 'token';
        $_SESSION['TOKEN_VALUE'] = $token;
        $_SESSION['USER_ID'] = 'visitante_' . $visitante['id'];
        $_SESSION['USER_TYPE'] = 'convidado';
        $_SESSION['USER_EMAIL'] = '';
        $_SESSION['USER_NAME'] = $visitante['nome'] ?? 'Convidado';
        $_SESSION['USER_IP'] = getClientIP();
        $_SESSION['USER_AGENT'] = getUserAgent();
        $_SESSION['LOGIN_TIME'] = time();
        $_SESSION['TOKEN_ID'] = $tokenData['id'];
        $_SESSION['VISITANTE_ID'] = $visitante['id'];
        $_SESSION['VISITANTE_HASH'] = $visitanteHash;
        $_SESSION['VISITANTE_RESPONDEU_MODAL'] = $visitante['respondeu_modal'];

        self::$tokenAuth = true;
        self::regenerarSessao();

        // Criar cookie persistente de 30 dias para convidados
        self::criarCookieVisitante($visitanteHash);

        return true;
    }

    /**
     * Cria cookie persistente para visitante (30 dias)
     *
     * @param string $visitanteHash
     */
    private static function criarCookieVisitante($visitanteHash)
    {
        $expiracao = time() + (30 * 24 * 60 * 60); // 30 dias

        setcookie(
            'visitante_hash',
            $visitanteHash,
            $expiracao,
            '/',
            '',
            isset($_SERVER['HTTPS']),
            true // httponly
        );
    }

    /**
     * Tenta restaurar sessão de visitante via cookie
     *
     * @return bool
     */
    public static function restaurarSessaoVisitante()
    {
        // Se já está autenticado, não precisa restaurar
        if (self::check()) {
            return true;
        }

        // Verificar se existe cookie de visitante
        $visitanteHash = $_COOKIE['visitante_hash'] ?? null;

        if (!$visitanteHash) {
            return false;
        }

        // Buscar visitante pelo hash
        $visitanteModel = new Visitante();
        $visitante = $visitanteModel->buscarPorHash($visitanteHash);

        if (!$visitante) {
            // Cookie inválido, remover
            self::removerCookieVisitante();
            return false;
        }

        // Buscar token associado
        $db = Database::getInstance();
        $token = $db->fetchOne(
            "SELECT t.token FROM tokens_acesso t WHERE t.id = :token_id AND t.ativo = 1 AND (t.expira_em IS NULL OR t.expira_em > NOW())",
            [':token_id' => $visitante['token_id']]
        );

        if (!$token) {
            // Token expirado ou inativo, remover cookie
            self::removerCookieVisitante();
            return false;
        }

        // Restaurar sessão
        return self::loginViaToken($token['token'], $visitanteHash);
    }

    /**
     * Remove cookie de visitante
     */
    private static function removerCookieVisitante()
    {
        if (isset($_COOKIE['visitante_hash'])) {
            setcookie(
                'visitante_hash',
                '',
                time() - 3600,
                '/',
                '',
                isset($_SERVER['HTTPS']),
                true
            );
        }
    }

    /**
     * Verifica se a autenticação foi via token
     *
     * @return bool
     */
    public static function isTokenAuth()
    {
        self::iniciarSessao();
        return isset($_SESSION['AUTH_METHOD']) && $_SESSION['AUTH_METHOD'] === 'token';
    }

    /**
     * Obtém o token atual (se autenticado via token)
     *
     * @return string|null
     */
    public static function getToken()
    {
        self::iniciarSessao();
        return $_SESSION['TOKEN_VALUE'] ?? null;
    }

    /**
     * Verifica e autentica via token se presente na URL
     *
     * @return bool
     */
    public static function checkTokenAuth()
    {
        // Se já está autenticado via token, validar se ainda é válido
        if (self::isTokenAuth()) {
            $token = self::getToken();
            $tokenModel = new TokenAcesso();
            $tokenData = $tokenModel->validar($token);

            if (!$tokenData) {
                // Token inválido ou expirado, fazer logout
                self::logout();
                return false;
            }

            return true;
        }

        // Verificar se há token na URL
        $token = $_GET['token'] ?? null;

        if ($token) {
            // Tentar pegar hash do localStorage via header customizado
            $visitanteHash = $_SERVER['HTTP_X_VISITANTE_HASH'] ?? null;
            return self::loginViaToken($token, $visitanteHash);
        }

        return false;
    }

    /**
     * Obtém ID do visitante atual (se autenticado via token)
     *
     * @return int|null
     */
    public static function visitanteId()
    {
        self::iniciarSessao();
        return $_SESSION['VISITANTE_ID'] ?? null;
    }

    /**
     * Obtém hash do visitante atual
     *
     * @return string|null
     */
    public static function visitanteHash()
    {
        self::iniciarSessao();
        return $_SESSION['VISITANTE_HASH'] ?? null;
    }

    /**
     * Verifica se visitante já respondeu modal
     *
     * @return bool
     */
    public static function visitanteRespondeuModal()
    {
        self::iniciarSessao();
        return isset($_SESSION['VISITANTE_RESPONDEU_MODAL']) && $_SESSION['VISITANTE_RESPONDEU_MODAL'];
    }

    /**
     * Marca que visitante respondeu modal
     */
    public static function marcarModalRespondido()
    {
        self::iniciarSessao();
        $_SESSION['VISITANTE_RESPONDEU_MODAL'] = true;
    }
}
