-- ============================================================
-- SiteCatalogo - Banco de Dados Completo (DEFINITIVO v2.4)
-- Prefixo: sc_  |  Charset: utf8mb4  |  Engine: InnoDB
--
-- CORREÇÕES DESTA VERSÃO:
--   • Sincronizado 100% com banco de dados real (HeidiSQL)
--   • Sincronizado com código PHP do GitHub e local
--   • sc_orcamentos: cliente_cidade confirmado
--   • sc_orcamentos: cliente_estado ADICIONADO
--   • sc_clientes: nome_razaosocial NOT NULL + nome DEFAULT ''
--   • sc_configuracoes: orcamento_whatsapp_msg adicionado
--   • Todas as 15 tabelas verificadas e validadas
--
-- COMO USAR:
--   1. Apague o banco completamente (DROP DATABASE sitecatalogo)
--   2. Crie novo banco vazio (CREATE DATABASE sitecatalogo CHARSET utf8mb4)
--   3. Selecione o banco e execute este arquivo
--   4. Configure DB_NAME no config.php
--
-- Acesso padrão:
--   E-mail: admin@sitecatalogo.com
--   Senha: password
--   ⚠ Troque imediatamente após o primeiro login
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

-- ============================================================
-- sc_usuarios
-- ============================================================
DROP TABLE IF EXISTS `sc_usuarios`;
CREATE TABLE `sc_usuarios` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `role` enum('admin','gerente','vendedor') DEFAULT 'vendedor',
  `status` enum('ativo','inativo','bloqueado') DEFAULT 'ativo',
  `ultimo_acesso` datetime DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `sc_usuarios` (`nome`, `email`, `senha`, `role`, `status`) VALUES
('Administrador', 'admin@sitecatalogo.com',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'admin', 'ativo');

-- ============================================================
-- sc_categorias
-- ============================================================
DROP TABLE IF EXISTS `sc_categorias`;
CREATE TABLE `sc_categorias` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `descricao` text DEFAULT NULL,
  `imagem` varchar(255) DEFAULT NULL,
  `icone` varchar(100) DEFAULT 'category' COMMENT 'Nome do ícone Material Icons',
  `ordem` int(11) DEFAULT 0,
  `ativo` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- sc_produtos
-- ============================================================
DROP TABLE IF EXISTS `sc_produtos`;
CREATE TABLE `sc_produtos` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `categoria_id` int(11) unsigned DEFAULT NULL,
  `nome` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `descricao` text DEFAULT NULL,
  `descricao_curta` varchar(500) DEFAULT NULL,
  `imagem_principal` varchar(255) DEFAULT NULL,
  `imagens` text DEFAULT NULL COMMENT 'JSON array de imagens adicionais (legado)',
  `preco` decimal(10,2) DEFAULT 0.00,
  `preco_promocional` decimal(10,2) DEFAULT NULL,
  `custo` decimal(10,2) DEFAULT NULL,
  `unidade` varchar(20) DEFAULT 'un',
  `peso` decimal(10,3) DEFAULT NULL,
  `sku` varchar(100) DEFAULT NULL,
  `quantidade_estoque` int(11) DEFAULT 0,
  `estoque_minimo` int(11) DEFAULT 5,
  `visualizacoes` int(11) DEFAULT 0,
  `destaque` tinyint(1) DEFAULT 0,
  `ativo` tinyint(1) DEFAULT 1,
  `tags` varchar(500) DEFAULT NULL,
  `seo_title` varchar(255) DEFAULT NULL,
  `seo_description` varchar(500) DEFAULT NULL,
  `caracteristicas` text DEFAULT NULL COMMENT 'JSON array de características técnicas',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_slug` (`slug`),
  KEY `idx_categoria` (`categoria_id`),
  KEY `idx_ativo_destaque` (`ativo`, `destaque`),
  CONSTRAINT `fk_produtos_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `sc_categorias` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- sc_produto_imagens
