<?php
/**
 * Página de Relatórios
 * Visualização de dados financeiros com gráficos e exportação
 * Última atualização: <?php echo date('Y-m-d H:i:s'); ?>
 */

define('SISTEMA_MAE', true);

require_once __DIR__ . '/../src/config/Config.php';
require_once __DIR__ . '/../src/middleware/Auth.php';
require_once __DIR__ . '/../src/middleware/CSRF.php';
require_once __DIR__ . '/../src/helpers/functions.php';

Config::load();
Auth::iniciarSessao();

// Verificar autenticação via token primeiro
Auth::checkTokenAuth();

Auth::requireAuth();

$page_title = 'Relatórios - Sistema de Prestação de Contas';
$is_admin = Auth::isAdmin();

include __DIR__ . '/includes/header.php';
?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<!-- Versão da página: <?php echo time(); ?> -->

<div x-data="gerenciarRelatorios()" x-init="init()">

    <!-- Cabeçalho -->
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-800 mb-2">
            <i class="fas fa-chart-bar text-purple-500 mr-2"></i>
            Relatórios
        </h1>
        <p class="text-gray-600">Visualização e análise de dados financeiros</p>
    </div>

    <!-- Mensagem Emotiva -->
    <div class="bg-purple-50 border-l-4 border-purple-500 rounded-lg p-4 mb-6">
        <p class="text-gray-700">
            <i class="fas fa-heart text-purple-600 mr-2"></i>
            Aqui está o reflexo do nosso amor transformado em cuidado.
        </p>
    </div>

    <!-- Filtros -->
    <div class="card mb-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">
            <i class="fas fa-calendar-alt mr-2"></i>Período
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="label">Data Início</label>
                <input type="text"
                       id="data_inicio_filtro_relatorio"
                       class="input"
                       placeholder="Selecione a data">
                <p x-show="erroData" class="text-red-500 text-xs mt-1" x-text="erroData" style="display: none;"></p>
            </div>

            <div>
                <label class="label">Data Fim</label>
                <input type="text"
                       id="data_fim_filtro_relatorio"
                       class="input"
                       placeholder="Selecione a data">
            </div>

            <div class="flex items-end">
                <button @click="carregar()" class="btn btn-primary w-full">
                    <i class="fas fa-sync-alt"></i>
                    Atualizar
                </button>
            </div>
        </div>
    </div>

    <!-- Loading -->
    <div x-show="loading" class="flex justify-center items-center py-12">
        <div class="loading" style="width: 40px; height: 40px; border-width: 4px;"></div>
    </div>

    <!-- Conteúdo do Relatório -->
    <div x-show="!loading && dados" style="display: none;">

        <!-- Cards de Resumo -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="card card-entrada">
                <h4 class="text-sm text-gray-600 mb-2">Total de Entradas</h4>
                <p class="text-3xl font-bold text-green-600" x-text="dados?.total_entradas_formatado"></p>
                <div class="mt-3 text-sm text-gray-600">
                    <template x-for="tipo in dados?.entradas_por_tipo || []" :key="tipo.tipo">
                        <div class="flex justify-between py-1">
                            <span class="capitalize" x-text="tipo.tipo"></span>
                            <span class="font-medium" x-text="tipo.total_formatado"></span>
                        </div>
                    </template>
                </div>
            </div>

            <div class="card card-saida">
                <h4 class="text-sm text-gray-600 mb-2">Total de Saídas</h4>
                <p class="text-3xl font-bold text-red-600" x-text="dados?.total_saidas_formatado"></p>
                <div class="mt-3 text-sm text-gray-600">
                    <template x-for="tipo in dados?.saidas_por_tipo || []" :key="tipo.tipo">
                        <div class="flex justify-between py-1">
                            <span class="capitalize" x-text="tipo.tipo"></span>
                            <span class="font-medium" x-text="tipo.total_formatado"></span>
                        </div>
                    </template>
                </div>
            </div>

            <div class="card card-saldo">
                <h4 class="text-sm text-gray-600 mb-2">Saldo do Período</h4>
                <p class="text-3xl font-bold"
                   :class="{ 'text-green-600': dados?.saldo >= 0, 'text-red-600': dados?.saldo < 0 }"
                   x-text="dados?.saldo_formatado"></p>
                <div class="mt-3 text-sm">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-calendar mr-1" :class="{ 'text-green-500': dados?.saldo >= 0, 'text-red-500': dados?.saldo < 0 }"></i>
                        <span x-text="dados?.data_inicio_formatada + ' a ' + dados?.data_fim_formatada"></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gráficos -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Gráfico de Entradas vs Saídas -->
            <div class="card">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">
                    <i class="fas fa-chart-bar mr-2"></i>
                    Entradas x Saídas
                </h3>
                <div class="h-64">
                    <canvas id="graficoEntradasSaidas" x-init="setTimeout(() => criarGraficos(), 300)"></canvas>
                </div>
            </div>

            <!-- Gráfico de Saídas por Categoria -->
            <div class="card">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">
                    <i class="fas fa-chart-pie mr-2"></i>
                    Saídas por Categoria
                </h3>
                <div class="h-64">
                    <canvas id="graficoCategorias"></canvas>
                </div>
            </div>
        </div>

        <!-- Tabela de Saídas por Categoria -->
        <div class="card mb-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-list mr-2"></i>
                Detalhamento por Categoria
            </h3>

            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Categoria</th>
                            <th class="text-right">Quantidade</th>
                            <th class="text-right">Total</th>
                            <th class="text-right">% do Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="cat in dados?.saidas_por_categoria || []" :key="cat.categoria">
                            <tr>
                                <td class="capitalize font-medium">
                                    <div class="flex items-center gap-2">
                                        <i :class="'fas ' + (cat.categoria_icone || 'fa-tag')" x-show="cat.categoria_icone"></i>
                                        <span x-text="cat.categoria"></span>
                                    </div>
                                </td>
                                <td class="text-right" x-text="cat.quantidade"></td>
                                <td class="text-right font-semibold text-red-600" x-text="cat.total_formatado"></td>
                                <td class="text-right">
                                    <div class="flex justify-end">
                                        <span class="badge"
                                              :class="calcularClassePercentual(cat.total, dados.total_saidas)"
                                              x-text="calcularPercentual(cat.total, dados.total_saidas) + '%'"></span>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Ações de Export (Admin Only) -->
        <?php if ($is_admin): ?>
        <div class="card">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-download mr-2"></i>
                Exportar Relatório
            </h3>

            <div class="flex gap-4">
                <button @click="exportarCSV()" class="btn btn-success">
                    <i class="fas fa-file-csv"></i>
                    Exportar CSV
                </button>
                <button @click="exportarPDF()" class="btn btn-danger">
                    <i class="fas fa-file-pdf"></i>
                    Exportar PDF
                </button>
            </div>
        </div>
        <?php endif; ?>

    </div>

