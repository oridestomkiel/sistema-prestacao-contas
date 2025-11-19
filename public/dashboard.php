<?php
/**
 * Dashboard - Página Principal
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

$page_title = 'Dashboard - Sistema de Prestação de Contas';
$mostrarModalIdentificacao = Auth::isTokenAuth() && !Auth::visitanteRespondeuModal();
$visitanteHash = Auth::visitanteHash();
$nomeVisitante = Auth::user('nome') ?? '';
include __DIR__ . '/includes/header.php';
?>

<div x-data="dashboard(<?php echo $mostrarModalIdentificacao ? 'true' : 'false'; ?>, '<?php echo e($visitanteHash ?? ''); ?>', '<?php echo e($nomeVisitante); ?>')">

    <!-- Modal de Identificação (apenas para convidados via link) -->
    <div x-show="mostrarModalIdentificacao"
         x-cloak
         class="modal-overlay"
         style="display: none;">
        <div class="modal" @click.away="">
            <div class="modal-header">
                <h3 class="text-xl font-semibold text-gray-800">
                    <i class="fas fa-user-circle text-blue-500 mr-2"></i>
                    Bem-vindo(a)!
                </h3>
            </div>

            <div class="modal-body">
                <p class="text-gray-700 mb-4">
                    Você está acessando o sistema de cuidados de <strong><?php echo Config::get('PATIENT_NAME', 'pessoa assistida'); ?></strong>.
                </p>
                <p class="text-gray-700 mb-4">
                    <strong>Gostaria de nos dizer quem você é?</strong> Assim saberemos quem está acompanhando com tanto carinho.
                </p>

                <div class="mb-4">
                    <label class="label">Como você se chama?</label>
                    <input type="text"
                           x-model="nomeVisitante"
                           class="input"
                           placeholder="Ex: João, Ana, Pedro..."
                           @keyup.enter="salvarIdentificacao()">
                </div>

                <p class="text-xs text-gray-500">
                    <i class="fas fa-heart mr-1 text-red-400"></i>
                    É só para sabermos quem está por aqui. Pode ficar tranquilo(a)!
                </p>
            </div>

            <div class="modal-footer">
                <button @click="fecharModal()"
                        type="button"
                        class="text-sm text-gray-500 hover:text-gray-700 hover:underline">
                    Agora não
                </button>
                <button @click="salvarIdentificacao()"
                        :disabled="salvandoIdentificacao || !nomeVisitante.trim()"
                        class="btn btn-primary whitespace-nowrap"
                        :class="{ 'opacity-50 cursor-not-allowed': !nomeVisitante.trim() }">
                    <span x-show="!salvandoIdentificacao">
                        <i class="fas fa-sign-in-alt"></i>
                        Entrar
                    </span>
                    <span x-show="salvandoIdentificacao" class="flex items-center gap-2">
                        <span class="loading"></span>
                        Entrando...
                    </span>
                </button>
            </div>
        </div>
    </div>

    <!-- Modal de Contribuição PIX -->
    <div x-show="mostrarModalContribuicao"
         x-cloak
         class="modal-overlay"
         style="display: none;">
        <div class="modal modal-contribuicao" @click.away="">
            <div class="modal-header">
                <h3 class="text-xl font-semibold text-gray-800">
                    <i class="fas fa-hand-holding-heart text-red-400 mr-2"></i>
                    Contribuir para os cuidados
                </h3>
                <button @click="fecharModalContribuicao()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <div class="modal-body">
                <!-- Pedir nome se não identificado -->
                <div x-show="contribuicao.perguntarNome && !qrcodeData" class="mb-6 p-4 bg-blue-50 rounded-lg border border-blue-200">
                    <p class="text-sm text-gray-700 mb-3">
                        <strong>Gostaria de nos dizer quem você é?</strong> É opcional, mas adoraríamos saber quem está contribuindo.
                    </p>
                    <input type="text"
                           x-model="contribuicao.nome"
                           class="input mb-3"
                           placeholder="Seu nome (opcional)">

                    <!-- Checkbox para mostrar nome -->
                    <label class="flex items-center text-sm text-gray-700 cursor-pointer">
                        <input type="checkbox"
                               x-model="contribuicao.mostrarNome"
                               :disabled="!contribuicao.nome.trim()"
                               class="mr-2">
                        <span :class="{ 'opacity-50': !contribuicao.nome.trim() }">
                            Quero que meu nome apareça na lista de contribuições
                        </span>
                    </label>
                </div>

                <!-- Checkbox para mostrar nome (se já identificado) -->
                <div x-show="!contribuicao.perguntarNome && !qrcodeData" class="mb-6 p-4 bg-blue-50 rounded-lg border border-blue-200">
                    <label class="flex items-center text-sm text-gray-700 cursor-pointer">
                        <input type="checkbox"
                               x-model="contribuicao.mostrarNome"
                               class="mr-2">
                        <span>
                            Quero que meu nome (<strong x-text="contribuicao.nome"></strong>) apareça na lista de contribuições
                        </span>
                    </label>
                    <p class="text-xs text-gray-500 mt-2">
                        <i class="fas fa-info-circle mr-1"></i>
                        Por padrão, sua contribuição será anônima
                    </p>
                </div>

                <!-- Campo de valor (opcional) -->
                <div x-show="!qrcodeData" class="mb-6">
                    <label class="label">Valor da contribuição (opcional)</label>
                    <input type="text"
                           x-model="contribuicao.valor"
                           @input="Utils.mascaraValor($event)"
                           class="input"
                           placeholder="R$ 0,00">
                    <p class="text-xs text-gray-500 mt-1">
                        <i class="fas fa-info-circle mr-1"></i>
                        Deixe em branco para informar o valor no aplicativo do banco
                    </p>
                </div>

                <!-- QR Code gerado -->
                <div x-show="qrcodeData" class="text-center mb-4">
                    <div class="bg-white p-4 rounded-lg inline-block border-2 border-gray-200">
                        <img :src="qrcodeData?.qrcode_url"
                             alt="QR Code PIX"
                             class="w-64 h-64 mx-auto">
                    </div>
                    <p class="text-sm text-gray-600 mt-3">
                        <i class="fas fa-qrcode mr-1"></i>
                        Escaneie o QR Code com seu aplicativo de banco
                    </p>
                    <p class="text-xs text-gray-500 mt-1" x-show="qrcodeData?.valor">
                        Valor: <strong x-text="Utils.formatarValor(qrcodeData?.valor)"></strong>
                    </p>
                </div>

                <!-- Código PIX Copia e Cola -->
                <div x-show="qrcodeData" class="mt-4 p-4 bg-blue-50 rounded-lg border border-blue-200">
                    <p class="text-sm font-semibold text-gray-800 mb-2">
                        <i class="fas fa-qrcode text-blue-500 mr-1"></i>
                        Código PIX Copia e Cola
                    </p>
                    <div class="flex items-center gap-2 mb-4">
                        <input type="text"
                               :value="qrcodeData?.pix_payload"
                               readonly
                               class="flex-1 input text-xs font-mono"
                               @click="$event.target.select()">
                        <button @click="navigator.clipboard.writeText(qrcodeData?.pix_payload); Alpine.store('toast').success('Código PIX copiado! Cole no seu banco.')"
                                class="btn btn-sm btn-primary whitespace-nowrap">
                            <i class="fas fa-copy"></i>
                            Copiar
                        </button>
                    </div>

                    <div class="pt-3 border-t border-blue-300">
                        <p class="text-sm font-semibold text-gray-800 mb-1">
                            <i class="fas fa-user text-blue-500 mr-1"></i>
                            Destinatário
                        </p>
                        <p class="text-sm text-gray-700"><?php echo Config::get('PIX_HOLDER_NAME', 'Nome do Beneficiário'); ?></p>
                        <p class="text-xs text-gray-600">CPF: <?php echo Config::get('PIX_CPF_DISPLAY', '***.***.***-**'); ?></p>
                        <p class="text-xs text-gray-600"><?php echo Config::get('PIX_BANK_NAME', 'Banco do Beneficiário'); ?></p>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button @click="fecharModalContribuicao()"
                        type="button"
                        class="btn btn-outline">
                    <i class="fas fa-times"></i>
                    Cancelar
                </button>

                <button @click="qrcodeData ? registrarContribuicao() : gerarQRCode()"
                        :disabled="gerandoQRCode || salvandoContribuicao"
                        class="btn btn-primary">
                    <span x-show="!gerandoQRCode && !salvandoContribuicao && !qrcodeData">
                        <i class="fas fa-qrcode"></i>
                        Gerar QR Code
                    </span>
                    <span x-show="!gerandoQRCode && !salvandoContribuicao && qrcodeData">
                        <i class="fas fa-check"></i>
                        Confirmar Contribuição
                    </span>
                    <span x-show="gerandoQRCode" class="flex items-center gap-2">
                        <span class="loading"></span>
                        Gerando QR Code...
                    </span>
                    <span x-show="salvandoContribuicao" class="flex items-center gap-2">
                        <span class="loading"></span>
                        Salvando...
                    </span>
                </button>
            </div>
        </div>
    </div>

    <!-- Mensagem Emotiva com Botão Contribuir -->
    <div class="bg-gradient-to-r from-blue-50 to-green-50 rounded-xl p-6 mb-8 border border-blue-100">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-center">
            <!-- Coluna Esquerda: Frases -->
            <div class="md:col-span-2">
                <div class="flex items-start gap-3 mb-3">
                    <i class="fas fa-heart text-3xl text-red-400 mt-1"></i>
                    <div>
                        <h2 class="text-2xl font-semibold text-gray-800 mb-2">
                            <?php echo Config::get('APP_TAGLINE', 'Cuidando com amor e transparência'); ?>
                        </h2>
                        <p class="text-gray-600">
                            Gratidão por acompanhar e contribuir
                        </p>
                    </div>
                </div>
            </div>

            <!-- Coluna Direita: Botão -->
            <div class="md:col-span-1 flex justify-center md:justify-end">
                <button @click="abrirModalContribuicao()"
                        class="btn btn-primary btn-contribuir font-bold px-12 py-6 whitespace-nowrap shadow-xl hover:shadow-2xl transform hover:scale-110 transition-all"
                        style="font-size: 1.5rem !important;">
                    <i class="fas fa-hand-holding-heart mr-3" style="font-size: 1.5rem;"></i>
                    Fazer contribuição
                </button>
            </div>
        </div>
    </div>

    <!-- Loading -->
    <div x-show="loading" class="flex justify-center items-center py-12">
        <div class="loading" style="width: 40px; height: 40px; border-width: 4px;"></div>
    </div>

    <!-- Conteúdo Principal -->
    <div x-show="!loading && resumo" style="display: none;">

        <!-- Cards de Resumo -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <!-- Card Entradas -->
            <a href="/entradas.php" class="card card-entrada hover:shadow-lg transition-shadow cursor-pointer">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Entradas no Mês Atual</p>
                        <h3 class="text-3xl font-bold" style="color: #48BB78;" x-text="resumo?.entradas_mes_atual_formatado || 'R$ 0,00'"></h3>
                    </div>
                    <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background-color: #A8D5BA;">
                        <i class="fas fa-arrow-up text-white text-xl"></i>
                    </div>
                </div>
                <div class="flex items-center justify-between text-sm text-gray-600">
                    <div class="flex items-center">
                        <i class="fas fa-calendar-alt mr-2"></i>
                        <span x-text="new Date().toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' })"></span>
                    </div>
                    <i class="fas fa-arrow-right text-gray-400"></i>
                </div>
            </a>

            <!-- Card Saídas -->
            <a href="/saidas.php" class="card card-saida hover:shadow-lg transition-shadow cursor-pointer">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Saídas no Mês Atual</p>
                        <h3 class="text-3xl font-bold" style="color: #F56565;" x-text="resumo?.saidas_mes_atual_formatado || 'R$ 0,00'"></h3>
                    </div>
                    <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background-color: #F4A5A5;">
                        <i class="fas fa-arrow-down text-white text-xl"></i>
                    </div>
                </div>
                <div class="flex items-center justify-between text-sm text-gray-600">
                    <div class="flex items-center">
                        <i class="fas fa-calendar-alt mr-2"></i>
                        <span x-text="new Date().toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' })"></span>
                    </div>
                    <i class="fas fa-arrow-right text-gray-400"></i>
                </div>
            </a>

            <!-- Card Saldo -->
            <div class="card card-saldo">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Dinheiro Guardado</p>
                        <h3 class="text-3xl font-bold"
                            :class="{
                                'text-green-600': resumo?.saldo_mes_atual >= 0,
                                'text-red-600': resumo?.saldo_mes_atual < 0
                            }"
                            x-text="resumo?.saldo_mes_atual_formatado || 'R$ 0,00'"></h3>
                    </div>
                    <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background-color: #A5C9E5;">
                        <i class="fas fa-wallet text-white text-xl"></i>
                    </div>
                </div>
                <div class="flex items-center text-sm text-gray-600">
                    <i class="fas fa-info-circle mr-2"></i>
                    <span>Total acumulado até hoje</span>
                </div>
            </div>
        </div>

        <!-- Grid de Últimas Transações -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">

            <!-- Últimas Entradas -->
            <div class="card">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-semibold text-gray-800">
                        <i class="fas fa-arrow-up text-green-500 mr-2"></i>
                        Últimas Entradas
                    </h3>
                    <a href="/entradas.php" class="text-sm text-blue-600 hover:underline">
                        Ver todas
                    </a>
                </div>

                <div x-show="resumo?.ultimas_entradas?.length === 0" class="text-center py-8 text-gray-500">
                    <i class="fas fa-inbox text-4xl mb-2"></i>
                    <p>Nenhuma entrada registrada ainda</p>
                </div>

                <div class="space-y-3" x-show="resumo?.ultimas_entradas?.length > 0">
                    <template x-for="entrada in resumo?.ultimas_entradas || []" :key="entrada.id">
                        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-2 sm:gap-3 p-3 bg-green-50 rounded-lg">
                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-gray-800 truncate" x-text="entrada.descricao"></p>
                                <p class="text-sm text-gray-600 flex items-center gap-2 flex-wrap">
                                    <span x-text="entrada.data_formatada"></span>
                                    <span>•</span>
                                    <span class="badge badge-entrada capitalize" x-text="entrada.tipo"></span>
                                    <template x-if="entrada.tipo === 'contribuicao' && entrada.observacoes">
                                        <template x-if="true">
                                            <span>
                                                <span>•</span>
                                                <span x-text="entrada.observacoes"></span>
                                            </span>
                                        </template>
                                    </template>
                                </p>
                            </div>
                            <div class="text-left sm:text-right flex-shrink-0">
                                <p class="font-semibold text-green-600 whitespace-nowrap" x-text="entrada.valor_formatado"></p>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Últimas Saídas -->
            <div class="card">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-semibold text-gray-800">
                        <i class="fas fa-arrow-down text-red-500 mr-2"></i>
                        Últimas Saídas
                    </h3>
                    <a href="/saidas.php" class="text-sm text-blue-600 hover:underline">
                        Ver todas
                    </a>
                </div>

                <div x-show="resumo?.ultimas_saidas?.length === 0" class="text-center py-8 text-gray-500">
                    <i class="fas fa-inbox text-4xl mb-2"></i>
                    <p>Nenhuma saída registrada ainda</p>
                </div>

                <div class="space-y-3" x-show="resumo?.ultimas_saidas?.length > 0">
                    <template x-for="saida in resumo?.ultimas_saidas || []" :key="saida.id">
                        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-2 sm:gap-3 p-3 bg-red-50 rounded-lg">
                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-gray-800 truncate" x-text="saida.item"></p>
                                <p class="text-sm text-gray-600 flex items-center gap-2 flex-wrap">
                                    <span x-text="saida.data_formatada"></span>
                                    <span>•</span>
                                    <span class="badge badge-saida capitalize inline-flex items-center gap-1.5">
                                        <i :class="'fas ' + (saida.categoria_icone || 'fa-tag')"></i>
                                        <span x-text="saida.categoria"></span>
                                    </span>
                                </p>
                            </div>
                            <div class="text-left sm:text-right flex-shrink-0">
                                <p class="font-semibold text-red-600 whitespace-nowrap" x-text="saida.valor_formatado"></p>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

        </div>

        <!-- Movimentação Mensal -->
        <div class="card">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-semibold text-gray-800">
                    <i class="fas fa-calendar-alt text-blue-500 mr-2"></i>
                    Movimentação Mensal
                </h3>
            </div>

            <div x-show="!dadosMensais || dadosMensais.length === 0" class="text-center py-8 text-gray-500">
                <i class="fas fa-inbox text-4xl mb-2"></i>
                <p>Nenhum dado disponível</p>
            </div>

            <!-- Grid de Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4" x-show="dadosMensais && dadosMensais.length > 0">
                <template x-for="mes in dadosMensais" :key="mes.mes">
                    <div class="relative bg-gradient-to-br from-white to-gray-50 rounded-xl border border-gray-200 hover:border-blue-300 hover:shadow-lg transition-all duration-300 overflow-hidden">
                        <!-- Header do Card -->
                        <div class="px-5 pt-4 pb-3 border-b border-gray-100 bg-white">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center shadow-md">
                                        <i class="fas fa-calendar text-white text-sm"></i>
                                    </div>
                                    <h4 class="font-bold text-gray-800 text-base" x-text="mes.nome_mes"></h4>
                                </div>
                                <button @click="abrirDetalhesMes(mes)"
                                        class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-600 transition-colors text-sm font-medium"
                                        x-show="mes.entradas > 0 || mes.saidas > 0"
                                        title="Ver detalhes">
                                    <i class="fas fa-search text-xs"></i>
                                    <span>Detalhes</span>
                                </button>
                            </div>
                        </div>

                        <!-- Corpo do Card -->
                        <div>
                            <!-- Entradas -->
                            <div class="flex items-center justify-between p-3 bg-green-50 border-b border-green-100">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-green-500 flex items-center justify-center shadow-sm">
                                        <i class="fas fa-arrow-up text-white text-xs"></i>
                                    </div>
                                    <span class="text-sm font-medium text-gray-700">Entradas</span>
                                </div>
                                <span class="font-bold text-green-600 text-base"
                                      x-text="mes.entradas > 0 ? 'R$ ' + mes.entradas.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '-'"></span>
                            </div>

                            <!-- Saídas -->
                            <div class="flex items-center justify-between p-3 bg-red-50 border-b border-red-100">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-red-500 flex items-center justify-center shadow-sm">
                                        <i class="fas fa-arrow-down text-white text-xs"></i>
                                    </div>
                                    <span class="text-sm font-medium text-gray-700">Saídas</span>
                                </div>
                                <span class="font-bold text-red-600 text-base"
                                      x-text="mes.saidas > 0 ? 'R$ ' + mes.saidas.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '-'"></span>
                            </div>

                            <!-- Saldo Acumulado -->
                            <div class="flex items-center justify-between p-3 bg-blue-50">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-blue-500 flex items-center justify-center shadow-sm">
                                        <i class="fas fa-wallet text-white text-xs"></i>
                                    </div>
                                    <span class="text-sm font-bold text-gray-700">Saldo</span>
                                </div>
                                <span class="font-bold text-blue-600 text-lg"
                                      x-text="mes.entradas > 0 || mes.saidas > 0 ? 'R$ ' + mes.saldo.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '-'"></span>
                            </div>
                        </div>

                        <!-- Indicador de vazio -->
                        <div x-show="mes.entradas === 0 && mes.saidas === 0"
                             class="absolute inset-0 bg-gray-100 bg-opacity-50 flex items-center justify-center rounded-xl">
                            <span class="text-gray-400 text-sm font-medium">Sem movimentações</span>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- Modal de Detalhes do Mês -->
        <div x-show="modalDetalhesMes"
             x-cloak
             class="modal-overlay"
             style="display: none;">
            <div class="modal modal-lg" @click.away="fecharDetalhesMes()">
                <div class="modal-header">
                    <h3 class="text-xl font-semibold text-gray-800">
                        <i class="fas fa-calendar-alt text-blue-500 mr-2"></i>
                        Detalhes de <span x-text="mesSelecionado?.nome_mes"></span>
                    </h3>
                    <button @click="fecharDetalhesMes()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>

                <div class="modal-body">
                    <!-- Loading -->
                    <div x-show="carregandoDetalhes" class="flex justify-center items-center py-12">
                        <div class="loading" style="width: 40px; height: 40px; border-width: 4px;"></div>
                    </div>

                    <!-- Conteúdo -->
                    <div x-show="!carregandoDetalhes" style="display: none;">
                        <!-- Resumo do Mês -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                            <div class="p-4 bg-green-50 rounded-lg">
                                <p class="text-sm text-gray-600 mb-1">Total de Entradas</p>
                                <p class="text-2xl font-bold text-green-600" x-text="'R$ ' + (mesSelecionado?.entradas || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></p>
                                <p class="text-xs text-gray-500 mt-1" x-text="(detalhesEntradas?.length || 0) + ' registro(s)'"></p>
                            </div>
                            <div class="p-4 bg-red-50 rounded-lg">
                                <p class="text-sm text-gray-600 mb-1">Total de Saídas</p>
                                <p class="text-2xl font-bold text-red-600" x-text="'R$ ' + (mesSelecionado?.saidas || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></p>
                                <p class="text-xs text-gray-500 mt-1" x-text="(detalhesSaidas?.length || 0) + ' registro(s)'"></p>
                            </div>
                            <div class="p-4 bg-blue-50 rounded-lg">
                                <p class="text-sm text-gray-600 mb-1">Saldo Acumulado</p>
                                <p class="text-2xl font-bold text-blue-600" x-text="'R$ ' + (mesSelecionado?.saldo || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></p>
                            </div>
                        </div>

                        <!-- Tabs -->
                        <div class="mb-4">
                            <div class="flex border-b border-gray-200">
                                <button @click="abaAtiva = 'entradas'"
                                        class="px-4 py-2 font-medium transition-colors"
                                        :class="abaAtiva === 'entradas' ? 'text-green-600 border-b-2 border-green-600' : 'text-gray-500 hover:text-gray-700'">
                                    <i class="fas fa-arrow-up mr-1"></i>
                                    Entradas (<span x-text="detalhesEntradas?.length || 0"></span>)
                                </button>
                                <button @click="abaAtiva = 'saidas'"
                                        class="px-4 py-2 font-medium transition-colors"
                                        :class="abaAtiva === 'saidas' ? 'text-red-600 border-b-2 border-red-600' : 'text-gray-500 hover:text-gray-700'">
                                    <i class="fas fa-arrow-down mr-1"></i>
                                    Saídas (<span x-text="detalhesSaidas?.length || 0"></span>)
                                </button>
                            </div>
                        </div>

                        <!-- Tabela de Entradas -->
                        <div x-show="abaAtiva === 'entradas'" style="max-height: 400px; overflow-y: auto;">
                            <div x-show="!detalhesEntradas || detalhesEntradas.length === 0" class="text-center py-8 text-gray-500">
                                <i class="fas fa-inbox text-4xl mb-2"></i>
                                <p>Nenhuma entrada neste mês</p>
                            </div>

                            <table class="table" x-show="detalhesEntradas && detalhesEntradas.length > 0">
                                <thead>
                                    <tr>
                                        <th>Data</th>
                                        <th>Tipo</th>
                                        <th>Descrição</th>
                                        <th class="text-right">Valor</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="entrada in detalhesEntradas" :key="entrada.id">
                                        <tr>
                                            <td x-text="entrada.data_formatada"></td>
                                            <td>
                                                <span class="badge badge-entrada capitalize" x-text="entrada.tipo"></span>
                                            </td>
                                            <td x-text="entrada.descricao"></td>
                                            <td class="text-right font-semibold text-green-600" x-text="entrada.valor_formatado"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>

                        <!-- Tabela de Saídas -->
                        <div x-show="abaAtiva === 'saidas'" style="max-height: 400px; overflow-y: auto;">
                            <div x-show="!detalhesSaidas || detalhesSaidas.length === 0" class="text-center py-8 text-gray-500">
                                <i class="fas fa-inbox text-4xl mb-2"></i>
                                <p>Nenhuma saída neste mês</p>
                            </div>

                            <table class="table" x-show="detalhesSaidas && detalhesSaidas.length > 0">
                                <thead>
                                    <tr>
                                        <th>Data</th>
                                        <th>Tipo</th>
                                        <th>Item</th>
                                        <th>Categoria</th>
                                        <th class="text-right">Valor</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="saida in detalhesSaidas" :key="saida.id">
                                        <tr>
                                            <td x-text="saida.data_formatada"></td>
                                            <td>
                                                <span class="badge badge-saida capitalize" x-text="saida.tipo"></span>
                                            </td>
                                            <td x-text="saida.item"></td>
                                            <td>
                                                <span class="badge badge-saida capitalize inline-flex items-center gap-1.5">
                                                    <i :class="'fas ' + (saida.categoria_icone || 'fa-tag')"></i>
                                                    <span x-text="saida.categoria"></span>
                                                </span>
                                            </td>
                                            <td class="text-right font-semibold text-red-600" x-text="saida.valor_formatado"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button @click="fecharDetalhesMes()" class="btn btn-outline">
                        <i class="fas fa-times"></i>
                        Fechar
                    </button>
                </div>
            </div>
        </div>

        <!-- Ações Rápidas (apenas para admin) -->
        <?php if (Auth::isAdmin()): ?>
        <div class="mt-8 card">
            <h3 class="text-xl font-semibold text-gray-800 mb-4">
                <i class="fas fa-bolt text-yellow-500 mr-2"></i>
                Ações Rápidas
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <a href="/entradas.php?novo=1" class="btn btn-success justify-center">
                    <i class="fas fa-plus-circle"></i>
                    Nova Entrada
                </a>
                <a href="/saidas.php?novo=1" class="btn btn-danger justify-center">
                    <i class="fas fa-plus-circle"></i>
                    Nova Saída
                </a>
                <a href="/relatorios.php" class="btn btn-primary justify-center">
                    <i class="fas fa-file-pdf"></i>
                    Gerar Relatório
                </a>
                <a href="/config.php" class="btn btn-outline justify-center">
                    <i class="fas fa-user-plus"></i>
                    Convidar Usuário
                </a>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
