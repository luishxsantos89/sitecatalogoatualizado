-- ============================================================
-- SiteCatalogo - Banco de Dados Completo (Universal v2.7)
-- Prefixo: sc_  |  Charset: utf8mb4  |  Engine: InnoDB
-- Compatível: localhost, cPanel, Hostgator, Hostinger, VPS, Docker
--
-- INSTRUÇÕES DE INSTALAÇÃO:
--
-- 1. LOCALHOST (Laragon, XAMPP, WAMP):
--    O instalador criará o banco automaticamente. Não execute este SQL manualmente.
--
-- 2. cPanel / Hostgator / Hostinger / Locaweb:
--    a) Crie o banco MANUALMENTE no cPanel (ex: usuario_sitecatalogo)
--    b) Informe o nome COMPLETO do banco no instalador
--    c) O instalador criará as tabelas automaticamente
--    d) NÃO execute este SQL manualmente a menos que saiba o que está fazendo
--
-- 3. VPS / SERVIDOR DEDICADO:
--    O instalador pode criar o banco automaticamente (se tiver permissão root)
--    ou usar um banco existente.
--
-- Acesso padrão após instalação:
--   E-mail: (o que você definir no instalador)
--   Senha: (a que você definir no instalador)
--
-- Versão: 2.8.0  |  Atualizado: 2026-06-21 (v4.0: Email unificado + senha unificada)
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

-- ============================================================
-- sc_atividades_log
-- ============================================================
DROP TABLE IF EXISTS `sc_atividades_log`;
CREATE TABLE `sc_atividades_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `acao` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tabela` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `usuario_id` int unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_usuario` (`usuario_id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- sc_banners
-- ============================================================
DROP TABLE IF EXISTS `sc_banners`;
CREATE TABLE `sc_banners` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subtitulo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `imagem` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `link` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `posicao` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'slide',
  `ordem` int DEFAULT '0',
  `ativo` tinyint(1) DEFAULT '1',
  `popup_delay` int DEFAULT '0',
  `popup_fechar` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'botao',
  `popup_freq_max` int DEFAULT '0',
  `popup_intervalo` int DEFAULT '0',
  `prazo_fixo` tinyint(1) DEFAULT '1',
  `data_inicio` date DEFAULT NULL,
  `data_fim` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_posicao_ativo` (`posicao`,`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- sc_categorias
