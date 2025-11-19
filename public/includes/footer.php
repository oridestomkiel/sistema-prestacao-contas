<?php
/**
 * Footer do sistema
 */

if (!defined('SISTEMA_MAE')) {
    die('Acesso negado');
}
?>
        </main> <!-- Fecha main aberto no header -->

        <!-- Footer -->
        <footer class="bg-white border-t border-gray-200 mt-auto">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                <div class="text-center">
                    <p class="text-gray-600">
                        <i class="fas fa-heart text-red-400"></i>
                        <?php echo Config::get('APP_TAGLINE', 'Transparência e amor em cada gesto'); ?>
                    </p>
                </div>
            </div>
        </footer>
    </div> <!-- Fecha div.min-h-screen -->

    <!-- Modal Global de Identificação -->
    <div x-data
         x-show="$store.visitante.mostrarModal"
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
                           x-model="$store.visitante.nomeVisitante"
                           class="input"
                           placeholder="Ex: João, Ana, Pedro..."
                           @keyup.enter="$store.visitante.salvar()">
                </div>

                <p class="text-xs text-gray-500">
                    <i class="fas fa-heart mr-1 text-red-400"></i>
                    É só para sabermos quem está por aqui. Pode ficar tranquilo(a)!
                </p>
            </div>

            <div class="modal-footer">
                <button @click="$store.visitante.fechar()"
                        type="button"
                        class="text-sm text-gray-500 hover:text-gray-700 hover:underline">
                    Agora não
                </button>
                <button @click="$store.visitante.salvar()"
                        :disabled="$store.visitante.salvando || !$store.visitante.nomeVisitante.trim()"
                        class="btn btn-primary whitespace-nowrap"
                        :class="{ 'opacity-50 cursor-not-allowed': !$store.visitante.nomeVisitante.trim() }">
                    <span x-show="!$store.visitante.salvando">
                        <i class="fas fa-sign-in-alt"></i>
                        Entrar
                    </span>
                    <span x-show="$store.visitante.salvando" class="flex items-center gap-2">
                        <span class="loading"></span>
                        Entrando...
                    </span>
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Global de Confirmação -->
    <div x-data
         x-show="$store.confirmar.show"
         x-cloak
         class="modal-overlay"
         style="display: none;">
        <div class="modal" @click.away="">
            <div class="modal-header">
                <h3 class="text-xl font-semibold text-gray-800 flex items-center gap-2">
                    <i class="fas" :class="{
                        'fa-exclamation-triangle text-yellow-500': $store.confirmar.tipo === 'warning',
                        'fa-exclamation-circle text-red-500': $store.confirmar.tipo === 'danger',
                        'fa-info-circle text-blue-500': $store.confirmar.tipo === 'info'
                    }"></i>
                    <span x-text="$store.confirmar.titulo"></span>
                </h3>
                <button @click="$store.confirmar.cancelar()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <div class="modal-body">
                <p class="text-gray-700" x-text="$store.confirmar.message"></p>
            </div>

            <div class="modal-footer">
                <button @click="$store.confirmar.cancelar()"
                        type="button"
                        class="btn btn-outline">
                    <i class="fas fa-times"></i>
                    Cancelar
                </button>
                <button @click="$store.confirmar.confirmar()"
                        class="btn"
                        :class="{
                            'btn-primary': $store.confirmar.tipo === 'info',
                            'btn-warning': $store.confirmar.tipo === 'warning',
                            'btn-danger': $store.confirmar.tipo === 'danger'
                        }">
                    <i class="fas fa-check"></i>
                    Confirmar
                </button>
            </div>
        </div>
    </div>

    <!-- Flatpickr JS -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/pt.js"></script>

    <!-- JavaScript Principal -->
    <script src="<?php echo asset('/assets/js/app.js'); ?>"></script>

</body>
</html>
