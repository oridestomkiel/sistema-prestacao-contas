<?php
/**
 * Página de Entradas
 * Gerenciamento completo de entradas financeiras
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

$page_title = 'Entradas - Sistema de Prestação de Contas';
$is_admin = Auth::isAdmin();
$mostrar_modal = isset($_GET['novo']) && $_GET['novo'] == '1' && $is_admin;

include __DIR__ . '/includes/header.php';
?>

<div x-data="gerenciarEntradas()" x-init="init()">

    <!-- Cabeçalho -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800 mb-2">
                <i class="fas fa-arrow-up text-green-500 mr-2"></i>
                Entradas
            </h1>
            <p class="text-gray-600">Gerenciamento de doações e aposentadoria</p>
        </div>

        <?php if ($is_admin): ?>
        <button @click="abrirModal()" class="btn btn-success">
            <i class="fas fa-plus-circle"></i>
            Nova Entrada
        </button>
        <?php endif; ?>
    </div>

    <!-- Mensagem Emotiva -->
    <div class="bg-green-50 border-l-4 border-green-500 rounded-lg p-4 mb-6">
        <p class="text-gray-700">
            <i class="fas fa-hand-holding-heart text-green-600 mr-2"></i>
            Toda contribuição faz a diferença no conforto e saúde de quem amamos.
        </p>
    </div>

    <!-- Filtros -->
    <div class="card mb-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">
            <i class="fas fa-filter mr-2"></i>Filtros
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <label class="label">Data Início</label>
                <input type="text"
                       id="data_inicio_filtro_entrada"
                       x-model="filtros.data_inicio"
                       class="input"
                       placeholder="Selecione a data">
                <p x-show="erroData" class="text-red-500 text-xs mt-1" x-text="erroData" style="display: none;"></p>
            </div>

            <div>
                <label class="label">Data Fim</label>
                <input type="text"
                       id="data_fim_filtro_entrada"
                       x-model="filtros.data_fim"
                       class="input"
                       placeholder="Selecione a data">
            </div>

            <div>
                <label class="label">Tipo</label>
                <select x-model="filtros.tipo" @change="carregar()" class="input">
                    <option value="">Todos</option>
                    <option value="doacao">Contribuição</option>
                    <option value="aposentadoria">Aposentadoria</option>
                    <option value="saldo">Saldo</option>
                </select>
            </div>

            <div>
                <label class="label">Buscar</label>
                <input type="text"
                       x-model="filtros.busca"
                       @input.debounce.500ms="carregar()"
                       class="input"
                       placeholder="Descrição...">
            </div>
        </div>
    </div>

    <!-- Loading -->
    <div x-show="loading" class="flex justify-center items-center py-12">
        <div class="loading" style="width: 40px; height: 40px; border-width: 4px;"></div>
    </div>

    <!-- Tabela de Entradas -->
    <div x-show="!loading" class="card" style="display: none;">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-gray-800">
                Entradas do Período
            </h3>
            <div class="text-right">
                <p class="text-sm text-gray-600">Total:</p>
                <p class="text-2xl font-bold text-green-600" x-text="totalFormatado"></p>
            </div>
        </div>

        <!-- Lista vazia -->
        <div x-show="entradas.length === 0" class="text-center py-12">
            <i class="fas fa-inbox text-gray-300 text-6xl mb-4"></i>
            <p class="text-gray-500 text-lg">Nenhuma entrada encontrada</p>
            <?php if ($is_admin): ?>
            <button @click="abrirModal()" class="btn btn-success mt-4">
                <i class="fas fa-plus-circle"></i>
                Adicionar Primeira Entrada
            </button>
            <?php endif; ?>
        </div>

        <!-- Tabela -->
        <div x-show="entradas.length > 0" class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Tipo</th>
                        <th>Descrição</th>
                        <th>Valor</th>
                        <th>Observações</th>
                        <?php if ($is_admin): ?>
                        <th class="text-center">Ações</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="entrada in entradas" :key="entrada.id">
                        <tr class="cursor-pointer hover:bg-gray-50" @click="visualizarEntrada(entrada)">
                            <td x-text="entrada.data_formatada"></td>
                            <td>
                                <span class="badge badge-entrada capitalize" x-text="formatarTipo(entrada.tipo)"></span>
                            </td>
                            <td x-text="entrada.descricao"></td>
                            <td class="font-semibold text-green-600" x-text="entrada.valor_formatado"></td>
                            <td class="text-sm text-gray-600" x-text="entrada.observacoes || '-'"></td>
                            <?php if ($is_admin): ?>
                            <td class="text-center" @click.stop>
                                <button @click="editarEntrada(entrada)" class="text-blue-600 hover:text-blue-800 mr-2">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button @click="excluirEntrada(entrada.id)" class="text-red-600 hover:text-red-800">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                            <?php endif; ?>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal de Criar/Editar -->
    <?php if ($is_admin): ?>
    <div x-show="modalAberto"
         x-transition
         class="modal-overlay"
         style="display: none;"
         @click.self="fecharModal()">
        <div class="modal">
            <div class="modal-header">
                <h3 class="text-xl font-semibold" x-text="entradaEditando ? 'Editar Entrada' : 'Nova Entrada'"></h3>
                <button @click="fecharModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <form @submit.prevent="salvarEntrada()">
                <div class="modal-body space-y-4">
                    <!-- Data -->
                    <div>
                        <label class="label">Data *</label>
                        <input type="text"
                               id="data_entrada_modal"
                               x-model="form.data"
                               required
                               class="input"
                               placeholder="Selecione a data">
                    </div>

                    <!-- Tipo -->
                    <div>
                        <label class="label">Tipo *</label>
                        <select x-model="form.tipo" required class="input">
                            <option value="">Selecione...</option>
                            <option value="doacao">Contribuição</option>
                            <option value="aposentadoria">Aposentadoria</option>
                            <option value="saldo">Saldo</option>
                        </select>
                    </div>

                    <!-- Descrição -->
                    <div>
                        <label class="label">Descrição *</label>
                        <input type="text"
                               x-model="form.descricao"
                               required
                               class="input"
                               placeholder="Ex: Contribuição de João, Pagamento INSS">
                    </div>

                    <!-- Valor -->
                    <div>
                        <label class="label">Valor *</label>
                        <input type="number"
                               x-model="form.valor"
                               required
                               step="0.01"
                               min="0.01"
                               class="input"
                               placeholder="0.00">
                    </div>

                    <!-- Observações -->
                    <div>
                        <label class="label">Observações</label>
                        <textarea x-model="form.observacoes"
                                  rows="3"
                                  class="input"
                                  placeholder="Informações adicionais (opcional)"></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button"
                            @click="fecharModal()"
                            class="btn btn-outline">
                        Cancelar
                    </button>
                    <button type="submit"
                            :disabled="salvando"
                            class="btn btn-success">
                        <span x-show="!salvando">Salvar</span>
                        <span x-show="salvando" class="flex items-center gap-2">
                            <span class="loading"></span>
                            Salvando...
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Modal de Visualização -->
    <div x-show="modalVisualizacao"
         x-transition
         class="modal-overlay"
         style="display: none;"
         @click.self="fecharVisualizacao()">
        <div class="modal" style="max-width: 600px;">
            <div class="modal-header">
                <h3 class="text-xl font-semibold flex items-center gap-2">
                    <i class="fas fa-arrow-up text-green-500"></i>
                    Detalhes da Entrada
                </h3>
                <button @click="fecharVisualizacao()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <div class="modal-body space-y-4" x-show="entradaVisualizada">
                <!-- Data -->
                <div class="flex justify-between items-center p-4 bg-gray-50 rounded-lg">
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Data</p>
                        <p class="font-medium text-gray-800" x-text="entradaVisualizada?.data_formatada"></p>
                    </div>
                    <i class="fas fa-calendar text-gray-400 text-2xl"></i>
                </div>

                <!-- Tipo -->
                <div class="flex justify-between items-center p-4 bg-gray-50 rounded-lg">
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Tipo</p>
                        <span class="badge badge-entrada capitalize" x-text="formatarTipo(entradaVisualizada?.tipo)"></span>
                    </div>
                    <i class="fas fa-tag text-gray-400 text-2xl"></i>
                </div>

                <!-- Descrição -->
                <div class="flex justify-between items-center p-4 bg-gray-50 rounded-lg">
                    <div class="flex-1">
                        <p class="text-sm text-gray-600 mb-1">Descrição</p>
                        <p class="font-medium text-gray-800" x-text="entradaVisualizada?.descricao"></p>
                    </div>
                    <i class="fas fa-align-left text-gray-400 text-2xl ml-4"></i>
                </div>

                <!-- Valor -->
                <div class="flex justify-between items-center p-4 bg-green-50 rounded-lg border-2 border-green-200">
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Valor</p>
                        <p class="text-2xl font-bold text-green-600" x-text="entradaVisualizada?.valor_formatado"></p>
                    </div>
                    <i class="fas fa-dollar-sign text-green-400 text-3xl"></i>
                </div>

                <!-- Observações -->
                <div class="p-4 bg-gray-50 rounded-lg" x-show="entradaVisualizada?.observacoes">
                    <p class="text-sm text-gray-600 mb-2 flex items-center gap-2">
                        <i class="fas fa-sticky-note"></i>
                        Observações
                    </p>
                    <p class="text-gray-800" x-text="entradaVisualizada?.observacoes"></p>
                </div>
            </div>

            <div class="modal-footer">
                <button @click="fecharVisualizacao()" class="btn btn-outline">
                    <i class="fas fa-times mr-2"></i>
                    Fechar
                </button>
                <?php if ($is_admin): ?>
                <button @click="editarDaVisualizacao()" class="btn btn-primary">
                    <i class="fas fa-edit mr-2"></i>
                    Editar
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<script>
function gerenciarEntradas() {
    return {
        entradas: [],
        loading: true,
        erroData: '',
        filtros: {
            data_inicio: null,
            data_fim: null,
            tipo: '',
            busca: ''
        },
        modalAberto: <?php echo $mostrar_modal ? 'true' : 'false'; ?>,
        modalVisualizacao: false,
        entradaEditando: null,
        entradaVisualizada: null,
        salvando: false,
        form: {
            data: new Date().toISOString().split('T')[0],
            tipo: '',
            descricao: '',
            valor: '',
            observacoes: ''
        },

        async init() {
            this.initDatasDefault();
            this.initFlatpickr();
            await this.carregar();
        },

        formatarTipo(tipo) {
            const tipos = {
                'doacao': 'Contribuição',
                'contribuicao': 'Contribuição',
                'aposentadoria': 'Aposentadoria',
                'saldo': 'Saldo'
            };
            return tipos[tipo] || tipo;
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
            const fpDataInicio = flatpickr('#data_inicio_filtro_entrada', {
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
            const fpDataFim = flatpickr('#data_fim_filtro_entrada', {
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

            // Modal Data
            flatpickr('#data_entrada_modal', {
                locale: 'pt',
                dateFormat: 'd/m/Y',
                altInput: true,
                altFormat: 'd/m/Y',
                defaultDate: new Date(),
                onChange: (selectedDates, dateStr, instance) => {
                    const date = selectedDates[0];
                    if (date) {
                        this.form.data = this.formatarDataLocal(date);
                    }
                }
            });
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

            this.carregar();
            return true;
        },

        async carregar() {
            if (this.erroData) return;

            this.loading = true;
            try {
                const params = new URLSearchParams();

                if (this.filtros.data_inicio) params.append('data_inicio', this.filtros.data_inicio);
                if (this.filtros.data_fim) params.append('data_fim', this.filtros.data_fim);
                if (this.filtros.tipo) params.append('tipo', this.filtros.tipo);
                if (this.filtros.busca) params.append('busca', this.filtros.busca);

                const response = await API.get(`/api/entradas.php?${params}`);
                this.entradas = response.data.entradas || [];
            } catch (error) {
                Alpine.store('toast').error('Erro ao carregar entradas');
            } finally {
                this.loading = false;
            }
        },

        get totalFormatado() {
            const total = this.entradas.reduce((sum, e) => sum + parseFloat(e.valor), 0);
            return Utils.formatarValor(total);
        },

        abrirModal() {
            this.modalAberto = true;
            this.entradaEditando = null;
            this.form = {
                data: new Date().toISOString().split('T')[0],
                tipo: '',
                descricao: '',
                valor: '',
                observacoes: ''
            };
        },

        editarEntrada(entrada) {
            this.modalAberto = true;
            this.entradaEditando = entrada;
            this.form = {
                data: entrada.data,
                tipo: entrada.tipo,
                descricao: entrada.descricao,
                valor: parseFloat(entrada.valor),
                observacoes: entrada.observacoes || ''
            };
        },

        fecharModal() {
            this.modalAberto = false;
            this.entradaEditando = null;
        },

        visualizarEntrada(entrada) {
            this.entradaVisualizada = entrada;
            this.modalVisualizacao = true;
        },

        fecharVisualizacao() {
            this.modalVisualizacao = false;
            this.entradaVisualizada = null;
        },

        editarDaVisualizacao() {
            this.fecharVisualizacao();
            this.editarEntrada(this.entradaVisualizada);
        },

        async salvarEntrada() {
            this.salvando = true;
            try {
                if (this.entradaEditando) {
                    await API.put(`/api/entradas.php?id=${this.entradaEditando.id}`, this.form);
                    Alpine.store('toast').success('Entrada atualizada com sucesso!');
                } else {
                    await API.post('/api/entradas.php', this.form);
                    Alpine.store('toast').success('Entrada criada com sucesso!');

                    // Ajustar filtro de data se a entrada criada estiver fora do período atual
                    const dataEntrada = new Date(this.form.data);
                    const dataInicio = this.filtros.data_inicio ? new Date(this.filtros.data_inicio) : null;
                    const dataFim = this.filtros.data_fim ? new Date(this.filtros.data_fim) : null;

                    if (dataInicio && dataEntrada < dataInicio) {
                        this.filtros.data_inicio = this.form.data;
                    }
                    if (dataFim && dataEntrada > dataFim) {
                        this.filtros.data_fim = this.form.data;
                    }
                }

                this.fecharModal();
                await this.carregar();
            } catch (error) {
                Alpine.store('toast').error(error.message || 'Erro ao salvar entrada');
            } finally {
                this.salvando = false;
            }
        },

        async excluirEntrada(id) {
            if (!await Utils.confirmar('Tem certeza que deseja excluir esta entrada?')) {
                return;
            }

            try {
                await API.delete(`/api/entradas.php?id=${id}`);
                Alpine.store('toast').success('Entrada excluída com sucesso!');
                await this.carregar();
            } catch (error) {
                Alpine.store('toast').error('Erro ao excluir entrada');
            }
        }
    };
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
