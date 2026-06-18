<?php
/**
 * Debug script for orcamento AJAX endpoint
 * Place this in your site root and access via browser to see errors
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

session_check();

// Simulate the POST request
$_POST['action'] = 'salvar_orcamento';
$_POST['cliente_nome'] = 'Teste Debug';
$_POST['cliente_tel'] = '21999999999';

// Add a test product to cart
$_SESSION['carrinho'] = [
    1 => [
        'id' => 1,
        'nome' => 'Produto Teste',
        'preco' => 90.00,
        'preco_promocional' => 0,
        'unidade' => 'un',
        'imagem_principal' => '',
        'qtd' => 1
    ]
];

echo "<h1>Debug - Salvar Orçamento</h1>";
echo "<pre>";

try {
    $carrinho_atual = $_SESSION['carrinho'] ?? [];

    echo "Carrinho: ";
    print_r($carrinho_atual);

    $codigo = 'ORC-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
    echo "\nCódigo gerado: $codigo\n";

    $valor_produtos = 0;
    foreach ($carrinho_atual as $pid => $item) {
        $p = $item['preco_promocional'] > 0 ? $item['preco_promocional'] : $item['preco'];
        $valor_produtos += (float)$p * (int)$item['qtd'];
    }
    echo "Valor produtos: $valor_produtos\n";

    $cliente_id = null;
    $cli_nome = trim($_POST['cliente_nome'] ?? '');
    $cli_tel = trim($_POST['cliente_tel'] ?? '');

    echo "Cliente: $cli_nome, Tel: $cli_tel\n";

    if (!empty($cli_tel)) {
        $tel = preg_replace('/\D/', '', $cli_tel);
        echo "Tel limpo: $tel\n";
        $stmt_cli = db()->prepare("SELECT id FROM " . table('clientes') . " WHERE celular = ? LIMIT 1");
        $stmt_cli->execute([$tel]);
        $found = $stmt_cli->fetchColumn();
        if ($found) {
            $cliente_id = $found;
            echo "Cliente encontrado: $cliente_id\n";
        }
    }

    $whatsapp_empresa = get_config('whatsapp', '');
    $msg_conf = get_config('orcamento_whatsapp_msg', defined('WHATSAPP_DEFAULT_MSG') ? WHATSAPP_DEFAULT_MSG : '');

    echo "WhatsApp empresa: $whatsapp_empresa\n";
    echo "Msg config: $msg_conf\n";

    // Test the INSERT
    echo "\n--- Tentando INSERT ---\n";
    $sql = "INSERT INTO " . table('orcamentos') . " (codigo,cliente_id,cliente_nome,cliente_telefone,tipo_contato,status,valor_produtos,valor_servicos,desconto,valor_total,usuario_id) VALUES (?,?,?,?,?,?,?,?,?,?,NULL)";
    echo "SQL: $sql\n";

    $stmt = db()->prepare($sql);
    $result = $stmt->execute([$codigo, $cliente_id, $cli_nome ?: 'Cliente do Site', $cli_tel, 'whatsapp', 'novo', $valor_produtos, 0, 0, $valor_produtos]);

    echo "INSERT result: " . ($result ? 'OK' : 'FALHOU') . "\n";

    $orc_id = (int)db()->lastInsertId();
    echo "Orcamento ID: $orc_id\n";

    // Insert items
    foreach ($carrinho_atual as $pid => $item) {
        $preco = (float)($item['preco_promocional'] > 0 ? $item['preco_promocional'] : $item['preco']);
        $qtd = (int)$item['qtd'];
        $item_sql = "INSERT INTO " . table('orcamento_itens') . " (orcamento_id,produto_id,produto_nome,quantidade,unidade,preco_unitario,subtotal) VALUES (?,?,?,?,?,?,?)";
        echo "\nItem SQL: $item_sql\n";
        $item_stmt = db()->prepare($item_sql);
        $item_result = $item_stmt->execute([$orc_id, $pid, $item['nome'], $qtd, $item['unidade'] ?? 'un', $preco, $preco * $qtd]);
        echo "Item INSERT: " . ($item_result ? 'OK' : 'FALHOU') . "\n";
    }

    echo "\n--- SUCESSO ---\n";

} catch (Exception $e) {
    echo "\n--- ERRO ---\n";
    echo "Mensagem: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . "\n";
    echo "Linha: " . $e->getLine() . "\n";
    echo "Trace: \n" . $e->getTraceAsString() . "\n";
}

echo "</pre>";
