<?php require 'config.php'; require_login(); $uid=$_SESSION['uid'];
$t=totals($pdo,$uid); $m=totals($pdo,$uid,date('Y-m-01'),date('Y-m-t'));
$cash=total_cash($pdo,$uid); $safe=safe_to_spend($pdo,$uid); $hs=health_score($pdo,$uid); $al=array_slice(array_filter(build_alerts($pdo,$uid),fn($a)=>$a[0]!=='info'),0,4);
$rec=$pdo->prepare('SELECT * FROM transactions WHERE user_id=? ORDER BY tx_date DESC,id DESC LIMIT 8'); $rec->execute([$uid]);
snapshot_networth($pdo,$uid);
page_head('Dashboard'); ?>
<h1>Welcome, <?= e($_SESSION['name']) ?></h1>
<?php foreach ($al as $a) echo '<div class="alert '.($a[0]==='bad'?'bad':($a[0]==='ok'?'ok':'warn')).'">'.e($a[1]).'</div>'; ?>
<div class="grid">
<div class="card stat"><span>Current balance</span><b class="<?= $t['balance']<0?'neg':'' ?>"><?= money($t['balance']) ?></b></div>
<div class="card stat"><span>Total income</span><b class="pos"><?= money($t['income']) ?></b></div>
<div class="card stat"><span>Total expenses</span><b class="neg"><?= money($t['expense']) ?></b></div>
<div class="card stat"><span>This month (net)</span><b><?= money($m['balance']) ?></b></div></div>
<div class="grid two">
<div class="card"><h2 style="margin-top:0">Safe to spend</h2>
<p class="stat" style="margin:0"><span>Today</span><b><?= money($safe['today']) ?></b></p>
<p class="stat" style="margin:6px 0"><span>This week</span><b><?= money($safe['week']) ?></b></p>
<p class="stat" style="margin:0"><span>This month</span><b class="<?= $safe['month']<0?'neg':'' ?>"><?= money($safe['month']) ?></b></p>
<p class="note">After upcoming bills (<?= money($safe['bills']) ?>) and goal savings (<?= money($safe['goals']) ?>).</p></div>
<div class="card"><h2 style="margin-top:0">Financial health</h2><p class="score"><?= $hs['score'] ?><small>/100</small></p><p class="verdict"><?= e($hs['label']) ?></p>
<?php foreach ($hs['parts'] as $k=>$p) echo '<div class="note">'.e($k).' '.$p[0].'/'.$p[1].'</div>'; ?></div></div>
<p><a class="btn" href="form.php?type=income">+ Income</a> <a class="btn alt" href="form.php?type=expense">+ Expense</a> <button class="btn alt" id="enable-notify" type="button">Turn on notifications</button></p>
<h2>Recent transactions</h2>
<div class="card scroll"><table><tr><th>Date</th><th>Type</th><th>Category</th><th>Description</th><th class="r">Amount</th></tr>
<?php foreach ($rec as $r): ?><tr><td><?= e($r['tx_date']) ?></td><td><span class="tag <?= $r['type'] ?>"><?= $r['type'] ?></span></td><td><?= e($r['category']) ?></td><td><?= e($r['description']) ?></td><td class="r"><?= money($r['amount']) ?></td></tr>
<?php endforeach; ?></table></div>
<?php page_foot();