</div>

<script>
function gerenciarRelatorios() {
    return {
        dados: null,
        loading: true,
        erroData: '',
        filtros: {
            data_inicio: null,
            data_fim: null
        },
        graficoBarras: null,
        graficoPizza: null,
        graficosJaCriados: false,

        async init() {
            this.initDatasDefault();
            this.initFlatpickr();
            await this.carregar();
        },

        formatarDataLocal(data) {
            const ano = data.getFullYear();
            const mes = String(data.getMonth() + 1).padStart(2, '0');
            const dia = String(data.getDate()).padStart(2, '0');
            return `${ano}-${mes}-${dia}`;
        },

        initDatasDefault() {
            const hoje = new Date();
            const trintaDiasAtras = new Date();
            trintaDiasAtras.setDate(hoje.getDate() - 30);

            this.filtros.data_inicio = this.formatarDataLocal(trintaDiasAtras);
            this.filtros.data_fim = this.formatarDataLocal(hoje);
        },

        initFlatpickr() {
            // Filtro Data Início
            const fpDataInicio = flatpickr('#data_inicio_filtro_relatorio', {
                locale: 'pt',
                dateFormat: 'd/m/Y',
                altInput: true,
                altFormat: 'd/m/Y',
                onChange: (selectedDates, dateStr, instance) => {
                    const date = selectedDates[0];
                    if (date) {
                        this.filtros.data_inicio = this.formatarDataLocal(date);
                        this.validarDatas();
                    }
                }
            });

            // Setar a data inicial - criar Date no timezone local
            const [anoInicio, mesInicio, diaInicio] = this.filtros.data_inicio.split('-');
            const dataInicioLocal = new Date(anoInicio, mesInicio - 1, diaInicio);
            fpDataInicio.setDate(dataInicioLocal, false);

            // Filtro Data Fim
            const fpDataFim = flatpickr('#data_fim_filtro_relatorio', {
                locale: 'pt',
                dateFormat: 'd/m/Y',
                altInput: true,
                altFormat: 'd/m/Y',
                onChange: (selectedDates, dateStr, instance) => {
                    const date = selectedDates[0];
                    if (date) {
                        this.filtros.data_fim = this.formatarDataLocal(date);
                        this.validarDatas();
                    }
                }
            });

            // Setar a data final - criar Date no timezone local
            const [anoFim, mesFim, diaFim] = this.filtros.data_fim.split('-');
            const dataFimLocal = new Date(anoFim, mesFim - 1, diaFim);
            fpDataFim.setDate(dataFimLocal, false);
        },

        validarDatas() {
            this.erroData = '';

            if (this.filtros.data_inicio && this.filtros.data_fim) {
                const dataInicio = new Date(this.filtros.data_inicio);
                const dataFim = new Date(this.filtros.data_fim);

                if (dataInicio > dataFim) {
                    this.erroData = 'Data início não pode ser maior que data fim';
                    return false;
                }
            }

            return true;
        },

        async carregar() {
            if (this.erroData) return;

            this.loading = true;
            this.graficosJaCriados = false; // Resetar flag

            try {
                const response = await API.get(`/api/relatorios.php?action=periodo&data_inicio=${this.filtros.data_inicio}&data_fim=${this.filtros.data_fim}`);
                this.dados = response.data;
            } catch (error) {
                Alpine.store('toast').error('Erro ao carregar relatório');
                this.dados = null;
            } finally {
                this.loading = false;
            }
        },

        criarGraficos() {
            // Prevenir múltiplas chamadas
            if (this.graficosJaCriados) {
                return;
            }

            // Verificar se há dados
            if (!this.dados) {
                console.warn('Dados não disponíveis para criar gráficos');
                return;
            }

            // Destruir gráficos existentes
            if (this.graficoBarras) {
                this.graficoBarras.destroy();
                this.graficoBarras = null;
            }
            if (this.graficoPizza) {
                this.graficoPizza.destroy();
                this.graficoPizza = null;
            }

            // Gráfico de Barras: Entradas vs Saídas
            const ctxBarras = document.getElementById('graficoEntradasSaidas');

            if (!ctxBarras) {
                console.error('Canvas graficoEntradasSaidas não encontrado no DOM');
                return;
            }

            if (ctxBarras) {
                this.graficoBarras = new Chart(ctxBarras, {
                    type: 'bar',
                    data: {
                        labels: ['Entradas', 'Saídas', 'Saldo'],
                        datasets: [{
                            label: 'Valores (R$)',
                            data: [
                                this.dados.total_entradas,
                                this.dados.total_saidas,
                                Math.abs(this.dados.saldo)
                            ],
                            backgroundColor: [
                                'rgba(168, 213, 186, 0.8)',
                                'rgba(244, 165, 165, 0.8)',
                                this.dados.saldo >= 0 ? 'rgba(165, 201, 229, 0.8)' : 'rgba(239, 68, 68, 0.8)'
                            ],
                            borderColor: [
                                'rgb(72, 187, 120)',
                                'rgb(245, 101, 101)',
                                this.dados.saldo >= 0 ? 'rgb(66, 153, 225)' : 'rgb(220, 38, 38)'
                            ],
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: (value) => 'R$ ' + value.toLocaleString('pt-BR')
                                }
                            }
                        }
                    }
                });
            }

            // Gráfico de Pizza: Categorias
            const ctxPizza = document.getElementById('graficoCategorias');

            if (!ctxPizza) {
                console.error('Canvas graficoCategorias não encontrado no DOM');
                return;
            }

            if (ctxPizza && this.dados.saidas_por_categoria?.length > 0) {
                const cores = [
                    '#F56565', '#ED8936', '#ECC94B', '#48BB78', '#38B2AC',
                    '#4299E1', '#667EEA', '#9F7AEA', '#ED64A6', '#FC8181'
                ];

                this.graficoPizza = new Chart(ctxPizza, {
                    type: 'pie',
                    data: {
                        labels: this.dados.saidas_por_categoria.map(c => c.categoria),
                        datasets: [{
                            data: this.dados.saidas_por_categoria.map(c => parseFloat(c.total)),
                            backgroundColor: cores.slice(0, this.dados.saidas_por_categoria.length),
                            borderWidth: 2,
                            borderColor: '#fff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'right',
                                labels: {
                                    boxWidth: 15,
                                    font: { size: 11 }
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: (context) => {
                                        const label = context.label || '';
                                        const value = context.parsed || 0;
                                        return label + ': R$ ' + value.toLocaleString('pt-BR', { minimumFractionDigits: 2 });
                                    }
                                }
                            }
                        }
                    }
                });
            }

            // Marcar como criados
            this.graficosJaCriados = true;
        },

        calcularPercentual(valor, total) {
            if (!total || total === 0) return 0;
            return ((valor / total) * 100).toFixed(1);
        },

        calcularClassePercentual(valor, total) {
            const perc = this.calcularPercentual(valor, total);
            if (perc >= 30) return 'bg-red-100 text-red-700';
            if (perc >= 15) return 'bg-orange-100 text-orange-700';
            return 'bg-gray-100 text-gray-700';
        },

        async exportarCSV() {
            try {
                const url = `/api/export.php?formato=csv&data_inicio=${this.filtros.data_inicio}&data_fim=${this.filtros.data_fim}`;
                window.open(url, '_blank');
                Alpine.store('toast').success('Exportação iniciada!');
            } catch (error) {
                Alpine.store('toast').error('Erro ao exportar CSV');
            }
        },

        async exportarPDF() {
            try {
                const url = `/api/export.php?formato=pdf&data_inicio=${this.filtros.data_inicio}&data_fim=${this.filtros.data_fim}`;
                window.open(url, '_blank');
                Alpine.store('toast').success('Exportação iniciada!');
            } catch (error) {
                Alpine.store('toast').error('Erro ao exportar PDF');
            }
        }
    };
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