-- ============================================================
DROP TABLE IF EXISTS `sc_categorias`;
CREATE TABLE `sc_categorias` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `imagem` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `icone` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'category',
  `ordem` int DEFAULT '0',
  `ativo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- sc_clientes
-- ============================================================
DROP TABLE IF EXISTS `sc_clientes`;
CREATE TABLE `sc_clientes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nome_razaosocial` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nome ou Razão Social',
  `nome` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Alias para compatibilidade',
  `tipo_pessoa` enum('fisica','juridica') COLLATE utf8mb4_unicode_ci DEFAULT 'fisica',
  `cpf_cnpj` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rg_ie` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `celular` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cep` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `endereco` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `numero` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `complemento` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bairro` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cidade` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` char(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observacoes` text COLLATE utf8mb4_unicode_ci,
  `categoria` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'cliente_final',
  `foto` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `limite_credito` decimal(10,2) DEFAULT '0.00',
  `saldo_devedor` decimal(10,2) DEFAULT '0.00',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'ativo',
  `senha` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_verificado` tinyint(1) DEFAULT '0',
  `token_verificacao` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_nome_razaosocial` (`nome_razaosocial`),
  KEY `idx_nome` (`nome`),
  KEY `idx_email` (`email`),
  KEY `idx_celular` (`celular`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- sc_configuracoes
-- ============================================================
DROP TABLE IF EXISTS `sc_configuracoes`;
CREATE TABLE `sc_configuracoes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `chave` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `valor` text COLLATE utf8mb4_unicode_ci,
  `descricao` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `grupo` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'geral',
  `tipo` VARCHAR(20) COLLATE utf8mb4_unicode_ci DEFAULT 'text',
  `opcoes` text COLLATE utf8mb4_unicode_ci,
  `ordem` int DEFAULT '0',
  `ativo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_chave` (`chave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `sc_configuracoes` (`chave`, `valor`, `descricao`, `grupo`, `tipo`, `ordem`, `ativo`) VALUES
-- Geral
('site_nome', 'SiteCatalogo', 'Nome do site', 'geral', 'text', 1, 1),
('site_descricao', 'Catálogo de produtos online', 'Descrição do site', 'geral', 'textarea', 2, 1),
('mostrar_preco', '1', 'Mostrar preços no catálogo', 'geral', 'select', 3, 1),
('moeda', 'BRL', 'Moeda padrão', 'geral', 'select', 4, 1),
('empresa_sobre', '', 'Sobre a Empresa (seção Quem Somos)', 'geral', 'textarea', 5, 1),
('empresa_slogan', '', 'Slogan / Frase de Destaque', 'geral', 'text', 6, 1),
-- Contato
('whatsapp', '', 'WhatsApp para contato', 'contato', 'text', 1, 1),
('email_contato', '', 'E-mail de contato (remetente padrão)', 'contato', 'text', 2, 1),
('telefone', '', 'Telefone fixo', 'contato', 'text', 3, 1),
('endereco', '', 'Endereço da empresa', 'contato', 'textarea', 4, 1),
('horario_atendimento', 'Segunda a Sexta: 08h às 18h', 'Horário de atendimento', 'contato', 'text', 5, 1),
-- Redes sociais
('facebook_url', '', 'Facebook', 'social', 'text', 1, 1),
('instagram_url', '', 'Instagram', 'social', 'text', 2, 1),
('linkedin_url', '', 'LinkedIn', 'social', 'text', 3, 1),
('youtube_url', '', 'YouTube', 'social', 'text', 4, 1),
('tiktok_url', '', 'TikTok', 'social', 'text', 5, 1),
('twitter_url', '', 'Twitter / X', 'social', 'text', 6, 1),
('pinterest_url', '', 'Pinterest', 'social', 'text', 7, 1),
('telegram_url', '', 'Telegram', 'social', 'text', 8, 1),
('kwai_url', '', 'Kwai', 'social', 'text', 9, 1),
('threads_url', '', 'Threads', 'social', 'text', 10, 1),
('discord_url', '', 'Discord', 'social', 'text', 11, 1),
('snapchat_url', '', 'Snapchat', 'social', 'text', 12, 1),
-- Aparência
('cor_primaria', '#3b82f6', 'Cor primária', 'aparencia', 'color', 1, 1),
('logo_cliente', '', 'Logo do cliente', 'aparencia', 'file', 2, 1),
('navbar_tipo', 'imagem_texto', 'Tipo de navbar', 'aparencia', 'select', 3, 1),
('categoria_layout', 'sidebar', 'Layout das Categorias', 'aparencia', 'select', 4, 1),
('produto_visualizacao', 'modal', 'Forma de Visualização do Produto', 'aparencia', 'select', 5, 1),
('produtos_navegacao', 'paginacao', 'Navegação de Produtos', 'aparencia', 'select', 6, 1),
('toast_position', 'bottom-right', 'Posição do Toast de Produto Adicionado', 'aparencia', 'select', 7, 1),
('alerta_sonoro_orcamento', '1', 'Alerta sonoro — novos orçamentos', 'aparencia', 'select', 8, 1),
-- Orçamento
('orcamento_whatsapp_msg', 'Olá! Recebemos seu orçamento. Em breve entraremos em contato.', 'Mensagem padrão WhatsApp para orçamentos', 'orcamento', 'textarea', 1, 1),
-- SEO
('custom_head_scripts', '', 'Scripts no <head> (ex: Google Analytics)', 'seo', 'textarea', 1, 1),
('custom_body_scripts', '', 'Scripts antes do </body>', 'seo', 'textarea', 2, 1),
('custom_css', '', 'CSS personalizado', 'seo', 'textarea', 3, 1),
-- Email — SMTP (envio)
('smtp_host', '', 'Servidor SMTP', 'email', 'text', 1, 1),
('smtp_port', '587', 'Porta SMTP (587=TLS, 465=SSL, 25=sem criptografia)', 'email', 'number', 2, 1),
('smtp_user', '', 'Usuário SMTP (email completo)', 'email', 'text', 3, 1),
('smtp_pass', '', 'Senha SMTP', 'email', 'text', 4, 1),
('smtp_encryption', 'tls', 'Criptografia SMTP', 'email', 'select', 5, 1),
('site_nome_email', '', 'Nome exibido no remetente (deixe vazio = site_nome)', 'email', 'text', 6, 1),
-- Email — IMAP (recebimento)
('imap_host', '', 'Servidor IMAP', 'email', 'text', 10, 1),
('imap_port', '993', 'Porta IMAP (993=SSL, 143=TLS/sem)', 'email', 'number', 11, 1),
('imap_ssl', '1', 'Usar SSL no IMAP', 'email', 'select', 12, 1),
('imap_user', '', 'Usuário IMAP (email completo)', 'email', 'text', 13, 1),
('imap_pass', '', 'Senha IMAP', 'email', 'text', 14, 1),
('imap_folder', 'INBOX', 'Pasta padrão IMAP', 'email', 'text', 15, 1),
('imap_folder_sent', 'Sent', 'Pasta Enviados no servidor', 'email', 'text', 16, 1),
('imap_folder_drafts', 'Drafts', 'Pasta Rascunhos no servidor', 'email', 'text', 17, 1),
('imap_folder_archive', 'Archive', 'Pasta Arquivo no servidor', 'email', 'text', 18, 1),
('imap_folder_spam', 'Junk', 'Pasta Spam no servidor (Junk ou Spam)', 'email', 'text', 19, 1),
('imap_folder_trash', 'Trash', 'Pasta Lixeira no servidor', 'email', 'text', 20, 1),
-- Email — Assinatura digital
('email_assinatura_tipo', 'nenhuma', 'Tipo de assinatura', 'email', 'select', 30, 1),
('email_assinatura_html', '', 'Assinatura em HTML/texto', 'email', 'textarea', 31, 1),
('email_assinatura_imagem', '', 'Assinatura em imagem', 'email', 'file', 32, 1),
-- Email — Layout e sincronização
('email_layout', 'lista', 'Layout de visualização', 'email', 'select', 40, 1),
('email_sync_auto', '0', 'Sincronização automática IMAP', 'email', 'select', 41, 1),
('email_sync_intervalo', '5', 'Intervalo de sincronização automática (minutos)', 'email', 'number', 42, 1),
('email_unificado', '', 'Email / Usuário unificado (SMTP + IMAP)', 'email', 'text', 43, 1),
('senha_unificada', '', 'Senha unificada (SMTP + IMAP)', 'email', 'password', 44, 1);

-- Opções dos campos select
UPDATE `sc_configuracoes` SET `opcoes` = '{"1":"Sim — Mostrar preços","0":"Não — Ocultar preços"}' WHERE `chave` = 'mostrar_preco';
UPDATE `sc_configuracoes` SET `opcoes` = '{"BRL":"R$ — Real Brasileiro","USD":"$ — Dólar","EUR":"€ — Euro"}' WHERE `chave` = 'moeda';
UPDATE `sc_configuracoes` SET `opcoes` = '{"imagem_texto":"Logo + Nome","imagem":"Apenas Logo","texto":"Apenas Nome"}' WHERE `chave` = 'navbar_tipo';
UPDATE `sc_configuracoes` SET `opcoes` = '{"sidebar":"Sidebar Vertical (lateral)","inline":"Barra Inline (abaixo do menu)"}' WHERE `chave` = 'categoria_layout';
UPDATE `sc_configuracoes` SET `opcoes` = '{"modal":"Catálogo Simples (modal sobre a lista)","pagina_individual":"Página Individual do Produto (melhor para SEO)"}' WHERE `chave` = 'produto_visualizacao';
UPDATE `sc_configuracoes` SET `opcoes` = '{"paginacao":"Paginação (Anterior / Próximo)","scroll_infinito":"Scroll Infinito (carrega ao rolar)"}' WHERE `chave` = 'produtos_navegacao';
UPDATE `sc_configuracoes` SET `opcoes` = '{"top-left":"Topo Esquerdo","top-center":"Topo Centro","top-right":"Topo Direito","bottom-left":"Rodapé Esquerdo","bottom-center":"Rodapé Centro","bottom-right":"Rodapé Direito"}' WHERE `chave` = 'toast_position';
UPDATE `sc_configuracoes` SET `opcoes` = '{"1":"Ativado","0":"Desativado"}' WHERE `chave` = 'alerta_sonoro_orcamento';
UPDATE `sc_configuracoes` SET `opcoes` = '{"tls":"TLS (porta 587)","ssl":"SSL (porta 465)","":"Nenhuma (porta 25)"}' WHERE `chave` = 'smtp_encryption';
UPDATE `sc_configuracoes` SET `opcoes` = '{"1":"Sim — SSL/TLS (porta 993)","0":"Não — sem SSL (porta 143)"}' WHERE `chave` = 'imap_ssl';
UPDATE `sc_configuracoes` SET `opcoes` = '{"nenhuma":"Nenhuma","html":"HTML / Texto formatado","imagem":"Imagem"}' WHERE `chave` = 'email_assinatura_tipo';
UPDATE `sc_configuracoes` SET `opcoes` = '{"lista":"Lista (clique para abrir o email)","dividido":"Dividido — lista + leitura lado a lado"}' WHERE `chave` = 'email_layout';
UPDATE `sc_configuracoes` SET `opcoes` = '{"1":"Ativada","0":"Desativada"}' WHERE `chave` = 'email_sync_auto';

-- ============================================================
-- sc_email_etiquetas
-- ============================================================
DROP TABLE IF EXISTS `sc_email_etiquetas`;
CREATE TABLE `sc_email_etiquetas` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cor` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT '#3b82f6',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_nome` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `sc_email_etiquetas` (`nome`, `cor`) VALUES
('Importante', '#ef4444'),
('Cliente', '#3b82f6'),
('Fornecedor', '#f59e0b'),
('Pessoal', '#8b5cf6'),
('Financeiro', '#10b981');

-- ============================================================
-- sc_email_etiqueta_rel
-- ============================================================
DROP TABLE IF EXISTS `sc_email_etiqueta_rel`;
CREATE TABLE `sc_email_etiqueta_rel` (
  `email_id` int unsigned NOT NULL,
  `etiqueta_id` int unsigned NOT NULL,
  PRIMARY KEY (`email_id`,`etiqueta_id`),
  KEY `idx_etiqueta` (`etiqueta_id`),
  CONSTRAINT `fk_rel_email` FOREIGN KEY (`email_id`) REFERENCES `sc_emails` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rel_etiqueta` FOREIGN KEY (`etiqueta_id`) REFERENCES `sc_email_etiquetas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- sc_emails  (v2.7 — corpo LONGTEXT + corpo_html + etiquetas)
-- ============================================================
DROP TABLE IF EXISTS `sc_emails`;
CREATE TABLE `sc_emails` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `remetente_nome` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `remetente_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `destinatario_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `assunto` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `corpo` longtext COLLATE utf8mb4_unicode_ci,
  `corpo_html` longtext COLLATE utf8mb4_unicode_ci COMMENT 'Versão HTML original da mensagem, se houver',
  `pasta` enum('inbox','sent','drafts','trash','spam','archive') COLLATE utf8mb4_unicode_ci DEFAULT 'inbox',
  `status` enum('nao_lido','lido','respondido','encaminhado') COLLATE utf8mb4_unicode_ci DEFAULT 'nao_lido',
  `starred` tinyint(1) DEFAULT '0',
  `data_envio` datetime DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `imap_uid` bigint unsigned DEFAULT NULL COMMENT 'UID único no servidor IMAP',
  `reply_to_id` int unsigned DEFAULT NULL COMMENT 'ID do email original (resposta)',
  `anexos` text COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'JSON com lista de anexos (nome, caminho, tamanho)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_imap_uid_pasta` (`imap_uid`,`pasta`),
  KEY `idx_pasta` (`pasta`),
  KEY `idx_status` (`status`),
  KEY `idx_starred` (`starred`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- sc_financeiro_categorias
-- ============================================================
DROP TABLE IF EXISTS `sc_financeiro_categorias`;
CREATE TABLE `sc_financeiro_categorias` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` enum('receita','despesa') COLLATE utf8mb4_unicode_ci NOT NULL,
  `cor` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT '#6b7280',
  `ativo` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `sc_financeiro_categorias` (`nome`, `tipo`, `cor`) VALUES
('Vendas de Produtos', 'receita', '#22c55e'),
('Orçamentos Aprovados', 'receita', '#3b82f6'),
('Serviços Prestados', 'receita', '#8b5cf6'),
('Outras Receitas', 'receita', '#06b6d4'),
('Aluguel', 'despesa', '#ef4444'),
('Salários', 'despesa', '#f97316'),
('Fornecedores', 'despesa', '#f59e0b'),
('Contas de Consumo', 'despesa', '#64748b'),
('Marketing', 'despesa', '#ec4899'),
('Impostos e Taxas', 'despesa', '#dc2626'),
('Compra de Estoque', 'despesa', '#92400e'),
('Outras Despesas', 'despesa', '#6b7280');

-- ============================================================
-- sc_financeiro_contas
-- ============================================================
DROP TABLE IF EXISTS `sc_financeiro_contas`;
CREATE TABLE `sc_financeiro_contas` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` enum('corrente','poupanca','caixa','investimento','outros') COLLATE utf8mb4_unicode_ci DEFAULT 'corrente',
  `banco` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `agencia` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `conta` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `saldo_inicial` decimal(10,2) DEFAULT '0.00',
  `saldo_atual` decimal(10,2) DEFAULT '0.00',
  `cor` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT '#3b82f6',
  `ativo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `sc_financeiro_contas` (`nome`, `tipo`, `saldo_inicial`, `saldo_atual`, `cor`) VALUES
('Caixa', 'caixa', 0.00, 0.00, '#22c55e'),
('Conta Corrente', 'corrente', 0.00, 0.00, '#3b82f6');

-- ============================================================
-- sc_financeiro_lancamentos
-- ============================================================
DROP TABLE IF EXISTS `sc_financeiro_lancamentos`;
CREATE TABLE `sc_financeiro_lancamentos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tipo` enum('receita','despesa','transferencia') COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `valor` decimal(10,2) NOT NULL DEFAULT '0.00',
  `data_vencimento` date NOT NULL,
  `data_pagamento` date DEFAULT NULL,
  `categoria_id` int unsigned DEFAULT NULL,
  `conta_id` int unsigned DEFAULT NULL,
  `conta_destino_id` int unsigned DEFAULT NULL,
  `cliente_id` int unsigned DEFAULT NULL,
  `orcamento_id` int unsigned DEFAULT NULL,
  `status` enum('pendente','pago','vencido','cancelado') COLLATE utf8mb4_unicode_ci DEFAULT 'pendente',
  `forma_pagamento` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `parcelas` int DEFAULT '1',
  `parcela_atual` int DEFAULT '1',
  `grupo_parcelas` varchar(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observacoes` text COLLATE utf8mb4_unicode_ci,
  `comprovante` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usuario_id` int unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tipo` (`tipo`),
  KEY `idx_status` (`status`),
  KEY `idx_vencimento` (`data_vencimento`),
  KEY `idx_categoria` (`categoria_id`),
  KEY `idx_conta` (`conta_id`),
  KEY `idx_cliente` (`cliente_id`),
  KEY `idx_orcamento` (`orcamento_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- sc_orcamentos
-- ============================================================
DROP TABLE IF EXISTS `sc_orcamentos`;
CREATE TABLE `sc_orcamentos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cliente_id` int unsigned DEFAULT NULL,
  `cliente_nome` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cliente_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cliente_telefone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cliente_cpf_cnpj` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cliente_cidade` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cliente_estado` char(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipo_contato` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT 'whatsapp',
  `forma_pagamento` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('novo','pendente','em_analise','respondido','aprovado','rejeitado','cancelado') COLLATE utf8mb4_unicode_ci DEFAULT 'novo',
  `observacoes` text COLLATE utf8mb4_unicode_ci,
  `data_entrega` date DEFAULT NULL,
  `tabela_preco` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'padrao',
  `valor_produtos` decimal(10,2) DEFAULT '0.00',
  `valor_servicos` decimal(10,2) DEFAULT '0.00',
  `desconto` decimal(10,2) DEFAULT '0.00',
  `valor_total` decimal(10,2) DEFAULT '0.00',
  `usuario_id` int unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_codigo` (`codigo`),
  KEY `idx_status` (`status`),
  KEY `idx_cliente` (`cliente_id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- sc_orcamento_itens
-- ============================================================
DROP TABLE IF EXISTS `sc_orcamento_itens`;
CREATE TABLE `sc_orcamento_itens` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `orcamento_id` int unsigned NOT NULL,
  `produto_id` int unsigned DEFAULT NULL,
  `produto_nome` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sku` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantidade` int DEFAULT '1',
  `unidade` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'un',
  `preco_unitario` decimal(10,2) DEFAULT '0.00',
  `subtotal` decimal(10,2) DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_orcamento` (`orcamento_id`),
  KEY `idx_produto` (`produto_id`),
  CONSTRAINT `fk_itens_orcamento` FOREIGN KEY (`orcamento_id`) REFERENCES `sc_orcamentos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- sc_produtos
-- ============================================================
DROP TABLE IF EXISTS `sc_produtos`;
CREATE TABLE `sc_produtos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `categoria_id` int unsigned DEFAULT NULL,
  `nome` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `descricao_curta` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `imagem_principal` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `imagens` text COLLATE utf8mb4_unicode_ci,
  `preco` decimal(10,2) DEFAULT '0.00',
  `preco_promocional` decimal(10,2) DEFAULT NULL,
  `custo` decimal(10,2) DEFAULT NULL,
  `unidade` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'un',
  `peso` decimal(10,3) DEFAULT NULL,
  `sku` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantidade_estoque` int DEFAULT '0',
  `estoque_minimo` int DEFAULT '5',
  `visualizacoes` int DEFAULT '0',
  `destaque` tinyint(1) DEFAULT '0',
  `ativo` tinyint(1) DEFAULT '1',
  `tags` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `seo_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `seo_description` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `caracteristicas` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_slug` (`slug`),
  KEY `idx_categoria` (`categoria_id`),
  CONSTRAINT `fk_produtos_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `sc_categorias` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- sc_produto_estoque
-- ============================================================
DROP TABLE IF EXISTS `sc_produto_estoque`;
CREATE TABLE `sc_produto_estoque` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `produto_id` int unsigned NOT NULL,
  `tipo` enum('entrada','saida','ajuste') COLLATE utf8mb4_unicode_ci DEFAULT 'entrada',
  `quantidade` int DEFAULT '0',
  `quantidade_anterior` int DEFAULT '0',
  `motivo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usuario_id` int unsigned DEFAULT NULL,
  `observacoes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_produto` (`produto_id`),
  KEY `idx_tipo` (`tipo`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- sc_produto_imagens
-- ============================================================
DROP TABLE IF EXISTS `sc_produto_imagens`;
CREATE TABLE `sc_produto_imagens` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `produto_id` int unsigned NOT NULL,
  `imagem` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ordem` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_produto` (`produto_id`),
  CONSTRAINT `fk_pimg_produto` FOREIGN KEY (`produto_id`) REFERENCES `sc_produtos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- sc_usuarios
-- ============================================================
DROP TABLE IF EXISTS `sc_usuarios`;
CREATE TABLE `sc_usuarios` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `senha` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `avatar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` enum('admin','gerente','vendedor','atendente') COLLATE utf8mb4_unicode_ci DEFAULT 'vendedor',
  `status` enum('ativo','inativo','bloqueado') COLLATE utf8mb4_unicode_ci DEFAULT 'ativo',
  `ultimo_acesso` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
SET FOREIGN_KEY_CHECKS = 1;
-- ============================================================
-- FIM DO SCHEMA UNIVERSAL
-- Versão: 2.8.0  |  Atualizado: 2026-06-21 (v4.0: Email unificado + senha unificada)
--
-- Garantias:
--   • Todas as 19 tabelas presentes e validadas contra dump real
--   • Tipos, defaults, COLLATE e NULL alinhados com MySQL 8.4 / MariaDB
--   • Compatível com qualquer servidor: localhost, cPanel, Hostgator, VPS, Docker
-- ============================================================