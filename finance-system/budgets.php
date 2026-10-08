<?php require 'config.php'; require_login(); $uid=$_SESSION['uid'];
$templates=['student'=>['Rent'=>30,'Food'=>25,'Transport'=>10,'School fees'=>15,'Airtime'=>5,'Entertainment'=>5,'Other'=>10],
 'family'=>['Rent'=>30,'Food'=>25,'Utilities'=>10,'School fees'=>15,'Transport'=>10,'Health'=>5,'Other'=>5],
 'freelancer'=>['Rent'=>25,'Food'=>20,'Transport'=>10,'Utilities'=>10,'Airtime'=>10,'Other'=>25],
 '50-30-20'=>['Rent'=>30,'Food'=>15,'Transport'=>5,'Entertainment'=>10,'Other'=>20]];
if ($_SERVER['REQUEST_METHOD']==='POST') { check_csrf(); $a=$_POST['action'] ?? '';
  if ($a==='delete') { $pdo->prepare('DELETE FROM budgets WHERE id=? AND user_id=?')->execute([(int)$_POST['id'],$uid]); flash('Budget removed.'); }
  elseif ($a==='add') { $cat=mb_substr(trim($_POST['category'] ?? ''),0,50) ?: 'All'; $amt=(float)($_POST['amount'] ?? 0); $per=in_array($_POST['period'] ?? '',['weekly','monthly','semester','custom'])?$_POST['period']:'monthly';
    $sd=$_POST['start'] ?: null; $ed=$_POST['end'] ?: null;
    if ($amt<=0) flash('Enter an amount above zero.'); elseif (in_array($per,['semester','custom']) && (!$sd||!$ed||$ed<$sd)) flash('Pick a start and end date for this period.');
    else { $pdo->prepare('INSERT INTO budgets(user_id,category,period,amount,start_date,end_date,rollover) VALUES(?,?,?,?,?,?,?)')->execute([$uid,$cat,$per,$amt,$sd,$ed,empty($_POST['rollover'])?0:1]); flash('Budget saved.'); } }
  elseif ($a==='template' && isset($templates[$_POST['tpl'] ?? ''])) { $inc=(float)($_POST['income'] ?? 0); if ($inc<=0) flash('Enter your monthly income.'); else {
      foreach ($templates[$_POST['tpl']] as $c=>$p) $pdo->prepare('INSERT INTO budgets(user_id,category,period,amount) VALUES(?,?,?,?)')->execute([$uid,$c,'monthly',round($inc*$p/100,2)]); flash('Template applied. Adjust any line you like.'); } }
  elseif ($a==='suggest') { $st=avg_stats($pdo,$uid); $s=$pdo->prepare("SELECT category,SUM(amount) FROM transactions WHERE user_id=? AND type='expense' AND tx_date BETWEEN ? AND ? GROUP BY category"); $s->execute([$uid,$st['from'],$st['to']]); $n=0;
    foreach ($s->fetchAll(PDO::FETCH_KEY_PAIR) as $c=>$v) { $x=$pdo->prepare("SELECT COUNT(*) FROM budgets WHERE user_id=? AND category=? AND period='monthly'"); $x->execute([$uid,$c]); if ($x->fetchColumn()) continue;
      $pdo->prepare('INSERT INTO budgets(user_id,category,period,amount) VALUES(?,?,?,?)')->execute([$uid,$c,'monthly',round($v/$st['months']*0.95,2)]); $n++; }
    flash($n?"$n budget(s) suggested from your last 3 months (5% below your average).":'No new categories to suggest yet.'); }
  header('Location: budgets.php'); exit; }
$bs=budget_status($pdo,$uid); page_head('Budgets'); ?>
<h1>Budgets</h1>
<div class="card scroll"><table><tr><th>Category</th><th>Period</th><th class="r">Limit</th><th class="r">Spent</th><th class="r">Left</th><th>Progress</th><th></th></tr>
<?php foreach ($bs as $b): $cls=$b['pct']>=100?'over':($b['pct']>=80?'near':'good'); ?><tr><td><?= e($b['category']) ?></td><td><?= e($b['period']) ?><br><span class="note"><?= e($b['from']) ?> to <?= e($b['to']) ?></span></td>
<td class="r"><?= money($b['limit']) ?><?= $b['carry']>0?'<br><span class="note">incl. '.money($b['carry']).' rolled over</span>':'' ?></td><td class="r"><?= money($b['spent']) ?></td><td class="r <?= $b['left']<0?'neg':'' ?>"><?= money($b['left']) ?></td>
<td><?= bar($b['pct'],$cls) ?><span class="note"><?= $b['pct'] ?>%</span></td>
<td><form method="post" data-confirm="Remove this budget?"><?= csrf() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $b['id'] ?>"><button class="link">Remove</button></form></td></tr><?php endforeach; if (!$bs) echo '<tr><td colspan="7">No budgets yet. Add one below, use a template, or let FinTrack suggest them.</td></tr>'; ?></table></div>
<div class="grid two">
<form method="post" class="card"><h2 style="margin-top:0">New budget</h2><?= csrf() ?><input type="hidden" name="action" value="add">
<label>Category <span class="note">(blank = all spending)</span><input name="category" list="cats" maxlength="50"></label>
<datalist id="cats"><option>Food</option><option>Rent</option><option>Transport</option><option>Utilities</option><option>School fees</option><option>Airtime</option><option>Entertainment</option><option>Other</option></datalist>
<label>Amount<input type="number" step="0.01" min="0.01" name="amount" required></label>
<label>Period<select name="period"><option value="monthly">Monthly</option><option value="weekly">Weekly</option><option value="semester">Semester</option><option value="custom">Custom dates</option></select></label>
<label>Start (semester/custom)<input type="date" name="start"></label><label>End (semester/custom)<input type="date" name="end"></label>
<label><input type="checkbox" name="rollover" value="1" style="display:inline;width:auto"> Roll unspent money over to next period (weekly/monthly)</label><button class="btn">Save budget</button></form>
<div><form method="post" class="card"><h2 style="margin-top:0">Budget templates</h2><?= csrf() ?><input type="hidden" name="action" value="template">
<label>Template<select name="tpl"><?php foreach ($templates as $k=>$v) echo '<option value="'.e($k).'">'.e(ucfirst($k)).'</option>'; ?></select></label>
<label>Monthly income<input type="number" step="0.01" name="income" required></label><button class="btn">Allocate automatically</button></form>
<form method="post" class="card"><h2 style="margin-top:0">Automatic suggestions</h2><?= csrf() ?><input type="hidden" name="action" value="suggest"><p class="note">Creates a monthly limit for each category you already spend on, based on your last 3 months.</p><button class="btn alt">Suggest budgets</button> <a href="budget.php">or ask the AI</a></form></div></div>
<?php page_foot();
