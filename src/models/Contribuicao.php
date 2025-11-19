<?php
/**
 * Model Contribuicao
 * Gerencia contribuições via PIX
 */

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../helpers/functions.php';

class Contribuicao
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Gera um txid único para rastreamento da contribuição
     * Formato: CONT + timestamp + hash curto (25 caracteres total)
     *
     * @return string
     */
    public static function gerarTxid()
    {
        // CONT + timestamp (10 dígitos) + hash aleatório (11 caracteres) = 25 caracteres
        $prefix = 'CONT';
        $timestamp = time();
        $random = strtoupper(substr(md5(uniqid(rand(), true)), 0, 11));

        return $prefix . $timestamp . $random;
    }

    /**
     * Gera payload PIX EMV QR Code
     *
     * @param string $chave Chave PIX (email, telefone, CPF, CNPJ ou aleatória)
     * @param string $nome Nome do recebedor
     * @param string $cidade Cidade do recebedor
     * @param float|null $valor Valor fixo (opcional)
     * @param string $nomeContribuinte Nome do contribuinte (opcional)
     * @param string $txid Identificador único da transação (26-35 caracteres para dinâmico, max 25 para estático)
     * @return string Payload PIX EMV
     */
    public static function gerarPixPayload($chave, $nome, $cidade, $valor = null, $nomeContribuinte = null, $txid = null)
    {
        // Limpar a chave PIX (apenas números/letras, sem formatação)
        // CPF e CNPJ devem estar SEM pontos e traços
        $chaveLimpa = preg_replace('/[^a-zA-Z0-9@._+-]/', '', $chave);

        // Função auxiliar para gerar campo EMV
        $emvField = function($id, $value) {
            $length = strlen($value);
            return str_pad($id, 2, '0', STR_PAD_LEFT) . str_pad($length, 2, '0', STR_PAD_LEFT) . $value;
        };

        // Payload Format Indicator (ID 00)
        $payload = '000201';

        // Merchant Account Information (ID 26) - Estrutura aninhada
        $merchantAccount = '0014br.gov.bcb.pix'; // GUI (ID 00 do campo 26)
        $merchantAccount .= $emvField('01', $chaveLimpa); // Chave PIX (ID 01 do campo 26)
        $payload .= $emvField('26', $merchantAccount);

        // Merchant Category Code
        $payload .= $emvField('52', '0000');

        // Transaction Currency (986 = BRL)
        $payload .= $emvField('53', '986');

        // Transaction Amount (se especificado)
        if ($valor !== null && $valor > 0) {
            $payload .= $emvField('54', number_format($valor, 2, '.', ''));
        }

        // Country Code (ID 58)
        $payload .= '5802BR';

        // Merchant Name (ID 59) - Máximo 25 caracteres
        $nomeLimpo = substr($nome, 0, 25);
        if (empty($nomeLimpo)) $nomeLimpo = 'N'; // Mínimo 1 caractere
        $payload .= $emvField('59', $nomeLimpo);

        // Merchant City (ID 60) - Máximo 15 caracteres
        $cidadeLimpa = substr($cidade, 0, 15);
        if (empty($cidadeLimpa)) $cidadeLimpa = 'C'; // Mínimo 1 caractere
        $payload .= $emvField('60', $cidadeLimpa);

        // Additional Data Field Template (ID 62)
        // O campo 62 é um template que DEVE conter subcampos
        if ($txid) {
            $txidLimpo = preg_replace('/[^a-zA-Z0-9]/', '', $txid);
            $txidLimpo = substr($txidLimpo, 0, 25); // Máximo 25 caracteres

            // Montar subcampo 05 (Reference Label)
            $subcampo05 = $emvField('05', $txidLimpo);

            // Adicionar o campo 62 completo
            $payload .= $emvField('62', $subcampo05);
        }

        // CRC16
        $payload .= '6304';
        $crc = self::crc16($payload);
        $payload .= strtoupper($crc);

        return $payload;
    }

    /**
     * Calcula CRC16-CCITT para PIX
     *
     * @param string $payload
     * @return string
     */
    private static function crc16($payload)
    {
        $polynomial = 0x1021;
        $crc = 0xFFFF;

        for ($i = 0; $i < strlen($payload); $i++) {
            $crc ^= (ord($payload[$i]) << 8);
            for ($j = 0; $j < 8; $j++) {
                if (($crc & 0x8000) !== 0) {
                    $crc = (($crc << 1) ^ $polynomial) & 0xFFFF;
                } else {
                    $crc = ($crc << 1) & 0xFFFF;
                }
            }
        }

        return sprintf('%04X', $crc);
    }

    /**
     * Registra intenção de contribuição
     *
     * @param int|null $visitanteId
     * @param string|null $nomeContribuinte
     * @param bool $mostrarNome
     * @param float|null $valor
     * @param string $pixChave
     * @param string|null $pixPayload
     * @param string $txid
     * @return int ID da contribuição criada
     */
    public function criar($visitanteId, $nomeContribuinte, $mostrarNome, $valor, $pixChave, $pixPayload, $txid)
    {
        $sql = "INSERT INTO contribuicoes (
                    visitante_id,
                    nome_contribuinte,
                    mostrar_nome,
                    valor,
                    pix_chave,
                    pix_payload,
                    txid,
                    ip_address,
                    user_agent
                ) VALUES (
                    :visitante_id,
                    :nome_contribuinte,
                    :mostrar_nome,
                    :valor,
                    :pix_chave,
                    :pix_payload,
                    :txid,
                    :ip_address,
                    :user_agent
                )";

        $params = [
            ':visitante_id' => $visitanteId,
            ':nome_contribuinte' => $nomeContribuinte ? sanitize($nomeContribuinte) : null,
            ':mostrar_nome' => $mostrarNome ? 1 : 0,
            ':valor' => $valor,
            ':pix_chave' => $pixChave,
            ':pix_payload' => $pixPayload,
            ':txid' => $txid,
            ':ip_address' => getIpAddress(),
            ':user_agent' => getUserAgent()
        ];

        return $this->db->insert($sql, $params);
    }

    /**
     * Busca contribuição por txid
     *
     * @param string $txid
     * @return array|false
     */
    public function buscarPorTxid($txid)
    {
        $sql = "SELECT * FROM contribuicoes WHERE txid = :txid";
        return $this->db->fetchOne($sql, [':txid' => $txid]);
    }

    /**
     * Confirma contribuição (quando admin verificar pagamento)
     *
     * @param int $id
     * @return bool
     */
    public function confirmar($id)
    {
        $sql = "UPDATE contribuicoes
                SET status = 'confirmado',
                    confirmado_em = NOW()
                WHERE id = :id";

        return $this->db->update($sql, [':id' => $id]) !== false;
    }

    /**
     * Lista contribuições recentes
     *
     * @param int $limite
     * @param bool $apenasConfirmadas
     * @return array
     */
    public function listar($limite = 50, $apenasConfirmadas = false)
    {
        $sql = "SELECT c.*,
                       v.nome as visitante_nome,
                       v.visitante_hash
                FROM contribuicoes c
                LEFT JOIN visitantes v ON c.visitante_id = v.id";

        if ($apenasConfirmadas) {
            $sql .= " WHERE c.status = 'confirmado'";
        }

        $sql .= " ORDER BY c.criado_em DESC LIMIT :limite";

        return $this->db->fetchAll($sql, [':limite' => $limite]);
    }

    /**
     * Lista contribuições públicas (com nome visível)
     *
     * @param int $limite
     * @return array
     */
    public function listarPublicas($limite = 20)
    {
        $sql = "SELECT
                    CASE
                        WHEN c.mostrar_nome = 1 THEN COALESCE(c.nome_contribuinte, v.nome, 'Anônimo')
                        ELSE 'Anônimo'
                    END as nome_exibicao,
                    c.valor,
                    c.confirmado_em,
                    c.criado_em
                FROM contribuicoes c
                LEFT JOIN visitantes v ON c.visitante_id = v.id
                WHERE c.status = 'confirmado'
                ORDER BY c.confirmado_em DESC
                LIMIT :limite";

        return $this->db->fetchAll($sql, [':limite' => $limite]);
    }

    /**
     * Obtém estatísticas de contribuições
     *
     * @return array
     */
    public function estatisticas()
    {
        $sql = "SELECT
                    COUNT(*) as total_contribuicoes,
                    SUM(CASE WHEN status = 'confirmado' THEN 1 ELSE 0 END) as confirmadas,
                    SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as pendentes,
                    SUM(CASE WHEN status = 'confirmado' AND valor IS NOT NULL THEN valor ELSE 0 END) as total_valor_confirmado,
                    SUM(CASE WHEN mostrar_nome = 1 THEN 1 ELSE 0 END) as com_nome_visivel
                FROM contribuicoes";

        return $this->db->fetchOne($sql);
    }

    /**
     * Busca contribuição por ID
     *
     * @param int $id
     * @return array|false
     */
    public function buscarPorId($id)
    {
        $sql = "SELECT * FROM contribuicoes WHERE id = :id";
        return $this->db->fetchOne($sql, [':id' => $id]);
    }
}
