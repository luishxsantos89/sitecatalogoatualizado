-- ============================================================
-- SiteCatalogo2 - Banco de Dados Completo
-- Prefixo: sc_  |  Charset: utf8mb4
--
-- IMPORTANTE: Crie o banco de dados manualmente antes de rodar
-- este arquivo, depois selecione-o no HeidiSQL ou phpMyAdmin.
-- O nome do banco fica definido no config.php (DB_NAME).
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------
-- sc_usuarios
-- --------------------------------------------------------
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
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- senha: password (hash bcrypt)
INSERT INTO `sc_usuarios` (`nome`, `email`, `senha`, `role`, `status`) VALUES
('Administrador', 'admin@sitecatalogo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'ativo');

-- --------------------------------------------------------
-- sc_categorias
-- --------------------------------------------------------
DROP TABLE IF EXISTS `sc_categorias`;
CREATE TABLE `sc_categorias` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `descricao` text,
  `imagem` varchar(255) DEFAULT NULL,
  `icone` varchar(100) DEFAULT 'category',
  `ordem` int(11) DEFAULT 0,
  `ativo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- sc_produtos
-- --------------------------------------------------------
DROP TABLE IF EXISTS `sc_produtos`;
CREATE TABLE `sc_produtos` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `categoria_id` int(11) unsigned DEFAULT NULL,
  `nome` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `descricao` text,
  `descricao_curta` varchar(500) DEFAULT NULL,
  `imagem_principal` varchar(255) DEFAULT NULL,
  `imagens` text COMMENT 'JSON array de imagens adicionais',
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
  `caracteristicas` text COMMENT 'JSON array de características',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `categoria_id` (`categoria_id`),
  CONSTRAINT `fk_produtos_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `sc_categorias` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- sc_produto_imagens
-- --------------------------------------------------------
DROP TABLE IF EXISTS `sc_produto_imagens`;
CREATE TABLE `sc_produto_imagens` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `produto_id` int(11) unsigned NOT NULL,
  `imagem` varchar(255) NOT NULL,
  `ordem` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `produto_id` (`produto_id`),
  CONSTRAINT `fk_pimg_produto` FOREIGN KEY (`produto_id`) REFERENCES `sc_produtos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- sc_produto_estoque (histórico)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `sc_produto_estoque`;
CREATE TABLE `sc_produto_estoque` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `produto_id` int(11) unsigned NOT NULL,
  `tipo` enum('entrada','saida','ajuste') DEFAULT 'entrada',
  `quantidade` int(11) DEFAULT 0,
  `quantidade_anterior` int(11) DEFAULT 0,
  `motivo` varchar(255) DEFAULT NULL,
  `usuario_id` int(11) unsigned DEFAULT NULL,
  `observacoes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `produto_id` (`produto_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- sc_banners  (v2 — inclui posicao slide/categoria/popup e prazo)
-- --------------------------------------------------------
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
  -- Configurações de Popup
  `popup_delay` int(11) NOT NULL DEFAULT 0 COMMENT 'Segundos para exibir o popup (0 = imediato)',
  `popup_fechar` varchar(20) NOT NULL DEFAULT 'botao' COMMENT 'botao | fora | ambos',
  -- Prazo de exibição
  `prazo_fixo` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 = sem prazo, 0 = usar data_inicio e data_fim',
  `data_inicio` date DEFAULT NULL COMMENT 'Data de início de exibição',
  `data_fim` date DEFAULT NULL COMMENT 'Data de fim de exibição',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- sc_clientes
-- --------------------------------------------------------
DROP TABLE IF EXISTS `sc_clientes`;
CREATE TABLE `sc_clientes` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `nome_razaosocial` varchar(255) NOT NULL,
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
  `observacoes` text,
  `categoria` varchar(50) DEFAULT 'cliente_final',
  `foto` varchar(255) DEFAULT '',
  `limite_credito` decimal(10,2) DEFAULT 0.00,
  `saldo_devedor` decimal(10,2) DEFAULT 0.00,
  `status` varchar(20) DEFAULT 'ativo',
  `senha` varchar(255) DEFAULT NULL COMMENT 'Senha para acesso à área do cliente',
  `email_verificado` tinyint(1) DEFAULT 0,
  `token_verificacao` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `nome_razaosocial` (`nome_razaosocial`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- sc_orcamentos
-- --------------------------------------------------------
DROP TABLE IF EXISTS `sc_orcamentos`;
CREATE TABLE `sc_orcamentos` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(50) NOT NULL,
  `cliente_id` int(11) unsigned DEFAULT NULL,
  `cliente_nome` varchar(255) NOT NULL,
  `cliente_email` varchar(255) DEFAULT NULL,
  `cliente_telefone` varchar(20) DEFAULT NULL,
  `cliente_cpf_cnpj` varchar(20) DEFAULT NULL,
  `tipo_contato` varchar(30) DEFAULT 'whatsapp',
  `forma_pagamento` varchar(255) DEFAULT NULL,
  `status` enum('novo','pendente','em_analise','respondido','aprovado','rejeitado','cancelado') DEFAULT 'novo',
  `observacoes` text,
  `data_entrega` date DEFAULT NULL,
  `tabela_preco` varchar(50) DEFAULT 'padrao',
  `valor_produtos` decimal(10,2) DEFAULT 0.00,
  `valor_servicos` decimal(10,2) DEFAULT 0.00,
  `desconto` decimal(10,2) DEFAULT 0.00,
  `valor_total` decimal(10,2) DEFAULT 0.00,
  `usuario_id` int(11) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo` (`codigo`),
  KEY `status` (`status`),
  KEY `cliente_id` (`cliente_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- sc_orcamento_itens
-- --------------------------------------------------------
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
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `orcamento_id` (`orcamento_id`),
  CONSTRAINT `fk_itens_orcamento` FOREIGN KEY (`orcamento_id`) REFERENCES `sc_orcamentos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- sc_configuracoes
-- --------------------------------------------------------
DROP TABLE IF EXISTS `sc_configuracoes`;
CREATE TABLE `sc_configuracoes` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `chave` varchar(100) NOT NULL,
  `valor` text,
  `descricao` varchar(255) DEFAULT NULL,
  `grupo` varchar(50) DEFAULT 'geral',
  `tipo` enum('text','textarea','file','select','number','color') DEFAULT 'text',
  `opcoes` text,
  `ordem` int(11) DEFAULT 0,
  `ativo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `chave` (`chave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `sc_configuracoes` (`chave`, `valor`, `descricao`, `grupo`, `tipo`, `ordem`, `ativo`) VALUES
('site_nome',                'SiteCatalogo',                   'Nome do site',                                        'geral',     'text',     1,  1),
('site_descricao',           'Catálogo de produtos online',    'Descrição do site',                                   'geral',     'textarea', 2,  1),
('mostrar_preco',            '1',                              'Mostrar preços no site',                              'geral',     'select',   3,  1),
('moeda',                    'BRL',                            'Moeda padrão',                                        'geral',     'select',   4,  1),
('empresa_sobre',            '',                               'Sobre a Empresa (seção Quem Somos)',                  'geral',     'textarea', 5,  1),
('empresa_slogan',           '',                               'Slogan / Frase de Destaque',                          'geral',     'text',     6,  1),
('whatsapp',                 '',                               'WhatsApp para contato',                               'contato',   'text',     1,  1),
('email_contato',            '',                               'Email de contato',                                    'contato',   'text',     2,  1),
('telefone',                 '',                               'Telefone fixo',                                       'contato',   'text',     3,  1),
('endereco',                 '',                               'Endereço da empresa',                                 'contato',   'textarea', 4,  1),
('horario_atendimento',      'Segunda a Sexta: 08h às 18h',   'Horário de atendimento',                              'contato',   'text',     5,  1),
('facebook_url',             '',                               'Facebook',                                            'social',    'text',     1,  1),
('instagram_url',            '',                               'Instagram',                                           'social',    'text',     2,  1),
('linkedin_url',             '',                               'LinkedIn',                                            'social',    'text',     3,  1),
('youtube_url',              '',                               'YouTube',                                             'social',    'text',     4,  1),
('tiktok_url',               '',                               'TikTok',                                              'social',    'text',     5,  1),
('twitter_url',              '',                               'Twitter / X',                                         'social',    'text',     6,  1),
('pinterest_url',            '',                               'Pinterest',                                           'social',    'text',     7,  1),
('telegram_url',             '',                               'Telegram',                                            'social',    'text',     8,  1),
('kwai_url',                 '',                               'Kwai',                                                'social',    'text',     9,  1),
('threads_url',              '',                               'Threads',                                             'social',    'text',     10, 1),
('discord_url',              '',                               'Discord',                                             'social',    'text',     11, 1),
('cor_primaria',             '#3b82f6',                        'Cor primária',                                        'aparencia', 'color',    1,  1),
('logo_cliente',             '',                               'Logo do cliente',                                     'aparencia', 'file',     2,  1),
('navbar_tipo',              'imagem_texto',                   'Tipo de navbar',                                      'aparencia', 'select',   3,  1),
('categoria_layout',         'sidebar',                        'Layout das Categorias',                               'aparencia', 'select',   4,  1),
('produto_visualizacao',     'modal',                          'Forma de Visualização do Produto',                    'aparencia', 'select',   5,  1),
('produtos_navegacao',       'paginacao',                      'Navegação de Produtos',                               'aparencia', 'select',   6,  1),
('toast_position',           'bottom-right',                   'Posição do Toast de Produto Adicionado',              'aparencia', 'select',   7,  1),
('alerta_sonoro_orcamento',  '1',                              'Alerta sonoro - novos orçamentos',                    'aparencia', 'select',   8,  1);

UPDATE `sc_configuracoes` SET `opcoes` = '{"1":"Sim - Mostrar preços","0":"Não - Ocultar preços"}' WHERE `chave` = 'mostrar_preco';
UPDATE `sc_configuracoes` SET `opcoes` = '{"BRL":"R$ - Real Brasileiro","USD":"$ - Dólar","EUR":"€ - Euro"}' WHERE `chave` = 'moeda';
UPDATE `sc_configuracoes` SET `opcoes` = '{"imagem_texto":"Logo + Nome","imagem":"Apenas Logo","texto":"Apenas Nome"}' WHERE `chave` = 'navbar_tipo';
UPDATE `sc_configuracoes` SET `opcoes` = '{"sidebar":"Sidebar Vertical (lateral)","inline":"Barra Inline (abaixo do menu)"}' WHERE `chave` = 'categoria_layout';
UPDATE `sc_configuracoes` SET `opcoes` = '{"modal":"Catálogo Simples (modal sobre a lista)","pagina_individual":"Página Individual do Produto (melhor para SEO)"}' WHERE `chave` = 'produto_visualizacao';
UPDATE `sc_configuracoes` SET `opcoes` = '{"paginacao":"Paginação (Anterior / Próximo)","scroll_infinito":"Scroll Infinito (carrega ao rolar)"}' WHERE `chave` = 'produtos_navegacao';
UPDATE `sc_configuracoes` SET `opcoes` = '{"bottom-left":"Rodapé Esquerdo","bottom-center":"Rodapé Centro","bottom-right":"Rodapé Direito"}' WHERE `chave` = 'toast_position';
UPDATE `sc_configuracoes` SET `opcoes` = '{"1":"Ativado","0":"Desativado"}' WHERE `chave` = 'alerta_sonoro_orcamento';

-- --------------------------------------------------------
-- sc_financeiro_categorias
-- --------------------------------------------------------
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
('Vendas de Produtos',   'receita', '#22c55e'),
('Orçamentos Aprovados', 'receita', '#3b82f6'),
('Serviços Prestados',   'receita', '#8b5cf6'),
('Outras Receitas',      'receita', '#06b6d4'),
('Aluguel',              'despesa', '#ef4444'),
('Salários',             'despesa', '#f97316'),
('Fornecedores',         'despesa', '#f59e0b'),
('Contas de Consumo',    'despesa', '#64748b'),
('Marketing',            'despesa', '#ec4899'),
('Impostos e Taxas',     'despesa', '#dc2626'),
('Compra de Estoque',    'despesa', '#92400e'),
('Outras Despesas',      'despesa', '#6b7280');

-- --------------------------------------------------------
-- sc_financeiro_contas
-- --------------------------------------------------------
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
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `sc_financeiro_contas` (`nome`, `tipo`, `saldo_inicial`, `saldo_atual`, `cor`) VALUES
('Caixa',          'caixa',    0.00, 0.00, '#22c55e'),
('Conta Corrente', 'corrente', 0.00, 0.00, '#3b82f6');

-- --------------------------------------------------------
-- sc_financeiro_lancamentos
-- --------------------------------------------------------
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
  `conta_destino_id` int(11) unsigned DEFAULT NULL COMMENT 'Para transferências',
  `cliente_id` int(11) unsigned DEFAULT NULL,
  `orcamento_id` int(11) unsigned DEFAULT NULL,
  `status` enum('pendente','pago','vencido','cancelado') DEFAULT 'pendente',
  `forma_pagamento` varchar(50) DEFAULT NULL,
  `parcelas` int(11) DEFAULT 1,
  `parcela_atual` int(11) DEFAULT 1,
  `grupo_parcelas` varchar(36) DEFAULT NULL,
  `observacoes` text,
  `comprovante` varchar(255) DEFAULT NULL,
  `usuario_id` int(11) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `tipo` (`tipo`),
  KEY `status` (`status`),
  KEY `data_vencimento` (`data_vencimento`),
  KEY `categoria_id` (`categoria_id`),
  KEY `conta_id` (`conta_id`),
  KEY `cliente_id` (`cliente_id`),
  KEY `orcamento_id` (`orcamento_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- sc_atividades_log
-- --------------------------------------------------------
DROP TABLE IF EXISTS `sc_atividades_log`;
CREATE TABLE `sc_atividades_log` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `acao` varchar(50) DEFAULT NULL,
  `tabela` varchar(50) DEFAULT NULL,
  `descricao` text,
  `usuario_id` int(11) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- sc_emails
-- --------------------------------------------------------
DROP TABLE IF EXISTS `sc_emails`;
CREATE TABLE `sc_emails` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `remetente_nome` varchar(255) DEFAULT '',
  `remetente_email` varchar(255) DEFAULT '',
  `destinatario_email` varchar(255) DEFAULT '',
  `assunto` varchar(500) DEFAULT '',
  `corpo` text,
  `pasta` enum('inbox','sent','drafts','trash','spam','archive') DEFAULT 'inbox',
  `status` enum('nao_lido','lido','respondido','encaminhado') DEFAULT 'nao_lido',
  `starred` tinyint(1) DEFAULT 0,
  `data_envio` datetime DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pasta` (`pasta`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;