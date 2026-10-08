<?php require 'config.php'; require_login(); require_premium('Financial simulator'); $uid=$_SESSION['uid'];
$a=avg_stats($pdo,$uid); $cash=total_cash($pdo,$uid); $sc=$_GET['s'] ?? 'buy'; $v=max(0,(float)($_GET['v'] ?? 0)); $res=null;
if ($v>0 || isset($_GET['pct'])) { $base=$a['net']; $new=$base; $note=''; $once=0;
  if ($sc==='buy') { $once=$v; $note='A one-time purchase of '.money($v).'.'; }
  elseif ($sc==='income') { $p=(float)($_GET['pct'] ?? 0); $new=$base+$a['inc']*$p/100; $note='Your income changes by '.$p.'% ('.money($a['inc']*$p/100).' a month).'; }
  elseif ($sc==='rent') { $new=$base-$v; $note='Your rent goes up by '.money($v).' a month.'; }
  elseif ($sc==='loan') { $r=max(0,(float)($_GET['rate'] ?? 0)); $mo=max(1,(int)($_GET['months'] ?? 12)); $pm=monthly_payment($v,$r,$mo); $new=$base-$pm; $once=-$v; $note='A loan of '.money($v).' at '.$r.'% over '.$mo.' months costs '.money($pm).' a month, '.money($pm*$mo-$v).' in interest.'; }
  elseif ($sc==='save') { $new=$base-$v; $note='You set aside '.money($v).' more each month.'; }
  $res=['base'=>$base,'new'=>$new,'note'=>$note,'b12'=>$cash+$base*12,'n12'=>$cash+$new*12-$once]; }
page_head('Simulator'); ?>
<h1>What-if simulator</h1><p class="note">Based on your usual month: income <?= money($a['inc']) ?>, spending <?= money($a['exp']) ?>, net <?= money($a['net']) ?>. Balance now: <?= money($cash) ?>.</p>
<form class="card filters" method="get"><select name="s" onchange="this.form.submit()"><?php foreach (['buy'=>'What if I buy this?','income'=>'What if my income changes?','rent'=>'What if my rent increases?','loan'=>'What if I take a loan?','save'=>'What if I save more?'] as $k=>$l) echo '<option value="'.$k.'"'.($sc===$k?' selected':'').'>'.$l.'</option>'; ?></select>
<?php if ($sc==='income'): ?><input type="number" step="1" name="pct" placeholder="Change in % (e.g. -20 or 15)" value="<?= e($_GET['pct'] ?? '') ?>"><?php else: ?><input type="number" step="0.01" min="0" name="v" placeholder="<?= ['buy'=>'Price','rent'=>'Rent increase per month','loan'=>'Loan amount','save'=>'Extra saving per month'][$sc] ?>" value="<?= $v?:'' ?>"><?php endif; ?>
<?php if ($sc==='loan'): ?><input type="number" step="0.01" name="rate" placeholder="Yearly rate %" value="<?= e($_GET['rate'] ?? '') ?>"><input type="number" name="months" placeholder="Months" value="<?= e($_GET['months'] ?? '12') ?>"><?php endif; ?><button class="btn">Simulate</button></form>
<?php if ($res): ?><div class="card"><p><?= e($res['note']) ?></p>
<table><tr><th></th><th class="r">Today's path</th><th class="r">With this change</th></tr><tr><td>Net per month</td><td class="r"><?= money($res['base']) ?></td><td class="r <?= $res['new']<0?'neg':'' ?>"><?= money($res['new']) ?></td></tr>
<tr><td>Balance in 12 months</td><td class="r"><?= money($res['b12']) ?></td><td class="r <?= $res['n12']<0?'neg':'' ?>"><?= money($res['n12']) ?></td></tr></table>
<?php if ($res['new']<0): ?><p class="neg"><b>This would make you spend more than you earn.</b></p><?php elseif ($res['n12']<$res['b12']*0.5 && $res['b12']>0): ?><p class="warn"><b>It would take a big bite out of your savings.</b></p><?php else: ?><p class="pos"><b>This looks manageable.</b></p><?php endif; ?></div><?php endif; page_foot();
