/**
 * JavaScript principal da aplicação
 * Utilizando Alpine.js para interatividade
 */

// Configuração global do Alpine.js
document.addEventListener('alpine:init', () => {

    // Store global para confirmação
    Alpine.store('confirmar', {
        show: false,
        message: '',
        titulo: 'Confirmar',
        tipo: 'warning', // warning, danger, info
        resolveFn: null,
        rejectFn: null,

        async perguntar(message, titulo = 'Confirmar', tipo = 'warning') {
            return new Promise((resolve, reject) => {
                this.message = message;
                this.titulo = titulo;
                this.tipo = tipo;
                this.show = true;
                this.resolveFn = resolve;
                this.rejectFn = reject;
            });
        },

        confirmar() {
            this.show = false;
            if (this.resolveFn) {
                this.resolveFn(true);
                this.resolveFn = null;
            }
        },

        cancelar() {
            this.show = false;
            if (this.resolveFn) {
                this.resolveFn(false);
                this.resolveFn = null;
            }
        }
    });

    // Store global para notificações (toast)
    Alpine.store('toast', {
        show: false,
        message: '',
        type: 'success', // success, error, info

        showToast(message, type = 'success', duration = 3000) {
            this.message = message;
            this.type = type;
            this.show = true;

            setTimeout(() => {
                this.show = false;
            }, duration);
        },

        success(message) {
            this.showToast(message, 'success');
        },

        error(message) {
            this.showToast(message, 'error');
        },

        info(message) {
            this.showToast(message, 'info');
        }
    });

    // Store para loading global
    Alpine.store('loading', {
        isLoading: false,

        start() {
            this.isLoading = true;
        },

        stop() {
            this.isLoading = false;
        }
    });

    // Store global para identificação de visitante
    Alpine.store('visitante', {
        mostrarModal: false,
        nomeVisitante: '',
        salvando: false,
        visitanteHash: '',

        init() {
            // Carregar hash do localStorage se existir
            const hashSalvo = localStorage.getItem('visitante_hash');
            if (hashSalvo) {
                this.visitanteHash = hashSalvo;
            }
        },

        abrir() {
            // Limpar nome se for "Convidado"
            if (this.nomeVisitante === 'Convidado') {
                this.nomeVisitante = '';
            }

            this.mostrarModal = true;

            // Dar foco no campo após a modal abrir
            setTimeout(() => {
                const input = document.querySelector('[x-model="$store.visitante.nomeVisitante"]');
                if (input) {
                    input.focus();
                }
            }, 100);
        },

        fechar() {
            // Marcar no localStorage que não quis informar agora
            localStorage.setItem('visitante_nao_quis_informar', 'true');
            this.mostrarModal = false;

            // Mostrar toast informativo
            Alpine.store('toast').info('Você pode se identificar a qualquer momento clicando em "Convidado" no menu');
        },

        async salvar() {
            if (!this.nomeVisitante.trim()) {
                Alpine.store('toast').error('Por favor, informe seu nome');
                return;
            }

            this.salvando = true;

            try {
                const dados = {
                    nome: this.nomeVisitante.trim(),
                    responder: true
                };

                const response = await API.post('/api/visitante.php?action=salvar_identificacao', dados);

                // Atualizar hash no localStorage
                if (response.data?.hash) {
                    this.visitanteHash = response.data.hash;
                    localStorage.setItem('visitante_hash', response.data.hash);
                }

                // Limpar flag de "não quis informar"
                localStorage.removeItem('visitante_nao_quis_informar');

                this.mostrarModal = false;
                Alpine.store('toast').success(`Olá, ${this.nomeVisitante}! Seja bem-vindo(a)!`);

                // Recarregar a página para atualizar o nome no header
                setTimeout(() => {
                    window.location.reload();
                }, 1500);

            } catch (error) {
                Alpine.store('toast').error(error.message || 'Erro ao salvar identificação');
            } finally {
                this.salvando = false;
            }
        }
    });
});

