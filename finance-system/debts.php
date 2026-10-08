<?php require 'config.php'; require_login(); $uid=$_SESSION['uid'];
const KINDS=['credit_card'=>'Credit card','student_loan'=>'Student loan','personal_loan'=>'Personal loan','other'=>'Other debt'];
if ($_SERVER['REQUEST_METHOD']==='POST') { check_csrf();
  if (($_POST['action'] ?? '')==='delete') { $pdo->prepare('DELETE FROM debts WHERE id=? AND user_id=?')->execute([(int)$_POST['id'],$uid]); flash('Debt removed.'); }
  elseif (($_POST['action'] ?? '')==='pay') { $pdo->prepare('UPDATE debts SET balance=GREATEST(0,balance-?) WHERE id=? AND user_id=?')->execute([max(0,(float)$_POST['amount']),(int)$_POST['id'],$uid]); flash('Payment recorded.'); }
  else { $n=mb_substr(trim($_POST['name'] ?? ''),0,80); $b=(float)($_POST['balance'] ?? 0); if ($n===''||$b<=0) flash('Enter a name and a balance above zero.');
    else { $pdo->prepare('INSERT INTO debts(user_id,name,kind,balance,rate,min_payment) VALUES(?,?,?,?,?,?)')->execute([$uid,$n,array_key_exists($_POST['kind'] ?? '',KINDS)?$_POST['kind']:'other',$b,max(0,min(100,(float)($_POST['rate'] ?? 0))),max(0,(float)($_POST['min'] ?? 0))]); flash('Debt added.'); } }
  header('Location: debts.php'); exit; }
$st=$pdo->prepare('SELECT * FROM debts WHERE user_id=? ORDER BY balance'); $st->execute([$uid]); $debts=$st->fetchAll(); $total=array_sum(array_column($debts,'balance'));
$method=($_GET['method'] ?? '')==='avalanche'?'avalanche':'snowball'; $extra=max(0,(float)($_GET['extra'] ?? 0)); $plan=$debts?debt_plan($debts,$extra,$method):null;
page_head('Debts'); ?>
<h1>Debt management</h1>
<div class="card scroll"><table><tr><th>Debt</th><th>Type</th><th class="r">Balance</th><th class="r">Rate</th><th class="r">Interest / month</th><th class="r">Minimum</th><th></th></tr>
<?php foreach ($debts as $d): ?><tr><td><?= e($d['name']) ?></td><td><?= e(KINDS[$d['kind']]) ?></td><td class="r"><?= money($d['balance']) ?></td><td class="r"><?= e($d['rate']) ?>%</td><td class="r"><?= money($d['balance']*$d['rate']/1200) ?></td><td class="r"><?= money($d['min_payment']) ?></td>
<td class="act"><form method="post" class="filters"><?= csrf() ?><input type="hidden" name="action" value="pay"><input type="hidden" name="id" value="<?= $d['id'] ?>"><input type="number" step="0.01" min="0.01" name="amount" placeholder="Paid" style="width:90px"><button class="link" style="color:var(--pri)">Record</button></form>
<form method="post" data-confirm="Remove this debt?"><?= csrf() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $d['id'] ?>"><button class="link">Delete</button></form></td></tr><?php endforeach;
if (!$debts) echo '<tr><td colspan="7">No debts recorded. Good news, or add one below.</td></tr>'; else echo '<tr><th colspan="2">Total debt</th><th class="r">'.money($total).'</th><th colspan="4"></th></tr>'; ?></table></div>
<form method="post" class="card narrow"><h2 style="margin-top:0">Add a debt</h2><?= csrf() ?><label>Name<input name="name" required maxlength="80" placeholder="e.g. HELB loan, Visa card"></label>
<label>Type<select name="kind"><?php foreach (KINDS as $k=>$v) echo '<option value="'.$k.'">'.e($v).'</option>'; ?></select></label>
<label>Balance owed<input type="number" step="0.01" min="0.01" name="balance" required></label><label>Yearly interest rate (%)<input type="number" step="0.01" min="0" name="rate" value="0"></label>
<label>Minimum monthly payment<input type="number" step="0.01" min="0" name="min" value="0"></label><button class="btn">Add debt</button></form>
<?php if ($debts): ?><h2>Payoff plan</h2><?php if (!is_premium()): ?><div class="card">The debt payoff planner (snowball, avalanche and debt-free date) is a Premium feature. <a href="plans.php">See plans</a></div>
<?php else: ?><form class="card filters" method="get"><label style="margin:0">Extra each month<input type="number" step="0.01" min="0" name="extra" value="<?= e($extra) ?>"></label>
<label style="margin:0">Method<select name="method"><option value="snowball" <?= $method==='snowball'?'selected':'' ?>>Snowball (smallest balance first)</option><option value="avalanche" <?= $method==='avalanche'?'selected':'' ?>>Avalanche (highest interest first)</option></select></label><button class="btn">Calculate</button></form>
<?php if ($plan['never']): ?><div class="alert bad">At these payments the debt is not paid off within 50 years. Your minimums may not even cover the interest. Add an extra amount above.</div>
<?php else: $alt=debt_plan($debts,$extra,$method==='snowball'?'avalanche':'snowball'); ?><div class="card"><p class="verdict pos">Debt-free by <?= e(date('F Y',strtotime($plan['free']))) ?></p>
<p><?= $plan['months'] ?> month(s) with the <?= $method ?> method. Total interest: <b><?= money($plan['interest']) ?></b>. The <?= $method==='snowball'?'avalanche':'snowball' ?> method would take <?= $alt['months'] ?> month(s) and cost <?= money($alt['interest']) ?> in interest.</p>
<table><tr><th>Debt</th><th class="r">Paid off in</th></tr><?php foreach ($plan['debts'] as $d) echo '<tr><td>'.e($d['name']).'</td><td class="r">month '.(int)$d['done'].'</td></tr>'; ?></table></div>
<div class="card scroll"><b>Payment schedule (first 12 months)</b><table><tr><th>Month</th><th class="r">Total debt left</th></tr><?php foreach ($plan['sched'] as $r) echo '<tr><td>'.$r['month'].'</td><td class="r">'.money($r['balance']).'</td></tr>'; ?></table></div><?php endif; endif; endif; page_foot();
