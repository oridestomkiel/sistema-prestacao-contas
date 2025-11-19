<?php
/**
 * Página de Categorias
 * Gerenciamento de categorias de saídas
 */

define('SISTEMA_MAE', true);

require_once __DIR__ . '/../src/config/Config.php';
require_once __DIR__ . '/../src/middleware/Auth.php';
require_once __DIR__ . '/../src/middleware/CSRF.php';
require_once __DIR__ . '/../src/helpers/functions.php';

Config::load();
Auth::iniciarSessao();
Auth::checkTokenAuth();
Auth::requireAdmin();

$page_title = 'Categorias - Sistema de Prestação de Contas';

include __DIR__ . '/includes/header.php';
?>

<div x-data="gerenciarCategorias()" x-init="init()">

    <!-- Cabeçalho -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800 mb-2">
                <i class="fas fa-tags text-purple-500 mr-2"></i>
                Categorias
            </h1>
            <p class="text-gray-600">Gerencie as categorias de saídas</p>
        </div>

        <button @click="abrirModal()" class="btn btn-primary">
            <i class="fas fa-plus-circle"></i>
            Nova Categoria
        </button>
    </div>

    <!-- Loading -->
    <div x-show="loading" class="flex justify-center items-center py-12">
        <div class="loading" style="width: 40px; height: 40px; border-width: 4px;"></div>
    </div>

    <!-- Grid de Categorias -->
    <div x-show="!loading" style="display: none;">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <template x-for="categoria in categorias" :key="categoria.id">
                <div class="card hover:shadow-lg transition-shadow">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-lg flex items-center justify-center"
                                 :style="'background-color: ' + categoria.cor + '20'">
                                <i :class="'fas ' + categoria.icone + ' text-xl'"
                                   :style="'color: ' + categoria.cor"></i>
                            </div>
                            <div>
                                <h3 class="font-semibold text-gray-800" x-text="categoria.nome"></h3>
                                <p class="text-sm text-gray-500" x-text="categoria.total_saidas + ' saída(s)'"></p>
                            </div>
                        </div>
                        <span x-show="!categoria.ativa" class="badge bg-gray-100 text-gray-600 text-xs">Inativa</span>
                    </div>

                    <p x-show="categoria.descricao" class="text-sm text-gray-600 mb-3" x-text="categoria.descricao"></p>

                    <div class="flex gap-2">
                        <button @click="editarCategoria(categoria)" class="btn btn-sm btn-outline flex-1">
                            <i class="fas fa-edit"></i> Editar
                        </button>
                        <button @click="excluirCategoria(categoria.id)" class="btn btn-sm text-red-600 hover:bg-red-50">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <!-- Modal -->
    <div x-show="modalAberto" x-transition class="modal-overlay" style="display: none;" @click.self="fecharModal()">
        <div class="modal">
            <div class="modal-header">
                <h3 class="text-xl font-semibold" x-text="categoriaEditando ? 'Editar Categoria' : 'Nova Categoria'"></h3>
                <button @click="fecharModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <form @submit.prevent="salvarCategoria()">
                <div class="modal-body space-y-4">
                    <div>
                        <label class="label">Nome *</label>
                        <input type="text" x-model="form.nome" required class="input">
                    </div>

                    <div>
                        <label class="label">Descrição</label>
                        <textarea x-model="form.descricao" class="input" rows="2"></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="label">Cor</label>
                            <input type="color" x-model="form.cor" class="input h-10">
                        </div>

                        <div>
                            <label class="label">Ícone (Font Awesome)</label>
                            <div class="flex gap-2">
                                <input type="text" x-model="form.icone" class="input flex-1" placeholder="fa-utensils">
                                <div class="w-10 h-10 rounded flex items-center justify-center border border-gray-300"
                                     :style="'background-color: ' + form.cor + '20'">
                                    <i :class="'fas ' + form.icone" :style="'color: ' + form.cor"></i>
                                </div>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">
                                <a href="https://fontawesome.com/icons" target="_blank" class="text-blue-600 hover:underline">
                                    <i class="fas fa-external-link-alt"></i> Ver ícones disponíveis
                                </a>
                            </p>
                        </div>
                    </div>

                    <div x-show="categoriaEditando">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" x-model="form.ativa" class="rounded">
                            <span class="text-sm text-gray-700">Categoria ativa</span>
                        </label>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" @click="fecharModal()" class="btn btn-outline">Cancelar</button>
                    <button type="submit" :disabled="salvando" class="btn btn-primary">
                        <span x-show="!salvando">Salvar</span>
                        <span x-show="salvando">Salvando...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
function gerenciarCategorias() {
    return {
        categorias: [],
        loading: true,
        modalAberto: false,
        categoriaEditando: null,
        salvando: false,
        form: {
            nome: '',
            descricao: '',
            cor: '#6B7280',
            icone: 'fa-folder',
            ativa: true
        },

        async init() {
            await this.carregar();
        },

        async carregar() {
            this.loading = true;
            try {
                const response = await API.get('/api/categorias.php?incluir_inativas=1');
                this.categorias = response.data.categorias || [];
            } catch (error) {
                Alpine.store('toast').error('Erro ao carregar categorias');
            } finally {
                this.loading = false;
            }
        },

        abrirModal() {
            this.modalAberto = true;
            this.categoriaEditando = null;
            this.form = {
                nome: '',
                descricao: '',
                cor: '#6B7280',
                icone: 'fa-folder',
                ativa: true
            };
        },

        editarCategoria(categoria) {
            this.modalAberto = true;
            this.categoriaEditando = categoria;
            this.form = {
                nome: categoria.nome,
                descricao: categoria.descricao || '',
                cor: categoria.cor,
                icone: categoria.icone,
                ativa: categoria.ativa == 1
            };
        },

        fecharModal() {
            this.modalAberto = false;
            this.categoriaEditando = null;
        },

        async salvarCategoria() {
            this.salvando = true;
            try {
                if (this.categoriaEditando) {
                    await API.put(`/api/categorias.php?id=${this.categoriaEditando.id}`, this.form);
                    Alpine.store('toast').success('Categoria atualizada!');
                } else {
                    await API.post('/api/categorias.php', this.form);
                    Alpine.store('toast').success('Categoria criada!');
                }

                this.fecharModal();
                await this.carregar();
            } catch (error) {
                Alpine.store('toast').error(error.message || 'Erro ao salvar categoria');
            } finally {
                this.salvando = false;
            }
        },

        async excluirCategoria(id) {
            if (!await Utils.confirmar('Tem certeza que deseja excluir esta categoria?')) {
                return;
            }

            try {
                await API.delete(`/api/categorias.php?id=${id}`);
                Alpine.store('toast').success('Categoria excluída!');
                await this.carregar();
            } catch (error) {
                Alpine.store('toast').error(error.message || 'Erro ao excluir categoria');
            }
        }
    };
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
