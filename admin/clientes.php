<?php
/**
 * SiteCatalogo2 - Clientes (CRUD)
 */
require_once __DIR__ . '/includes/functions.php';

// === CONTROLE DE ACESSO ===
require_auth();
if (!check_permission('atendente')) {
    header('Location: ' . admin_url());
    exit('Acesso negado.');
}

$page_title = 'Clientes';

$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ========== IMPORTAÇÃO POR PLANILHA ==========
if ($action === 'importar_planilha' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    if (empty($_FILES['planilha']['tmp_name'])) {
        echo json_encode(['ok' => false, 'msg' => 'Nenhum arquivo enviado.']);
        exit;
    }

    $ext = strtolower(pathinfo($_FILES['planilha']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['csv', 'xlsx', 'xls'])) {
        echo json_encode(['ok' => false, 'msg' => 'Formato não suportado. Use CSV, XLS ou XLSX.']);
        exit;
    }

    $resultado = ['ok' => true, 'importados' => 0, 'atualizados' => 0, 'erros' => []];

    try {
        $handle = fopen($_FILES['planilha']['tmp_name'], 'r');
        if (!$handle) {
            echo json_encode(['ok' => false, 'msg' => 'Não foi possível ler o arquivo.']);
            exit;
        }

        // Detectar delimitador
        $primeira_linha = fgets($handle);
        rewind($handle);
        $delimitador = strpos($primeira_linha, ';') !== false ? ';' : ',';

        // Ler cabeçalho
        $cabecalho = fgetcsv($handle, 0, $delimitador);
        if (!$cabecalho) {
            echo json_encode(['ok' => false, 'msg' => 'Arquivo vazio ou formato inválido.']);
            exit;
        }

        // Normalizar cabeçalho
        $cabecalho = array_map(function($h) {
            return strtolower(trim(str_replace([' ', '_', '-', '/'], '', \mb_convert_encoding($h, 'UTF-8', 'UTF-8'))));
        }, $cabecalho);

        // Mapear colunas
        $map = [];
        $colunas_esperadas = [
            'nome_razaosocial' => ['nome', 'nomerazaosocial', 'cliente', 'razaosocial', 'nomecompleto'],
            'tipo_pessoa'      => ['tipopessoa', 'tipo', 'pessoa'],
            'cpf_cnpj'         => ['cpfcnpj', 'cpf', 'cnpj', 'documento'],
            'rg_ie'            => ['rgie', 'rg', 'ie', 'inscricaoestadual'],
            'email'            => ['email', 'emailcliente', 'ecommerce'],
            'telefone'         => ['telefone', 'fone'],
            'celular'          => ['celular', 'whatsapp', 'celularwhatsapp'],
            'cep'              => ['cep'],
            'endereco'         => ['endereco', 'rua', 'logradouro'],
            'numero'           => ['numero', 'nendereco', 'num'],
            'complemento'      => ['complemento'],
            'bairro'           => ['bairro'],
            'cidade'           => ['cidade', 'municipio'],
            'estado'           => ['estado', 'uf'],
            'observacoes'      => ['observacoes', 'obs', 'observacao'],
            'categoria'        => ['categoria'],
            'limite_credito'   => ['limitecredito', 'limite', 'creditolimite'],
            'status'           => ['status', 'situacao'],
        ];

        foreach ($cabecalho as $idx => $col) {
            foreach ($colunas_esperadas as $campo => $sinonimos) {
                if (in_array($col, $sinonimos)) {
                    $map[$campo] = $idx;
                    break;
                }
            }
        }

        if (!isset($map['nome_razaosocial'])) {
            echo json_encode(['ok' => false, 'msg' => 'Coluna "Nome" não encontrada no cabeçalho. Cabeçalho detectado: ' . implode(', ', $cabecalho)]);
            exit;
        }

        $categorias_validas = ['cliente_final', 'revendedor', 'parceiro', 'fornecedor'];
        $status_validos = ['ativo', 'inativo', 'bloqueado'];

        $linha_num = 1;
        while (($dados = fgetcsv($handle, 0, $delimitador)) !== false) {
            $linha_num++;

            // Pular linhas vazias
            if (empty(array_filter($dados, 'trim'))) continue;

            $nome = trim(mb_convert_encoding($dados[$map['nome_razaosocial']] ?? '', 'UTF-8', 'UTF-8'));
            if (empty($nome)) {
                $resultado['erros'][] = "Linha {$linha_num}: nome vazio";
                continue;
            }

            $tipo_pessoa = isset($map['tipo_pessoa']) ? strtolower(trim($dados[$map['tipo_pessoa']] ?? '')) : 'fisica';
            $tipo_pessoa = in_array($tipo_pessoa, ['fisica', 'juridica', 'pf', 'pj']) ? (in_array($tipo_pessoa,['juridica','pj']) ? 'juridica' : 'fisica') : 'fisica';

            $cpf_cnpj = isset($map['cpf_cnpj']) ? preg_replace('/\D/', '', $dados[$map['cpf_cnpj']] ?? '') : '';
            $rg_ie    = isset($map['rg_ie']) ? trim($dados[$map['rg_ie']] ?? '') : '';
            $email    = isset($map['email']) ? trim($dados[$map['email']] ?? '') : '';
            $telefone = isset($map['telefone']) ? preg_replace('/\D/', '', $dados[$map['telefone']] ?? '') : '';
            $celular  = isset($map['celular']) ? preg_replace('/\D/', '', $dados[$map['celular']] ?? '') : '';
            $cep      = isset($map['cep']) ? preg_replace('/\D/', '', $dados[$map['cep']] ?? '') : '';
            $endereco = isset($map['endereco']) ? trim(mb_convert_encoding($dados[$map['endereco']] ?? '', 'UTF-8', 'UTF-8')) : '';
            $numero   = isset($map['numero']) ? trim($dados[$map['numero']] ?? '') : '';
            $complemento = isset($map['complemento']) ? trim(mb_convert_encoding($dados[$map['complemento']] ?? '', 'UTF-8', 'UTF-8')) : '';
            $bairro   = isset($map['bairro']) ? trim(mb_convert_encoding($dados[$map['bairro']] ?? '', 'UTF-8', 'UTF-8')) : '';
            $cidade   = isset($map['cidade']) ? trim(mb_convert_encoding($dados[$map['cidade']] ?? '', 'UTF-8', 'UTF-8')) : '';
            $estado   = isset($map['estado']) ? strtoupper(trim($dados[$map['estado']] ?? '')) : '';
            $observacoes = isset($map['observacoes']) ? trim(mb_convert_encoding($dados[$map['observacoes']] ?? '', 'UTF-8', 'UTF-8')) : '';

            $categoria = isset($map['categoria']) ? strtolower(trim($dados[$map['categoria']] ?? '')) : 'cliente_final';
            if (!in_array($categoria, $categorias_validas)) $categoria = 'cliente_final';

            $limite_credito = isset($map['limite_credito']) && $dados[$map['limite_credito']] !== ''
                ? (float)str_replace(['.', ','], ['', '.'], $dados[$map['limite_credito']])
                : 0;

            $status = isset($map['status']) ? strtolower(trim($dados[$map['status']] ?? '')) : 'ativo';
            if (!in_array($status, $status_validos)) $status = 'ativo';

            $dados_cliente = [
                'nome_razaosocial' => $nome,
                'tipo_pessoa'      => $tipo_pessoa,
                'cpf_cnpj'         => $cpf_cnpj,
                'rg_ie'            => $rg_ie,
                'email'            => $email,
                'telefone'         => $telefone,
                'celular'          => $celular,
                'cep'              => $cep,
                'endereco'         => $endereco,
                'numero'           => $numero,
                'complemento'      => $complemento,
                'bairro'           => $bairro,
                'cidade'           => $cidade,
                'estado'           => $estado,
                'observacoes'      => $observacoes,
                'categoria'        => $categoria,
                'limite_credito'   => $limite_credito,
                'status'           => $status,
            ];

            // Verificar se já existe por CPF/CNPJ ou e-mail
            $existente = null;
            if (!empty($cpf_cnpj)) {
                $stmt_ex = db()->prepare("SELECT id FROM " . table('clientes') . " WHERE cpf_cnpj = ? LIMIT 1");
                $stmt_ex->execute([$cpf_cnpj]);
                $existente = $stmt_ex->fetchColumn();
            }
            if (!$existente && !empty($email)) {
                $stmt_ex = db()->prepare("SELECT id FROM " . table('clientes') . " WHERE email = ? LIMIT 1");
                $stmt_ex->execute([$email]);
                $existente = $stmt_ex->fetchColumn();
            }

            if ($existente) {
                $fields = []; $values = [];
                foreach ($dados_cliente as $k => $v) { $fields[] = "{$k} = ?"; $values[] = $v; }
                $values[] = $existente;
                db()->prepare("UPDATE " . table('clientes') . " SET " . implode(', ', $fields) . " WHERE id = ?")->execute($values);
                $resultado['atualizados']++;
            } else {
                $dados_cliente['foto'] = '';
                $cols = implode(', ', array_keys($dados_cliente));
                $ph = implode(', ', array_fill(0, count($dados_cliente), '?'));
                db()->prepare("INSERT INTO " . table('clientes') . " ({$cols}) VALUES ({$ph})")->execute(array_values($dados_cliente));
                $resultado['importados']++;
            }
        }

        fclose($handle);

        $resultado['msg'] = "Importação concluída! {$resultado['importados']} novo(s), {$resultado['atualizados']} atualizado(s).";
        if (!empty($resultado['erros'])) {
            $resultado['msg'] .= " " . count($resultado['erros']) . " erro(s) encontrado(s).";
        }

        echo json_encode($resultado);
        exit;

    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'msg' => 'Erro: ' . $e->getMessage()]);
        exit;
    }
}