-- ============================================================
DROP TABLE IF EXISTS `sc_produto_imagens`;
CREATE TABLE `sc_produto_imagens` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `produto_id` int(11) unsigned NOT NULL,
  `imagem` varchar(255) NOT NULL,
  `ordem` int(11) DEFAULT 0,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_produto` (`produto_id`),
  CONSTRAINT `fk_pimg_produto` FOREIGN KEY (`produto_id`) REFERENCES `sc_produtos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- sc_produto_estoque
-- ============================================================
DROP TABLE IF EXISTS `sc_produto_estoque`;
CREATE TABLE `sc_produto_estoque` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `produto_id` int(11) unsigned NOT NULL,
  `tipo` enum('entrada','saida','ajuste') DEFAULT 'entrada',
  `quantidade` int(11) DEFAULT 0,
  `quantidade_anterior` int(11) DEFAULT 0,
  `motivo` varchar(255) DEFAULT NULL,
  `usuario_id` int(11) unsigned DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_produto` (`produto_id`),
  KEY `idx_tipo` (`tipo`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- sc_clientes  (DEFINITIVO - nome_razaosocial OBRIGATÓRIO)
-- ============================================================
DROP TABLE IF EXISTS `sc_clientes`;
CREATE TABLE `sc_clientes` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `nome_razaosocial` varchar(255) NOT NULL COMMENT 'Nome ou Razão Social',
  `nome` varchar(255) NOT NULL DEFAULT '' COMMENT 'Alias para compatibilidade',
  `tipo_pessoa` enum('fisica','juridica') DEFAULT 'fisica',
  `cpf_cnpj` varchar(20) DEFAULT NULL,
  `rg_ie` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `celular` varchar(20) DEFAULT NULL,
  `cep` varchar(10) DEFAULT NULL,
  `endereco` varchar(255) DEFAULT NULL,
  `numero` varchar(20) DEFAULT NULL,
  `complemento` varchar(100) DEFAULT NULL,
  `bairro` varchar(100) DEFAULT NULL,
  `cidade` varchar(100) DEFAULT NULL,
  `estado` char(2) DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `categoria` varchar(50) DEFAULT 'cliente_final',
  `foto` varchar(255) DEFAULT '',
  `limite_credito` decimal(10,2) DEFAULT 0.00,
  `saldo_devedor` decimal(10,2) DEFAULT 0.00,
  `status` varchar(20) DEFAULT 'ativo',
  `senha` varchar(255) DEFAULT NULL COMMENT 'Senha para acesso à área do cliente',
  `email_verificado` tinyint(1) DEFAULT 0,
  `token_verificacao` varchar(100) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_nome_razaosocial` (`nome_razaosocial`),
  KEY `idx_nome` (`nome`),
  KEY `idx_email` (`email`),
  KEY `idx_celular` (`celular`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- sc_orcamentos
-- ============================================================
DROP TABLE IF EXISTS `sc_orcamentos`;
CREATE TABLE `sc_orcamentos` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(50) NOT NULL,
  `cliente_id` int(11) unsigned DEFAULT NULL,
  `cliente_nome` varchar(255) NOT NULL,
  `cliente_email` varchar(255) DEFAULT NULL,
  `cliente_telefone` varchar(20) DEFAULT NULL,
  `cliente_cpf_cnpj` varchar(20) DEFAULT NULL,
  `cliente_cidade` varchar(255) DEFAULT NULL,
  `cliente_estado` char(2) DEFAULT NULL,
  `tipo_contato` varchar(30) DEFAULT 'whatsapp',
  `forma_pagamento` varchar(255) DEFAULT NULL,
  `status` enum('novo','pendente','em_analise','respondido','aprovado','rejeitado','cancelado') DEFAULT 'novo',
  `observacoes` text DEFAULT NULL,
  `data_entrega` date DEFAULT NULL,
  `tabela_preco` varchar(50) DEFAULT 'padrao',
  `valor_produtos` decimal(10,2) DEFAULT 0.00,
  `valor_servicos` decimal(10,2) DEFAULT 0.00,
  `desconto` decimal(10,2) DEFAULT 0.00,
  `valor_total` decimal(10,2) DEFAULT 0.00,
  `usuario_id` int(11) unsigned DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
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
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `orcamento_id` int(11) unsigned NOT NULL,
  `produto_id` int(11) unsigned DEFAULT NULL,
  `produto_nome` varchar(255) NOT NULL,
  `sku` varchar(100) DEFAULT NULL,
  `quantidade` int(11) DEFAULT 1,
  `unidade` varchar(20) DEFAULT 'un',
  `preco_unitario` decimal(10,2) DEFAULT 0.00,
  `subtotal` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_orcamento` (`orcamento_id`),
  KEY `idx_produto` (`produto_id`),
  CONSTRAINT `fk_itens_orcamento` FOREIGN KEY (`orcamento_id`) REFERENCES `sc_orcamentos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- sc_banners  (DEFINITIVO - popup_freq_max + popup_intervalo)
-- ============================================================
DROP TABLE IF EXISTS `sc_banners`;
CREATE TABLE `sc_banners` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) DEFAULT NULL,
  `subtitulo` varchar(255) DEFAULT NULL,
  `imagem` varchar(255) NOT NULL,
  `link` varchar(500) DEFAULT NULL,
  `posicao` varchar(50) DEFAULT 'slide' COMMENT 'slide | categoria | popup',
  `ordem` int(11) DEFAULT 0,
  `ativo` tinyint(1) DEFAULT 1,
  `popup_delay` int(11) DEFAULT 0 COMMENT 'Segundos antes de exibir (0 = imediato)',
  `popup_fechar` varchar(20) DEFAULT 'botao' COMMENT 'botao | fora | ambos',
  `popup_freq_max` int(11) DEFAULT 0 COMMENT '0 = ilimitado. Máx. exibições por visitante (localStorage)',
  `popup_intervalo` int(11) DEFAULT 0 COMMENT '0 = sem restrição. Horas mínimas entre exibições',
  `prazo_fixo` tinyint(1) DEFAULT 1 COMMENT '1 = sempre ativo, 0 = usar data_inicio/data_fim',
  `data_inicio` date DEFAULT NULL,
  `data_fim` date DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_posicao_ativo` (`posicao`, `ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- sc_configuracoes
