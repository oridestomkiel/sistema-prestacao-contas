<?php
/**
 * API de Exportação
 * Exporta dados em CSV e PDF
 */

require_once __DIR__ . '/../../src/config/Config.php';
require_once __DIR__ . '/../../src/config/Database.php';
require_once __DIR__ . '/../../src/models/Entrada.php';
require_once __DIR__ . '/../../src/models/Saida.php';
require_once __DIR__ . '/../../src/middleware/Auth.php';
require_once __DIR__ . '/../../src/helpers/functions.php';

// Inicializar
Config::load();
Auth::iniciarSessao();

// Verificar autenticação e privilégios de admin
Auth::requireAdmin();

// Obter parâmetros
$formato = $_GET['formato'] ?? 'csv';
$mes = isset($_GET['mes']) ? (int) $_GET['mes'] : (int) date('n');
$ano = isset($_GET['ano']) ? (int) $_GET['ano'] : (int) date('Y');

try {
    if ($formato === 'csv') {
        exportarCSV($mes, $ano);
    } elseif ($formato === 'pdf') {
        exportarPDF($mes, $ano);
    } else {
        http_response_code(400);
        die('Formato inválido');
    }
} catch (Exception $e) {
    error_log("Erro na exportação: " . $e->getMessage());
    http_response_code(500);
    die('Erro ao gerar exportação');
}

/**
 * Exporta dados em CSV
 */
function exportarCSV($mes, $ano)
{
    $entradaModel = new Entrada();
    $saidaModel = new Saida();

    $periodo = periodoMes($mes, $ano);

    // Buscar dados
    $entradas = $entradaModel->listar([
        'mes' => $mes,
        'ano' => $ano
    ]);

    $saidas = $saidaModel->listar([
        'mes' => $mes,
        'ano' => $ano
    ]);

    // Calcular totais
    $totalEntradas = $entradaModel->totalPorPeriodo($periodo['inicio'], $periodo['fim']);
    $totalSaidas = $saidaModel->totalPorPeriodo($periodo['inicio'], $periodo['fim']);
    $saldo = $totalEntradas - $totalSaidas;

    // Headers para download
    $nomeArquivo = "relatorio_{$mes}_{$ano}.csv";
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename={$nomeArquivo}");

    // Criar arquivo CSV
    $output = fopen('php://output', 'w');

    // BOM para UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Cabeçalho do relatório
    fputcsv($output, ['Sistema de Prestação de Contas'], ';');
    fputcsv($output, ['Relatório de ' . nomeMes($mes) . '/' . $ano], ';');
    fputcsv($output, ['Gerado em: ' . date('d/m/Y H:i')], ';');
    fputcsv($output, [], ';');

    // Resumo
    fputcsv($output, ['RESUMO'], ';');
    fputcsv($output, ['Total de Entradas', formatarValor($totalEntradas, false)], ';');
    fputcsv($output, ['Total de Saídas', formatarValor($totalSaidas, false)], ';');
    fputcsv($output, ['Saldo', formatarValor($saldo, false)], ';');
    fputcsv($output, [], ';');

    // Entradas
    fputcsv($output, ['ENTRADAS'], ';');
    fputcsv($output, ['Data', 'Tipo', 'Descrição', 'Valor', 'Observações'], ';');

    foreach ($entradas as $entrada) {
        fputcsv($output, [
            formatarData($entrada['data']),
            ucfirst($entrada['tipo']),
            $entrada['descricao'],
            formatarValor($entrada['valor'], false),
            $entrada['observacoes'] ?? ''
        ], ';');
    }

    fputcsv($output, [], ';');

    // Saídas
    fputcsv($output, ['SAÍDAS'], ';');
    fputcsv($output, ['Data', 'Tipo', 'Categoria', 'Item', 'Fornecedor', 'Valor', 'Observações'], ';');

    foreach ($saidas as $saida) {
        fputcsv($output, [
            formatarData($saida['data']),
            ucfirst($saida['tipo']),
            ucfirst($saida['categoria']),
            $saida['item'],
            $saida['fornecedor'] ?? '',
            formatarValor($saida['valor'], false),
            $saida['observacoes'] ?? ''
        ], ';');
    }

    fclose($output);
    exit;
}

/**
 * Exporta dados em PDF (versão simplificada sem biblioteca externa)
 */
