<?php
/**
 * Funções auxiliares do sistema
 */

/**
 * Formata um valor monetário para exibição
 *
 * @param float $valor
 * @param bool $comSimbolo
 * @return string
 */
function formatarValor($valor, $comSimbolo = true)
{
    $valorFormatado = number_format($valor, 2, ',', '.');

    return $comSimbolo ? "R$ {$valorFormatado}" : $valorFormatado;
}

/**
 * Converte valor formatado (R$ 1.234,56) para float
 *
 * @param string $valor
 * @return float
 */
function desformatarValor($valor)
{
    // Remove R$, espaços e pontos
    $valor = str_replace(['R$', ' ', '.'], '', $valor);
    // Substitui vírgula por ponto
    $valor = str_replace(',', '.', $valor);

    return (float) $valor;
}

/**
 * Formata uma data para exibição (d/m/Y)
 *
 * @param string $data Data no formato Y-m-d ou timestamp
 * @param string $formato Formato de saída
 * @return string
 */
function formatarData($data, $formato = 'd/m/Y')
{
    if (empty($data)) {
        return '';
    }

    if (is_numeric($data)) {
        return date($formato, $data);
    }

    $timestamp = strtotime($data);
    return $timestamp ? date($formato, $timestamp) : $data;
}

/**
 * Formata data e hora
 *
 * @param string $dataHora
 * @return string
 */
function formatarDataHora($dataHora)
{
    return formatarData($dataHora, 'd/m/Y H:i');
}

/**
 * Converte data do formato BR (d/m/Y) para formato MySQL (Y-m-d)
 *
 * @param string $data
 * @return string
 */
function dataParaMySQL($data)
{
    if (empty($data)) {
        return null;
    }

    // Se já está no formato MySQL
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
        return $data;
    }

    // Converte de d/m/Y para Y-m-d
    $partes = explode('/', $data);
    if (count($partes) === 3) {
        return "{$partes[2]}-{$partes[1]}-{$partes[0]}";
    }

    return null;
}

/**
 * Sanitiza uma string removendo tags HTML e caracteres especiais
 *
 * @param string $input
 * @return string
 */
function sanitize($input)
{
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }

    $input = trim($input);
    $input = strip_tags($input);
    $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');

    return $input;
}

/**
 * Escapa output para HTML
 *
 * @param string $string
 * @return string
 */
function e($string)
{
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Redireciona para uma URL
 *
 * @param string $url
 * @param int $statusCode
 */
function redirect($url, $statusCode = 302)
{
    header("Location: {$url}", true, $statusCode);
    exit;
}

/**
 * Retorna uma resposta JSON
 *
 * @param mixed $data
 * @param int $statusCode
 */
function jsonResponse($data, $statusCode = 200)
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Retorna erro JSON
 *
 * @param string $message
 * @param int $statusCode
 * @param array $errors
 */
function jsonError($message, $statusCode = 400, $errors = [])
{
    $response = [
        'success' => false,
        'message' => $message
    ];

    if (!empty($errors)) {
        $response['errors'] = $errors;
    }

    jsonResponse($response, $statusCode);
}

/**
 * Retorna sucesso JSON
 *
 * @param mixed $data
 * @param string $message
 */
function jsonSuccess($data = null, $message = 'Operação realizada com sucesso')
{
    $response = [
        'success' => true,
        'message' => $message
    ];

    if ($data !== null) {
        $response['data'] = $data;
    }

    jsonResponse($response, 200);
}

/**
 * Gera um código de convite aleatório
 *
 * @param int $length
 * @return string
 */
function gerarCodigoConvite($length = 12)
{
    $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code = '';

    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[random_int(0, strlen($characters) - 1)];
    }

    // Formatar como: XXXX-XXXX-XXXX
    if ($length === 12) {
        return substr($code, 0, 4) . '-' . substr($code, 4, 4) . '-' . substr($code, 8, 4);
    }

    return $code;
}

/**
 * Gera um token aleatório
 *
 * @param int $length
 * @return string
 */
function gerarToken($length = 32)
{
    return bin2hex(random_bytes($length));
}

/**
 * Valida email
 *
 * @param string $email
 * @return bool
 */