/**
 * Funções utilitárias
 */
const Utils = {
    /**
     * Formata valor monetário
     */
    formatarValor(valor) {
        return new Intl.NumberFormat('pt-BR', {
            style: 'currency',
            currency: 'BRL'
        }).format(valor);
    },

    /**
     * Formata data no padrão brasileiro
     */
    formatarData(data) {
        if (!data) return '';
        const d = new Date(data + 'T00:00:00');
        return d.toLocaleDateString('pt-BR');
    },

    /**
     * Formata data e hora
     */
    formatarDataHora(dataHora) {
        if (!dataHora) return '';
        const d = new Date(dataHora);
        return d.toLocaleString('pt-BR');
    },

    /**
     * Converte valor formatado para número
     */
    desformatarValor(valor) {
        if (typeof valor === 'number') return valor;
        return parseFloat(valor.replace(/[^0-9,-]/g, '').replace(',', '.')) || 0;
    },

    /**
     * Aplica máscara de valor monetário
     */
    mascaraValor(event) {
        let valor = event.target.value.replace(/\D/g, '');
        valor = (parseInt(valor) / 100).toFixed(2);
        valor = valor.replace('.', ',');
        valor = valor.replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1.');
        event.target.value = 'R$ ' + valor;
    },

    /**
     * Copia texto para clipboard
     */
    async copiarTexto(texto) {
        try {
            await navigator.clipboard.writeText(texto);
            Alpine.store('toast').success('Copiado para a área de transferência!');
            return true;
        } catch (err) {
            Alpine.store('toast').error('Erro ao copiar texto');
            return false;
        }
    },

    /**
     * Confirmação de ação
     */
    async confirmar(mensagem, titulo = 'Confirmar', tipo = 'warning') {
        return await Alpine.store('confirmar').perguntar(mensagem, titulo, tipo);
    }
};

/**
 * API Client
 */
const API = {
    /**
     * Faz uma requisição para a API
     */
    async request(url, options = {}) {
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        };

        try {
            const response = await fetch(url, { ...defaultOptions, ...options });
            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Erro na requisição');
            }

            return data;
        } catch (error) {
            console.error('Erro na API:', error);
            throw error;
        }
    },

    /**
     * GET
     */
    async get(url) {
        return this.request(url, { method: 'GET' });
    },

    /**
     * POST
     */
    async post(url, data) {
        return this.request(url, {
            method: 'POST',
            body: JSON.stringify(data)
        });
    },

    /**
     * PUT
     */
    async put(url, data) {
        return this.request(url, {
            method: 'PUT',
            body: JSON.stringify(data)
        });
    },

    /**
     * DELETE
     */
    async delete(url) {
        return this.request(url, { method: 'DELETE' });
    }
};

/**
 * Component: Formulário de Login
 */
function loginForm() {
    return {
        email: '',
        senha: '',
        loading: false,
        error: '',

        async submit() {
            this.error = '';
            this.loading = true;

            try {
                const response = await API.post('/api/auth.php?action=login', {
                    email: this.email,
                    senha: this.senha
                });

                if (response.success) {
                    window.location.href = response.data.redirect || '/dashboard.php';
                }
            } catch (error) {
                this.error = error.message;
            } finally {
                this.loading = false;
            }
        }
    };
}

/**
 * Component: Formulário de Registro
 */
function registerForm() {
    return {
        codigo: '',
        nome: '',
        email: '',
        senha: '',
        loading: false,
        error: '',

        init() {
            // Pegar código da URL se existir
            const urlParams = new URLSearchParams(window.location.search);
            this.codigo = urlParams.get('codigo') || '';
        },

        async submit() {
            this.error = '';
            this.loading = true;

            try {
                const response = await API.post('/api/auth.php?action=register', {
                    codigo: this.codigo,
                    nome: this.nome,
                    email: this.email,
                    senha: this.senha
                });

                if (response.success) {
                    window.location.href = response.data.redirect || '/dashboard.php';
                }
            } catch (error) {
                this.error = error.message;
            } finally {
                this.loading = false;
            }
        }
    };
}