// ========== EXPORTAR CLIENTES ==========
if ($action === 'exportar') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="clientes_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, [
        'Nome', 'Tipo Pessoa', 'CPF/CNPJ', 'RG/IE', 'Email', 'Telefone', 'Celular',
        'CEP', 'Endereço', 'Número', 'Complemento', 'Bairro', 'Cidade', 'Estado',
        'Categoria', 'Limite Crédito', 'Status', 'Observações'
    ], ';');

    try {
        $stmt_exp = db()->query("SELECT * FROM " . table('clientes') . " ORDER BY nome_razaosocial");
        foreach ($stmt_exp->fetchAll() as $c) {
            fputcsv($output, [
                $c['nome_razaosocial'],
                $c['tipo_pessoa'],
                $c['cpf_cnpj'],
                $c['rg_ie'],
                $c['email'],
                $c['telefone'],
                $c['celular'],
                $c['cep'],
                $c['endereco'],
                $c['numero'],
                $c['complemento'],
                $c['bairro'],
                $c['cidade'],
                $c['estado'],
                $c['categoria'],
                number_format((float)$c['limite_credito'], 2, ',', ''),
                $c['status'],
                $c['observacoes'],
            ], ';');
        }
    } catch (Exception $e) {}

    fclose($output);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    // Redefinir senha do cliente (pelo admin — AJAX, sem form aninhado)
    if ($acao === 'redefinir_senha_admin') {
        header('Content-Type: application/json');
        $senha_nova     = (string)($_POST['senha_nova'] ?? '');
        $senha_confirma = (string)($_POST['senha_confirma'] ?? '');
        $cliente_id_rs  = (int)($_POST['cliente_id_rs'] ?? 0);

        if ($cliente_id_rs <= 0) {
            echo json_encode(['ok' => false, 'msg' => 'Cliente inválido.']); exit;
        }
        if (strlen($senha_nova) < 6) {
            echo json_encode(['ok' => false, 'msg' => 'A nova senha deve ter ao menos 6 caracteres.']); exit;
        }
        if ($senha_nova !== $senha_confirma) {
            echo json_encode(['ok' => false, 'msg' => 'As senhas não coincidem.']); exit;
        }
        try {
            $hash = password_hash($senha_nova, PASSWORD_DEFAULT);
            db()->prepare("UPDATE " . table('clientes') . " SET senha = ? WHERE id = ?")->execute([$hash, $cliente_id_rs]);
            echo json_encode(['ok' => true, 'msg' => 'Senha redefinida com sucesso!']); exit;
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'msg' => 'Erro: ' . $e->getMessage()]); exit;
        }
    }

    if ($acao === 'salvar') {
        $dados = [
            'nome_razaosocial' => trim($_POST['nome_razaosocial'] ?? ''),
            'tipo_pessoa'      => $_POST['tipo_pessoa'] ?? 'fisica',
            'cpf_cnpj'         => preg_replace('/\D/', '', $_POST['cpf_cnpj'] ?? ''),
            'rg_ie'            => trim($_POST['rg_ie'] ?? ''),
            'email'            => trim($_POST['email'] ?? ''),
            'telefone'         => preg_replace('/\D/', '', $_POST['telefone'] ?? ''),
            'celular'          => preg_replace('/\D/', '', $_POST['celular'] ?? ''),
            'cep'              => preg_replace('/\D/', '', $_POST['cep'] ?? ''),
            'endereco'         => trim($_POST['endereco'] ?? ''),
            'numero'           => trim($_POST['numero'] ?? ''),
            'complemento'      => trim($_POST['complemento'] ?? ''),
            'bairro'           => trim($_POST['bairro'] ?? ''),
            'cidade'           => trim($_POST['cidade'] ?? ''),
            'estado'           => $_POST['estado'] ?? '',
            'observacoes'      => trim($_POST['observacoes'] ?? ''),
            'categoria'        => $_POST['categoria'] ?? 'cliente_final',
            'limite_credito'   => (float)str_replace(',', '.', $_POST['limite_credito'] ?? 0),
            'status'           => $_POST['status'] ?? 'ativo',
        ];

        if (!empty($_FILES['foto']['name'])) {
            $up = handle_upload(['name'=>$_FILES['foto']['name'],'tmp_name'=>$_FILES['foto']['tmp_name'],'error'=>$_FILES['foto']['error']], 'clientes');
            if ($up) {
                if ($id) { $old = db()->prepare("SELECT foto FROM " . table('clientes') . " WHERE id = ?"); $old->execute([$id]); $o = $old->fetchColumn(); if ($o) delete_upload($o); }
                $dados['foto'] = $up;
            }
        }

        if (empty($dados['nome_razaosocial'])) {
            set_flash('error', 'Nome é obrigatório');
        } else {
            try {
                if ($id) {
                    if (empty($dados['foto'])) unset($dados['foto']);
                    $f = []; $v = [];
                    foreach ($dados as $k => $val) { $f[] = "{$k} = ?"; $v[] = $val; }
                    $v[] = $id;
                    db()->prepare("UPDATE " . table('clientes') . " SET " . implode(', ', $f) . " WHERE id = ?")->execute($v);
                    set_flash('success', 'Cliente atualizado!');
                } else {
                    $dados['foto'] = $dados['foto'] ?? '';
                    $cols = implode(', ', array_keys($dados));
                    $ph = implode(', ', array_fill(0, count($dados), '?'));
                    db()->prepare("INSERT INTO " . table('clientes') . " ({$cols}) VALUES ({$ph})")->execute(array_values($dados));
                    set_flash('success', 'Cliente criado!');
                }
                header('Location: clientes.php'); exit;
            } catch (Exception $e) { set_flash('error', 'Erro: ' . $e->getMessage()); }
        }
    }
}