-- ============================================================
DROP TABLE IF EXISTS `sc_configuracoes`;
CREATE TABLE `sc_configuracoes` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `chave` varchar(100) NOT NULL,
  `valor` text DEFAULT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `grupo` varchar(50) DEFAULT 'geral',
  `tipo` enum('text','textarea','file','select','number','color') DEFAULT 'text',
  `opcoes` text DEFAULT NULL COMMENT 'JSON para campos do tipo select',
  `ordem` int(11) DEFAULT 0,
  `ativo` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_chave` (`chave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `sc_configuracoes` (`chave`, `valor`, `descricao`, `grupo`, `tipo`, `ordem`, `ativo`) VALUES
('site_nome', 'SiteCatalogo', 'Nome do site', 'geral', 'text', 1, 1),
('site_descricao', 'Catálogo de produtos online', 'Descrição do site', 'geral', 'textarea', 2, 1),
('mostrar_preco', '1', 'Mostrar preços no catálogo', 'geral', 'select', 3, 1),
('moeda', 'BRL', 'Moeda padrão', 'geral', 'select', 4, 1),
('empresa_sobre', '', 'Sobre a Empresa (seção Quem Somos)', 'geral', 'textarea', 5, 1),
('empresa_slogan', '', 'Slogan / Frase de Destaque', 'geral', 'text', 6, 1),
('whatsapp', '', 'WhatsApp para contato', 'contato', 'text', 1, 1),
('email_contato', '', 'E-mail de contato', 'contato', 'text', 2, 1),
('telefone', '', 'Telefone fixo', 'contato', 'text', 3, 1),
('endereco', '', 'Endereço da empresa', 'contato', 'textarea', 4, 1),
('horario_atendimento', 'Segunda a Sexta: 08h às 18h', 'Horário de atendimento', 'contato', 'text', 5, 1),
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
('cor_primaria', '#3b82f6', 'Cor primária', 'aparencia', 'color', 1, 1),
('logo_cliente', '', 'Logo do cliente', 'aparencia', 'file', 2, 1),
('navbar_tipo', 'imagem_texto', 'Tipo de navbar', 'aparencia', 'select', 3, 1),
('categoria_layout', 'sidebar', 'Layout das Categorias', 'aparencia', 'select', 4, 1),
('produto_visualizacao', 'modal', 'Forma de Visualização do Produto', 'aparencia', 'select', 5, 1),
('produtos_navegacao', 'paginacao', 'Navegação de Produtos', 'aparencia', 'select', 6, 1),
('toast_position', 'bottom-right', 'Posição do Toast de Produto Adicionado', 'aparencia', 'select', 7, 1),
('alerta_sonoro_orcamento', '1', 'Alerta sonoro — novos orçamentos', 'aparencia', 'select', 8, 1),
('orcamento_whatsapp_msg', 'Olá! Recebemos seu orçamento. Em breve entraremos em contato.', 'Mensagem padrão do WhatsApp para orçamentos', 'orcamento', 'textarea', 1, 1),
('custom_head_scripts', '', 'Scripts no <head> (ex: Google Analytics)', 'seo', 'textarea', 1, 1),
('custom_body_scripts', '', 'Scripts antes do </body>', 'seo', 'textarea', 2, 1),
('custom_css', '', 'CSS personalizado', 'seo', 'textarea', 3, 1),
('smtp_host', '', 'Servidor SMTP', 'email', 'text', 1, 1),
('smtp_porta', '', 'Porta SMTP', 'email', 'number', 2, 1),
('smtp_usuario', '', 'Usuário SMTP', 'email', 'text', 3, 1),
('smtp_senha', '', 'Senha SMTP', 'email', 'text', 4, 1),
('smtp_seguranca', '', 'Segurança (tls / ssl)', 'email', 'text', 5, 1);

