<?php
/**
 * Página de Configurações
 * Gerenciamento de links de acesso e usuários (Admin Only)
 */

define('SISTEMA_MAE', true);

require_once __DIR__ . '/../src/config/Config.php';
require_once __DIR__ . '/../src/middleware/Auth.php';
require_once __DIR__ . '/../src/middleware/CSRF.php';
require_once __DIR__ . '/../src/helpers/functions.php';

Config::load();
Auth::iniciarSessao();
Auth::requireAuth();

// Apenas admin pode acessar
if (!Auth::isAdmin()) {
    header('HTTP/1.1 403 Forbidden');
    die('Acesso negado. Apenas administradores podem acessar esta página.');
}

$page_title = 'Configurações - Sistema de Prestação de Contas';

include __DIR__ . '/includes/header.php';
?>

<div x-data="gerenciarConfig()" x-init="init()">

    <!-- Cabeçalho -->
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-800 mb-2">
            <i class="fas fa-cog text-orange-500 mr-2"></i>
            Configurações
        </h1>
        <p class="text-gray-600">Gerenciamento de links de acesso direto</p>
    </div>

    <!-- Seção: Criar Link de Acesso -->
    <div class="card mb-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">
            <i class="fas fa-link text-blue-500 mr-2"></i>
            Criar Link de Acesso Direto
        </h3>

        <p class="text-gray-600 mb-4">
            Crie um link de acesso direto para convidados. Eles poderão acessar o sistema apenas clicando no link, sem precisar criar conta ou fazer login.
        </p>

        <button @click="criarToken()"
                :disabled="criandoToken"
                class="btn btn-primary">
            <span x-show="!criandoToken">
                <i class="fas fa-plus-circle"></i>
                Criar Link de Acesso
            </span>
            <span x-show="criandoToken" class="flex items-center gap-2">
                <span class="loading"></span>
                Criando...
            </span>
        </button>

        <!-- Link Gerado -->
        <div x-show="linkGerado" x-transition class="mt-6 bg-green-50 border border-green-200 rounded-lg p-4" style="display: none;">
            <p class="text-sm text-green-800 mb-3 font-medium">
                <i class="fas fa-check-circle mr-2"></i>
                Link criado com sucesso!
            </p>

            <div class="space-y-3">
                <div>
                    <label class="text-xs font-medium text-gray-600 mb-1 block">LINK DE ACESSO</label>
                    <div class="bg-white border border-green-300 rounded p-3 flex items-center justify-between gap-3">
                        <code class="text-sm text-gray-800 break-all flex-1" x-text="linkGerado"></code>
                        <button @click="copiarLink()" class="btn btn-sm btn-success flex-shrink-0">
                            <i class="fas fa-copy"></i>
                            Copiar
                        </button>
                    </div>
                </div>

                <div class="text-sm text-gray-700">
                    <p class="text-xs text-gray-500">
                        <i class="fas fa-info-circle mr-1"></i>
                        Envie este link para o convidado. Ele terá acesso direto ao sistema com permissão de visualização apenas.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Seção: Links Ativos -->
    <div class="card mb-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-gray-800">
                <i class="fas fa-list text-purple-500 mr-2"></i>
                Links de Acesso
            </h3>
            <button @click="carregarTokens()" class="btn btn-sm btn-outline">
                <i class="fas fa-sync-alt"></i>
                Atualizar
            </button>
        </div>

        <!-- Loading -->
        <div x-show="loadingTokens" class="flex justify-center py-8">
            <div class="loading" style="width: 30px; height: 30px;"></div>
        </div>

        <!-- Lista vazia -->
        <div x-show="!loadingTokens && tokens.length === 0" class="text-center py-8 text-gray-500">
            <i class="fas fa-link-slash text-4xl mb-2"></i>
            <p>Nenhum link de acesso criado ainda</p>
        </div>

        <!-- Tabela de Tokens -->
        <div x-show="!loadingTokens && tokens.length > 0" class="overflow-x-auto" style="display: none;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Criado em</th>
                        <th>Criado por</th>
                        <th>Último Acesso</th>
                        <th>Status</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="token in tokens" :key="token.id">
                        <tr>
                            <td class="text-sm" x-text="token.criado_em_formatado"></td>
                            <td class="text-sm" x-text="token.criado_por_nome"></td>
                            <td class="text-sm" x-text="token.ultimo_acesso_formatado"></td>
                            <td>
                                <span class="badge"
                                      :class="token.ativo ? 'badge-sucesso' : 'bg-gray-200 text-gray-700'"
                                      x-text="token.ativo_texto"></span>
                            </td>
                            <td class="text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <button @click="copiarLinkToken(token.link)"
                                            class="text-blue-600 hover:text-blue-800"
                                            title="Copiar link">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                    <button @click="toggleToken(token)"
                                            :class="token.ativo ? 'text-yellow-600 hover:text-yellow-800' : 'text-green-600 hover:text-green-800'"
                                            :title="token.ativo ? 'Desativar' : 'Ativar'">
                                        <i class="fas" :class="token.ativo ? 'fa-pause-circle' : 'fa-play-circle'"></i>
                                    </button>
                                    <button @click="deletarToken(token)"
                                            class="text-red-600 hover:text-red-800"
                                            title="Deletar">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Seção: Alterar Senha -->
    <div class="card">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">
            <i class="fas fa-key text-yellow-500 mr-2"></i>
            Alterar Senha
        </h3>

        <form @submit.prevent="alterarSenha()" class="max-w-md">
            <div class="space-y-4">
                <div>
                    <label class="label">Senha Atual</label>
                    <input type="password"
                           x-model="formSenha.senhaAtual"
                           required
                           class="input"
                           placeholder="••••••••">
                </div>

                <div>
                    <label class="label">Nova Senha</label>
                    <input type="password"
                           x-model="formSenha.novaSenha"
                           required
                           minlength="8"
                           class="input"
                           placeholder="Mínimo 8 caracteres">
                </div>

                <div>
                    <label class="label">Confirmar Nova Senha</label>
                    <input type="password"
                           x-model="formSenha.confirmarSenha"
                           required
                           minlength="8"
                           class="input"
                           placeholder="Repita a senha">
                </div>

                <button type="submit"
                        :disabled="alterandoSenha"
                        class="btn btn-primary">
                    <span x-show="!alterandoSenha">
                        <i class="fas fa-save"></i>
                        Alterar Senha
                    </span>
                    <span x-show="alterandoSenha" class="flex items-center gap-2">
                        <span class="loading"></span>
                        Alterando...
                    </span>
                </button>
            </div>
        </form>
    </div>