if ($action === 'delete' && $id) {
    try {
        $stmt = db()->prepare("SELECT foto FROM " . table('clientes') . " WHERE id = ?"); $stmt->execute([$id]);
        $c = $stmt->fetchColumn(); if ($c) delete_upload($c);
        db()->prepare("DELETE FROM " . table('clientes') . " WHERE id = ?")->execute([$id]);
        set_flash('success', 'Cliente excluído!');
    } catch (Exception $e) { set_flash('error', 'Erro: ' . $e->getMessage()); }
    header('Location: clientes.php'); exit;
}

$cliente = null;
if (($action === 'edit' || $action === 'view') && $id) {
    $stmt = db()->prepare("SELECT * FROM " . table('clientes') . " WHERE id = ?"); $stmt->execute([$id]); $cliente = $stmt->fetch();
}

// Buscar orçamentos do cliente
$cliente_orcamentos = [];
if ($action === 'view' && $id) {
    $stmt = db()->prepare("SELECT * FROM " . table('orcamentos') . " WHERE cliente_id = ? ORDER BY created_at DESC LIMIT 10");
    $stmt->execute([$id]);
    $cliente_orcamentos = $stmt->fetchAll();
}

$busca = $_GET['busca'] ?? '';
$status_filtro = $_GET['status'] ?? '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