/**
 * Component: Dashboard
 */
function dashboard(mostrarModal = false, visitanteHashInicial = '', nomeVisitanteInicial = '') {
    return {
        loading: true,
        resumo: null,
        dadosMensais: [],

        // Modal de identificação
        mostrarModalIdentificacao: mostrarModal,
        nomeVisitante: nomeVisitanteInicial,
        salvandoIdentificacao: false,
        visitanteHash: visitanteHashInicial,

        // Modal de contribuição
        mostrarModalContribuicao: false,
        contribuicao: {
            valor: '',
            nome: '',
            mostrarNome: false,
            perguntarNome: false
        },
        qrcodeData: null,
        gerandoQRCode: false,
        salvandoContribuicao: false,

        async init() {
            // Expor componente globalmente para poder chamar do header
            window.dashboardComponent = this;

            // Carregar hash do localStorage se existir
            const hashSalvo = localStorage.getItem('visitante_hash');
            if (hashSalvo) {
                this.visitanteHash = hashSalvo;
            } else if (this.visitanteHash) {
                // Salvar hash novo no localStorage
                localStorage.setItem('visitante_hash', this.visitanteHash);
            }

            // Verificar se usuário clicou "Agora não" anteriormente
            const naoQuisInformar = localStorage.getItem('visitante_nao_quis_informar');
            if (naoQuisInformar === 'true') {
                this.mostrarModalIdentificacao = false;
            }

            // Se a modal deve ser mostrada, limpar "Convidado" e dar foco
            if (this.mostrarModalIdentificacao) {
                if (this.nomeVisitante === 'Convidado') {
                    this.nomeVisitante = '';
                }

                // Dar foco no input após a modal abrir
                this.$nextTick(() => {
                    setTimeout(() => {
                        const input = document.querySelector('[x-model="nomeVisitante"]');
                        if (input) {
                            input.focus();
                        }
                    }, 200);
                });
            }

            await this.carregarResumo();
        },

        async carregarResumo() {
            this.loading = true;
            try {
                const response = await API.get('/api/relatorios.php?action=resumo');
                this.resumo = response.data;

                // Carregar dados mensais após carregar o resumo
                this.carregarDadosMensais();
            } catch (error) {
                Alpine.store('toast').error('Erro ao carregar resumo');
            } finally {
                this.loading = false;
            }
        },

        async carregarDadosMensais() {
            try {
                const anoAtual = new Date().getFullYear();
                const response = await API.get(`/api/relatorios.php?action=grafico_mensal&ano=${anoAtual}`);
                const dados = response.data;

                // Filtrar apenas a partir de abril (mês 4)
                this.dadosMensais = dados.meses.filter(m => m.mes >= 4);
            } catch (error) {
                console.error('Erro ao carregar dados mensais:', error);
                this.dadosMensais = [];
            }
        },

        fecharModal() {
            // Marcar no localStorage que não quis informar agora
            localStorage.setItem('visitante_nao_quis_informar', 'true');
            this.mostrarModalIdentificacao = false;

            // Mostrar toast informativo
            Alpine.store('toast').info('Você pode se identificar a qualquer momento clicando em "Convidado" no menu');
        },

        abrirModalIdentificacao() {
            // Limpar nome se for "Convidado"
            if (this.nomeVisitante === 'Convidado') {
                this.nomeVisitante = '';
            }

            this.mostrarModalIdentificacao = true;

            // Dar foco no campo após a modal abrir
            this.$nextTick(() => {
                const input = document.querySelector('[x-model="nomeVisitante"]');
                if (input) {
                    input.focus();
                }
            });
        },

        async salvarIdentificacao() {
            if (!this.nomeVisitante.trim()) {
                Alpine.store('toast').error('Por favor, informe seu nome');
                return;
            }

            this.salvandoIdentificacao = true;

            try {
                const dados = {
                    nome: this.nomeVisitante.trim(),
                    responder: true
                };

                const response = await API.post('/api/visitante.php?action=salvar_identificacao', dados);

                // Atualizar hash no localStorage
                if (response.data.visitante_hash) {
                    localStorage.setItem('visitante_hash', response.data.visitante_hash);
                }

                // Remover flag de não quis informar
                localStorage.removeItem('visitante_nao_quis_informar');

                // Fechar modal
                this.mostrarModalIdentificacao = false;

                // Mostrar mensagem
                Alpine.store('toast').success(`Bem-vindo(a), ${dados.nome}!`);

                // Recarregar página para atualizar nome no header
                setTimeout(() => {
                    window.location.reload();
                }, 1500);

            } catch (error) {
                Alpine.store('toast').error(error.message || 'Erro ao salvar identificação');
            } finally {
                this.salvandoIdentificacao = false;
            }
        },

        // Métodos de contribuição
        abrirModalContribuicao() {
            // Verificar se visitante já se identificou (prioriza sessão, depois localStorage)
            const nomeVisitanteAtual = this.nomeVisitante || localStorage.getItem('visitante_nome');

            if (!nomeVisitanteAtual || nomeVisitanteAtual === 'Convidado') {
                this.contribuicao.perguntarNome = true;
                this.contribuicao.nome = '';
            } else {
                this.contribuicao.nome = nomeVisitanteAtual;
                this.contribuicao.perguntarNome = false;
            }

            this.contribuicao.mostrarNome = false; // Padrão: não mostrar
            this.contribuicao.valor = '';
            this.qrcodeData = null;
            this.mostrarModalContribuicao = true;
        },

        fecharModalContribuicao() {
            this.mostrarModalContribuicao = false;
            this.qrcodeData = null;
            this.contribuicao = {
                valor: '',
                nome: '',
                mostrarNome: false,
                perguntarNome: false
            };
        },

        async gerarQRCode() {
            this.gerandoQRCode = true;

            try {
                // IMPORTANTE: Pegar valor direto do DOM, não do modelo Alpine
                // O x-model pode ter o separador de milhar (ponto) corrompido
                const campoValorDOM = document.querySelector('[x-model="contribuicao.valor"]');
                const valorDoCampo = campoValorDOM?.value || '';

                // Extrair apenas números do valor
                let valorNumerico = null;
                if (valorDoCampo) {
                    // Remove tudo exceto números e vírgula
                    // Em pt-BR: R$ 5.000,00 → 5000,00 → 5000.00
                    const valorLimpo = valorDoCampo.replace(/[^0-9,]/g, '').replace(',', '.');
                    valorNumerico = parseFloat(valorLimpo);
                }

                // Passar o nome do contribuinte para incluir no payload PIX
                const response = await API.post('/api/contribuicao.php?action=gerar_qrcode', {
                    valor: valorNumerico,
                    nome: this.contribuicao.nome.trim() || null
                });

                this.qrcodeData = response.data;

            } catch (error) {
                Alpine.store('toast').error(error.message || 'Erro ao gerar QR Code');
            } finally {
                this.gerandoQRCode = false;
            }
        },

        async registrarContribuicao() {
            // Se deve perguntar nome e não foi informado, mostrar erro
            if (this.contribuicao.perguntarNome && !this.contribuicao.nome.trim()) {
                Alpine.store('toast').error('Por favor, informe seu nome');
                return;
            }

            // Se não gerou QR Code ainda, gerar primeiro
            if (!this.qrcodeData) {
                await this.gerarQRCode();
                if (!this.qrcodeData) {
                    return; // Erro ao gerar
                }
            }

            this.salvandoContribuicao = true;

            try {
                const dados = {
                    nome: this.contribuicao.nome.trim() || null,
                    mostrar_nome: this.contribuicao.mostrarNome,
                    valor: this.qrcodeData.valor,
                    pix_payload: this.qrcodeData.pix_payload,
                    txid: this.qrcodeData.txid
                };

                const response = await API.post('/api/contribuicao.php?action=registrar_contribuicao', dados);

                // Salvar nome no localStorage se foi informado
                if (dados.nome) {
                    localStorage.setItem('visitante_nome', dados.nome);
                }

                Alpine.store('toast').success(response.message || 'Obrigado pela contribuição!');

                // Fechar modal após 1 segundo
                setTimeout(() => {
                    this.fecharModalContribuicao();
                }, 1500);

            } catch (error) {
                Alpine.store('toast').error(error.message || 'Erro ao registrar contribuição');
            } finally {
                this.salvandoContribuicao = false;
            }
        },

        // Modal de detalhes do mês
        modalDetalhesMes: false,
        mesSelecionado: null,
        detalhesEntradas: [],
        detalhesSaidas: [],
        carregandoDetalhes: false,
        abaAtiva: 'entradas',

        async abrirDetalhesMes(mes) {
            this.mesSelecionado = mes;
            this.modalDetalhesMes = true;
            this.abaAtiva = 'entradas';
            this.detalhesEntradas = [];
            this.detalhesSaidas = [];
            this.carregandoDetalhes = true;

            try {
                const anoAtual = new Date().getFullYear();

                // Carregar entradas do mês
                const responseEntradas = await API.get(`/api/entradas.php?mes=${mes.mes}&ano=${anoAtual}`);
                this.detalhesEntradas = responseEntradas.data.entradas || [];

                // Carregar saídas do mês
                const responseSaidas = await API.get(`/api/saidas.php?mes=${mes.mes}&ano=${anoAtual}`);
                this.detalhesSaidas = responseSaidas.data.saidas || [];

            } catch (error) {
                console.error('Erro ao carregar detalhes do mês:', error);
                Alpine.store('toast').error('Erro ao carregar detalhes do mês');
            } finally {
                this.carregandoDetalhes = false;
            }
        },

        fecharDetalhesMes() {
            this.modalDetalhesMes = false;
            this.mesSelecionado = null;
            this.detalhesEntradas = [];
            this.detalhesSaidas = [];
        }
    };
}

