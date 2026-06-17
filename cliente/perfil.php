<?php
/**
 * SiteCatalogo2 - Área do Cliente
 * Meus Dados / Alterar Senha
 */
require_once __DIR__ . '/includes/auth.php';

exigir_login_cliente();
$cliente_logado_atual = cliente_logado();
$page_active = 'perfil';
$page_title = 'Meus Dados';

$estados = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];

$erro_dados = ''; $sucesso_dados = '';
$erro_senha = ''; $sucesso_senha = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'atualizar_dados') {
        $nome      = trim($_POST['nome_razaosocial'] ?? '');
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

        if ($nome === '') {
            $erro_dados = 'Informe seu nome / razão social.';
        } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erro_dados = 'Informe um e-mail válido.';
        } else {
            // Verifica se e-mail já é usado por outro cliente
            $stmt_check = db()->prepare("SELECT id FROM " . table('clientes') . " WHERE email = ? AND id != ? LIMIT 1");
            $stmt_check->execute([$email, $cliente_logado_atual['id']]);
            if ($stmt_check->fetchColumn()) {
                $erro_dados = 'Este e-mail já está sendo usado por outra conta.';
            } else {
                try {
                    db()->prepare("UPDATE " . table('clientes') . " SET nome_razaosocial=?, email=?, telefone=?, celular=?, cep=?, endereco=?, numero=?, complemento=?, bairro=?, cidade=?, estado=? WHERE id=?")
                        ->execute([$nome, $email, $telefone, $celular, $cep, $endereco, $numero, $complemento, $bairro, $cidade, $estado, $cliente_logado_atual['id']]);
                    $_SESSION['cliente_nome'] = $nome;
                    $sucesso_dados = 'Dados atualizados com sucesso!';
                    // Recarrega dados atualizados
                    $stmt_re = db()->prepare("SELECT * FROM " . table('clientes') . " WHERE id = ?");
                    $stmt_re->execute([$cliente_logado_atual['id']]);
                    $cliente_logado_atual = $stmt_re->fetch();
                } catch (Exception $e) {
                    $erro_dados = 'Erro ao atualizar: ' . $e->getMessage();
                }
            }
        }
    }

    if ($acao === 'alterar_senha') {
        $senha_atual = (string)($_POST['senha_atual'] ?? '');
        $senha_nova  = (string)($_POST['senha_nova'] ?? '');
        $senha_confirma = (string)($_POST['senha_confirma'] ?? '');

        if (empty($cliente_logado_atual['senha']) || !password_verify($senha_atual, $cliente_logado_atual['senha'])) {
            $erro_senha = 'Senha atual incorreta.';
        } elseif (strlen($senha_nova) < 6) {
            $erro_senha = 'A nova senha deve ter ao menos 6 caracteres.';
        } elseif ($senha_nova !== $senha_confirma) {
            $erro_senha = 'As senhas não coincidem.';
        } else {
            try {
                db()->prepare("UPDATE " . table('clientes') . " SET senha = ? WHERE id = ?")
                    ->execute([hash_senha_cliente($senha_nova), $cliente_logado_atual['id']]);
                $sucesso_senha = 'Senha alterada com sucesso!';
                $stmt_re = db()->prepare("SELECT * FROM " . table('clientes') . " WHERE id = ?");
                $stmt_re->execute([$cliente_logado_atual['id']]);
                $cliente_logado_atual = $stmt_re->fetch();
            } catch (Exception $e) {
                $erro_senha = 'Erro ao alterar senha: ' . $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-user-cog"></i> Meus Dados</h1>
</div>

<div class="card">
    <div class="card-header"><h3><i class="fas fa-id-card"></i> Informações Pessoais</h3></div>
    <div class="card-body">
        <?php if ($erro_dados): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo sanitize($erro_dados); ?></div>
        <?php endif; ?>
        <?php if ($sucesso_dados): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo sanitize($sucesso_dados); ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="acao" value="atualizar_dados">

            <div class="form-row form-row-2">
                <div class="form-group">
                    <label><?php echo $cliente_logado_atual['tipo_pessoa']==='juridica' ? 'Razão Social' : 'Nome Completo'; ?></label>
                    <input type="text" name="nome_razaosocial" value="<?php echo sanitize($cliente_logado_atual['nome_razaosocial']); ?>" required>
                </div>
                <div class="form-group">
                    <label><?php echo $cliente_logado_atual['tipo_pessoa']==='juridica' ? 'CNPJ' : 'CPF'; ?> (não editável)</label>
                    <input type="text" value="<?php echo format_cpf_cnpj($cliente_logado_atual['cpf_cnpj'] ?? ''); ?>" readonly style="background:var(--gray-100);">
                </div>
            </div>

            <div class="form-row form-row-2">
                <div class="form-group">
                    <label>E-mail</label>
                    <input type="email" name="email" value="<?php echo sanitize($cliente_logado_atual['email']); ?>" required>
                </div>
                <div class="form-group">
                    <label>WhatsApp / Celular</label>
                    <input type="tel" name="celular" value="<?php echo sanitize($cliente_logado_atual['celular'] ?? ''); ?>" placeholder="(00) 00000-0000">
                </div>
            </div>

            <div class="form-group">
                <label>Telefone</label>
                <input type="tel" name="telefone" value="<?php echo sanitize($cliente_logado_atual['telefone'] ?? ''); ?>" placeholder="(00) 0000-0000">
            </div>

            <div class="form-row form-row-2">
                <div class="form-group">
                    <label>CEP</label>
                    <input type="text" name="cep" id="cep" value="<?php echo sanitize($cliente_logado_atual['cep'] ?? ''); ?>" placeholder="00000-000" onblur="buscarCep(this.value)">
                </div>
                <div class="form-group">
                    <label>Estado</label>
                    <select name="estado" id="estado">
                        <option value="">—</option>
                        <?php foreach ($estados as $uf): ?>
                        <option value="<?php echo $uf; ?>" <?php echo ($cliente_logado_atual['estado'] ?? '') === $uf ? 'selected' : ''; ?>><?php echo $uf; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row form-row-2">
                <div class="form-group">
                    <label>Endereço</label>
                    <input type="text" name="endereco" id="endereco" value="<?php echo sanitize($cliente_logado_atual['endereco'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Número</label>
                    <input type="text" name="numero" value="<?php echo sanitize($cliente_logado_atual['numero'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-row form-row-2">
                <div class="form-group">
                    <label>Bairro</label>
                    <input type="text" name="bairro" id="bairro" value="<?php echo sanitize($cliente_logado_atual['bairro'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Cidade</label>
                    <input type="text" name="cidade" id="cidade" value="<?php echo sanitize($cliente_logado_atual['cidade'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-group">
                <label>Complemento</label>
                <input type="text" name="complemento" value="<?php echo sanitize($cliente_logado_atual['complemento'] ?? ''); ?>">
            </div>

            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar Dados</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3><i class="fas fa-lock"></i> Alterar Senha</h3></div>
    <div class="card-body">
        <?php if ($erro_senha): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo sanitize($erro_senha); ?></div>
        <?php endif; ?>
        <?php if ($sucesso_senha): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo sanitize($sucesso_senha); ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="acao" value="alterar_senha">
            <div class="form-group">
                <label>Senha Atual</label>
                <input type="password" name="senha_atual" required>
            </div>
            <div class="form-row form-row-2">
                <div class="form-group">
                    <label>Nova Senha</label>
                    <input type="password" name="senha_nova" placeholder="Mínimo 6 caracteres" required>
                </div>
                <div class="form-group">
                    <label>Confirmar Nova Senha</label>
                    <input type="password" name="senha_confirma" required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-key"></i> Alterar Senha</button>
        </form>
    </div>
</div>

<script>
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
