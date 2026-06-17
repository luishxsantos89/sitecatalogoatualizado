<?php
/**
 * SiteCatalogo2 - API para receber orçamentos do site público
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once dirname(dirname(__FILE__)) . '/config.php';
require_once dirname(dirname(__FILE__)) . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$dados = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$cliente_nome  = trim($dados['cliente_nome'] ?? '');
$cliente_email = trim($dados['cliente_email'] ?? '');
$cliente_tel   = trim($dados['cliente_telefone'] ?? '');
$mensagem      = trim($dados['mensagem'] ?? '');
$produtos      = $dados['produtos'] ?? [];

if (empty($cliente_nome)) {
    echo json_encode(['success' => false, 'message' => 'Nome é obrigatório']);
    exit;
}

try {
    $codigo = 'WEB-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
    $total = 0;
    foreach ($produtos as $p) {
        $total += (float)($p['preco'] ?? 0) * (int)($p['qtd'] ?? 1);
    }

    db()->prepare("INSERT INTO " . table('orcamentos') . " (codigo,cliente_nome,cliente_email,cliente_telefone,status,observacoes,valor_total) VALUES (?,?,?,?,?,?,?)")
        ->execute([$codigo,$cliente_nome,$cliente_email,$cliente_tel,'novo',$mensagem,$total]);

    $orc_id = db()->lastInsertId();

    foreach ($produtos as $p) {
        $preco = (float)($p['preco'] ?? 0);
        $qtd   = (int)($p['qtd'] ?? 1);
        db()->prepare("INSERT INTO " . table('orcamento_itens') . " (orcamento_id,produto_nome,quantidade,preco_unitario,subtotal) VALUES (?,?,?,?,?)")
            ->execute([$orc_id,$p['nome']??'Produto',$qtd,$preco,$preco*$qtd]);
    }

    // Registrar email de contato
    try {
        db()->prepare("INSERT INTO " . table('emails') . " (remetente_nome,remetente_email,assunto,corpo,pasta,status) VALUES (?,?,?,?,?,?)")
            ->execute([$cliente_nome,$cliente_email,"Solicitação de Orçamento - {$codigo}","Cliente: {$cliente_nome}\nTelefone: {$cliente_tel}\n\n{$mensagem}",'inbox','nao_lido']);
    } catch(Exception $e) {}

    echo json_encode(['success' => true, 'message' => 'Orçamento enviado com sucesso!', 'codigo' => $codigo]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
}
