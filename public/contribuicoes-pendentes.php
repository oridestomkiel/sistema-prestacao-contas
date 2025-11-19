<?php
/**
 * Contribuições Pendentes - Gerenciamento
 * Apenas para administradores
 */

define('SISTEMA_MAE', true);

require_once __DIR__ . '/../src/config/Config.php';
require_once __DIR__ . '/../src/middleware/Auth.php';
require_once __DIR__ . '/../src/middleware/CSRF.php';
require_once __DIR__ . '/../src/helpers/functions.php';

Config::load();
Auth::iniciarSessao();
Auth::requireAuth();
Auth::requireAdmin(); // Apenas admin

$page_title = 'Contribuições Pendentes - Sistema de Prestação de Contas';
include __DIR__ . '/includes/header.php';
?>

<div x-data="contribuicoesPendentes()">

    <!-- Header -->
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-800 mb-2">
            <i class="fas fa-hand-holding-heart text-red-400 mr-2"></i>
            Contribuições Pendentes
        </h2>
        <p class="text-gray-600">
            Revise e aprove as contribuições recebidas para gerar entradas automáticas
        </p>
    </div>

    <!-- Filtros -->
    <div class="card mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="label">Status</label>
                <select x-model="filtros.status" @change="carregar()" class="input">
                    <option value="">Todos</option>
                    <option value="pendente">Pendentes</option>
                    <option value="aprovada">Aprovadas</option>
                    <option value="rejeitada">Rejeitadas</option>
                </select>
            </div>

            <div>
                <label class="label">Data Início</label>
                <input type="text"
                       id="data_inicio_filtro"
                       class="input"
                       placeholder="DD/MM/AAAA">
            </div>

            <div>
                <label class="label">Data Fim</label>
                <input type="text"
                       id="data_fim_filtro"
                       class="input"
                       placeholder="DD/MM/AAAA">
            </div>

            <div class="flex items-end">
                <button @click="carregar()" class="btn btn-primary w-full">
                    <i class="fas fa-search"></i>
                    Filtrar
                </button>
            </div>
        </div>
    </div>

    <!-- Loading -->
    <div x-show="loading" class="flex justify-center items-center py-12">
        <div class="loading" style="width: 40px; height: 40px; border-width: 4px;"></div>
    </div>

    <!-- Lista de Contribuições -->
    <div x-show="!loading">
        <!-- Contador -->
        <div class="mb-4 flex justify-between items-center">
            <p class="text-gray-600">
                <span x-text="contribuicoes.length"></span> contribui<span x-text="contribuicoes.length === 1 ? 'ção' : 'ções'"></span>
                <span x-show="filtros.status === 'pendente'" class="ml-2 badge badge-warning">
                    <i class="fas fa-clock"></i> Aguardando aprovação
                </span>
            </p>
        </div>

        <!-- Mensagem de lista vazia -->
        <div x-show="contribuicoes.length === 0" class="card text-center py-12">
            <i class="fas fa-inbox text-gray-400 text-5xl mb-4"></i>
            <p class="text-gray-600">Nenhuma contribuição encontrada</p>
        </div>

        <!-- Cards de Contribuições -->
        <div class="space-y-4">
            <template x-for="contrib in contribuicoes" :key="contrib.id">
                <div class="card" :class="{
                    'border-l-4 border-yellow-400': contrib.status === 'pendente',
                    'border-l-4 border-green-400': contrib.status === 'aprovada',
                    'border-l-4 border-red-400': contrib.status === 'rejeitada'
                }">
                    <div class="flex justify-between items-start mb-4">
                        <div class="flex-1">
                            <div class="flex items-center gap-3 mb-2">
                                <h3 class="text-lg font-semibold text-gray-800">
                                    R$ <span x-text="Utils.formatarValor(contrib.valor)"></span>
                                </h3>
                                <span class="badge" :class="{
                                    'badge-warning': contrib.status === 'pendente',
                                    'badge-success': contrib.status === 'aprovada',
                                    'badge-danger': contrib.status === 'rejeitada'
                                }" x-text="contrib.status_formatado"></span>
                            </div>

                            <div class="space-y-1 text-sm text-gray-600">
                                <p>
                                    <i class="fas fa-user mr-2"></i>
                                    <strong>Doador:</strong>
                                    <span x-text="contrib.nome_doador || 'Não informado'"></span>
                                    <span x-show="contrib.exibir_anonimo" class="ml-2 text-xs badge badge-secondary">
                                        <i class="fas fa-eye-slash"></i> Prefere anônimo
                                    </span>
                                </p>

                                <p x-show="contrib.nome_sessao">
                                    <i class="fas fa-user-tag mr-2"></i>
                                    <strong>Sessão:</strong>
                                    <span x-text="contrib.nome_sessao"></span>
                                </p>

                                <p>
                                    <i class="fas fa-calendar mr-2"></i>
                                    <strong>Registrado em:</strong>
                                    <span x-text="contrib.criado_em_formatado"></span>
                                </p>

                                <p x-show="contrib.observacoes">
                                    <i class="fas fa-sticky-note mr-2"></i>
                                    <strong>Observações:</strong>
                                    <span x-text="contrib.observacoes"></span>
                                </p>

                                <p x-show="contrib.status !== 'pendente' && contrib.aprovador_nome">
                                    <i class="fas fa-user-check mr-2"></i>
                                    <strong x-text="contrib.status === 'aprovada' ? 'Aprovado' : 'Rejeitado'"></strong> por
                                    <span x-text="contrib.aprovador_nome"></span>
                                    em <span x-text="contrib.aprovado_em_formatado"></span>
                                </p>

                                <p x-show="contrib.entrada_id">
                                    <i class="fas fa-check-circle text-green-600 mr-2"></i>
                                    <strong>Entrada #<span x-text="contrib.entrada_id"></span> criada</strong>
                                </p>
                            </div>
                        </div>

                        <!-- Ações -->
                        <div class="flex gap-2" x-show="contrib.status === 'pendente'">
                            <button @click="aprovar(contrib.id)"
                                    class="btn btn-success btn-sm">
                                <i class="fas fa-check"></i>
                                Aprovar
                            </button>
                            <button @click="rejeitar(contrib.id)"
                                    class="btn btn-danger btn-sm">
                                <i class="fas fa-times"></i>
                                Rejeitar
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <!-- Modal de Confirmação de Aprovação -->
    <div x-show="mostrarModalAprovacao"
         x-cloak
         class="modal-overlay"
         style="display: none;">
        <div class="modal" @click.away="">
            <div class="modal-header">
                <h3 class="text-xl font-semibold text-gray-800">
                    <i class="fas fa-check-circle text-green-500 mr-2"></i>
                    Aprovar Contribuição
                </h3>
                <button @click="fecharModalAprovacao()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <div class="modal-body">
                <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-info-circle text-blue-500"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-blue-700">
                                Ao aprovar, uma <strong>entrada</strong> será criada automaticamente no sistema com os dados da contribuição.
                            </p>
                        </div>
                    </div>
                </div>

                <p class="text-gray-700">
                    Tem certeza que deseja aprovar esta contribuição?
                </p>
            </div>

            <div class="modal-footer">
                <button @click="fecharModalAprovacao()"
                        type="button"
                        class="btn btn-outline">
                    Cancelar
                </button>
                <button @click="confirmarAprovacao()"
                        :disabled="processando"
                        class="btn btn-success">
                    <span x-show="!processando">
                        <i class="fas fa-check"></i>
                        Confirmar Aprovação
                    </span>
                    <span x-show="processando" class="flex items-center gap-2">
                        <span class="loading"></span>
                        Processando...
                    </span>
                </button>
            </div>
        </div>
    </div>

    <!-- Modal de Confirmação de Rejeição -->
    <div x-show="mostrarModalRejeicao"
         x-cloak
         class="modal-overlay"
         style="display: none;">
        <div class="modal" @click.away="">
            <div class="modal-header">
                <h3 class="text-xl font-semibold text-gray-800">
                    <i class="fas fa-times-circle text-red-500 mr-2"></i>
                    Rejeitar Contribuição
                </h3>
                <button @click="fecharModalRejeicao()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <div class="modal-body">
                <p class="text-gray-700 mb-4">
                    Tem certeza que deseja rejeitar esta contribuição?
                </p>

                <div>
                    <label class="label">Motivo da rejeição (opcional)</label>
                    <textarea x-model="motivoRejeicao"
                              class="input"
                              rows="3"
                              placeholder="Explique o motivo da rejeição..."></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button @click="fecharModalRejeicao()"
                        type="button"
                        class="btn btn-outline">
                    Cancelar
                </button>
                <button @click="confirmarRejeicao()"
                        :disabled="processando"
                        class="btn btn-danger">
                    <span x-show="!processando">
                        <i class="fas fa-times"></i>
                        Confirmar Rejeição
                    </span>
                    <span x-show="processando" class="flex items-center gap-2">
                        <span class="loading"></span>
                        Processando...
                    </span>
                </button>
            </div>
        </div>
    </div>

