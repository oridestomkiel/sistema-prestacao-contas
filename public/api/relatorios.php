<?php
/**
 * API de Relatórios
 * Endpoints para obter dados consolidados e estatísticas
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../src/config/Config.php';
require_once __DIR__ . '/../../src/config/Database.php';
require_once __DIR__ . '/../../src/models/Entrada.php';
require_once __DIR__ . '/../../src/models/Saida.php';
require_once __DIR__ . '/../../src/middleware/Auth.php';
require_once __DIR__ . '/../../src/helpers/functions.php';

// Inicializar
Config::load();
Auth::iniciarSessao();

// Verificar autenticação
Auth::requireAuth();

// Obter ação
$action = $_GET['action'] ?? 'resumo';

$entradaModel = new Entrada();
$saidaModel = new Saida();

try {
    switch ($action) {
        case 'resumo':
            handleResumoGeral($entradaModel, $saidaModel);
            break;

        case 'mensal':
            handleResumoMensal($entradaModel, $saidaModel);
            break;

        case 'periodo':
            handleResumoPeriodo($entradaModel, $saidaModel);
            break;

        case 'categorias':
            handlePorCategorias($saidaModel);
            break;

        case 'grafico_mensal':
            handleGraficoMensal($entradaModel, $saidaModel);
            break;

        default:
            jsonError('Ação inválida', 400);
    }

} catch (Exception $e) {
    error_log("Erro na API de relatórios: " . $e->getMessage());
    jsonError('Erro: ' . $e->getMessage(), 500);
}

/**
 * Resumo geral (totais gerais)
 */
function handleResumoGeral($entradaModel, $saidaModel)
{
    $totalEntradas = $entradaModel->totalGeral();
    $totalSaidas = $saidaModel->totalGeral();
    $saldo = $totalEntradas - $totalSaidas;

    // Totais do mês atual
    $mesAtual = (int) date('n');
    $anoAtual = (int) date('Y');

    $entradasMesAtual = $entradaModel->totalPorPeriodo(
        date('Y-m-01'), // Primeiro dia do mês
        date('Y-m-t')   // Último dia do mês
    );

    $saidasMesAtual = $saidaModel->totalPorPeriodo(
        date('Y-m-01'),
        date('Y-m-t')
    );

    // Saldo acumulado até o mês atual (igual ao saldo total)
    $saldoAcumuladoMesAtual = $saldo;

    // Últimas transações
    $ultimasEntradas = $entradaModel->ultimas(5);
    $ultimasSaidas = $saidaModel->ultimas(5);

    // Formatar valores
    foreach ($ultimasEntradas as &$entrada) {
        $entrada['data_formatada'] = formatarData($entrada['data']);
        $entrada['valor_formatado'] = formatarValor($entrada['valor']);
    }

    foreach ($ultimasSaidas as &$saida) {
        $saida['data_formatada'] = formatarData($saida['data']);
        $saida['valor_formatado'] = formatarValor($saida['valor']);
    }

    jsonSuccess([
        'total_entradas' => $totalEntradas,
        'total_saidas' => $totalSaidas,
        'saldo' => $saldo,
        'total_entradas_formatado' => formatarValor($totalEntradas),
        'total_saidas_formatado' => formatarValor($totalSaidas),
        'saldo_formatado' => formatarValor($saldo),
        'entradas_mes_atual' => $entradasMesAtual,
        'saidas_mes_atual' => $saidasMesAtual,
        'saldo_mes_atual' => $saldoAcumuladoMesAtual,
        'entradas_mes_atual_formatado' => formatarValor($entradasMesAtual),
        'saidas_mes_atual_formatado' => formatarValor($saidasMesAtual),
        'saldo_mes_atual_formatado' => formatarValor($saldoAcumuladoMesAtual),
        'ultimas_entradas' => $ultimasEntradas,
        'ultimas_saidas' => $ultimasSaidas
    ]);
}

/**
 * Resumo mensal
 */
function handleResumoMensal($entradaModel, $saidaModel)
{
    $mes = isset($_GET['mes']) ? (int) $_GET['mes'] : (int) date('n');
    $ano = isset($_GET['ano']) ? (int) $_GET['ano'] : (int) date('Y');

    if ($mes < 1 || $mes > 12) {
        jsonError('Mês inválido', 400);
    }

    $periodo = periodoMes($mes, $ano);

    // Totais do período
    $totalEntradas = $entradaModel->totalPorPeriodo($periodo['inicio'], $periodo['fim']);
    $totalSaidas = $saidaModel->totalPorPeriodo($periodo['inicio'], $periodo['fim']);
    $saldo = $totalEntradas - $totalSaidas;

    // Detalhamento
    $entradasPorTipo = $entradaModel->totaisPorTipo($periodo['inicio'], $periodo['fim']);
    $saidasPorTipo = $saidaModel->totaisPorTipo($periodo['inicio'], $periodo['fim']);
    $saidasPorCategoria = $saidaModel->totaisPorCategoria($periodo['inicio'], $periodo['fim']);

    // Formatar valores
    foreach ($entradasPorTipo as &$item) {
        $item['total_formatado'] = formatarValor($item['total']);
    }

    foreach ($saidasPorTipo as &$item) {
        $item['total_formatado'] = formatarValor($item['total']);
    }

    foreach ($saidasPorCategoria as &$item) {
        $item['total_formatado'] = formatarValor($item['total']);
    }

    jsonSuccess([
        'mes' => $mes,
        'ano' => $ano,
        'nome_mes' => nomeMes($mes),
        'total_entradas' => $totalEntradas,
        'total_saidas' => $totalSaidas,
        'saldo' => $saldo,
        'total_entradas_formatado' => formatarValor($totalEntradas),
        'total_saidas_formatado' => formatarValor($totalSaidas),
        'saldo_formatado' => formatarValor($saldo),
        'entradas_por_tipo' => $entradasPorTipo,
        'saidas_por_tipo' => $saidasPorTipo,
        'saidas_por_categoria' => $saidasPorCategoria
    ]);
}

