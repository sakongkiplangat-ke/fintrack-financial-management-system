<?php require 'config.php'; require_login(); $uid=$_SESSION['uid'];
if ($_SERVER['REQUEST_METHOD']==='POST') { check_csrf(); $a=$_POST['action'] ?? '';
  if ($a==='delete') $pdo->prepare('DELETE FROM recurring WHERE id=? AND user_id=?')->execute([(int)$_POST['id'],$uid]);
  elseif ($a==='toggle') $pdo->prepare('UPDATE recurring SET active=1-active WHERE id=? AND user_id=?')->execute([(int)$_POST['id'],$uid]);
  else { $d=DateTime::createFromFormat('Y-m-d',$_POST['next'] ?? ''); $amt=(float)($_POST['amount'] ?? 0); $cat=trim($_POST['category'] ?? '') ?: auto_category($pdo,$uid,$_POST['description'] ?? '');
    $fr=in_array($_POST['frequency'] ?? '',['once','daily','weekly','monthly','yearly'])?$_POST['frequency']:'monthly';
    if (!$d||$amt<=0||$cat==='') { flash('Fill in the amount, a category and the first date.'); }
    else { $pdo->prepare('INSERT INTO recurring(user_id,type,category,amount,description,account_id,frequency,next_date) VALUES(?,?,?,?,?,?,?,?)')
      ->execute([$uid,($_POST['type'] ?? '')==='income'?'income':'expense',$cat,$amt,mb_substr(trim($_POST['description'] ?? ''),0,255),(int)($_POST['account_id'] ?? 0) ?: null,$fr,$d->format('Y-m-d')]); flash('Saved. It will be recorded automatically on the due date.'); } }
  header('Location: recurring.php'); exit; }
$st=$pdo->prepare('SELECT * FROM recurring WHERE user_id=? ORDER BY active DESC,next_date'); $st->execute([$uid]); $rows=$st->fetchAll(); $accts=account_balances($pdo,$uid);
page_head('Recurring & scheduled'); ?>
<h1>Recurring &amp; scheduled transactions</h1><p class="note">Salary, rent, subscriptions and one-off future payments. FinTrack adds each one by itself on its due date, and warns you before bills arrive.</p>
<div class="card scroll"><table><tr><th>Next date</th><th>Type</th><th>What</th><th>How often</th><th class="r">Amount</th><th></th></tr>
<?php foreach ($rows as $r): ?><tr style="<?= $r['active']?'':'opacity:.5' ?>"><td><?= e($r['next_date']) ?></td><td><span class="tag <?= $r['type'] ?>"><?= $r['type'] ?></span></td><td><?= e($r['description'] ?: $r['category']) ?> <span class="note">(<?= e($r['category']) ?>)</span></td><td><?= e($r['frequency']) ?></td><td class="r"><?= money($r['amount']) ?></td>
<td class="act"><form method="post"><?= csrf() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="link" style="color:var(--pri)"><?= $r['active']?'Pause':'Resume' ?></button></form>
<form method="post" data-confirm="Delete this?"><?= csrf() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="link">Delete</button></form></td></tr><?php endforeach; if (!$rows) echo '<tr><td colspan="6">Nothing scheduled yet.</td></tr>'; ?></table></div>
<form method="post" class="card narrow" data-validate><h2 style="margin-top:0">Add recurring or scheduled</h2><?= csrf() ?>
<label>Type<select name="type"><option value="expense">Expense</option><option value="income">Income</option></select></label>
<label>Description<input name="description" maxlength="255" placeholder="e.g. Rent, Netflix, Salary"></label>
<label>Category <span class="note">(blank = sort automatically)</span><input name="category" maxlength="50"></label>
<label>Amount<input type="number" name="amount" step="0.01" min="0.01" required></label>
<label>Account<select name="account_id"><option value="">No account</option><?php foreach ($accts as $a) echo '<option value="'.$a['id'].'">'.e($a['name']).'</option>'; ?></select></label>
<label>How often<select name="frequency"><option value="monthly">Monthly</option><option value="weekly">Weekly</option><option value="daily">Daily</option><option value="yearly">Yearly</option><option value="once">One time (scheduled)</option></select></label>
<label>First / next date<input type="date" name="next" required value="<?= date('Y-m-d') ?>"></label><button class="btn">Save</button></form>
<?php page_foot();