</div>

<script>
function contribuicoesPendentes() {
    return {
        loading: true,
        contribuicoes: [],
        filtros: {
            status: 'pendente',
            data_inicio: '',
            data_fim: ''
        },
        mostrarModalRejeicao: false,
        contribuicaoParaRejeitar: null,
        motivoRejeicao: '',
        mostrarModalAprovacao: false,
        contribuicaoParaAprovar: null,
        processando: false,

        async init() {
            this.initFlatpickr();
            await this.carregar();
        },

        initFlatpickr() {
            const self = this;

            flatpickr('#data_inicio_filtro', {
                locale: 'pt',
                dateFormat: 'd/m/Y',
                altInput: true,
                altFormat: 'd/m/Y',
                onChange: (selectedDates, dateStr, instance) => {
                    const date = selectedDates[0];
                    if (date) {
                        self.filtros.data_inicio = self.formatarDataLocal(date);
                    } else {
                        self.filtros.data_inicio = '';
                    }
                }
            });

            flatpickr('#data_fim_filtro', {
                locale: 'pt',
                dateFormat: 'd/m/Y',
                altInput: true,
                altFormat: 'd/m/Y',
                onChange: (selectedDates, dateStr, instance) => {
                    const date = selectedDates[0];
                    if (date) {
                        self.filtros.data_fim = self.formatarDataLocal(date);
                    } else {
                        self.filtros.data_fim = '';
                    }
                }
            });
        },

        formatarDataLocal(data) {
            const ano = data.getFullYear();
            const mes = String(data.getMonth() + 1).padStart(2, '0');
            const dia = String(data.getDate()).padStart(2, '0');
            return `${ano}-${mes}-${dia}`;
        },

        async carregar() {
            this.loading = true;
            try {
                const params = new URLSearchParams();
                if (this.filtros.status) params.append('status', this.filtros.status);
                if (this.filtros.data_inicio) params.append('data_inicio', this.filtros.data_inicio);
                if (this.filtros.data_fim) params.append('data_fim', this.filtros.data_fim);

                const response = await API.get(`/api/contribuicoes-pendentes.php?${params.toString()}`);
                this.contribuicoes = response.data.contribuicoes || [];
            } catch (error) {
                Alpine.store('toast').error('Erro ao carregar contribuições');
            } finally {
                this.loading = false;
            }
        },

        aprovar(id) {
            this.contribuicaoParaAprovar = id;
            this.mostrarModalAprovacao = true;
        },

        fecharModalAprovacao() {
            this.mostrarModalAprovacao = false;
            this.contribuicaoParaAprovar = null;
        },

        async confirmarAprovacao() {
            if (!this.contribuicaoParaAprovar) return;

            this.processando = true;
            try {
                const response = await API.post(`/api/contribuicoes-pendentes.php?action=aprovar&id=${this.contribuicaoParaAprovar}`);
                Alpine.store('toast').success(response.message || 'Contribuição aprovada com sucesso!');
                this.fecharModalAprovacao();
                await this.carregar();
            } catch (error) {
                Alpine.store('toast').error(error.message || 'Erro ao aprovar contribuição');
            } finally {
                this.processando = false;
            }
        },

        rejeitar(id) {
            this.contribuicaoParaRejeitar = id;
            this.motivoRejeicao = '';
            this.mostrarModalRejeicao = true;
        },

        fecharModalRejeicao() {
            this.mostrarModalRejeicao = false;
            this.contribuicaoParaRejeitar = null;
            this.motivoRejeicao = '';
        },

        async confirmarRejeicao() {
            if (!this.contribuicaoParaRejeitar) return;

            this.processando = true;
            try {
                const response = await API.post(`/api/contribuicoes-pendentes.php?action=rejeitar&id=${this.contribuicaoParaRejeitar}`, {
                    motivo: this.motivoRejeicao
                });

                Alpine.store('toast').success(response.message || 'Contribuição rejeitada');
                this.fecharModalRejeicao();
                await this.carregar();
            } catch (error) {
                Alpine.store('toast').error(error.message || 'Erro ao rejeitar contribuição');
            } finally {
                this.processando = false;
            }
        }
    };
}

window.contribuicoesPendentes = contribuicoesPendentes;
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