/**
 * Resumo de período customizado
 */
function handleResumoPeriodo($entradaModel, $saidaModel)
{
    // Aceitar tanto de/ate quanto data_inicio/data_fim
    $dataInicio = $_GET['data_inicio'] ?? $_GET['de'] ?? null;
    $dataFim = $_GET['data_fim'] ?? $_GET['ate'] ?? null;

    if (!$dataInicio || !$dataFim) {
        jsonError('Período inválido. Use os parâmetros "data_inicio" e "data_fim"', 400);
    }

    if (!validarData($dataInicio) || !validarData($dataFim)) {
        jsonError('Datas inválidas', 400);
    }

    // Totais do período
    $totalEntradas = $entradaModel->totalPorPeriodo($dataInicio, $dataFim);
    $totalSaidas = $saidaModel->totalPorPeriodo($dataInicio, $dataFim);
    $saldo = $totalEntradas - $totalSaidas;

    // Detalhamento
    $entradasPorTipo = $entradaModel->totaisPorTipo($dataInicio, $dataFim);
    $saidasPorTipo = $saidaModel->totaisPorTipo($dataInicio, $dataFim);
    $saidasPorCategoria = $saidaModel->totaisPorCategoria($dataInicio, $dataFim);

    // Formatar valores
    foreach ($entradasPorTipo as &$item) {
        $item['total_formatado'] = formatarValor($item['total']);
    }

    foreach ($saidasPorTipo as &$item) {
        $item['total_formatado'] = formatarValor($item['total']);
    }

    foreach ($saidasPorCategoria as &$item) {
        $item['total_formatado'] = formatarValor($item['total']);
    }

    jsonSuccess([
        'data_inicio' => $dataInicio,
        'data_fim' => $dataFim,
        'data_inicio_formatada' => formatarData($dataInicio),
        'data_fim_formatada' => formatarData($dataFim),
        'total_entradas' => $totalEntradas,
        'total_saidas' => $totalSaidas,
        'saldo' => $saldo,
        'total_entradas_formatado' => formatarValor($totalEntradas),
        'total_saidas_formatado' => formatarValor($totalSaidas),
        'saldo_formatado' => formatarValor($saldo),
        'entradas_por_tipo' => $entradasPorTipo,
        'saidas_por_tipo' => $saidasPorTipo,
        'saidas_por_categoria' => $saidasPorCategoria
    ]);
}

/**
 * Totais por categoria (para gráfico de pizza)
 */
function handlePorCategorias($saidaModel)
{
    $mes = isset($_GET['mes']) ? (int) $_GET['mes'] : (int) date('n');
    $ano = isset($_GET['ano']) ? (int) $_GET['ano'] : (int) date('Y');

    $periodo = periodoMes($mes, $ano);

    $categorias = $saidaModel->totaisPorCategoria($periodo['inicio'], $periodo['fim']);

    // Formatar valores
    foreach ($categorias as &$cat) {
        $cat['total_formatado'] = formatarValor($cat['total']);
    }

    jsonSuccess([
        'mes' => $mes,
        'ano' => $ano,
        'categorias' => $categorias
    ]);
}

/**
 * Dados para gráfico mensal (12 meses)
 */
function handleGraficoMensal($entradaModel, $saidaModel)
{
    $ano = isset($_GET['ano']) ? (int) $_GET['ano'] : (int) date('Y');

    $entradasPorMes = $entradaModel->porMes($ano);
    $saidasPorMes = $saidaModel->porMes($ano);

    // Criar arrays com todos os 12 meses
    $meses = [];
    for ($i = 1; $i <= 12; $i++) {
        $meses[$i] = [
            'mes' => $i,
            'nome_mes' => nomeMes($i),
            'entradas' => 0,
            'saidas' => 0,
            'saldo' => 0
        ];
    }

    // Preencher entradas
    foreach ($entradasPorMes as $entrada) {
        $mes = (int) $entrada['mes'];
        $meses[$mes]['entradas'] = (float) $entrada['total'];
    }

    // Preencher saídas
    foreach ($saidasPorMes as $saida) {
        $mes = (int) $saida['mes'];
        $meses[$mes]['saidas'] = (float) $saida['total'];
    }

    // Calcular saldos acumulados
    $saldoAcumulado = 0;
    foreach ($meses as $key => &$mes) {
        $saldoMes = $mes['entradas'] - $mes['saidas'];
        $saldoAcumulado += $saldoMes;
        $mes['saldo'] = $saldoAcumulado;
        $mes['saldo_mes'] = $saldoMes; // Saldo apenas do mês (para referência)
    }

    jsonSuccess([
        'ano' => $ano,
        'meses' => array_values($meses)
    ]);
}