function exportarPDF($mes, $ano)
{
    $entradaModel = new Entrada();
    $saidaModel = new Saida();

    $periodo = periodoMes($mes, $ano);

    // Buscar dados
    $entradas = $entradaModel->listar([
        'mes' => $mes,
        'ano' => $ano
    ]);

    $saidas = $saidaModel->listar([
        'mes' => $mes,
        'ano' => $ano
    ]);

    // Calcular totais
    $totalEntradas = $entradaModel->totalPorPeriodo($periodo['inicio'], $periodo['fim']);
    $totalSaidas = $saidaModel->totalPorPeriodo($periodo['inicio'], $periodo['fim']);
    $saldo = $totalEntradas - $totalSaidas;

    // Gerar HTML que pode ser impresso como PDF pelo navegador
    $nomeArquivo = "relatorio_{$mes}_{$ano}.pdf";

    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório <?php echo nomeMes($mes); ?>/<?php echo $ano; ?></title>
    <style>
        @media print {
            @page { margin: 1.5cm; }
            body { margin: 0; }
            .no-print { display: none; }
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #4299E1;
            padding-bottom: 20px;
        }
        .header h1 {
            color: #2D3748;
            margin: 0 0 10px 0;
        }
        .header p {
            color: #718096;
            margin: 5px 0;
        }
        .summary {
            background: #F7FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
        }
        .summary-item {
            text-align: center;
        }
        .summary-item .label {
            font-size: 11px;
            color: #718096;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        .summary-item .value {
            font-size: 24px;
            font-weight: bold;
        }
        .summary-item.entradas .value { color: #48BB78; }
        .summary-item.saidas .value { color: #F56565; }
        .summary-item.saldo .value { color: #4299E1; }
        .section {
            margin-bottom: 30px;
        }
        .section h2 {
            color: #2D3748;
            border-bottom: 2px solid #E2E8F0;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table th {
            background: #EDF2F7;
            padding: 10px;
            text-align: left;
            font-weight: bold;
            border-bottom: 2px solid #CBD5E0;
        }
        table td {
            padding: 8px 10px;
            border-bottom: 1px solid #E2E8F0;
        }
        table tr:hover {
            background: #F7FAFC;
        }
        .text-right {
            text-align: right;
        }
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #4299E1;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
        }
        .print-button:hover {
            background: #3182CE;
        }
        .footer {
            text-align: center;
            color: #A0AEC0;
            font-size: 11px;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #E2E8F0;
        }
    </style>
</head>
<body>
    <button class="print-button no-print" onclick="window.print()">
        Imprimir / Salvar PDF
    </button>

    <div class="header">
        <h1>Sistema de Prestação de Contas</h1>
        <p>Relatório de <?php echo nomeMes($mes); ?> de <?php echo $ano; ?></p>
        <p>Gerado em: <?php echo date('d/m/Y H:i'); ?></p>
    </div>

    <div class="summary">
        <div class="summary-grid">
            <div class="summary-item entradas">
                <div class="label">Total de Entradas</div>
                <div class="value"><?php echo formatarValor($totalEntradas); ?></div>
            </div>
            <div class="summary-item saidas">
                <div class="label">Total de Saídas</div>
                <div class="value"><?php echo formatarValor($totalSaidas); ?></div>
            </div>
            <div class="summary-item saldo">
                <div class="label">Saldo do Período</div>
                <div class="value"><?php echo formatarValor($saldo); ?></div>
            </div>
        </div>
    </div>

    <div class="section">
        <h2>Entradas (<?php echo count($entradas); ?>)</h2>
        <?php if (count($entradas) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Tipo</th>
                    <th>Descrição</th>
                    <th class="text-right">Valor</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($entradas as $entrada): ?>
                <tr>
                    <td><?php echo formatarData($entrada['data']); ?></td>
                    <td><?php echo ucfirst($entrada['tipo']); ?></td>
                    <td><?php echo e($entrada['descricao']); ?></td>
                    <td class="text-right"><?php echo formatarValor($entrada['valor']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p style="text-align: center; color: #A0AEC0;">Nenhuma entrada registrada neste período.</p>
        <?php endif; ?>
    </div>

    <div class="section">
        <h2>Saídas (<?php echo count($saidas); ?>)</h2>
        <?php if (count($saidas) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Categoria</th>
                    <th>Item</th>
                    <th>Fornecedor</th>
                    <th class="text-right">Valor</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($saidas as $saida): ?>
                <tr>
                    <td><?php echo formatarData($saida['data']); ?></td>
                    <td><?php echo ucfirst($saida['categoria']); ?></td>
                    <td><?php echo e($saida['item']); ?></td>
                    <td><?php echo e($saida['fornecedor'] ?? '-'); ?></td>
                    <td class="text-right"><?php echo formatarValor($saida['valor']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p style="text-align: center; color: #A0AEC0;">Nenhuma saída registrada neste período.</p>
        <?php endif; ?>
    </div>

    <div class="footer">
        <p>Cada cuidado é um ato de amor ❤</p>
        <p>Sistema de Prestação de Contas © <?php echo date('Y'); ?></p>
    </div>

    <script>
        // Auto-print quando carregado (opcional)
        // window.onload = function() { window.print(); }
    </script>
</body>
</html>
    <?php
    exit;
}
