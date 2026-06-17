<?php
/**
 * SiteCatalogo2 - Área do Cliente
 * Cadastro
 */
require_once __DIR__ . '/includes/auth.php';

// Já logado? manda para pedidos
if (cliente_logado()) {
    header('Location: pedidos.php'); exit;
}

$page_title = 'Criar Conta';
$hide_sidebar = true;
$cliente_logado_atual = null;

$estados = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];

$erro = '';
$old = $_POST; // repopular formulário em caso de erro

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome      = trim($_POST['nome_razaosocial'] ?? '');
    $tipo_pessoa = ($_POST['tipo_pessoa'] ?? 'fisica') === 'juridica' ? 'juridica' : 'fisica';
    $cpf_cnpj  = preg_replace('/\D/', '', $_POST['cpf_cnpj'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $telefone  = preg_replace('/\D/', '', $_POST['telefone'] ?? '');
    $celular   = preg_replace('/\D/', '', $_POST['celular'] ?? '');
    $cep       = preg_replace('/\D/', '', $_POST['cep'] ?? '');
    $endereco  = trim($_POST['endereco'] ?? '');
    $numero    = trim($_POST['numero'] ?? '');
    $complemento = trim($_POST['complemento'] ?? '');
    $bairro    = trim($_POST['bairro'] ?? '');
    $cidade    = trim($_POST['cidade'] ?? '');
    $estado    = $_POST['estado'] ?? '';
    $senha     = (string)($_POST['senha'] ?? '');
    $senha_confirma = (string)($_POST['senha_confirma'] ?? '');

    if ($nome === '') {
        $erro = 'Informe seu nome completo / razão social.';
    } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Informe um e-mail válido.';
    } elseif ($celular === '') {
        $erro = 'Informe um celular/WhatsApp para contato.';
    } elseif (strlen($senha) < 6) {
        $erro = 'A senha deve ter ao menos 6 caracteres.';
    } elseif ($senha !== $senha_confirma) {
        $erro = 'As senhas não coincidem.';
    } else {
        // Verifica duplicidade por e-mail ou CPF/CNPJ
        $stmt = db()->prepare("SELECT id, senha FROM " . table('clientes') . " WHERE email = ?" . (!empty($cpf_cnpj) ? " OR cpf_cnpj = ?" : "") . " LIMIT 1");
        $params = [$email];
        if (!empty($cpf_cnpj)) $params[] = $cpf_cnpj;
        $stmt->execute($params);
        $existente = $stmt->fetch();

        if ($existente && !empty($existente['senha'])) {
            $erro = 'Já existe uma conta com este e-mail ou CPF/CNPJ. Faça login ou recupere o acesso.';
        } else {
            try {
                $senha_hash = hash_senha_cliente($senha);

                if ($existente) {
                    // Cliente já cadastrado pelo admin/loja, mas sem senha: apenas habilita o acesso
                    db()->prepare("UPDATE " . table('clientes') . " SET senha = ?, nome_razaosocial = ?, tipo_pessoa = ?, telefone = ?, celular = ?, cep = ?, endereco = ?, numero = ?, complemento = ?, bairro = ?, cidade = ?, estado = ? WHERE id = ?")
                        ->execute([$senha_hash, $nome, $tipo_pessoa, $telefone, $celular, $cep, $endereco, $numero, $complemento, $bairro, $cidade, $estado, $existente['id']]);
                    $cliente_id = $existente['id'];
                } else {
                    db()->prepare("INSERT INTO " . table('clientes') . "
                        (nome_razaosocial, tipo_pessoa, cpf_cnpj, email, senha, telefone, celular, cep, endereco, numero, complemento, bairro, cidade, estado, categoria, status, foto)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,'cliente_final','ativo','')")
                        ->execute([$nome, $tipo_pessoa, $cpf_cnpj, $email, $senha_hash, $telefone, $celular, $cep, $endereco, $numero, $complemento, $bairro, $cidade, $estado]);
                    $cliente_id = (int)db()->lastInsertId();
                }

                $stmt_novo = db()->prepare("SELECT * FROM " . table('clientes') . " WHERE id = ?");
                $stmt_novo->execute([$cliente_id]);
                $novo_cliente = $stmt_novo->fetch();

                iniciar_sessao_cliente($novo_cliente);
                header('Location: pedidos.php');
                exit;

            } catch (Exception $e) {
                $erro = 'Erro ao criar conta: ' . $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-wrapper">
    <div class="auth-box auth-box-lg">
        <div class="auth-logo">
            <div class="icon">
                <?php if ($logo_cliente): ?>
                <img src="<?php echo uploads_url($logo_cliente); ?>" alt="<?php echo sanitize($site_name); ?>">
                <?php else: ?>
                <i class="fas fa-store"></i>
                <?php endif; ?>
            </div>
            <div class="name"><?php echo sanitize($site_name); ?></div>
        </div>

        <div class="auth-title">Criar minha conta</div>
        <div class="auth-subtitle">Cadastre-se para acompanhar seus pedidos e orçamentos</div>

        <?php if ($erro): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo sanitize($erro); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-row form-row-2">
                <div class="form-group">
                    <label>Tipo de Pessoa</label>
                    <select name="tipo_pessoa" id="tipo_pessoa" onchange="toggleTipoPessoa(this.value)">
                        <option value="fisica" <?php echo ($old['tipo_pessoa'] ?? 'fisica') === 'fisica' ? 'selected' : ''; ?>>Pessoa Física</option>
                        <option value="juridica" <?php echo ($old['tipo_pessoa'] ?? '') === 'juridica' ? 'selected' : ''; ?>>Pessoa Jurídica</option>
                    </select>
                </div>
                <div class="form-group">
                    <label id="label_cpf_cnpj">CPF</label>
                    <input type="text" name="cpf_cnpj" value="<?php echo sanitize($old['cpf_cnpj'] ?? ''); ?>" placeholder="000.000.000-00">
                </div>
            </div>

            <div class="form-group">
                <label id="label_nome">Nome Completo / Razão Social *</label>
                <input type="text" name="nome_razaosocial" value="<?php echo sanitize($old['nome_razaosocial'] ?? ''); ?>" required>
            </div>

            <div class="form-row form-row-2">
                <div class="form-group">
                    <label>E-mail *</label>
                    <input type="email" name="email" value="<?php echo sanitize($old['email'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label>WhatsApp / Celular *</label>
                    <input type="tel" name="celular" value="<?php echo sanitize($old['celular'] ?? ''); ?>" placeholder="(00) 00000-0000" required>
                </div>
            </div>

            <div class="form-group">
                <label>Telefone (opcional)</label>
                <input type="tel" name="telefone" value="<?php echo sanitize($old['telefone'] ?? ''); ?>" placeholder="(00) 0000-0000">
            </div>

            <div class="form-row form-row-2">
                <div class="form-group">
                    <label>CEP</label>
                    <input type="text" name="cep" id="cep" value="<?php echo sanitize($old['cep'] ?? ''); ?>" placeholder="00000-000" onblur="buscarCep(this.value)">
                </div>
                <div class="form-group">
                    <label>Estado</label>
                    <select name="estado" id="estado">
                        <option value="">—</option>
                        <?php foreach ($estados as $uf): ?>
                        <option value="<?php echo $uf; ?>" <?php echo ($old['estado'] ?? '') === $uf ? 'selected' : ''; ?>><?php echo $uf; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row form-row-2">
                <div class="form-group">
                    <label>Endereço</label>
                    <input type="text" name="endereco" id="endereco" value="<?php echo sanitize($old['endereco'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Número</label>
                    <input type="text" name="numero" value="<?php echo sanitize($old['numero'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-row form-row-2">
                <div class="form-group">
                    <label>Bairro</label>
                    <input type="text" name="bairro" id="bairro" value="<?php echo sanitize($old['bairro'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Cidade</label>
                    <input type="text" name="cidade" id="cidade" value="<?php echo sanitize($old['cidade'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-group">
                <label>Complemento</label>
                <input type="text" name="complemento" value="<?php echo sanitize($old['complemento'] ?? ''); ?>">
            </div>

            <div class="form-row form-row-2">
                <div class="form-group">
                    <label>Senha *</label>
                    <input type="password" name="senha" placeholder="Mínimo 6 caracteres" required>
                </div>
                <div class="form-group">
                    <label>Confirmar Senha *</label>
                    <input type="password" name="senha_confirma" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-lg btn-block">
                <i class="fas fa-user-plus"></i> Criar Conta
            </button>
        </form>

        <div class="auth-footer">
            Já tem conta? <a href="login.php">Entrar</a>
        </div>
    </div>
</div>

<script>
function toggleTipoPessoa(tipo) {
    document.getElementById('label_cpf_cnpj').textContent = tipo === 'juridica' ? 'CNPJ' : 'CPF';
    document.getElementById('label_nome').textContent = tipo === 'juridica' ? 'Razão Social *' : 'Nome Completo *';
}
toggleTipoPessoa(document.getElementById('tipo_pessoa').value);

function buscarCep(cep) {
    cep = cep.replace(/\D/g, '');
    if (cep.length !== 8) return;
    fetch('https://viacep.com.br/ws/' + cep + '/json/')
        .then(r => r.json())
        .then(d => {
            if (!d.erro) {
                document.getElementById('endereco').value = d.logradouro || '';
                document.getElementById('bairro').value = d.bairro || '';
                document.getElementById('cidade').value = d.localidade || '';
                document.getElementById('estado').value = d.uf || '';
            }
        }).catch(() => {});
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
