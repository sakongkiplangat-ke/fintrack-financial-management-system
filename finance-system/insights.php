<?php require 'config.php'; require_login(); $uid=$_SESSION['uid'];

$err=[];

// ---- Dates ----
$today=date('Y-m-d'); $mStart=date('Y-m-01'); $mEnd=date('Y-m-t');
$daysLeft=(int)date('t')-(int)date('j')+1;                       // includes today
$prevStart=date('Y-m-01',strtotime('first day of last month')); $prevEnd=date('Y-m-t',strtotime('first day of last month'));
$avgFrom=date('Y-m-01',strtotime('first day of -3 months')); $avgTo=$prevEnd;   // last 3 full months

function cat_spend($pdo,$uid,$f,$t){
  $s=$pdo->prepare("SELECT category,SUM(amount) FROM transactions WHERE user_id=? AND type='expense' AND tx_date BETWEEN ? AND ? GROUP BY category ORDER BY 2 DESC");
  $s->execute([$uid,$f,$t]); return array_map('floatval',$s->fetchAll(PDO::FETCH_KEY_PAIR));
}

// ---- Numbers every section uses ----
$mo=$pdo->prepare('SELECT COUNT(DISTINCT DATE_FORMAT(tx_date,"%Y-%m")) FROM transactions WHERE user_id=? AND tx_date BETWEEN ? AND ?');
$mo->execute([$uid,$avgFrom,$avgTo]); $months=max(1,(int)$mo->fetchColumn());
$avg=totals($pdo,$uid,$avgFrom,$avgTo); $avgInc=$avg['income']/$months; $avgExp=$avg['expense']/$months; $avgNet=$avgInc-$avgExp;
$hasHistory=($avg['income']+$avg['expense'])>0;
$cur=totals($pdo,$uid,$mStart,$mEnd); $todaySpent=totals($pdo,$uid,$today,$today)['expense'];
$expectedInc=max($avgInc,$cur['income']);

$g=$pdo->prepare('SELECT * FROM goals WHERE user_id=? ORDER BY priority,target_date LIMIT 1'); $g->execute([$uid]); $goal=$g->fetch();
$setAside=0; $monthsLeft=0; $proj=0; $need=0;
if ($goal) {
  $monthsLeft=max(0.1,(strtotime($goal['target_date'])-time())/(30.44*86400));
  $need=max(0,(float)$goal['target_amount']-(float)$goal['saved_amount']);
  $setAside=min($need,$need/$monthsLeft);                          // what to put away this month
  $proj=(float)$goal['saved_amount']+$avgNet*$monthsLeft;
}
$left=$expectedInc-$cur['expense']-$setAside;                      // free to spend for the rest of this month
$pool=$expectedInc-($cur['expense']-$todaySpent)-$setAside;        // free before today's spending
$daily=$pool/$daysLeft; $remainToday=$daily-$todaySpent;

page_head('Insights'); ?>
<h1>Insights</h1>
<?php if (!$hasHistory): ?><div class="card">Add some income and expenses from the last few months and these answers will fill in. <a href="form.php">Add a transaction</a></div><?php endif; ?>

<h2>Where is my money going?</h2>
<?php $now=cat_spend($pdo,$uid,$mStart,$mEnd); $prev=cat_spend($pdo,$uid,$prevStart,$prevEnd); $tot=array_sum($now); ?>
<div class="card scroll"><table><tr><th>Category (this month)</th><th class="r">Spent</th><th>Share</th><th class="r">Last month</th></tr>
<?php foreach ($now as $c=>$v): $pct=$tot>0?round($v/$tot*100):0; ?>
<tr><td><?= e($c) ?></td><td class="r"><?= money($v) ?></td><td><div class="meter"><i style="width:<?= $pct ?>%"></i></div><span class="note"><?= $pct ?>%</span></td><td class="r"><?= money($prev[$c] ?? 0) ?></td></tr>
<?php endforeach; if (!$now) echo '<tr><td colspan="4">No spending recorded this month yet.</td></tr>'; ?></table></div>