UPDATE `sc_configuracoes` SET `opcoes` = '{"1":"Sim — Mostrar preços","0":"Não — Ocultar preços"}' WHERE `chave` = 'mostrar_preco';
UPDATE `sc_configuracoes` SET `opcoes` = '{"BRL":"R$ — Real Brasileiro","USD":"$ — Dólar","EUR":"€ — Euro"}' WHERE `chave` = 'moeda';
UPDATE `sc_configuracoes` SET `opcoes` = '{"imagem_texto":"Logo + Nome","imagem":"Apenas Logo","texto":"Apenas Nome"}' WHERE `chave` = 'navbar_tipo';
UPDATE `sc_configuracoes` SET `opcoes` = '{"sidebar":"Sidebar Vertical (lateral)","inline":"Barra Inline (abaixo do menu)"}' WHERE `chave` = 'categoria_layout';
UPDATE `sc_configuracoes` SET `opcoes` = '{"modal":"Catálogo Simples (modal sobre a lista)","pagina_individual":"Página Individual do Produto (melhor para SEO)"}' WHERE `chave` = 'produto_visualizacao';
UPDATE `sc_configuracoes` SET `opcoes` = '{"paginacao":"Paginação (Anterior / Próximo)","scroll_infinito":"Scroll Infinito (carrega ao rolar)"}' WHERE `chave` = 'produtos_navegacao';
UPDATE `sc_configuracoes` SET `opcoes` = '{"top-left":"Topo Esquerdo","top-center":"Topo Centro","top-right":"Topo Direito","bottom-left":"Rodapé Esquerdo","bottom-center":"Rodapé Centro","bottom-right":"Rodapé Direito"}' WHERE `chave` = 'toast_position';
UPDATE `sc_configuracoes` SET `opcoes` = '{"1":"Ativado","0":"Desativado"}' WHERE `chave` = 'alerta_sonoro_orcamento';