$where = ["1=1"]; $params = [];
if ($busca) { $where[] = "(nome_razaosocial LIKE ? OR cpf_cnpj LIKE ? OR celular LIKE ? OR email LIKE ?)"; $like="%{$busca}%"; $params=[$like,$like,$like,$like]; }
if ($status_filtro) { $where[] = "status = ?"; $params[] = $status_filtro; }
$where_sql = implode(' AND ', $where);

$stmt_count = db()->prepare("SELECT COUNT(*) FROM " . table('clientes') . " WHERE {$where_sql}"); $stmt_count->execute($params); $total = (int)$stmt_count->fetchColumn();
$pagination = paginate($total, $page, ADMIN_ITEMS_PER_PAGE);
$stmt_list = db()->prepare("SELECT * FROM " . table('clientes') . " WHERE {$where_sql} ORDER BY nome_razaosocial LIMIT {$pagination['offset']}, " . ADMIN_ITEMS_PER_PAGE);
$stmt_list->execute($params); $clientes = $stmt_list->fetchAll();

$estados = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];

require_once __DIR__ . '/includes/header.php';

if ($action === 'edit' || $action === 'new'):
?>
<div class="page-header">
    <h1><i class="fas fa-<?php echo $action==='edit'?'edit':'user-plus'; ?>"></i> <?php echo $action==='edit'?'Editar':'Novo'; ?> Cliente</h1>
    <a href="clientes.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
</div>

