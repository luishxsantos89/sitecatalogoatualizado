<?php
/**
 * SiteCatalogo — Instalador
 * Similar ao WordPress: testa conexão, cria banco/tabelas, gera config.php
 */

define('INSTALL_VERSION', '2.1');
define('ROOT_PATH', dirname(__DIR__));
define('DB_NAME_FIXED', 'sitecatalogo');
define('DB_PREFIX_FIXED', 'sc_');

// Se já instalado, redireciona para o admin
if (file_exists(ROOT_PATH . '/config.php') && !isset($_GET['force'])) {
    header('Location: ../admin/login.php'); exit;
}

// ─── Funções auxiliares ───────────────────────────────────────────────

function already_installed(): bool {
    return file_exists(ROOT_PATH . '/config.php');
}

function test_db(string $host, string $user, string $pass): array {
    try {
        $pdo = new PDO("mysql:host={$host};charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        $version = $pdo->query('SELECT VERSION()')->fetchColumn();
        return ['ok' => true, 'pdo' => $pdo, 'version' => $version];
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        // Mensagem amigável por cenário
        if (str_contains($msg, 'Connection refused') || str_contains($msg, '2002'))
            $msg = "Não foi possível conectar ao servidor MySQL. Verifique o host ({$host}).";
        elseif (str_contains($msg, 'Access denied'))
            $msg = "Usuário ou senha incorretos.";
        elseif (str_contains($msg, 'getaddrinfo'))
            $msg = "Host '{$host}' não encontrado. Verifique o endereço do servidor.";
        return ['ok' => false, 'error' => $msg];
    }
}

function create_database(PDO $pdo, string $dbname): bool {
    try {
        // Tenta criar — no cPanel o banco já existe, então só faz USE
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    } catch (PDOException $e) {
        // Sem permissão para criar (cPanel) — tenta só usar o existente
    }
    $pdo->exec("USE `{$dbname}`");
    return true;
}

function run_schema(PDO $pdo, string $admin_nome, string $admin_email, string $admin_senha): array {
    $errors = [];
    try {
        $pdo->exec("SET NAMES utf8mb4");
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

        // ── Tabelas ──────────────────────────────────────────────────
        $sql_tables = <<<SQL
CREATE TABLE `sc_usuarios` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `role` enum('admin','gerente','vendedor','atendente') DEFAULT 'vendedor',
  `status` enum('ativo','inativo','bloqueado') DEFAULT 'ativo',
  `ultimo_acesso` datetime DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sc_categorias` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `descricao` text DEFAULT NULL,
  `imagem` varchar(255) DEFAULT NULL,
  `icone` varchar(100) DEFAULT 'category',
  `ordem` int DEFAULT 0,
  `ativo` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sc_produtos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `categoria_id` int unsigned DEFAULT NULL,
  `nome` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `descricao` text DEFAULT NULL,
  `descricao_curta` varchar(500) DEFAULT NULL,
  `imagem_principal` varchar(255) DEFAULT NULL,
  `imagens` text DEFAULT NULL,
  `preco` decimal(10,2) DEFAULT 0.00,
  `preco_promocional` decimal(10,2) DEFAULT NULL,
  `custo` decimal(10,2) DEFAULT NULL,
  `unidade` varchar(20) DEFAULT 'un',
  `peso` decimal(10,3) DEFAULT NULL,
  `sku` varchar(100) DEFAULT NULL,
  `quantidade_estoque` int DEFAULT 0,
  `estoque_minimo` int DEFAULT 5,
  `visualizacoes` int DEFAULT 0,
  `destaque` tinyint(1) DEFAULT 0,
  `ativo` tinyint(1) DEFAULT 1,
  `tags` varchar(500) DEFAULT NULL,
  `seo_title` varchar(255) DEFAULT NULL,
  `seo_description` varchar(500) DEFAULT NULL,
  `caracteristicas` text DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_slug` (`slug`),
  KEY `idx_categoria` (`categoria_id`),
  CONSTRAINT `fk_produtos_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `sc_categorias` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sc_produto_imagens` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `produto_id` int unsigned NOT NULL,
  `imagem` varchar(255) NOT NULL,
  `ordem` int DEFAULT 0,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_produto` (`produto_id`),
  CONSTRAINT `fk_pimg_produto` FOREIGN KEY (`produto_id`) REFERENCES `sc_produtos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sc_produto_estoque` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `produto_id` int unsigned NOT NULL,
  `tipo` enum('entrada','saida','ajuste') DEFAULT 'entrada',
  `quantidade` int DEFAULT 0,
  `quantidade_anterior` int DEFAULT 0,
  `motivo` varchar(255) DEFAULT NULL,
  `usuario_id` int unsigned DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_produto` (`produto_id`),
  KEY `idx_tipo` (`tipo`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sc_clientes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
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
  `senha` varchar(255) DEFAULT NULL,
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

CREATE TABLE `sc_orcamentos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(50) NOT NULL,
  `cliente_id` int unsigned DEFAULT NULL,
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
  `usuario_id` int unsigned DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_codigo` (`codigo`),
  KEY `idx_status` (`status`),
  KEY `idx_cliente` (`cliente_id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sc_orcamento_itens` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `orcamento_id` int unsigned NOT NULL,
  `produto_id` int unsigned DEFAULT NULL,
  `produto_nome` varchar(255) NOT NULL,
  `sku` varchar(100) DEFAULT NULL,
  `quantidade` int DEFAULT 1,
  `unidade` varchar(20) DEFAULT 'un',
  `preco_unitario` decimal(10,2) DEFAULT 0.00,
  `subtotal` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_orcamento` (`orcamento_id`),
  KEY `idx_produto` (`produto_id`),
  CONSTRAINT `fk_itens_orcamento` FOREIGN KEY (`orcamento_id`) REFERENCES `sc_orcamentos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sc_banners` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) DEFAULT NULL,
  `subtitulo` varchar(255) DEFAULT NULL,
  `imagem` varchar(255) NOT NULL,
  `link` varchar(500) DEFAULT NULL,
  `posicao` varchar(50) DEFAULT 'slide',
  `ordem` int DEFAULT 0,
  `ativo` tinyint(1) DEFAULT 1,
  `popup_delay` int DEFAULT 0,
  `popup_fechar` varchar(20) DEFAULT 'botao',
  `popup_freq_max` int DEFAULT 0,
  `popup_intervalo` int DEFAULT 0,
  `prazo_fixo` tinyint(1) DEFAULT 1,
  `data_inicio` date DEFAULT NULL,
  `data_fim` date DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_posicao_ativo` (`posicao`, `ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sc_configuracoes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `chave` varchar(100) NOT NULL,
  `valor` text DEFAULT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `grupo` varchar(50) DEFAULT 'geral',
  `tipo` enum('text','textarea','file','select','number','color') DEFAULT 'text',
  `opcoes` text DEFAULT NULL,
  `ordem` int DEFAULT 0,
  `ativo` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_chave` (`chave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sc_financeiro_categorias` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `tipo` enum('receita','despesa') NOT NULL,
  `cor` varchar(20) DEFAULT '#6b7280',
  `ativo` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sc_financeiro_contas` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
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

CREATE TABLE `sc_financeiro_lancamentos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tipo` enum('receita','despesa','transferencia') NOT NULL,
  `descricao` varchar(255) NOT NULL,
  `valor` decimal(10,2) NOT NULL DEFAULT 0.00,
  `data_vencimento` date NOT NULL,
  `data_pagamento` date DEFAULT NULL,
  `categoria_id` int unsigned DEFAULT NULL,
  `conta_id` int unsigned DEFAULT NULL,
  `conta_destino_id` int unsigned DEFAULT NULL,
  `cliente_id` int unsigned DEFAULT NULL,
  `orcamento_id` int unsigned DEFAULT NULL,
  `status` enum('pendente','pago','vencido','cancelado') DEFAULT 'pendente',
  `forma_pagamento` varchar(50) DEFAULT NULL,
  `parcelas` int DEFAULT 1,
  `parcela_atual` int DEFAULT 1,
  `grupo_parcelas` varchar(36) DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `comprovante` varchar(255) DEFAULT NULL,
  `usuario_id` int unsigned DEFAULT NULL,
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

CREATE TABLE `sc_atividades_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `acao` varchar(50) DEFAULT NULL,
  `tabela` varchar(50) DEFAULT NULL,
  `descricao` text DEFAULT NULL,
  `usuario_id` int unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_usuario` (`usuario_id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sc_emails` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
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
SQL;

        // Executar tabela por tabela
        foreach (array_filter(array_map('trim', explode(';', $sql_tables))) as $stmt) {
            if ($stmt) $pdo->exec($stmt);
        }

        // ── Usuário admin ────────────────────────────────────────────
        $hash = password_hash($admin_senha, PASSWORD_BCRYPT);
        $st = $pdo->prepare("INSERT INTO sc_usuarios (nome, email, senha, role, status) VALUES (?, ?, ?, 'admin', 'ativo')");
        $st->execute([$admin_nome, $admin_email, $hash]);

        // ── Configurações padrão ─────────────────────────────────────
        $configs = [
            ['site_nome',            'Meu Catálogo',  'Nome do Site',                 'geral',    'text',   1],
            ['site_descricao',       '',              'Descrição do Site',             'geral',    'text',   2],
            ['whatsapp',             '',              'WhatsApp de Contato',           'contato',  'text',   1],
            ['email_contato',        $admin_email,    'E-mail de Contato',             'contato',  'text',   2],
            ['orcamento_whatsapp_msg', 'Olá! Recebi seu orçamento e entrarei em contato em breve.', 'Mensagem padrão WhatsApp orçamento', 'contato', 'textarea', 3],
            ['mostrar_preco',        '1',             'Mostrar Preços',                'geral',    'select', 4],
            ['moeda',                'BRL',           'Moeda',                         'geral',    'select', 5],
            ['facebook_url',         '',              'Facebook',                      'social',   'text',   1],
            ['instagram_url',        '',              'Instagram',                     'social',   'text',   2],
            ['youtube_url',          '',              'YouTube',                       'social',   'text',   4],
            ['tiktok_url',           '',              'TikTok',                        'social',   'text',   5],
            ['twitter_url',          '',              'Twitter / X',                   'social',   'text',   6],
            ['linkedin_url',         '',              'LinkedIn',                      'social',   'text',   3],
            ['pinterest_url',        '',              'Pinterest',                     'social',   'text',   7],
            ['kwai_url',             '',              'Kwai',                          'social',   'text',   9],
            ['threads_url',          '',              'Threads',                       'social',   'text',   10],
            ['discord_url',          '',              'Discord',                       'social',   'text',   11],
            ['telegram_url',         '',              'Telegram',                      'social',   'text',   8],
            ['cor_primaria',         '#3b82f6',       'Cor primária',                  'aparencia','color',  1],
            ['logo_cliente',         '',              'Logo do cliente',               'aparencia','file',   2],
            ['navbar_tipo',          'imagem_texto',  'Tipo de navbar',                'aparencia','select', 3],
            ['produto_visualizacao', 'modal',         'Forma de Visualização',         'aparencia','select', 5],
            ['produtos_navegacao',   'paginacao',     'Navegação de Produtos',         'aparencia','select', 6],
            ['toast_position',       'bottom-right',  'Posição do Toast',              'aparencia','select', 7],
            ['alerta_sonoro_orcamento','1',           'Alerta sonoro - orçamentos',    'aparencia','select', 8],
            ['modo_produto',         'modal',         'Exibição de Produtos',          'aparencia','select', 9],
        ];
        $ins = $pdo->prepare("INSERT INTO sc_configuracoes (chave, valor, descricao, grupo, tipo, ordem, ativo) VALUES (?, ?, ?, ?, ?, ?, 1)");
        foreach ($configs as $c) $ins->execute($c);

        // opcoes dos selects
        $opts = [
            'mostrar_preco'        => '{"1":"Sim - Mostrar preços","0":"Não - Ocultar preços"}',
            'moeda'                => '{"BRL":"R$ - Real Brasileiro","USD":"$ - Dólar","EUR":"€ - Euro"}',
            'navbar_tipo'          => '{"imagem_texto":"Logo + Nome","imagem":"Apenas Logo","texto":"Apenas Nome"}',
            'produto_visualizacao' => '{"modal":"Catálogo Simples (modal)","pagina_individual":"Página Individual (SEO)"}',
            'produtos_navegacao'   => '{"paginacao":"Paginação","scroll_infinito":"Scroll Infinito"}',
            'toast_position'       => '{"bottom-left":"Rodapé Esquerdo","bottom-center":"Rodapé Centro","bottom-right":"Rodapé Direito","top-left":"Topo Esquerdo","top-center":"Topo Centro","top-right":"Topo Direito"}',
            'alerta_sonoro_orcamento' => '{"1":"Ativado","0":"Desativado"}',
            'modo_produto'         => '{"modal":"Modal (catálogo simples)","pagina":"Página Individual (SEO)"}',
        ];
        $upd = $pdo->prepare("UPDATE sc_configuracoes SET opcoes = ? WHERE chave = ?");
        foreach ($opts as $k => $v) $upd->execute([$v, $k]);

        // ── Financeiro padrão ────────────────────────────────────────
        $pdo->exec("INSERT INTO sc_financeiro_categorias (nome, tipo, cor) VALUES
            ('Vendas de Produtos','receita','#22c55e'),('Orçamentos Aprovados','receita','#3b82f6'),
            ('Serviços Prestados','receita','#8b5cf6'),('Outras Receitas','receita','#06b6d4'),
            ('Aluguel','despesa','#ef4444'),('Salários','despesa','#f97316'),
            ('Fornecedores','despesa','#f59e0b'),('Contas de Consumo','despesa','#64748b'),
            ('Marketing','despesa','#ec4899'),('Impostos e Taxas','despesa','#dc2626'),
            ('Compra de Estoque','despesa','#92400e'),('Outras Despesas','despesa','#6b7280')");
        $pdo->exec("INSERT INTO sc_financeiro_contas (nome, tipo, saldo_inicial, saldo_atual, cor) VALUES
            ('Caixa','caixa',0.00,0.00,'#22c55e'),('Conta Corrente','corrente',0.00,0.00,'#3b82f6')");

    } catch (PDOException $e) {
        $errors[] = $e->getMessage();
    }
    return $errors;
}

function generate_config(string $host, string $user, string $pass, string $site_url): string {
    $secret = bin2hex(random_bytes(32));
    $date   = date('d/m/Y H:i');
    return <<<PHP
<?php
/**
 * SiteCatalogo - Configuração
 * Gerado automaticamente pelo instalador em: {$date}
 * NÃO edite este arquivo manualmente a menos que saiba o que está fazendo.
 */

// ── Banco de dados ──────────────────────────────────────────────────
define('DB_HOST',   '{$host}');
define('DB_NAME',   'sitecatalogo');
define('DB_USER',   '{$user}');
define('DB_PASS',   '{$pass}');
define('DB_PREFIX', 'sc_');

// ── URLs ────────────────────────────────────────────────────────────
define('SITE_URL',    '{$site_url}');
define('ADMIN_URL',   '{$site_url}/admin/');
define('ASSETS_URL',  '{$site_url}/assets/');
define('UPLOADS_URL', '{$site_url}/uploads/');

// ── Caminhos ────────────────────────────────────────────────────────
if (!defined('ROOT_PATH'))    define('ROOT_PATH',    __DIR__);
if (!defined('UPLOADS_PATH')) define('UPLOADS_PATH', __DIR__ . '/uploads');

// ── Segurança ───────────────────────────────────────────────────────
define('SECRET_KEY',   '{$secret}');
define('SESSION_NAME', 'sc2_session');

// ── Sistema ─────────────────────────────────────────────────────────
define('SITE_NAME',        'SiteCatalogo');
define('SITE_DESCRIPTION', '');
define('WHATSAPP',         '');
define('WHATSAPP_DEFAULT_MSG', 'Olá! Recebi seu orçamento e entrarei em contato em breve.');

if (!defined('ADMIN_ITEMS_PER_PAGE')) define('ADMIN_ITEMS_PER_PAGE', 20);
if (!defined('ITEMS_PER_PAGE'))       define('ITEMS_PER_PAGE', ADMIN_ITEMS_PER_PAGE);
PHP;
}

function write_config(string $content): bool {
    return file_put_contents(ROOT_PATH . '/config.php', $content) !== false;
}

// ─── Processamento dos POSTs ──────────────────────────────────────────

$step   = (int)($_GET['step'] ?? 1);
$errors = [];
$success = false;

// STEP 2 — Testar conexão
if ($step === 2 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $db_host  = trim($_POST['db_host'] ?? 'localhost');
    $db_user  = trim($_POST['db_user'] ?? '');
    $db_pass  = $_POST['db_pass'] ?? '';
    $result   = test_db($db_host, $db_user, $db_pass);
    if (!$result['ok']) {
        $errors[] = $result['error'];
        $step = 2;
    } else {
        // Avança para step 3 com dados em sessão
        session_start();
        $_SESSION['install_db'] = compact('db_host','db_user','db_pass');
        header('Location: ?step=3'); exit;
    }
}

// STEP 4 — Instalar
if ($step === 4 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    session_start();
    $db = $_SESSION['install_db'] ?? [];
    $admin_nome  = trim($_POST['admin_nome'] ?? 'Administrador');
    $admin_email = trim($_POST['admin_email'] ?? '');
    $admin_senha = $_POST['admin_senha'] ?? '';
    $site_url    = rtrim(trim($_POST['site_url'] ?? ''), '/');

    if (empty($admin_email) || !filter_var($admin_email, FILTER_VALIDATE_EMAIL))
        $errors[] = 'E-mail inválido.';
    if (strlen($admin_senha) < 8)
        $errors[] = 'Senha deve ter pelo menos 8 caracteres.';
    if (empty($site_url))
        $errors[] = 'URL do site é obrigatória.';

    if (empty($errors)) {
        $conn = test_db($db['db_host'], $db['db_user'], $db['db_pass']);
        if (!$conn['ok']) { $errors[] = $conn['error']; }
        else {
            $pdo = $conn['pdo'];
            create_database($pdo, DB_NAME_FIXED);

            $schema_errors = run_schema($pdo, $admin_nome, $admin_email, $admin_senha);
            if (!empty($schema_errors)) {
                $errors = array_merge($errors, $schema_errors);
            } else {
                $config = generate_config($db['db_host'], $db['db_user'], $db['db_pass'], $site_url);
                if (!write_config($config)) {
                    $errors[] = 'Não foi possível criar o arquivo config.php. Verifique as permissões da pasta raiz.';
                } else {
                    unset($_SESSION['install_db']);
                    $step = 5;
                    $success = true;
                }
            }
        }
    }
}

// Detectar URL automaticamente
$detected_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

// Detectar host MySQL por domínio
$http_host = $_SERVER['HTTP_HOST'] ?? '';
$suggested_host = 'localhost';
if (str_contains($http_host, 'hostgator')) $suggested_host = 'localhost';
elseif (str_contains($http_host, 'hostinger')) $suggested_host = 'localhost';
elseif (str_contains($http_host, '.com.br') || str_contains($http_host, '.com')) {
    // Produção genérica — localhost funciona na maioria
    $suggested_host = 'localhost';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Instalação — SiteCatalogo</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;background:linear-gradient(135deg,#eff6ff 0%,#f0fdf4 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;}
.wrap{width:100%;max-width:560px;}
.logo{display:flex;align-items:center;gap:12px;justify-content:center;margin-bottom:32px;}
.logo-icon{width:52px;height:52px;background:linear-gradient(135deg,#3b82f6,#6366f1);border-radius:14px;display:flex;align-items:center;justify-content:center;box-shadow:0 8px 24px rgba(59,130,246,.35);}
.logo-text{font-size:1.5rem;font-weight:800;color:#0f172a;}
.logo-text span{color:#3b82f6;}
.card{background:#fff;border-radius:20px;box-shadow:0 4px 40px rgba(0,0,0,.10);overflow:hidden;}
.card-header{background:linear-gradient(135deg,#3b82f6,#6366f1);padding:28px 32px;color:#fff;}
.card-header h2{font-size:1.25rem;font-weight:700;margin-bottom:4px;}
.card-header p{font-size:0.875rem;opacity:.85;}
.steps{display:flex;gap:0;padding:0 32px;border-bottom:1px solid #f1f5f9;background:#f8fafc;}
.step-item{display:flex;align-items:center;gap:8px;padding:14px 0;flex:1;font-size:0.8rem;font-weight:500;color:#94a3b8;border-bottom:2px solid transparent;transition:all .2s;}
.step-item.active{color:#3b82f6;border-bottom-color:#3b82f6;}
.step-item.done{color:#22c55e;}
.step-num{width:22px;height:22px;border-radius:50%;background:#e2e8f0;display:flex;align-items:center;justify-content:center;font-size:0.7rem;font-weight:700;flex-shrink:0;transition:all .2s;}
.step-item.active .step-num{background:#3b82f6;color:#fff;}
.step-item.done .step-num{background:#22c55e;color:#fff;}
.card-body{padding:32px;}
.form-group{margin-bottom:20px;}
label{display:block;font-size:0.875rem;font-weight:600;color:#374151;margin-bottom:6px;}
label .hint{font-weight:400;color:#9ca3af;font-size:0.8rem;margin-left:6px;}
input[type=text],input[type=password],input[type=email],input[type=url]{width:100%;padding:11px 14px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:0.9rem;font-family:inherit;outline:none;transition:border-color .2s,box-shadow .2s;color:#111827;}
input:focus{border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.15);}
.input-icon{position:relative;}
.input-icon i{position:absolute;left:13px;top:50%;transform:translateY(-50%);color:#9ca3af;font-size:0.875rem;}
.input-icon input{padding-left:38px;}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:12px 24px;border-radius:10px;font-weight:600;font-size:0.9rem;cursor:pointer;border:none;transition:all .2s;text-decoration:none;font-family:inherit;}
.btn-primary{background:linear-gradient(135deg,#3b82f6,#6366f1);color:#fff;width:100%;margin-top:8px;box-shadow:0 4px 16px rgba(59,130,246,.3);}
.btn-primary:hover{opacity:.92;box-shadow:0 6px 20px rgba(59,130,246,.4);}
.alert{padding:12px 16px;border-radius:10px;font-size:0.875rem;margin-bottom:20px;display:flex;align-items:flex-start;gap:10px;}
.alert-error{background:#fef2f2;border:1px solid #fecaca;color:#dc2626;}
.alert-success{background:#f0fdf4;border:1px solid #bbf7d0;color:#16a34a;}
.alert-info{background:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8;}
.badge-fixed{display:inline-flex;align-items:center;gap:6px;background:#f0fdf4;border:1px solid #bbf7d0;color:#15803d;font-size:0.8rem;font-weight:600;padding:5px 12px;border-radius:20px;margin-bottom:20px;}
.divider{border:none;border-top:1px solid #f1f5f9;margin:20px 0;}
.success-icon{width:80px;height:80px;background:linear-gradient(135deg,#22c55e,#16a34a);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 24px;box-shadow:0 8px 30px rgba(34,197,94,.35);}
.access-box{background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:20px;margin:20px 0;}
.access-row{display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid #e2e8f0;font-size:0.875rem;}
.access-row:last-child{border-bottom:none;}
.access-row .key{color:#6b7280;font-weight:500;}
.access-row .val{color:#111827;font-weight:700;font-family:monospace;}
.warn-box{background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:14px 16px;margin-top:16px;font-size:0.8rem;color:#92400e;display:flex;gap:10px;}
</style>
</head>
<body>
<div class="wrap">
    <div class="logo">
        <div class="logo-icon">
            <svg width="28" height="28" viewBox="0 0 36 36" fill="none"><path d="M10 24V14l8-5 8 5v10H10z" stroke="white" stroke-width="2.2" fill="none"/><circle cx="18" cy="19" r="2.5" fill="white"/></svg>
        </div>
        <div class="logo-text">Site<span>Catalogo</span>2</div>
    </div>
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-magic"></i> Assistente de Instalação</h2>
            <p>Configure o sistema em poucos passos — sem editar arquivos manualmente.</p>
        </div>

        <!-- Steps -->
        <div class="steps">
            <?php
            $steps_labels = ['Boas-vindas','Banco de Dados','Confirmação','Conta Admin','Concluído'];
            for ($i = 1; $i <= 5; $i++):
                $cls = $i < $step ? 'done' : ($i == $step ? 'active' : '');
                $icon = $i < $step ? '<i class="fas fa-check" style="font-size:.65rem;"></i>' : $i;
            ?>
            <div class="step-item <?= $cls ?>">
                <span class="step-num"><?= $icon ?></span>
                <span class="hide-xs"><?= $steps_labels[$i-1] ?></span>
            </div>
            <?php endfor; ?>
        </div>

        <div class="card-body">

        <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle" style="flex-shrink:0;margin-top:2px;"></i>
            <div><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
        </div>
        <?php endif; ?>

        <?php if ($step === 1): ?>
        <!-- ── STEP 1: Boas-vindas ──────────────────────────────── -->
        <h3 style="font-size:1.1rem;font-weight:700;margin-bottom:12px;color:#0f172a;">Bem-vindo à instalação!</h3>
        <p style="color:#6b7280;font-size:0.9rem;margin-bottom:20px;">Este assistente irá configurar o banco de dados e criar o arquivo <code>config.php</code> automaticamente.</p>

        <div class="alert alert-info">
            <i class="fas fa-info-circle" style="flex-shrink:0;margin-top:2px;"></i>
            <div><strong>Antes de começar, você vai precisar de:</strong><br>
            • Host do MySQL (geralmente <code>localhost</code>)<br>
            • Usuário e senha do banco de dados<br>
            • A pasta raiz do projeto deve ter permissão de escrita</div>
        </div>

        <div class="badge-fixed"><i class="fas fa-lock"></i> Banco fixo: <strong>sitecatalogo</strong> &nbsp;|&nbsp; Prefixo: <strong>sc_</strong></div>

        <hr class="divider">

        <div style="font-size:0.8rem;color:#9ca3af;margin-bottom:20px;">
            <strong style="color:#374151;">Hospedagens suportadas:</strong>
            Hostgator · Hostinger · Locaweb · KingHost · localhost (Laragon/XAMPP/WAMP)
        </div>

        <a href="?step=2" class="btn btn-primary"><i class="fas fa-arrow-right"></i> Iniciar Instalação</a>

        <?php elseif ($step === 2): ?>
        <!-- ── STEP 2: Dados do banco ───────────────────────────── -->
        <h3 style="font-size:1.1rem;font-weight:700;margin-bottom:6px;color:#0f172a;">Configurar Banco de Dados</h3>
        <p style="color:#6b7280;font-size:0.875rem;margin-bottom:16px;">O banco <strong>sitecatalogo</strong> será criado automaticamente se o usuário tiver permissão. No cPanel geralmente você cria o banco antes.</p>

        <!-- Guia cPanel colapsável -->
        <details style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;margin-bottom:20px;overflow:hidden;">
            <summary style="padding:12px 16px;cursor:pointer;font-weight:600;font-size:0.875rem;color:#374151;display:flex;align-items:center;gap:8px;user-select:none;">
                <i class="fas fa-question-circle" style="color:#3b82f6;"></i>
                Usando cPanel (Hostgator, Hostinger, Locaweb…)? Clique para ver o guia
            </summary>
            <div style="padding:0 16px 16px;font-size:0.825rem;color:#374151;border-top:1px solid #e2e8f0;margin-top:0;">
                <p style="margin:12px 0 8px;font-weight:700;color:#0f172a;">📋 Passo a passo para criar o banco no cPanel:</p>
                <ol style="padding-left:18px;line-height:2;">
                    <li>Acesse o cPanel da sua hospedagem (geralmente <code>seudominio.com.br/cpanel</code>)</li>
                    <li>Vá em <strong>Bancos de Dados MySQL</strong> (ou <em>MySQL® Databases</em>)</li>
                    <li>Em <strong>"Criar novo banco"</strong>, digite <code>sitecatalogo</code> e clique em <strong>Criar</strong><br>
                        <span style="color:#9ca3af;">⚠️ O cPanel geralmente prefixa com seu usuário: ex. <code>usuario_sitecatalogo</code> — use esse nome completo no campo DB_NAME.</span>
                    </li>
                    <li>Em <strong>"Usuários MySQL"</strong>, crie um usuário ou use um existente</li>
                    <li>Associe o usuário ao banco em <strong>"Adicionar usuário ao banco"</strong> → marque <strong>Todos os privilégios</strong></li>
                    <li>Volte aqui e preencha os campos abaixo com os dados criados</li>
                </ol>
                <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:10px 12px;margin-top:12px;">
                    <strong style="color:#92400e;"><i class="fas fa-lightbulb"></i> Onde encontro o Host?</strong>
                    <ul style="padding-left:16px;margin-top:6px;line-height:1.8;color:#78350f;">
                        <li><strong>Hostgator / Locaweb / KingHost:</strong> use <code>localhost</code></li>
                        <li><strong>Hostinger:</strong> use <code>localhost</code> (ou veja em <em>Banco de dados → Detalhes</em>)</li>
                        <li><strong>Outros:</strong> procure em <em>Bancos de dados → Detalhes do servidor MySQL</em> no cPanel</li>
                        <li><strong>Localhost (Laragon/XAMPP):</strong> use <code>localhost</code></li>
                    </ul>
                </div>
                <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:10px 12px;margin-top:10px;color:#15803d;">
                    <strong><i class="fas fa-info-circle"></i> Nome do banco no cPanel:</strong><br>
                    Se o cPanel criar como <code>usuario_sitecatalogo</code>, use exatamente esse nome completo no campo Usuário e o banco será referenciado corretamente. O instalador tentará criar o banco — se já existir, apenas usará o existente.
                </div>
            </div>
        </details>

        <form method="POST" action="?step=2">
            <div class="form-group">
                <label>Host do MySQL <span class="hint">— geralmente <code>localhost</code></span></label>
                <div class="input-icon">
                    <i class="fas fa-server"></i>
                    <input type="text" name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? $suggested_host) ?>" placeholder="localhost" required>
                </div>
                <div style="margin-top:6px;font-size:0.78rem;color:#9ca3af;">
                    💡 Na maioria das hospedagens é <code>localhost</code>. Veja o guia acima se tiver dúvida.
                </div>
            </div>
            <div class="form-group">
                <label>Usuário do banco <span class="hint">— no cPanel: <code>usuario_seuusuario</code></span></label>
                <div class="input-icon">
                    <i class="fas fa-user"></i>
                    <input type="text" name="db_user" value="<?= htmlspecialchars($_POST['db_user'] ?? '') ?>" placeholder="root" required>
                </div>
            </div>
            <div class="form-group">
                <label>Senha do banco <span class="hint">— pode ser vazia no localhost</span></label>
                <div class="input-icon">
                    <i class="fas fa-key"></i>
                    <input type="password" name="db_pass" value="<?= htmlspecialchars($_POST['db_pass'] ?? '') ?>" placeholder="••••••••">
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-plug"></i> Testar Conexão e Continuar</button>
        </form>

        <?php elseif ($step === 3): ?>
        <!-- ── STEP 3: Confirmação ──────────────────────────────── -->
        <?php session_start(); $db = $_SESSION['install_db'] ?? []; ?>
        <h3 style="font-size:1.1rem;font-weight:700;margin-bottom:12px;color:#0f172a;">Conexão estabelecida! ✅</h3>

        <div class="alert alert-success">
            <i class="fas fa-check-circle" style="flex-shrink:0;"></i>
            <div>Conectado com sucesso ao MySQL via <strong><?= htmlspecialchars($db['db_host'] ?? 'localhost') ?></strong>.<br>
            O banco <strong>sitecatalogo</strong> será criado/recriado com todas as tabelas.</div>
        </div>

        <div class="access-box" style="margin-bottom:0;">
            <div class="access-row"><span class="key">Banco de dados</span><span class="val">sitecatalogo</span></div>
            <div class="access-row"><span class="key">Prefixo</span><span class="val">sc_</span></div>
            <div class="access-row"><span class="key">Host</span><span class="val"><?= htmlspecialchars($db['db_host'] ?? '') ?></span></div>
            <div class="access-row"><span class="key">Usuário</span><span class="val"><?= htmlspecialchars($db['db_user'] ?? '') ?></span></div>
        </div>

        <div class="warn-box">
            <i class="fas fa-exclamation-triangle" style="flex-shrink:0;margin-top:2px;"></i>
            <span>Se o banco <strong>sitecatalogo</strong> já existir, as tabelas serão <strong>recriadas do zero</strong>. Dados existentes serão perdidos.</span>
        </div>

        <a href="?step=4" class="btn btn-primary" style="margin-top:20px;"><i class="fas fa-arrow-right"></i> Continuar para Conta Admin</a>

        <?php elseif ($step === 4 && !$success): ?>
        <!-- ── STEP 4: Conta admin ──────────────────────────────── -->
        <h3 style="font-size:1.1rem;font-weight:700;margin-bottom:6px;color:#0f172a;">Criar Conta do Administrador</h3>
        <p style="color:#6b7280;font-size:0.875rem;margin-bottom:20px;">Configure suas credenciais de acesso ao painel.</p>

        <form method="POST" action="?step=4">
            <div class="form-group">
                <label>Seu nome</label>
                <div class="input-icon"><i class="fas fa-user"></i>
                <input type="text" name="admin_nome" value="<?= htmlspecialchars($_POST['admin_nome'] ?? 'Administrador') ?>" placeholder="Seu nome completo" required></div>
            </div>
            <div class="form-group">
                <label>E-mail de acesso</label>
                <div class="input-icon"><i class="fas fa-envelope"></i>
                <input type="email" name="admin_email" value="<?= htmlspecialchars($_POST['admin_email'] ?? '') ?>" placeholder="admin@seusite.com" required></div>
            </div>
            <div class="form-group">
                <label>Senha <span class="hint">— mínimo 8 caracteres</span></label>
                <div class="input-icon"><i class="fas fa-lock"></i>
                <input type="password" name="admin_senha" placeholder="••••••••" required minlength="8"></div>
            </div>
            <div class="form-group">
                <label>URL do site <span class="hint">— sem barra no final</span></label>
                <div class="input-icon"><i class="fas fa-globe"></i>
                <input type="text" name="site_url" value="<?= htmlspecialchars($_POST['site_url'] ?? $detected_url) ?>" placeholder="https://seusite.com.br" required></div>
                <div style="margin-top:6px;font-size:0.78rem;color:#9ca3af;">
                    Detectado automaticamente: <strong><?= htmlspecialchars($detected_url) ?></strong>
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-rocket"></i> Instalar Agora!</button>
        </form>

        <?php elseif ($step === 5 || $success): ?>
        <!-- ── STEP 5: Sucesso ──────────────────────────────────── -->
        <div style="text-align:center;">
            <div class="success-icon"><i class="fas fa-check" style="color:#fff;font-size:2.5rem;"></i></div>
            <h3 style="font-size:1.25rem;font-weight:800;color:#0f172a;margin-bottom:8px;">Instalação concluída!</h3>
            <p style="color:#6b7280;font-size:0.875rem;">O SiteCatalogo foi instalado com sucesso.</p>
        </div>

        <div class="access-box">
            <div class="access-row"><span class="key">Painel Admin</span><span class="val">/admin/login.php</span></div>
            <div class="access-row"><span class="key">E-mail</span><span class="val"><?= htmlspecialchars($_POST['admin_email'] ?? '') ?></span></div>
            <div class="access-row"><span class="key">Senha</span><span class="val">a que você definiu</span></div>
        </div>

        <div class="warn-box">
            <i class="fas fa-shield-alt" style="flex-shrink:0;margin-top:2px;"></i>
            <span>Por segurança, <strong>remova ou renomeie a pasta <code>/install/</code></strong> após o primeiro acesso.</span>
        </div>

        <a href="../admin/login.php" class="btn btn-primary" style="margin-top:20px;">
            <i class="fas fa-sign-in-alt"></i> Acessar Painel Admin
        </a>
        <?php endif; ?>

        </div><!-- card-body -->
    </div><!-- card -->

    <p style="text-align:center;color:#94a3b8;font-size:0.78rem;margin-top:20px;">
        SiteCatalogo v<?= INSTALL_VERSION ?> — Instalador
    </p>
</div>
</body>
</html>