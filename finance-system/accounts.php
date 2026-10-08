<?php require 'config.php'; require_login(); $uid=$_SESSION['uid'];
if ($_SERVER['REQUEST_METHOD']==='POST') { check_csrf();
  if (($_POST['action'] ?? '')==='delete') { $pdo->prepare('UPDATE transactions SET account_id=NULL WHERE account_id=? AND user_id=?')->execute([(int)$_POST['id'],$uid]); $pdo->prepare('DELETE FROM accounts WHERE id=? AND user_id=?')->execute([(int)$_POST['id'],$uid]); flash('Account removed.'); }
  else { $n=mb_substr(trim($_POST['name'] ?? ''),0,80); $ty=array_key_exists($_POST['type'] ?? '',ACCOUNT_TYPES)?$_POST['type']:'bank';
    if ($n==='') flash('Give the account a name.'); else { $pdo->prepare('INSERT INTO accounts(user_id,name,type,opening_balance) VALUES(?,?,?,?)')->execute([$uid,$n,$ty,(float)($_POST['opening'] ?? 0)]); flash('Account added.'); } }
  header('Location: accounts.php'); exit; }
$rows=account_balances($pdo,$uid); $un=unassigned_net($pdo,$uid); $tot=$un; foreach ($rows as $r) $tot+=(float)$r['bal'];
page_head('Accounts'); ?>
<h1>Accounts</h1><p class="note">Bank, cash, savings, credit cards and mobile wallets. Pick an account when you add a transaction and its balance updates on its own.</p>
<div class="card scroll"><table><tr><th>Account</th><th>Type</th><th class="r">Balance</th><th></th></tr>
<?php foreach ($rows as $r): ?><tr><td><?= e($r['name']) ?></td><td><?= e(ACCOUNT_TYPES[$r['type']] ?? $r['type']) ?></td><td class="r <?= $r['bal']<0?'neg':'' ?>"><?= money($r['bal']) ?></td>
<td><form method="post" data-confirm="Remove this account? Its transactions are kept."><?= csrf() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="link">Remove</button></form></td></tr><?php endforeach; ?>
<tr><td>Transactions with no account</td><td></td><td class="r"><?= money($un) ?></td><td></td></tr>
<tr><th colspan="2">Total across everything</th><th class="r"><?= money($tot) ?></th><th></th></tr></table></div>
<form method="post" class="card narrow"><h2 style="margin-top:0">Add an account</h2><?= csrf() ?>
<label>Name<input name="name" required maxlength="80" placeholder="e.g. Equity Bank, M-Pesa"></label>
<label>Type<select name="type"><?php foreach (ACCOUNT_TYPES as $k=>$v) echo '<option value="'.$k.'">'.e($v).'</option>'; ?></select></label>
<label>Opening balance<input type="number" step="0.01" name="opening" value="0"></label><button class="btn">Add account</button></form>
<?php page_foot();
