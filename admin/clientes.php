<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_token($_POST['csrf_token'] ?? null);
    if (($_POST['form_action'] ?? '') === 'delete') {
        db_execute('DELETE FROM users WHERE id = ? AND role = ?', [(int)$_POST['id'], 'client']);
    } elseif (($_POST['form_action'] ?? '') === 'activate') {
        $uid = (int)$_POST['id'];
        $quota = env_int('MONTHLY_TOKEN_QUOTA', 30, 1, 10000);
        db_execute('UPDATE client_profiles SET subscription_status=?, subscription_expires_at=?, tokens_remaining=?, monthly_token_quota=? WHERE user_id=?', ['active', date('Y-m-d H:i:s', strtotime('+30 days')), $quota, $quota, $uid]);
    } else {
        create_or_update_client($_POST, !empty($_POST['id']) ? (int)$_POST['id'] : null);
    }
    header('Location: clientes.php', true, 303); exit;
}

$client = $id ? db_fetch('SELECT u.*, cp.* FROM users u LEFT JOIN client_profiles cp ON cp.user_id=u.id WHERE u.id=? AND u.role=?', [$id, 'client']) : null;
$clients = db_fetch_all('SELECT u.*, cp.subscription_status, cp.subscription_expires_at, cp.tokens_remaining, cp.business_name FROM users u LEFT JOIN client_profiles cp ON cp.user_id=u.id WHERE u.role=? ORDER BY u.id DESC', ['client']);
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Clientes</title><link rel="stylesheet" href="style.css"></head><body><main class="wrap"><?php include __DIR__.'/nav.php'; ?>
<?php if($action==='new' || $action==='edit'): ?><section class="card"><h1><?= $client?'Editar':'Novo' ?> cliente</h1><form method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="id" value="<?= h($client['id'] ?? '') ?>"><div class="form-grid"><label>Nome<input name="name" required value="<?= h($client['name'] ?? '') ?>"></label><label>Empresa<input name="business_name" value="<?= h($client['business_name'] ?? '') ?>"></label><label>E-mail<input name="email" type="email" required value="<?= h($client['email'] ?? '') ?>"></label><label>Telefone<input name="phone" value="<?= h($client['phone'] ?? '') ?>"></label><label>Documento<input name="document" value="<?= h($client['document'] ?? '') ?>"></label><label>Senha<input name="password" type="password" placeholder="<?= $client?'Deixe vazio para manter':'' ?>"></label><label>Status<select name="status"><option value="active">Ativo</option><option value="blocked">Bloqueado</option></select></label><label>Tokens/mês<input name="monthly_token_quota" type="number" value="<?= h($client['monthly_token_quota'] ?? env_value('MONTHLY_TOKEN_QUOTA',30)) ?>"></label></div><p><button class="btn btn-primary">Salvar</button></p></form></section><?php else: ?><h1>Clientes</h1><section class="card"><table><thead><tr><th>Cliente</th><th>Status</th><th>Expira</th><th>Tokens</th><th>Ações</th></tr></thead><tbody><?php foreach($clients as $c): ?><tr><td><?= h($c['name']) ?><br><small><?= h($c['email']) ?></small></td><td><?= h($c['subscription_status'] ?? 'inactive') ?></td><td><?= h($c['subscription_expires_at'] ?? '-') ?></td><td><?= h($c['tokens_remaining'] ?? 0) ?></td><td><a class="btn" href="?action=edit&id=<?= h($c['id']) ?>">Editar</a><form method="post" style="display:inline"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="form_action" value="activate"><input type="hidden" name="id" value="<?= h($c['id']) ?>"><button class="btn">Liberar 30 dias</button></form><form method="post" style="display:inline" onsubmit="return confirm('Excluir cliente?')"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="form_action" value="delete"><input type="hidden" name="id" value="<?= h($c['id']) ?>"><button class="btn">Excluir</button></form></td></tr><?php endforeach; ?></tbody></table></section><?php endif; ?></main></body></html>
