<?php require 'config.php'; require_login(); require_premium('Financial forecasting'); $uid=$_SESSION['uid'];
$a=avg_stats($pdo,$uid); $cash=total_cash($pdo,$uid); $fac=['daily'=>30,'weekly'=>4.345,'monthly'=>1,'yearly'=>1/12,'once'=>0];
$r=$pdo->prepare('SELECT type,amount,frequency FROM recurring WHERE user_id=? AND active=1'); $r->execute([$uid]); $recInc=$recExp=0; foreach ($r as $x) { $v=$x['amount']*$fac[$x['frequency']]; if ($x['type']==='income') $recInc+=$v; else $recExp+=$v; }
$predInc=max($a['inc'],$recInc); $predExp=max($a['exp'],$recExp); $net=$predInc-$predExp; $bills=upcoming_bills($pdo,$uid,30); $goals=all_goals($pdo,$uid);
page_head('Forecast'); ?>
<h1>Financial forecast</h1>
<?php if (!$a['has']) echo '<div class="card">Add a few months of transactions and this page will fill in.</div>'; ?>
<div class="grid"><div class="card stat"><span>Predicted income / month</span><b class="pos"><?= money($predInc) ?></b></div><div class="card stat"><span>Predicted spending / month</span><b class="neg"><?= money($predExp) ?></b></div><div class="card stat"><span>Predicted net / month</span><b class="<?= $net<0?'neg':'' ?>"><?= money($net) ?></b></div></div>
<p class="note">Based on your last <?= $a['months'] ?> month(s) of records and your recurring items.</p>
<h2>Future balance</h2><div class="card scroll"><table><tr><th>When</th><th class="r">Predicted balance</th></tr><tr><td>Now</td><td class="r"><?= money($cash) ?></td></tr>
<?php for ($i=1;$i<=6;$i++) { $b=$cash+$net*$i; echo '<tr><td>'.e(date('F Y',strtotime("+$i month"))).'</td><td class="r '.($b<0?'neg':'').'">'.money($b).'</td></tr>'; } ?></table></div>
<?php if ($net<0 && $cash>0): ?><div class="alert bad">At this pace your money runs out in about <?= max(1,(int)floor($cash/abs($net))) ?> month(s).</div><?php endif; ?>
<h2>Upcoming bills (next 30 days)</h2><div class="card scroll"><table><tr><th>Due</th><th>Bill</th><th class="r">Amount</th></tr><?php $bt=0; foreach ($bills as $b) { $bt+=$b['amount']; echo '<tr><td>'.e($b['next_date']).'</td><td>'.e($b['description'] ?: $b['category']).'</td><td class="r">'.money($b['amount']).'</td></tr>'; }
if (!$bills) echo '<tr><td colspan="3">No recurring bills due. Add them on the Recurring page.</td></tr>'; else echo '<tr><th colspan="2">Total</th><th class="r">'.money($bt).'</th></tr>'; ?></table></div>
<h2>Goal completion</h2><div class="card scroll"><table><tr><th>Goal</th><th class="r">Still needed</th><th>Predicted finish</th></tr><?php foreach ($goals as $g) { $n=goal_need($g); $d=$n['need']<=0?'Done':($net>0?date('M Y',strtotime('+'.(int)ceil($n['need']/$net).' months')).(strtotime('+'.(int)ceil($n['need']/$net).' months')>strtotime($g['target_date'])?' (after your deadline)':' (on time)'):'Not at the current pace'); echo '<tr><td>'.e($g['name']).'</td><td class="r">'.money($n['need']).'</td><td>'.e($d).'</td></tr>'; }
if (!$goals) echo '<tr><td colspan="3">No goals yet.</td></tr>'; ?></table></div>
<?php page_foot();