<form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="acao" value="salvar">

    <div class="form-row" style="grid-template-columns:2fr 1fr;gap:20px;">
        <div>
            <div class="card">
                <div class="card-header"><h3><i class="fas fa-user"></i> Dados Pessoais</h3></div>
                <div class="card-body">
                    <div class="form-row form-row-2">
                        <div class="form-group">
                            <label>Tipo de Pessoa</label>
                            <select name="tipo_pessoa" id="tipo_pessoa" onchange="toggleTipoPessoa(this.value)">
                                <option value="fisica" <?php echo selected($cliente['tipo_pessoa']??'fisica','fisica'); ?>>Pessoa Física</option>
                                <option value="juridica" <?php echo selected($cliente['tipo_pessoa']??'fisica','juridica'); ?>>Pessoa Jurídica</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Categoria</label>
                            <select name="categoria">
                                <option value="cliente_final" <?php echo selected($cliente['categoria']??'cliente_final','cliente_final'); ?>>Cliente Final</option>
                                <option value="revendedor" <?php echo selected($cliente['categoria']??'','revendedor'); ?>>Revendedor</option>
                                <option value="parceiro" <?php echo selected($cliente['categoria']??'','parceiro'); ?>>Parceiro</option>
                                <option value="fornecedor" <?php echo selected($cliente['categoria']??'','fornecedor'); ?>>Fornecedor</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label id="label_nome">Nome Completo / Razão Social *</label>
                        <input type="text" name="nome_razaosocial" value="<?php echo sanitize($cliente['nome_razaosocial']??''); ?>" required>
                    </div>
                    <div class="form-row form-row-2">
                        <div class="form-group">
                            <label id="label_cpf_cnpj">CPF</label>
                            <input type="text" name="cpf_cnpj" value="<?php echo sanitize($cliente['cpf_cnpj']??''); ?>" placeholder="000.000.000-00">
                        </div>
                        <div class="form-group">
                            <label id="label_rg_ie">RG</label>
                            <input type="text" name="rg_ie" value="<?php echo sanitize($cliente['rg_ie']??''); ?>">
                        </div>
                    </div>
                    <div class="form-row form-row-3">
                        <div class="form-group">
                            <label>E-mail</label>
                            <input type="email" name="email" value="<?php echo sanitize($cliente['email']??''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Telefone</label>
                            <input type="tel" name="telefone" value="<?php echo sanitize($cliente['telefone']??''); ?>" placeholder="(00) 0000-0000">
                        </div>
                        <div class="form-group">
                            <label>WhatsApp / Celular</label>
                            <input type="tel" name="celular" value="<?php echo sanitize($cliente['celular']??''); ?>" placeholder="(00) 00000-0000">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Limite de Crédito (R$)</label>
                        <input type="text" name="limite_credito" value="<?php echo number_format((float)($cliente['limite_credito']??0),2,',','.'); ?>">
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3><i class="fas fa-map-marker-alt"></i> Endereço</h3></div>
                <div class="card-body">
                    <div class="form-row form-row-2">
                        <div class="form-group">
                            <label>CEP</label>
                            <input type="text" name="cep" id="cep" value="<?php echo sanitize($cliente['cep']??''); ?>" placeholder="00000-000" onblur="buscarCep(this.value)">
                        </div>
                        <div class="form-group">
                            <label>Estado</label>
                            <select name="estado">
                                <option value="">—</option>
                                <?php foreach ($estados as $uf): ?>
                                <option value="<?php echo $uf; ?>" <?php echo selected($cliente['estado']??'',$uf); ?>><?php echo $uf; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row form-row-2">
                        <div class="form-group">
                            <label>Endereço</label>
                            <input type="text" name="endereco" id="endereco" value="<?php echo sanitize($cliente['endereco']??''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Número</label>
                            <input type="text" name="numero" value="<?php echo sanitize($cliente['numero']??''); ?>">
                        </div>
                    </div>
                    <div class="form-row form-row-3">
                        <div class="form-group">
                            <label>Complemento</label>
                            <input type="text" name="complemento" value="<?php echo sanitize($cliente['complemento']??''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Bairro</label>
                            <input type="text" name="bairro" id="bairro" value="<?php echo sanitize($cliente['bairro']??''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Cidade</label>
                            <input type="text" name="cidade" id="cidade" value="<?php echo sanitize($cliente['cidade']??''); ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Observações</label>
                        <textarea name="observacoes" rows="2"><?php echo sanitize($cliente['observacoes']??''); ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div>
            <div class="card">
                <div class="card-header"><h3><i class="fas fa-toggle-on"></i> Status</h3></div>
                <div class="card-body">
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="ativo" <?php echo selected($cliente['status']??'ativo','ativo'); ?>>Ativo</option>
                            <option value="inativo" <?php echo selected($cliente['status']??'','inativo'); ?>>Inativo</option>
                            <option value="bloqueado" <?php echo selected($cliente['status']??'','bloqueado'); ?>>Bloqueado</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><h3><i class="fas fa-camera"></i> Foto</h3></div>
                <div class="card-body">
                    <?php if (!empty($cliente['foto'])): ?>
                    <img src="<?php echo uploads_url($cliente['foto']); ?>" style="width:100%;max-height:150px;object-fit:contain;border-radius:8px;margin-bottom:10px;">
                    <?php endif; ?>
                    <input type="file" name="foto" accept="image/*">
                </div>
            </div>

            <?php if ($action === 'edit' && $id): ?>
            <div class="card" style="border:2px solid #f59e0b;background:#fffbeb;">
                <div class="card-header" style="background:#fef3c7;border-bottom:1px solid #f59e0b;">
                    <h3 style="color:#92400e;"><i class="fas fa-key" style="color:#f59e0b;"></i> Redefinir Senha</h3>
                </div>
                <div class="card-body">
                    <p style="font-size:0.8125rem;color:#78350f;margin-bottom:14px;line-height:1.5;">
                        <i class="fas fa-exclamation-triangle" style="color:#f59e0b;"></i>
                        Use esta opção apenas quando o cliente <strong>esquecer a senha</strong>. A nova senha será aplicada imediatamente.
                    </p>
                    <div id="rsMsg" style="display:none;padding:10px 12px;border-radius:8px;font-size:0.8125rem;margin-bottom:12px;"></div>
                    <div class="form-group">
                        <label style="font-size:0.8rem;">Nova Senha <span style="color:#ef4444;">*</span></label>
                        <input type="password" id="rsSenhaNova" placeholder="Mínimo 6 caracteres" style="background:#fff;width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;font-size:0.875rem;">
                    </div>
                    <div class="form-group">
                        <label style="font-size:0.8rem;">Confirmar Nova Senha <span style="color:#ef4444;">*</span></label>
                        <input type="password" id="rsSenhaConfirma" style="background:#fff;width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;font-size:0.875rem;">
                    </div>
                    <button type="button" onclick="redefinirSenha(<?php echo $id; ?>)"
                            style="background:#f59e0b;color:#fff;border:none;border-radius:8px;padding:10px 16px;font-weight:700;width:100%;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;font-size:0.875rem;">
                        <i class="fas fa-key"></i> Redefinir Senha Agora
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save"></i> Salvar Cliente</button>
        <a href="clientes.php" class="btn btn-secondary btn-lg">Cancelar</a>
    </div>
</form>

<script>
function toggleTipoPessoa(tipo) {
    document.getElementById('label_cpf_cnpj').textContent = tipo === 'juridica' ? 'CNPJ' : 'CPF';
    document.getElementById('label_rg_ie').textContent = tipo === 'juridica' ? 'Inscrição Estadual' : 'RG';
}
toggleTipoPessoa(document.getElementById('tipo_pessoa').value);