</div>

<script>
function gerenciarConfig() {
    return {
        // Criar Token
        criandoToken: false,
        linkGerado: null,

        // Tokens
        tokens: [],
        loadingTokens: true,

        // Alterar Senha
        formSenha: {
            senhaAtual: '',
            novaSenha: '',
            confirmarSenha: ''
        },
        alterandoSenha: false,

        async init() {
            await this.carregarTokens();
        },

        async criarToken() {
            this.criandoToken = true;
            try {
                const response = await API.post('/api/tokens.php?action=criar', {
                    nome: 'Convidado',
                    dias: null
                });

                this.linkGerado = response.data.link;

                Alpine.store('toast').success('Link criado com sucesso!');

                // Recarregar lista
                await this.carregarTokens();

            } catch (error) {
                Alpine.store('toast').error(error.message || 'Erro ao criar link');
            } finally {
                this.criandoToken = false;
            }
        },

        async copiarLink() {
            await Utils.copiarTexto(this.linkGerado);
        },

        async copiarLinkToken(link) {
            await Utils.copiarTexto(link);
        },

        async carregarTokens() {
            this.loadingTokens = true;
            try {
                const response = await API.get('/api/tokens.php?action=listar');
                this.tokens = response.data.tokens || [];
            } catch (error) {
                Alpine.store('toast').error('Erro ao carregar links');
            } finally {
                this.loadingTokens = false;
            }
        },

        async toggleToken(token) {
            const acao = token.ativo ? 'desativar' : 'ativar';
            const mensagem = token.ativo
                ? 'Desativar este link de acesso? O convidado não poderá mais acessar.'
                : 'Ativar este link de acesso novamente?';

            if (!await Utils.confirmar(mensagem)) {
                return;
            }

            try {
                await API.post(`/api/tokens.php?action=${acao}`, {
                    id: token.id
                });

                Alpine.store('toast').success(`Link ${acao}do com sucesso!`);
                await this.carregarTokens();
            } catch (error) {
                Alpine.store('toast').error(`Erro ao ${acao} link`);
            }
        },

        async deletarToken(token) {
            if (!await Utils.confirmar('Deletar este link permanentemente?')) {
                return;
            }

            try {
                await API.post('/api/tokens.php?action=deletar', {
                    id: token.id
                });

                Alpine.store('toast').success('Link deletado com sucesso!');
                await this.carregarTokens();
            } catch (error) {
                Alpine.store('toast').error('Erro ao deletar link');
            }
        },

        async alterarSenha() {
            // Validar senhas
            if (this.formSenha.novaSenha !== this.formSenha.confirmarSenha) {
                Alpine.store('toast').error('As senhas não coincidem');
                return;
            }

            if (this.formSenha.novaSenha.length < 8) {
                Alpine.store('toast').error('A senha deve ter no mínimo 8 caracteres');
                return;
            }

            this.alterandoSenha = true;
            try {
                await API.post('/api/config.php?action=alterar_senha', {
                    senha_atual: this.formSenha.senhaAtual,
                    nova_senha: this.formSenha.novaSenha,
                    confirmar_senha: this.formSenha.confirmarSenha
                });

                Alpine.store('toast').success('Senha alterada com sucesso!');

                // Limpar formulário
                this.formSenha = {
                    senhaAtual: '',
                    novaSenha: '',
                    confirmarSenha: ''
                };
            } catch (error) {
                Alpine.store('toast').error(error.message || 'Erro ao alterar senha');
            } finally {
                this.alterandoSenha = false;
            }
        }
    };
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
