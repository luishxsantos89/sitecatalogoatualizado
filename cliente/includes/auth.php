<?php
/**
 * SiteCatalogo2 - Área do Cliente
 * Funções auxiliares de autenticação e sessão do cliente
 *
 * Importante: usa a chave $_SESSION['cliente_id'], separada de
 * $_SESSION['admin_id'] usada no painel administrativo, então um
 * usuário pode estar autenticado nos dois sem conflito.
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/functions.php';

session_check();

/**
 * Retorna os dados do cliente logado, ou null se não estiver logado.
 */
function cliente_logado() {
    if (empty($_SESSION['cliente_id'])) return null;

    static $cliente_cache = null;
    if ($cliente_cache !== null) return $cliente_cache;

    $stmt = db()->prepare("SELECT * FROM " . table('clientes') . " WHERE id = ? LIMIT 1");
    $stmt->execute([$_SESSION['cliente_id']]);
    $cliente = $stmt->fetch();

    if (!$cliente) {
        // Cliente foi removido / sessão inválida
        unset($_SESSION['cliente_id'], $_SESSION['cliente_nome']);
        return null;
    }

    $cliente_cache = $cliente;
    return $cliente;
}

/**
 * Exige que o cliente esteja logado. Caso contrário, redireciona
 * para a tela de login, preservando a página de origem.
 */
function exigir_login_cliente() {
    if (!cliente_logado()) {
        $destino = $_SERVER['REQUEST_URI'] ?? 'pedidos.php';
        header('Location: login.php?redirect=' . urlencode($destino));
        exit;
    }
}

/**
 * Efetua o login do cliente verificando e-mail/CPF/CNPJ e senha.
 * Retorna o array do cliente em caso de sucesso, ou null se
 * credenciais inválidas.
 */
function tentar_login_cliente($identificador, $senha) {
    $identificador = trim($identificador);
    $doc = preg_replace('/\D/', '', $identificador);

    if (!empty($doc) && strlen($doc) >= 11) {
        $stmt = db()->prepare("SELECT * FROM " . table('clientes') . " WHERE (email = ? OR cpf_cnpj = ?) LIMIT 1");
        $stmt->execute([$identificador, $doc]);
    } else {
        $stmt = db()->prepare("SELECT * FROM " . table('clientes') . " WHERE email = ? LIMIT 1");
        $stmt->execute([$identificador]);
    }

    $cliente = $stmt->fetch();
    if (!$cliente) return null;

    if (empty($cliente['senha'])) return null;
    if (!password_verify($senha, $cliente['senha'])) return null;

    if (isset($cliente['acesso_ativo']) && (int)$cliente['acesso_ativo'] === 0) return null;
    if (isset($cliente['status']) && $cliente['status'] === 'bloqueado') return null;

    return $cliente;
}

/**
 * Cria a sessão do cliente após login/cadastro bem-sucedido.
 */
function iniciar_sessao_cliente($cliente) {
    $_SESSION['cliente_id']   = $cliente['id'];
    $_SESSION['cliente_nome'] = $cliente['nome_razaosocial'];
}

/**
 * Encerra a sessão do cliente.
 */
function encerrar_sessao_cliente() {
    unset($_SESSION['cliente_id'], $_SESSION['cliente_nome']);
}

/**
 * Gera o hash de senha padrão do projeto.
 */
function hash_senha_cliente($senha) {
    return password_hash($senha, PASSWORD_DEFAULT);
}

/**
 * Mapeia o status de orçamento para um label amigável e ícone.
 */
function status_pedido_label($status) {
    $map = [
        'novo'            => ['label' => 'Novo / Recebido',  'icon' => 'fa-inbox',           'cor' => 'status-novo'],
        'em_analise'      => ['label' => 'Em análise',       'icon' => 'fa-search',          'cor' => 'status-em_analise'],
        'aprovado'        => ['label' => 'Aprovado',         'icon' => 'fa-check-circle',    'cor' => 'status-aprovado'],
        'em_producao'     => ['label' => 'Em produção',      'icon' => 'fa-cogs',            'cor' => 'status-em_producao'],
        'enviado'         => ['label' => 'Enviado',          'icon' => 'fa-truck',           'cor' => 'status-enviado'],
        'concluido'       => ['label' => 'Concluído',        'icon' => 'fa-flag-checkered',  'cor' => 'status-concluido'],
        'cancelado'       => ['label' => 'Cancelado',        'icon' => 'fa-times-circle',    'cor' => 'status-cancelado'],
    ];

    return $map[$status] ?? ['label' => ucfirst(str_replace('_', ' ', $status)), 'icon' => 'fa-circle', 'cor' => 'status-novo'];
}