function redefinirSenha(clienteId) {
    const nova     = document.getElementById('rsSenhaNova').value;
    const confirma = document.getElementById('rsSenhaConfirma').value;
    const msg      = document.getElementById('rsMsg');

    if (!nova || nova.length < 6) {
        msg.style.display = 'block';
        msg.style.background = '#fee2e2'; msg.style.color = '#991b1b';
        msg.innerHTML = '<i class="fas fa-exclamation-circle"></i> A senha deve ter ao menos 6 caracteres.';
        return;
    }
    if (nova !== confirma) {
        msg.style.display = 'block';
        msg.style.background = '#fee2e2'; msg.style.color = '#991b1b';
        msg.innerHTML = '<i class="fas fa-exclamation-circle"></i> As senhas não coincidem.';
        return;
    }
    if (!confirm('Redefinir a senha deste cliente?')) return;

    const fd = new FormData();
    fd.append('acao', 'redefinir_senha_admin');
    fd.append('cliente_id_rs', clienteId);
    fd.append('senha_nova', nova);
    fd.append('senha_confirma', confirma);

    fetch('clientes.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            msg.style.display = 'block';
            if (d.ok) {
                msg.style.background = '#dcfce7'; msg.style.color = '#166534';
                msg.innerHTML = '<i class="fas fa-check-circle"></i> ' + d.msg;
                document.getElementById('rsSenhaNova').value = '';
                document.getElementById('rsSenhaConfirma').value = '';
            } else {
                msg.style.background = '#fee2e2'; msg.style.color = '#991b1b';
                msg.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + d.msg;
            }
        })
        .catch(() => {
            msg.style.display = 'block';
            msg.style.background = '#fee2e2'; msg.style.color = '#991b1b';
            msg.innerHTML = '<i class="fas fa-exclamation-circle"></i> Erro de conexão.';
        });
}

function buscarCep(cep) {
    cep = cep.replace(/\D/g, '');
    if (cep.length !== 8) return;
    fetch('https://viacep.com.br/ws/' + cep + '/json/')
        .then(r => r.json())
        .then(d => {
            if (!d.erro) {
                document.querySelector('[name=endereco]').value = d.logradouro || '';
                document.querySelector('[name=bairro]').value = d.bairro || '';
                document.querySelector('[name=cidade]').value = d.localidade || '';
                document.querySelector('[name=estado]').value = d.uf || '';
            }
        }).catch(() => {});
}
</script>

<?php elseif ($action === 'view' && $cliente): ?>
<div class="page-header">
    <h1><i class="fas fa-user"></i> <?php echo sanitize($cliente['nome_razaosocial']); ?></h1>
    <div style="display:flex;gap:8px;">
        <a href="clientes.php?action=edit&id=<?php echo $id; ?>" class="btn btn-primary"><i class="fas fa-edit"></i> Editar</a>
        <a href="clientes.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
    </div>
