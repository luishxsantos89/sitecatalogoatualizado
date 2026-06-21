<?php
/**
 * debug_senha_db.php — Verifica se as senhas estão salvas no banco
 * Cole este arquivo na pasta admin/ e acesse via navegador
 */
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: text/html; charset=utf-8');

echo '<h1>🔍 Debug: Senhas no Banco de Dados</h1>';
echo '<hr>';

// Verificar tabela de configuracoes
$table = table('configuracoes');

echo '<h2>📋 Tabela: ' . $table . '</h2>';

try {
    $stmt = db()->query("SELECT chave, valor, grupo, tipo FROM {$table} WHERE chave LIKE '%pass%' OR chave LIKE '%smtp%' OR chave LIKE '%imap%' ORDER BY chave");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) {
        echo '<p style="color:red"><strong>⚠️ Nenhuma config encontrada!</strong></p>';
    } else {
        echo '<table border="1" cellpadding="8" style="border-collapse:collapse;font-family:monospace">';
        echo '<tr style="background:#333;color:#fff"><th>Chave</th><th>Valor (visível)</th><th>Tamanho</th><th>Grupo</th><th>Tipo</th><th>Status</th></tr>';
        foreach ($rows as $row) {
            $valor = $row['valor'];
            $tamanho = strlen($valor);
            $visivel = ($tamanho > 0) ? substr($valor, 0, 50) . ($tamanho > 50 ? '...' : '') : '<em style="color:red">(VAZIO)</em>';
            $status = ($tamanho > 0) 
                ? '<span style="color:green">✅ SALVO (' . $tamanho . ' chars)</span>' 
                : '<span style="color:red">❌ VAZIO</span>';
            echo '<tr>';
            echo '<td><strong>' . htmlspecialchars($row['chave']) . '</strong></td>';
            echo '<td>' . htmlspecialchars($visivel) . '</td>';
            echo '<td>' . $tamanho . '</td>';
            echo '<td>' . htmlspecialchars($row['grupo']) . '</td>';
            echo '<td>' . htmlspecialchars($row['tipo']) . '</td>';
            echo '<td>' . $status . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }

    echo '<hr>';
    echo '<h2>🔧 Teste get_config()</h2>';

    $smtp_pass = get_config('smtp_pass', '[não encontrado]');
    $imap_pass = get_config('imap_pass', '[não encontrado]');

    echo '<p><strong>smtp_pass via get_config():</strong> ';
    echo (strlen($smtp_pass) > 0) 
        ? '✅ Tem valor (' . strlen($smtp_pass) . ' chars) — início: ' . htmlspecialchars(substr($smtp_pass, 0, 20)) . '...'
        : '❌ VAZIO ou não encontrado';
    echo '</p>';

    echo '<p><strong>imap_pass via get_config():</strong> ';
    echo (strlen($imap_pass) > 0) 
        ? '✅ Tem valor (' . strlen($imap_pass) . ' chars) — início: ' . htmlspecialchars(substr($imap_pass, 0, 20)) . '...'
        : '❌ VAZIO ou não encontrado';
    echo '</p>';

    echo '<hr>';
    echo '<h2>📝 POST da última submissão (se houver)</h2>';
    echo '<pre>';
    print_r($_POST);
    echo '</pre>';

} catch (Exception $e) {
    echo '<p style="color:red">Erro: ' . htmlspecialchars($e->getMessage()) . '</p>';
}

echo '<hr>';
echo '<p><a href="configuracoes.php">← Voltar para Configurações</a></p>';