<h2>Can I afford this?</h2>
<?php $price=max(0,(float)($_GET['price'] ?? 0)); ?>
<form class="card filters" method="get"><input type="number" name="price" min="0" step="0.01" placeholder="Price in Ksh" value="<?= $price?e($price):'' ?>"><button class="btn">Check</button></form>
<?php if ($price>0): $after=$left-$price; ?>
<div class="card">
<?php if ($after>=0.1*$expectedInc): ?><p class="verdict pos">Yes, you can afford it.</p>
<?php elseif ($after>=0): ?><p class="verdict warn">Yes, but it will be tight.</p>
<?php else: ?><p class="verdict neg">Not this month.</p><?php endif; ?>
<p>You have <b><?= money(max(0,$left)) ?></b> free to spend this month. After this purchase you would have <b class="<?= $after<0?'neg':'' ?>"><?= money($after) ?></b>.</p>
<?php if ($after<0 && $avgNet>0): ?><p class="note">At your usual saving of <?= money($avgNet) ?> a month, you could afford it in about <?= (int)ceil($price/$avgNet) ?> month(s).</p><?php endif; ?></div>
<?php endif; ?>

<h2>How much can I spend today?</h2>
<div class="card"><?php if ($pool<=0): ?><p class="verdict neg">Nothing left to spend freely this month.</p><p class="note">Your income, spending so far and savings goal already use up this month's money.</p>
<?php else: ?><p class="verdict"><?= money(max(0,$remainToday)) ?> left today</p>
<p>Your daily allowance is <b><?= money($daily) ?></b> (<?= $daysLeft ?> day(s) left including today). You have spent <?= money($todaySpent) ?> today.<?= $remainToday<0?' <b class="neg">You are over today\'s allowance, so tomorrow will be a little tighter.</b>':'' ?></p><?php endif; ?>
<p class="note">Expected income this month: <?= money($expectedInc) ?>, based on your last <?= $months ?> month(s) and what you have received so far.</p></div>

<h2>Will I reach my savings goal?</h2>
<?php if ($goal): $pct=min(100,round((float)$goal['saved_amount']/(float)$goal['target_amount']*100)); $short=max(0,(float)$goal['target_amount']-$proj); ?>
<div class="card"><b><?= e($goal['name']) ?></b> &middot; <?= money($goal['target_amount']) ?> by <?= e($goal['target_date']) ?>
<div class="meter" style="margin:8px 0"><i style="width:<?= $pct ?>%"></i></div><span class="note">Saved so far: <?= money($goal['saved_amount']) ?> (<?= $pct ?>%)</span>
<?php if ($proj>=(float)$goal['target_amount']): ?><p class="verdict pos">Yes, you are on track.</p><p>At your current pace you would have about <?= money($proj) ?> by the target date.</p>
<?php else: ?><p class="verdict neg">Not at your current pace.</p><p>You would reach about <?= money(max(0,$proj)) ?>, which is <b><?= money($short) ?></b> short. To close the gap, save <b><?= money($short/$monthsLeft) ?></b> more each month.</p><?php endif; ?>
</div>
<?php endif; ?>
<p><a class="btn" href="goals.php">Manage all savings goals</a></p>

<h2>What should I change?</h2>
<?php $flex=array_filter(cat_spend($pdo,$uid,$avgFrom,$avgTo),fn($k)=>!in_array(strtolower($k),['rent','utilities','school fees']),ARRAY_FILTER_USE_KEY); $top=array_slice($flex,0,3,true); ?>
<div class="card">
<?php if (!$hasHistory): ?><p>Nothing to suggest yet. Record a few months of transactions first.</p><?php else: ?>
<?php if ($avgExp>$avgInc): ?><p class="neg"><b>You spend more than you earn:</b> about <?= money($avgExp-$avgInc) ?> more each month. Cutting spending comes before saving.</p><?php endif; ?>
<?php if ($top): ?><p>Your biggest flexible categories, per month:</p><ul>
<?php foreach ($top as $c=>$v): $m=$v/$months; ?><li><b><?= e($c) ?></b>: <?= money($m) ?>. Trimming 10% frees <?= money($m*0.1) ?>; 20% frees <?= money($m*0.2) ?>.</li><?php endforeach; ?></ul>
<?php if ($goal && $proj<(float)$goal['target_amount']): $gap=((float)$goal['target_amount']-$proj)/$monthsLeft; ?><p>To reach <b><?= e($goal['name']) ?></b> you need about <b><?= money($gap) ?></b> more a month. Cutting these three categories by 20% would free <?= money(array_sum($top)/$months*0.2) ?>.</p><?php endif; ?>
<?php else: ?><p>You have no flexible spending categories to trim. Look at the fixed costs instead.</p><?php endif; ?>
<p class="note">Want a full category-by-category plan? Try <a href="budget.php">AI Budget</a>.</p><?php endif; ?></div>
<?php page_foot();