</div>
<div class="dashboard-grid">
    <div class="card">
        <div class="card-header"><h3>Dados do Cliente</h3></div>
        <div class="card-body">
            <table style="width:100%;border-collapse:collapse;">
                <tr><td style="padding:8px 0;color:var(--gray-500);width:140px;">Tipo:</td><td><?php echo $cliente['tipo_pessoa']==='juridica'?'Jurídica':'Física'; ?></td></tr>
                <tr><td>CPF/CNPJ:</td><td><?php echo format_cpf_cnpj($cliente['cpf_cnpj']); ?></td></tr>
                <tr><td>E-mail:</td><td><?php echo sanitize($cliente['email']); ?></td></tr>
                <tr><td>Celular:</td><td><?php echo format_phone($cliente['celular']); ?></td></tr>
                <tr><td>Telefone:</td><td><?php echo format_phone($cliente['telefone']); ?></td></tr>
                <tr><td>Cidade:</td><td><?php echo sanitize($cliente['cidade']); ?> / <?php echo sanitize($cliente['estado']); ?></td></tr>
                <tr><td>Status:</td><td><span class="badge-status status-<?php echo $cliente['status']; ?>"><?php echo ucfirst($cliente['status']); ?></span></td></tr>
                <tr><td>Limite:</td><td><?php echo format_currency((float)$cliente['limite_credito']); ?></td></tr>
                <tr><td>Cadastro:</td><td><?php echo format_date($cliente['created_at'], 'd/m/Y'); ?></td></tr>
            </table>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><h3>Orçamentos</h3></div>
        <div class="card-body" style="padding:0;">
            <?php if (empty($cliente_orcamentos)): ?>
            <div class="empty-state-sm">Nenhum orçamento</div>
            <?php else: ?>
            <table class="table table-sm">
                <thead><tr><th>Código</th><th>Total</th><th>Status</th><th>Data</th></tr></thead>
                <tbody>
                    <?php foreach ($cliente_orcamentos as $o): ?>
                    <tr>
                        <td><a href="orcamentos.php?action=view&id=<?php echo $o['id']; ?>" style="color:var(--primary);"><?php echo sanitize($o['codigo']); ?></a></td>
                        <td><?php echo format_currency((float)$o['valor_total']); ?></td>
                        <td><span class="badge-status status-<?php echo $o['status']; ?>"><?php echo ucfirst(str_replace('_',' ',$o['status'])); ?></span></td>
                        <td><?php echo format_date($o['created_at'],'d/m/Y'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php else: ?>

<div class="page-header">
    <h1><i class="fas fa-users"></i> Clientes</h1>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <button onclick="abrirModalImportar()" class="btn btn-success"><i class="fas fa-file-excel"></i> Importar Planilha</button>
        <a href="clientes.php?action=exportar" class="btn btn-outline"><i class="fas fa-file-export"></i> Exportar Planilha</a>
        <a href="clientes.php?action=new" class="btn btn-primary"><i class="fas fa-plus"></i> Novo Cliente</a>
    </div>
</div>

<!-- Modal Importar Planilha de Clientes -->
<div id="modalImportar" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:2000;align-items:center;justify-content:center;padding:20px;">
    <div style="background:#fff;border-radius:var(--radius);width:100%;max-width:520px;padding:28px;position:relative;box-shadow:var(--shadow-lg);">
        <button onclick="fecharModalImportar()" style="position:absolute;top:16px;right:16px;background:var(--gray-100);border:none;width:32px;height:32px;border-radius:50%;cursor:pointer;font-size:1.1rem;display:flex;align-items:center;justify-content:center;">&times;</button>

        <h3 style="font-size:1.125rem;font-weight:700;margin-bottom:6px;"><i class="fas fa-file-excel" style="color:var(--success);margin-right:8px;"></i>Importar Clientes por Planilha</h3>
        <p style="color:var(--gray-500);font-size:0.875rem;margin-bottom:20px;">Envie um arquivo CSV, XLS ou XLSX com os dados dos clientes.</p>

        <div style="margin-bottom:16px;">
            <a href="includes/clientes.csv" download class="btn btn-outline btn-sm" style="font-size:0.8125rem;">
                <i class="fas fa-download"></i> Baixar modelo de planilha
            </a>
        </div>

        <div id="dropZone"
             style="border:2px dashed var(--gray-300);border-radius:var(--radius);padding:40px 20px;text-align:center;transition:all 0.2s;cursor:pointer;background:var(--gray-50);"
             ondragover="event.preventDefault();this.style.borderColor='var(--primary)';this.style.background='var(--primary-light)';"
             ondragleave="this.style.borderColor='var(--gray-300)';this.style.background='var(--gray-50)';"
             ondrop="event.preventDefault();handleDrop(event);"
             onclick="document.getElementById('inputPlanilha').click()">
            <i class="fas fa-cloud-upload-alt" style="font-size:2.5rem;color:var(--gray-400);margin-bottom:12px;display:block;"></i>
            <p style="font-weight:600;color:var(--gray-700);margin-bottom:4px;">Arraste o arquivo aqui</p>
            <p style="font-size:0.8125rem;color:var(--gray-500);">ou clique para selecionar (CSV, XLS, XLSX)</p>
        </div>

        <input type="file" id="inputPlanilha" accept=".csv,.xls,.xlsx" style="display:none;" onchange="handleFileSelect(this)">

        <div id="arquivoSelecionado" style="display:none;margin-top:12px;padding:10px 14px;background:var(--primary-light);border-radius:8px;border:1px solid var(--primary);display:flex;align-items:center;gap:10px;">
            <i class="fas fa-file-csv" style="color:var(--primary);font-size:1.25rem;"></i>
            <div style="flex:1;">
                <p id="nomeArquivo" style="font-weight:600;font-size:0.875rem;color:var(--gray-800);margin:0;"></p>
                <p id="tamanhoArquivo" style="font-size:0.75rem;color:var(--gray-500);margin:0;"></p>
            </div>
            <button onclick="limparArquivo()" style="background:none;border:none;color:var(--danger);cursor:pointer;font-size:0.875rem;padding:4px;"><i class="fas fa-times"></i></button>
        </div>

        <div id="importarLoading" style="display:none;text-align:center;padding:20px;">
            <i class="fas fa-spinner fa-spin" style="font-size:2rem;color:var(--primary);"></i>
            <p style="margin-top:12px;color:var(--gray-500);font-size:0.875rem;">Importando clientes, aguarde...</p>
        </div>

        <div id="importarResultado" style="display:none;margin-top:16px;"></div>

        <div style="display:flex;gap:10px;margin-top:20px;" id="botoesImportar">
            <button onclick="enviarPlanilha()" id="btnEnviar" class="btn btn-success" style="flex:1;" disabled>
                <i class="fas fa-upload"></i> Importar Agora
            </button>
            <button onclick="fecharModalImportar()" class="btn btn-secondary">Cancelar</button>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body" style="padding-bottom:0;">
        <form method="GET" class="filters-bar">
            <div class="form-group"><input type="text" name="busca" value="<?php echo sanitize($busca); ?>" placeholder="Buscar cliente..."></div>
            <div class="form-group">
                <select name="status">
                    <option value="">Todos status</option>
                    <option value="ativo" <?php echo selected($status_filtro,'ativo'); ?>>Ativo</option>
                    <option value="inativo" <?php echo selected($status_filtro,'inativo'); ?>>Inativo</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filtrar</button>
            <?php if ($busca||$status_filtro): ?><a href="clientes.php" class="btn btn-outline">Limpar</a><?php endif; ?>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>Nome</th><th>Tipo</th><th>CPF/CNPJ</th><th>Celular</th><th>Cidade</th><th>Status</th><th>Ações</th></tr></thead>
            <tbody>
                <?php if (empty($clientes)): ?>
                <tr><td colspan="7"><div class="empty-state-sm">Nenhum cliente encontrado</div></td></tr>
                <?php else: ?>
                <?php foreach ($clientes as $c): ?>
                <tr>
                    <td><strong><?php echo sanitize($c['nome_razaosocial']); ?></strong><br><small class="text-muted"><?php echo sanitize($c['email']??''); ?></small></td>
                    <td><?php echo $c['tipo_pessoa']==='juridica'?'Jurídica':'Física'; ?></td>
                    <td><?php echo format_cpf_cnpj($c['cpf_cnpj']); ?></td>
                    <td><?php echo format_phone($c['celular']); ?></td>
                    <td><?php echo sanitize($c['cidade']??''); ?>/<?php echo sanitize($c['estado']??''); ?></td>
                    <td><span class="badge-status status-<?php echo $c['status']; ?>"><?php echo ucfirst($c['status']); ?></span></td>
                    <td>
                        <a href="clientes.php?action=view&id=<?php echo $c['id']; ?>" class="btn btn-sm btn-light" title="Ver"><i class="fas fa-eye"></i></a>
                        <a href="clientes.php?action=edit&id=<?php echo $c['id']; ?>" class="btn btn-sm btn-primary" title="Editar"><i class="fas fa-edit"></i></a>
                        <a href="clientes.php?action=delete&id=<?php echo $c['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Excluir cliente?')"><i class="fas fa-trash"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card-body">
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <small class="text-muted"><?php echo $total; ?> cliente(s) encontrado(s)</small>
            <?php echo pagination_links($pagination, 'clientes.php', array_filter(['busca'=>$busca,'status'=>$status_filtro])); ?>
        </div>
    </div>
</div>

<script>
// ===== IMPORTAÇÃO DE CLIENTES POR PLANILHA =====
let arquivoSelecionado = null;

function abrirModalImportar() {
    document.getElementById('modalImportar').style.display = 'flex';
    limparArquivo();
}

function fecharModalImportar() {
    document.getElementById('modalImportar').style.display = 'none';
    limparArquivo();
}

function handleDrop(e) {
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        processarArquivo(files[0]);
    }
}