function validarEmail($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Valida data no formato d/m/Y ou Y-m-d
 *
 * @param string $data
 * @return bool
 */
function validarData($data)
{
    if (empty($data)) {
        return false;
    }

    // Formato Y-m-d
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
        $partes = explode('-', $data);
        return checkdate($partes[1], $partes[2], $partes[0]);
    }

    // Formato d/m/Y
    if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $data)) {
        $partes = explode('/', $data);
        return checkdate($partes[1], $partes[0], $partes[2]);
    }

    return false;
}

/**
 * Retorna o início e fim de um mês
 *
 * @param int $mes
 * @param int $ano
 * @return array ['inicio' => 'Y-m-d', 'fim' => 'Y-m-d']
 */
function periodoMes($mes, $ano)
{
    $inicio = date('Y-m-01', strtotime("{$ano}-{$mes}-01"));
    $fim = date('Y-m-t', strtotime("{$ano}-{$mes}-01"));

    return [
        'inicio' => $inicio,
        'fim' => $fim
    ];
}

/**
 * Obtém o mês e ano atual
 *
 * @return array ['mes' => int, 'ano' => int]
 */
function mesAnoAtual()
{
    return [
        'mes' => (int) date('n'),
        'ano' => (int) date('Y')
    ];
}

/**
 * Retorna nome do mês por extenso
 *
 * @param int $mes
 * @return string
 */
function nomeMes($mes)
{
    $meses = [
        1 => 'Janeiro',
        2 => 'Fevereiro',
        3 => 'Março',
        4 => 'Abril',
        5 => 'Maio',
        6 => 'Junho',
        7 => 'Julho',
        8 => 'Agosto',
        9 => 'Setembro',
        10 => 'Outubro',
        11 => 'Novembro',
        12 => 'Dezembro'
    ];

    return $meses[$mes] ?? '';
}

/**
 * Verifica se a requisição é via AJAX
 *
 * @return bool
 */
function isAjax()
{
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Obtém o método da requisição HTTP
 *
 * @return string
 */
function getRequestMethod()
{
    return $_SERVER['REQUEST_METHOD'] ?? 'GET';
}

/**
 * Verifica se é POST
 *
 * @return bool
 */
function isPost()
{
    return getRequestMethod() === 'POST';
}

/**
 * Verifica se é GET
 *
 * @return bool
 */
function isGet()
{
    return getRequestMethod() === 'GET';
}

/**
 * Obtém o IP do usuário
 *
 * @return string
 */
function getClientIP()
{
    $keys = [
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'REMOTE_ADDR'
    ];

    foreach ($keys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = $_SERVER[$key];
            if (strpos($ip, ',') !== false) {
                $ip = explode(',', $ip)[0];
            }
            return trim($ip);
        }
    }

    return '0.0.0.0';
}

/**
 * Obtém o User Agent
 *
 * @return string
 */
function getUserAgent()
{
    return $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
}

/**
 * Obtém o endereço IP do cliente
 *
 * @return string
 */
function getIpAddress()
{
    // Verificar se está atrás de proxy
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        // Pode conter múltiplos IPs, pegar o primeiro
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        return $_SERVER['HTTP_X_REAL_IP'];
    } else {
        return $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    }
}

/**
 * Debug (apenas em desenvolvimento)
 *
 * @param mixed $data
 * @param bool $die
 */
function dd($data, $die = true)
{
    echo '<pre>';
    var_dump($data);
    echo '</pre>';

    if ($die) {
        die();
    }
}

/**
 * Formata número de telefone
 *
 * @param string $telefone
 * @return string
 */
function formatarTelefone($telefone)
{
    $telefone = preg_replace('/[^0-9]/', '', $telefone);

    if (strlen($telefone) === 11) {
        return '(' . substr($telefone, 0, 2) . ') ' .
               substr($telefone, 2, 5) . '-' .
               substr($telefone, 7);
    } elseif (strlen($telefone) === 10) {
        return '(' . substr($telefone, 0, 2) . ') ' .
               substr($telefone, 2, 4) . '-' .
               substr($telefone, 6);
    }

    return $telefone;
}

/**
 * Gera URL de asset com timestamp para cache busting
 *
 * @param string $path Caminho do arquivo (ex: /assets/css/styles.css)
 * @return string URL com timestamp
 */
function asset($path)
{
    $filePath = $_SERVER['DOCUMENT_ROOT'] . $path;

    // Se o arquivo existir, usa o timestamp de modificação
    if (file_exists($filePath)) {
        $timestamp = filemtime($filePath);
        return $path . '?v=' . $timestamp;
    }

    // Caso contrário, usa timestamp atual
    return $path . '?v=' . time();
}