/**
 * Component: Lista de Entradas/Saídas
 */
function listaTransacoes(tipo) {
    return {
        tipo: tipo, // 'entradas' ou 'saidas'
        items: [],
        loading: true,
        filtros: {
            mes: new Date().getMonth() + 1,
            ano: new Date().getFullYear()
        },

        async init() {
            await this.carregar();
        },

        async carregar() {
            this.loading = true;
            try {
                const url = `/api/${this.tipo}.php?mes=${this.filtros.mes}&ano=${this.filtros.ano}`;
                const response = await API.get(url);
                this.items = response.data[this.tipo];
            } catch (error) {
                Alpine.store('toast').error('Erro ao carregar dados');
            } finally {
                this.loading = false;
            }
        },

        async excluir(id) {
            if (!await Utils.confirmar('Tem certeza que deseja excluir?')) {
                return;
            }

            try {
                await API.delete(`/api/${this.tipo}.php?id=${id}`);
                Alpine.store('toast').success('Excluído com sucesso!');
                await this.carregar();
            } catch (error) {
                Alpine.store('toast').error('Erro ao excluir');
            }
        }
    };
}

/**
 * Função para fazer logout
 */
async function logout() {
    try {
        await API.post('/api/auth.php?action=logout');
        window.location.href = '/login.php';
    } catch (error) {
        Alpine.store('toast').error('Erro ao fazer logout');
    }
}

// Expor funções globalmente
window.Utils = Utils;
window.API = API;
window.loginForm = loginForm;
window.registerForm = registerForm;
window.dashboard = dashboard;
window.listaTransacoes = listaTransacoes;
window.logout = logout;
