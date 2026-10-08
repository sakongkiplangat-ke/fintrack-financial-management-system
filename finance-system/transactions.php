<?php require 'config.php'; require_login(); $uid=$_SESSION['uid'];
if ($_SERVER['REQUEST_METHOD']==='POST') {
  check_csrf();
  $pdo->prepare('DELETE FROM transactions WHERE id=? AND user_id=?')->execute([(int)$_POST['id'],$uid]); audit($pdo,$uid,'tx_delete','#'.(int)$_POST['id']);
  flash('Transaction deleted.'); header('Location: transactions.php'); exit;
}
$q=trim($_GET['q'] ?? ''); $type=$_GET['type'] ?? ''; $from=$_GET['from'] ?? ''; $to=$_GET['to'] ?? '';
$sql='SELECT t.*,a.name AS acct FROM transactions t LEFT JOIN accounts a ON a.id=t.account_id WHERE t.user_id=?'; $p=[$uid];
if ($q!=='') { $sql.=' AND (t.category LIKE ? OR t.description LIKE ?)'; $p[]="%$q%"; $p[]="%$q%"; }
if (in_array($type,['income','expense'])) { $sql.=' AND t.type=?'; $p[]=$type; }
if ($from) { $sql.=' AND t.tx_date>=?'; $p[]=$from; }
if ($to)   { $sql.=' AND t.tx_date<=?'; $p[]=$to; }
$st=$pdo->prepare($sql.' ORDER BY t.tx_date DESC,t.id DESC'); $st->execute($p); $rows=$st->fetchAll();
page_head('Transactions'); ?>
<h1>Transactions</h1>
<form class="card filters" method="get">
<input name="q" placeholder="Search category or description" value="<?= e($q) ?>">
<select name="type"><option value="">All types</option><option value="income" <?= $type==='income'?'selected':'' ?>>Income</option><option value="expense" <?= $type==='expense'?'selected':'' ?>>Expense</option></select>
<input type="date" name="from" value="<?= e($from) ?>"><input type="date" name="to" value="<?= e($to) ?>">
<button class="btn">Filter</button><a class="btn alt" href="transactions.php">Reset</a></form>
<div class="card scroll"><table><tr><th>Date</th><th>Type</th><th>Category</th><th>Description</th><th>Account</th><th class="r">Amount</th><th>Actions</th></tr>
<?php foreach ($rows as $r): ?><tr><td><?= e($r['tx_date']) ?></td><td><span class="tag <?= $r['type'] ?>"><?= $r['type'] ?></span></td><td><?= e($r['category']) ?></td><td><?= e($r['description']) ?><?= $r['split_group']?' <span class="pill">split</span>':'' ?><?= $r['notes']?' <span title="Has notes">&#128221;</span>':'' ?><?= $r['receipt']?' <a href="receipt.php?id='.$r['id'].'" target="_blank" title="Receipt">&#128206;</a>':'' ?></td><td><?= e($r['acct'] ?? '') ?></td><td class="r"><?= money($r['amount']) ?></td>
<td class="act"><a href="form.php?id=<?= $r['id'] ?>">Edit</a>
<form method="post" data-confirm="Delete this transaction?"><?= csrf() ?><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="link">Delete</button></form></td></tr>
<?php endforeach; if (!$rows) echo '<tr><td colspan="7">No transactions found.</td></tr>'; ?></table></div>
<?php page_foot();
