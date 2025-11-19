<?php
/**
 * Página de Saídas
 * Gerenciamento completo de saídas financeiras
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

$page_title = 'Saídas - Sistema de Prestação de Contas';
$is_admin = Auth::isAdmin();
$mostrar_modal = isset($_GET['novo']) && $_GET['novo'] == '1' && $is_admin;

include __DIR__ . '/includes/header.php';
?>

<div x-data="gerenciarSaidas()" x-init="init()">

    <!-- Cabeçalho -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800 mb-2">
                <i class="fas fa-arrow-down text-red-500 mr-2"></i>
                Saídas
            </h1>
            <p class="text-gray-600">Gerenciamento de compras e pagamentos</p>
        </div>

        <?php if ($is_admin): ?>
        <button @click="abrirModal()" class="btn btn-danger">
            <i class="fas fa-plus-circle"></i>
            Nova Saída
        </button>
        <?php endif; ?>
    </div>

    <!-- Mensagem Emotiva -->
    <div class="bg-red-50 border-l-4 border-red-500 rounded-lg p-4 mb-6">
        <p class="text-gray-700">
            <i class="fas fa-receipt text-red-600 mr-2"></i>
            Transparência é amor. Cada centavo investido em cuidado é um gesto de carinho.
        </p>
    </div>

    <!-- Filtros -->
    <div class="card mb-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">
            <i class="fas fa-filter mr-2"></i>Filtros
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
            <div>
                <label class="label">Data Início</label>
                <input type="text"
                       id="data_inicio_filtro_saida"
                       x-model="filtros.data_inicio"
                       class="input"
                       placeholder="Selecione a data">
                <p x-show="erroData" class="text-red-500 text-xs mt-1" x-text="erroData" style="display: none;"></p>
            </div>

            <div>
                <label class="label">Data Fim</label>
                <input type="text"
                       id="data_fim_filtro_saida"
                       x-model="filtros.data_fim"
                       class="input"
                       placeholder="Selecione a data">
            </div>

            <div>
                <label class="label">Tipo</label>
                <select x-model="filtros.tipo" @change="carregar()" class="input">
                    <option value="">Todos</option>
                    <option value="compra">Compra</option>
                    <option value="pagamento">Pagamento</option>
                </select>
            </div>

            <div>
                <label class="label">Categoria</label>
                <select x-model="filtros.categoria_id" @change="carregar()" class="input">
                    <option value="">Todas</option>
                    <template x-for="cat in listaCategorias" :key="cat.id">
                        <option :value="cat.id" x-text="cat.nome"></option>
                    </template>
                </select>
            </div>

            <div>
                <label class="label">Buscar</label>
                <input type="text"
                       x-model="filtros.busca"
                       @input.debounce.500ms="carregar()"
                       class="input"
                       placeholder="Item, fornecedor...">
            </div>
        </div>
    </div>

    <!-- Loading -->
    <div x-show="loading" class="flex justify-center items-center py-12">
        <div class="loading" style="width: 40px; height: 40px; border-width: 4px;"></div>
    </div>

    <!-- Tabela de Saídas -->
    <div x-show="!loading" class="card" style="display: none;">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-gray-800">
                Saídas do Período
            </h3>
            <div class="text-right">
                <p class="text-sm text-gray-600">Total:</p>
                <p class="text-2xl font-bold text-red-600" x-text="totalFormatado"></p>
            </div>
        </div>

        <!-- Lista vazia -->
        <div x-show="saidas.length === 0" class="text-center py-12">
            <i class="fas fa-inbox text-gray-300 text-6xl mb-4"></i>
            <p class="text-gray-500 text-lg">Nenhuma saída encontrada</p>
            <?php if ($is_admin): ?>
            <button @click="abrirModal()" class="btn btn-danger mt-4">
                <i class="fas fa-plus-circle"></i>
                Adicionar Primeira Saída
            </button>
            <?php endif; ?>
        </div>

        <!-- Tabela -->
        <div x-show="saidas.length > 0" class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Tipo</th>
                        <th>Categoria</th>
                        <th>Item</th>
                        <th>Fornecedor</th>
                        <th>Valor</th>
                        <?php if ($is_admin): ?>
                        <th class="text-center">Ações</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="saida in saidas" :key="saida.id">
                        <tr class="cursor-pointer hover:bg-gray-50" @click="visualizarSaida(saida)">
                            <td x-text="saida.data_formatada"></td>
                            <td>
                                <span class="badge badge-saida capitalize" x-text="saida.tipo"></span>
                            </td>
                            <td class="text-gray-700 capitalize">
                                <div class="flex items-center gap-2">
                                    <i :class="'fas ' + (saida.categoria_icone || 'fa-tag')" x-show="saida.categoria_icone"></i>
                                    <span x-text="saida.categoria"></span>
                                </div>
                            </td>
                            <td x-text="saida.item"></td>
                            <td class="text-sm text-gray-600" x-text="saida.fornecedor || '-'"></td>
                            <td class="font-semibold text-red-600" x-text="saida.valor_formatado"></td>
                            <?php if ($is_admin): ?>
                            <td class="text-center" @click.stop>
                                <button @click="editarSaida(saida)" class="text-blue-600 hover:text-blue-800 mr-2">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button @click="excluirSaida(saida.id)" class="text-red-600 hover:text-red-800">
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
                <h3 class="text-xl font-semibold" x-text="saidaEditando ? 'Editar Saída' : 'Nova Saída'"></h3>
                <button @click="fecharModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <form @submit.prevent="salvarSaida()">
                <div class="modal-body space-y-4">
                    <!-- Data -->
                    <div>
                        <label class="label">Data *</label>
                        <input type="text"
                               id="data_saida_modal"
                               x-model="form.data"
                               required
                               class="input"
                               placeholder="Selecione a data">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <!-- Tipo -->
                        <div>
                            <label class="label">Tipo *</label>
                            <select x-model="form.tipo" required class="input">
                                <option value="">Selecione...</option>
                                <option value="compra">Compra</option>
                                <option value="pagamento">Pagamento</option>
                            </select>
                        </div>

                        <!-- Categoria -->
                        <div>
                            <label class="label">Categoria *</label>
                            <select x-model="form.categoria_id" required class="input">
                                <option value="">Selecione...</option>
                                <template x-for="cat in listaCategorias" :key="cat.id">
                                    <option :value="cat.id" x-text="cat.nome"></option>
                                </template>
                            </select>
                        </div>
                    </div>

                    <!-- Item -->
                    <div>
                        <label class="label">Item/Serviço *</label>
                        <input type="text"
                               x-model="form.item"
                               required
                               class="input"
                               placeholder="Ex: Medicamento X, Consulta médica">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
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

                        <!-- Fornecedor -->
                        <div>
                            <label class="label">Fornecedor</label>
                            <input type="text"
                                   x-model="form.fornecedor"
                                   class="input"
                                   placeholder="Nome do fornecedor">
                        </div>
                    </div>

                    <!-- Observações -->
                    <div>
                        <label class="label">Observações</label>
                        <textarea x-model="form.observacoes"
                                  rows="3"
                                  class="input"
                                  placeholder="Informações adicionais (opcional)"></textarea>
                    </div>

                    <!-- Não Contabilizar -->
                    <div class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input type="checkbox"
                                   x-model="form.nao_contabilizar"
                                   class="mt-1">
                            <div>
                                <span class="font-medium text-gray-800">Não contabilizar no total</span>
                                <p class="text-sm text-gray-600 mt-1">
                                    <i class="fas fa-info-circle text-yellow-600 mr-1"></i>
                                    Marque esta opção se esta saída foi doada (produto ou serviço) e não deve entrar no cálculo do total de gastos.
                                </p>
                            </div>
                        </label>
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
                            class="btn btn-danger">
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
                    <i class="fas fa-arrow-down text-red-500"></i>
                    Detalhes da Saída
                </h3>
                <button @click="fecharVisualizacao()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <div class="modal-body space-y-4" x-show="saidaVisualizada">
                <!-- Data -->
                <div class="flex justify-between items-center p-4 bg-gray-50 rounded-lg">
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Data</p>
                        <p class="font-medium text-gray-800" x-text="saidaVisualizada?.data_formatada"></p>
                    </div>
                    <i class="fas fa-calendar text-gray-400 text-2xl"></i>
                </div>

                <!-- Tipo -->
                <div class="flex justify-between items-center p-4 bg-gray-50 rounded-lg">
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Tipo</p>
                        <span class="badge badge-saida capitalize" x-text="saidaVisualizada?.tipo"></span>
                    </div>
                    <i class="fas fa-tag text-gray-400 text-2xl"></i>
                </div>

                <!-- Categoria -->
                <div class="flex justify-between items-center p-4 bg-gray-50 rounded-lg">
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Categoria</p>
                        <div class="flex items-center gap-2 font-medium text-gray-800 capitalize">
                            <i :class="'fas ' + (saidaVisualizada?.categoria_icone || 'fa-tag')"></i>
                            <span x-text="saidaVisualizada?.categoria"></span>
                        </div>
                    </div>
                    <i class="fas fa-folder text-gray-400 text-2xl"></i>
                </div>

                <!-- Item -->
                <div class="flex justify-between items-center p-4 bg-gray-50 rounded-lg">
                    <div class="flex-1">
                        <p class="text-sm text-gray-600 mb-1">Item</p>
                        <p class="font-medium text-gray-800" x-text="saidaVisualizada?.item"></p>
                    </div>
                    <i class="fas fa-shopping-cart text-gray-400 text-2xl ml-4"></i>
                </div>

                <!-- Fornecedor -->
                <div class="flex justify-between items-center p-4 bg-gray-50 rounded-lg" x-show="saidaVisualizada?.fornecedor">
                    <div class="flex-1">
                        <p class="text-sm text-gray-600 mb-1">Fornecedor</p>
                        <p class="font-medium text-gray-800" x-text="saidaVisualizada?.fornecedor"></p>
                    </div>
                    <i class="fas fa-store text-gray-400 text-2xl ml-4"></i>
                </div>

                <!-- Valor -->
                <div class="flex justify-between items-center p-4 bg-red-50 rounded-lg border-2 border-red-200">
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Valor</p>
                        <p class="text-2xl font-bold text-red-600" x-text="saidaVisualizada?.valor_formatado"></p>
                    </div>
                    <i class="fas fa-dollar-sign text-red-400 text-3xl"></i>
                </div>

                <!-- Observações -->
                <div class="p-4 bg-gray-50 rounded-lg" x-show="saidaVisualizada?.observacoes">
                    <p class="text-sm text-gray-600 mb-2 flex items-center gap-2">
                        <i class="fas fa-sticky-note"></i>
                        Observações
                    </p>
                    <p class="text-gray-800" x-text="saidaVisualizada?.observacoes"></p>
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
function gerenciarSaidas() {
    return {
        saidas: [],
        categorias: [],
        listaCategorias: [],
        loading: true,
        erroData: '',
        filtros: {
            data_inicio: null,
            data_fim: null,
            tipo: '',
            categoria_id: '',
            busca: ''
        },
        modalAberto: <?php echo $mostrar_modal ? 'true' : 'false'; ?>,
        modalVisualizacao: false,
        saidaEditando: null,
        saidaVisualizada: null,
        salvando: false,
        form: {
            data: new Date().toISOString().split('T')[0],
            tipo: '',
            categoria_id: '',
            item: '',
            valor: '',
            fornecedor: '',
            observacoes: '',
            nao_contabilizar: false
        },

        async init() {
            this.initDatasDefault();
            this.initFlatpickr();
            await this.carregarCategorias();
            await this.carregar();
        },

        async carregarCategorias() {
            try {
                const response = await API.get('/api/categorias.php');
                this.listaCategorias = response.data.categorias || [];
            } catch (error) {
                console.error('Erro ao carregar categorias:', error);
            }
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
            const fpDataInicio = flatpickr('#data_inicio_filtro_saida', {
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
            const fpDataFim = flatpickr('#data_fim_filtro_saida', {
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
            flatpickr('#data_saida_modal', {
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
                if (this.filtros.categoria_id) params.append('categoria_id', this.filtros.categoria_id);
                if (this.filtros.busca) params.append('busca', this.filtros.busca);

                const response = await API.get(`/api/saidas.php?${params}`);
                this.saidas = response.data.saidas || [];
            } catch (error) {
                Alpine.store('toast').error('Erro ao carregar saídas');
            } finally {
                this.loading = false;
            }
        },

        get totalFormatado() {
            const total = this.saidas.reduce((sum, s) => sum + parseFloat(s.valor), 0);
            return Utils.formatarValor(total);
        },

        abrirModal() {
            this.modalAberto = true;
            this.saidaEditando = null;
            this.form = {
                data: new Date().toISOString().split('T')[0],
                tipo: '',
                categoria_id: '',
                item: '',
                valor: '',
                fornecedor: '',
                observacoes: '',
                nao_contabilizar: false
            };
        },

        editarSaida(saida) {
            this.modalAberto = true;
            this.saidaEditando = saida;
            this.form = {
                data: saida.data,
                tipo: saida.tipo,
                categoria_id: saida.categoria_id || '',
                item: saida.item,
                valor: parseFloat(saida.valor),
                fornecedor: saida.fornecedor || '',
                observacoes: saida.observacoes || '',
                nao_contabilizar: saida.nao_contabilizar == 1 || saida.nao_contabilizar === true
            };
        },

        fecharModal() {
            this.modalAberto = false;
            this.saidaEditando = null;
        },

        visualizarSaida(saida) {
            this.saidaVisualizada = saida;
            this.modalVisualizacao = true;
        },

        fecharVisualizacao() {
            this.modalVisualizacao = false;
            this.saidaVisualizada = null;
        },

        editarDaVisualizacao() {
            this.fecharVisualizacao();
            this.editarSaida(this.saidaVisualizada);
        },

        async salvarSaida() {
            this.salvando = true;
            try {
                if (this.saidaEditando) {
                    await API.put(`/api/saidas.php?id=${this.saidaEditando.id}`, this.form);
                    Alpine.store('toast').success('Saída atualizada com sucesso!');
                } else {
                    await API.post('/api/saidas.php', this.form);
                    Alpine.store('toast').success('Saída criada com sucesso!');

                    // Ajustar filtro de data se a saída criada estiver fora do período atual
                    const dataSaida = new Date(this.form.data);
                    const dataInicio = this.filtros.data_inicio ? new Date(this.filtros.data_inicio) : null;
                    const dataFim = this.filtros.data_fim ? new Date(this.filtros.data_fim) : null;

                    if (dataInicio && dataSaida < dataInicio) {
                        this.filtros.data_inicio = this.form.data;
                    }
                    if (dataFim && dataSaida > dataFim) {
                        this.filtros.data_fim = this.form.data;
                    }
                }

                this.fecharModal();
                await this.carregar();
            } catch (error) {
                Alpine.store('toast').error(error.message || 'Erro ao salvar saída');
            } finally {
                this.salvando = false;
            }
        },

        async excluirSaida(id) {
            if (!await Utils.confirmar('Tem certeza que deseja excluir esta saída?')) {
                return;
            }

            try {
                await API.delete(`/api/saidas.php?id=${id}`);
                Alpine.store('toast').success('Saída excluída com sucesso!');
                await this.carregar();
            } catch (error) {
                Alpine.store('toast').error('Erro ao excluir saída');
            }
        }
    };
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