function handleFileSelect(input) {
    if (input.files.length > 0) {
        processarArquivo(input.files[0]);
    }
}

function processarArquivo(file) {
    const extensoes = ['csv', 'xls', 'xlsx'];
    const ext = file.name.split('.').pop().toLowerCase();

    if (!extensoes.includes(ext)) {
        alert('Formato não suportado. Use CSV, XLS ou XLSX.');
        return;
    }

    arquivoSelecionado = file;
    document.getElementById('nomeArquivo').textContent = file.name;
    document.getElementById('tamanhoArquivo').textContent = (file.size / 1024).toFixed(1) + ' KB';
    document.getElementById('arquivoSelecionado').style.display = 'flex';
    document.getElementById('dropZone').style.display = 'none';
    document.getElementById('btnEnviar').disabled = false;
}

function limparArquivo() {
    arquivoSelecionado = null;
    document.getElementById('inputPlanilha').value = '';
    document.getElementById('arquivoSelecionado').style.display = 'none';
    document.getElementById('dropZone').style.display = 'block';
    document.getElementById('btnEnviar').disabled = true;
    document.getElementById('importarResultado').style.display = 'none';
}

function enviarPlanilha() {
    if (!arquivoSelecionado) return;

    const formData = new FormData();
    formData.append('planilha', arquivoSelecionado);

    document.getElementById('botoesImportar').style.display = 'none';
    document.getElementById('importarLoading').style.display = 'block';
    document.getElementById('importarResultado').style.display = 'none';

    fetch('clientes.php?action=importar_planilha', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(d => {
        document.getElementById('importarLoading').style.display = 'none';
        document.getElementById('botoesImportar').style.display = 'flex';

        const resultado = document.getElementById('importarResultado');
        resultado.style.display = 'block';

        if (d.ok) {
            let html = `<div style="padding:12px 16px;border-radius:8px;background:#dcfce7;color:#166534;border:1px solid #bbf7d0;font-size:0.875rem;">`;
            html += `<strong><i class="fas fa-check-circle" style="margin-right:6px;"></i>${d.msg}</strong>`;
            if (d.erros && d.erros.length > 0) {
                html += `<ul style="margin-top:8px;margin-bottom:0;padding-left:20px;font-size:0.8rem;">`;
                d.erros.slice(0, 5).forEach(e => html += `<li>${e}</li>`);
                if (d.erros.length > 5) html += `<li>...e mais ${d.erros.length - 5} erro(s)</li>`;
                html += `</ul>`;
            }
            html += `</div>`;
            resultado.innerHTML = html;

            if (d.importados > 0 || d.atualizados > 0) {
                setTimeout(() => window.location.reload(), 2000);
            }
        } else {
            resultado.innerHTML = `<div style="padding:12px 16px;border-radius:8px;background:#fee2e2;color:#991b1b;border:1px solid #fecaca;font-size:0.875rem;"><strong><i class="fas fa-exclamation-circle" style="margin-right:6px;"></i>Erro:</strong> ${d.msg}</div>`;
        }
    })
    .catch(() => {
        document.getElementById('importarLoading').style.display = 'none';
        document.getElementById('botoesImportar').style.display = 'flex';
        document.getElementById('importarResultado').style.display = 'block';
        document.getElementById('importarResultado').innerHTML = `<div style="padding:12px 16px;border-radius:8px;background:#fee2e2;color:#991b1b;border:1px solid #fecaca;font-size:0.875rem;"><strong><i class="fas fa-exclamation-circle" style="margin-right:6px;"></i>Erro de conexão.</strong> Tente novamente.</div>`;
    });
}

// Fechar modal ao clicar fora
document.getElementById('modalImportar').addEventListener('click', function(e) {
    if (e.target === this) fecharModalImportar();
});

// ESC para fechar
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') fecharModalImportar();
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>