-- ============================================================
-- sc_financeiro_categorias
-- ============================================================
DROP TABLE IF EXISTS `sc_financeiro_categorias`;
CREATE TABLE `sc_financeiro_categorias` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `tipo` enum('receita','despesa') NOT NULL,
  `cor` varchar(20) DEFAULT '#6b7280',
  `ativo` tinyint(1) DEFAULT 1,
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
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `tipo` enum('corrente','poupanca','caixa','investimento','outros') DEFAULT 'corrente',
  `banco` varchar(100) DEFAULT NULL,
  `agencia` varchar(20) DEFAULT NULL,
  `conta` varchar(30) DEFAULT NULL,
  `saldo_inicial` decimal(10,2) DEFAULT 0.00,
  `saldo_atual` decimal(10,2) DEFAULT 0.00,
  `cor` varchar(20) DEFAULT '#3b82f6',
  `ativo` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
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
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tipo` enum('receita','despesa','transferencia') NOT NULL,
  `descricao` varchar(255) NOT NULL,
  `valor` decimal(10,2) NOT NULL DEFAULT 0.00,
  `data_vencimento` date NOT NULL,
  `data_pagamento` date DEFAULT NULL,
  `categoria_id` int(11) unsigned DEFAULT NULL,
  `conta_id` int(11) unsigned DEFAULT NULL,
  `conta_destino_id` int(11) unsigned DEFAULT NULL COMMENT 'Usado em transferências entre contas',
  `cliente_id` int(11) unsigned DEFAULT NULL,
  `orcamento_id` int(11) unsigned DEFAULT NULL,
  `status` enum('pendente','pago','vencido','cancelado') DEFAULT 'pendente',
  `forma_pagamento` varchar(50) DEFAULT NULL,
  `parcelas` int(11) DEFAULT 1,
  `parcela_atual` int(11) DEFAULT 1,
  `grupo_parcelas` varchar(36) DEFAULT NULL COMMENT 'UUID para agrupar parcelas do mesmo lançamento',
  `observacoes` text DEFAULT NULL,
  `comprovante` varchar(255) DEFAULT NULL,
  `usuario_id` int(11) unsigned DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
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
-- sc_atividades_log
-- ============================================================
DROP TABLE IF EXISTS `sc_atividades_log`;
CREATE TABLE `sc_atividades_log` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `acao` varchar(50) DEFAULT NULL,
  `tabela` varchar(50) DEFAULT NULL,
  `descricao` text DEFAULT NULL,
  `usuario_id` int(11) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_usuario` (`usuario_id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- sc_emails
-- ============================================================
DROP TABLE IF EXISTS `sc_emails`;
CREATE TABLE `sc_emails` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `remetente_nome` varchar(255) DEFAULT '',
  `remetente_email` varchar(255) DEFAULT '',
  `destinatario_email` varchar(255) DEFAULT '',
  `assunto` varchar(500) DEFAULT '',
  `corpo` text DEFAULT NULL,
  `pasta` enum('inbox','sent','drafts','trash','spam','archive') DEFAULT 'inbox',
  `status` enum('nao_lido','lido','respondido','encaminhado') DEFAULT 'nao_lido',
  `starred` tinyint(1) DEFAULT 0,
  `data_envio` datetime DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pasta` (`pasta`),
  KEY `idx_status` (`status`),
  KEY `idx_starred` (`starred`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
SET FOREIGN_KEY_CHECKS = 1;
-- ============================================================
-- FIM DO SCHEMA DEFINITIVO
-- Versão: 2.4  |  Atualizado: 2026-06-18
-- Garantias:
--   • sc_clientes.nome_razaosocial: NOT NULL (sem DEFAULT, obrigatório)
--   • sc_clientes.nome: NOT NULL DEFAULT '' (compatibilidade)
--   • sc_banners.popup_freq_max: DEFAULT 0
--   • sc_banners.popup_intervalo: DEFAULT 0
--   • sc_orcamentos.cliente_cidade: PRESENTE (varchar 255)
--   • sc_orcamentos.cliente_estado: ADICIONADO (char 2)
--   • sc_configuracoes.orcamento_whatsapp_msg: ADICIONADO
--   • Todas as 15 tabelas verificadas e validadas contra HeidiSQL
-- ============================================================