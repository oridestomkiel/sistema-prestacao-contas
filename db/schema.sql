-- ============================================================
-- Sistema de Prestação de Contas - Database Schema
-- ============================================================
--
-- Sistema completo para gestão financeira de cuidados
-- com pessoas em situação de vulnerabilidade
--
-- Versão: 1.0.0
-- Charset: utf8mb4
-- Collation: utf8mb4_unicode_ci
-- ============================================================

-- Criar banco de dados (se não existir)
CREATE DATABASE IF NOT EXISTS prestacao_contas
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE prestacao_contas;

-- ============================================================
-- Tabela: usuarios
-- Armazena usuários do sistema (admin e convidados)
-- ============================================================
CREATE TABLE IF NOT EXISTS usuarios (
  id INT(11) NOT NULL AUTO_INCREMENT,
  nome VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL,
  senha VARCHAR(255) NOT NULL COMMENT 'Hash bcrypt',
  tipo ENUM('admin','convidado') DEFAULT 'convidado',
  codigo_acesso VARCHAR(10) DEFAULT NULL,
  data_expiracao_codigo DATETIME DEFAULT NULL,
  ativo TINYINT(1) DEFAULT 1,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY email (email),
  KEY idx_tipo (tipo),
  KEY idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Usuários do sistema';

-- ============================================================
-- Tabela: sessoes
-- Gerenciamento de sessões de usuários
-- ============================================================
CREATE TABLE IF NOT EXISTS sessoes (
  id INT(11) NOT NULL AUTO_INCREMENT,
  usuario_id INT(11) DEFAULT NULL,
  token VARCHAR(64) NOT NULL,
  ip VARCHAR(45) DEFAULT NULL,
  user_agent TEXT,
  expira_em DATETIME NOT NULL,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY token (token),
  KEY usuario_id (usuario_id),
  KEY idx_expira_em (expira_em),
  CONSTRAINT sessoes_ibfk_1 FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Sessões ativas de usuários';

-- ============================================================
-- Tabela: tokens_acesso
-- Links temporários para acesso de convidados
-- ============================================================
CREATE TABLE IF NOT EXISTS tokens_acesso (
  id INT(11) NOT NULL AUTO_INCREMENT,
  token VARCHAR(64) NOT NULL,
  nome_convidado VARCHAR(100) NOT NULL,
  tipo ENUM('leitura','convidado') DEFAULT 'leitura',
  ativo TINYINT(1) DEFAULT 1,
  expira_em DATETIME DEFAULT NULL,
  ultimo_acesso DATETIME DEFAULT NULL,
  criado_por INT(11) NOT NULL,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY token (token),
  KEY criado_por (criado_por),
  KEY idx_ativo (ativo),
  CONSTRAINT tokens_acesso_ibfk_1 FOREIGN KEY (criado_por) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tokens de acesso para convidados';

-- ============================================================
-- Tabela: visitantes
-- Rastreamento de visitantes anônimos
-- ============================================================
CREATE TABLE IF NOT EXISTS visitantes (
  id INT(11) NOT NULL AUTO_INCREMENT,
  token_id INT(11) DEFAULT NULL,
  visitante_hash VARCHAR(64) NOT NULL,
  nome VARCHAR(100) DEFAULT NULL,
  respondeu_modal TINYINT(1) DEFAULT 0,
  primeiro_acesso DATETIME DEFAULT NULL,
  ultimo_acesso DATETIME DEFAULT NULL,
  total_acessos INT(11) DEFAULT 0,
  user_agent TEXT,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY visitante_hash (visitante_hash),
  KEY token_id (token_id),
  KEY idx_primeiro_acesso (primeiro_acesso),
  KEY idx_ultimo_acesso (ultimo_acesso),
  CONSTRAINT visitantes_ibfk_1 FOREIGN KEY (token_id) REFERENCES tokens_acesso (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Visitantes do sistema';

-- ============================================================
-- Tabela: categorias
-- Categorias de despesas
-- ============================================================
CREATE TABLE IF NOT EXISTS categorias (
  id INT(11) NOT NULL AUTO_INCREMENT,
  nome VARCHAR(100) NOT NULL,
  descricao TEXT,
  cor VARCHAR(20) DEFAULT '#6B7280',
  icone VARCHAR(50) DEFAULT 'fa-folder',
  ativa TINYINT(1) NOT NULL DEFAULT 1,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY nome (nome),
  KEY idx_ativa (ativa),
  KEY idx_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Categorias de despesas';

-- ============================================================
-- Tabela: entradas
-- Registro de entradas financeiras (aposentadoria, contribuições)
-- ============================================================
CREATE TABLE IF NOT EXISTS entradas (
  id INT(11) NOT NULL AUTO_INCREMENT,
  data DATE NOT NULL,
  tipo ENUM('contribuicao','aposentadoria','doacao','saldo') NOT NULL,
  descricao VARCHAR(255) NOT NULL,
  pessoa VARCHAR(255) DEFAULT NULL,
  valor DECIMAL(10,2) NOT NULL,
  observacoes TEXT,
  criado_por INT(11) NOT NULL,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at DATETIME DEFAULT NULL,
  conferido TINYINT(1) DEFAULT 0,
  atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_data (data),
  KEY idx_tipo (tipo),
  KEY idx_criado_por (criado_por),
  CONSTRAINT entradas_ibfk_1 FOREIGN KEY (criado_por) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Entradas financeiras';

-- ============================================================
-- Tabela: saidas
-- Registro de saídas/despesas
-- ============================================================
CREATE TABLE IF NOT EXISTS saidas (
  id INT(11) NOT NULL AUTO_INCREMENT,
  data DATE NOT NULL,
  tipo ENUM('compra','pagamento') NOT NULL,
  categoria_id INT(11) DEFAULT NULL,
  item VARCHAR(255) NOT NULL,
  valor DECIMAL(10,2) NOT NULL,
  fornecedor VARCHAR(150) DEFAULT NULL,
  observacoes TEXT,
  nao_contabilizar TINYINT(1) DEFAULT 0 COMMENT 'Se 1, não entra nos totais (doações de produtos/serviços)',
  criado_por INT(11) NOT NULL,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at DATETIME DEFAULT NULL,
  conferido TINYINT(1) DEFAULT 0,
  atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_data (data),
  KEY idx_tipo (tipo),
  KEY idx_criado_por (criado_por),
  KEY categoria_id (categoria_id),
  CONSTRAINT saidas_ibfk_1 FOREIGN KEY (criado_por) REFERENCES usuarios (id) ON DELETE CASCADE,
  CONSTRAINT saidas_ibfk_2 FOREIGN KEY (categoria_id) REFERENCES categorias (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Saídas/Despesas';

-- ============================================================
-- Tabela: contribuicoes
-- Rastreamento de contribuições via PIX
-- ============================================================
CREATE TABLE IF NOT EXISTS contribuicoes (
  id INT(11) NOT NULL AUTO_INCREMENT,
  visitante_id INT(11) DEFAULT NULL,
  nome_contribuinte VARCHAR(100) DEFAULT NULL,
  mostrar_nome TINYINT(1) DEFAULT 0,
  valor DECIMAL(10,2) DEFAULT NULL COMMENT 'Valor pode ser null se pessoa só leu QR code',
  pix_chave VARCHAR(255) NOT NULL COMMENT 'Chave PIX usada',
  pix_payload TEXT COMMENT 'Payload do PIX EMV',
  txid VARCHAR(35) DEFAULT NULL,
  status ENUM('pendente','confirmado','cancelado') DEFAULT 'pendente',
  confirmado_em DATETIME DEFAULT NULL,
  ip_address VARCHAR(45) DEFAULT NULL,
  user_agent TEXT,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY txid (txid),
  KEY idx_visitante_id (visitante_id),
  KEY idx_status (status),
  KEY idx_criado_em (criado_em),
  KEY idx_txid (txid),
  CONSTRAINT contribuicoes_ibfk_1 FOREIGN KEY (visitante_id) REFERENCES visitantes (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Contribuições via PIX';

-- ============================================================
-- Tabela: contribuicoes_pendentes
-- Contribuições aguardando aprovação do admin
-- ============================================================
CREATE TABLE IF NOT EXISTS contribuicoes_pendentes (
  id INT(11) NOT NULL AUTO_INCREMENT,
  nome_doador VARCHAR(255) DEFAULT NULL,
  nome_sessao VARCHAR(255) DEFAULT NULL COMMENT 'Nome do usuário logado na sessão',
  exibir_anonimo TINYINT(1) DEFAULT 0 COMMENT 'Se 1, exibe como Anônimo',
  valor DECIMAL(10,2) NOT NULL,
  comprovante_path VARCHAR(500) DEFAULT NULL,
  status ENUM('pendente','aprovada','rejeitada') DEFAULT 'pendente',
  observacoes TEXT,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  aprovado_por INT(11) DEFAULT NULL,
  aprovado_em TIMESTAMP NULL DEFAULT NULL,
  entrada_id INT(11) DEFAULT NULL COMMENT 'ID da entrada gerada após aprovação',
  PRIMARY KEY (id),
  KEY aprovado_por (aprovado_por),
  KEY contribuicoes_pendentes_ibfk_2 (entrada_id),
  CONSTRAINT contribuicoes_pendentes_ibfk_1 FOREIGN KEY (aprovado_por) REFERENCES usuarios (id),
  CONSTRAINT contribuicoes_pendentes_ibfk_2 FOREIGN KEY (entrada_id) REFERENCES entradas (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Contribuições pendentes de aprovação';

-- ============================================================
-- Views: Relatórios agregados
-- ============================================================

-- View: Entradas por mês
CREATE OR REPLACE VIEW vw_entradas_mensais AS
SELECT
    YEAR(data) AS ano,
    MONTH(data) AS mes,
    tipo,
    COUNT(*) AS quantidade,
    SUM(valor) AS total
FROM entradas
WHERE deleted_at IS NULL
GROUP BY YEAR(data), MONTH(data), tipo;

-- View: Saídas por mês
CREATE OR REPLACE VIEW vw_saidas_mensais AS
SELECT
    YEAR(data) AS ano,
    MONTH(data) AS mes,
    tipo,
    COUNT(*) AS quantidade,
    SUM(valor) AS total
FROM saidas
WHERE deleted_at IS NULL
  AND nao_contabilizar = 0
GROUP BY YEAR(data), MONTH(data), tipo;

-- ============================================================
-- Dados Iniciais
-- ============================================================

-- Usuário Admin Padrão
-- Senha padrão: Admin@2025 (DEVE SER ALTERADA após instalação!)
-- O hash abaixo corresponde a senha temporária
INSERT INTO usuarios (nome, email, senha, tipo, ativo)
VALUES ('Administrador', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1)
ON DUPLICATE KEY UPDATE
    nome = 'Administrador',
    tipo = 'admin',
    ativo = 1;

-- Categorias Padrão
INSERT INTO categorias (nome, descricao, cor, icone) VALUES
('Alimentação', 'Despesas com alimentação e nutrição', '#10B981', 'fa-utensils'),
('Medicamentos', 'Remédios e suplementos', '#EF4444', 'fa-pills'),
('Fraldas e Higiene', 'Produtos de higiene pessoal', '#3B82F6', 'fa-baby'),
('Consultas Médicas', 'Consultas e exames', '#8B5CF6', 'fa-stethoscope'),
('Cuidadores', 'Pagamento de cuidadores e enfermeiros', '#F59E0B', 'fa-user-nurse'),
('Transporte', 'Despesas com transporte', '#6366F1', 'fa-car'),
('Outros', 'Outras despesas', '#6B7280', 'fa-ellipsis-h')
ON DUPLICATE KEY UPDATE
    descricao = VALUES(descricao),
    cor = VALUES(cor),
    icone = VALUES(icone);

-- ============================================================
-- Fim do Schema
-- ============================================================
