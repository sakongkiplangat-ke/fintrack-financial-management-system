<?php require 'config.php'; require_login(); $uid=$_SESSION['uid'];
if ($_SERVER['REQUEST_METHOD']==='POST') { check_csrf(); $a=$_POST['action'] ?? '';
  if ($a==='delete') { $pdo->prepare('DELETE FROM goals WHERE id=? AND user_id=?')->execute([(int)$_POST['id'],$uid]); flash('Goal removed.'); }
  elseif ($a==='add_money') { $amt=(float)($_POST['amount'] ?? 0); if ($amt>0) { $pdo->prepare('UPDATE goals SET saved_amount=saved_amount+? WHERE id=? AND user_id=?')->execute([$amt,(int)$_POST['id'],$uid]); flash('Savings added.'); } }
  else { $d=DateTime::createFromFormat('Y-m-d',$_POST['date'] ?? ''); $name=mb_substr(trim($_POST['name'] ?? ''),0,100); $target=(float)($_POST['target'] ?? 0);
    if ($name===''||$target<=0||!$d||$d->format('Y-m-d')<=date('Y-m-d')) flash('Give the goal a name, a target above zero and a future date.');
    else { $pdo->prepare('INSERT INTO goals(user_id,name,target_amount,saved_amount,target_date,priority,kind) VALUES(?,?,?,?,?,?,?)')->execute([$uid,$name,$target,max(0,(float)($_POST['saved'] ?? 0)),$d->format('Y-m-d'),max(1,min(3,(int)($_POST['priority'] ?? 2))),($_POST['kind'] ?? '')==='emergency'?'emergency':'general']); flash('Goal saved.'); } }
  header('Location: goals.php'); exit; }
$goals=all_goals($pdo,$uid); $a=avg_stats($pdo,$uid); $efSuggest=round($a['exp']*3,2);
page_head('Savings goals'); ?>
<h1>Savings goals</h1>
<?php foreach ($goals as $g): $n=goal_need($g); $pct=(float)$g['target_amount']>0?min(100,round($g['saved_amount']/$g['target_amount']*100)):0; $proj=(float)$g['saved_amount']+max(0,$a['net'])*$n['months'];
  $eta=$a['net']>0&&$n['need']>0?date('M Y',strtotime('+'.(int)ceil($n['need']/$a['net']).' months')):null; $ms=array_filter([25,50,75,100],fn($x)=>$pct>=$x); ?>
<div class="card"><b><?= e($g['name']) ?></b> <span class="pill"><?= ['','High','Medium','Low'][(int)$g['priority']] ?> priority</span> <?= $g['kind']==='emergency'?'<span class="pill">Emergency fund</span>':'' ?>
<p style="margin:6px 0"><?= money($g['saved_amount']) ?> of <?= money($g['target_amount']) ?> by <?= e($g['target_date']) ?></p><?= bar($pct,$pct>=100?'good':'') ?>
<p class="note"><?= $pct ?>% saved<?= $ms?' &middot; milestone reached: '.max($ms).'%':'' ?></p>
<?php if ($n['need']>0): ?><p>Save <b><?= money($n['monthly']) ?></b> a month (about <?= money($n['monthly']/4.345) ?> a week) to finish on time.
<?= $proj>=$g['target_amount']?'<b class="pos">At your usual saving pace you will make it.</b>':($eta?'At your usual pace you would reach it around <b>'.e($eta).'</b>.':'<b class="neg">You are not saving anything extra right now, so this goal will not be reached.</b>') ?></p><?php else: ?><p class="verdict pos">Goal reached!</p><?php endif; ?>
<div class="filters"><form method="post" class="filters"><?= csrf() ?><input type="hidden" name="action" value="add_money"><input type="hidden" name="id" value="<?= $g['id'] ?>"><input type="number" step="0.01" min="0.01" name="amount" placeholder="Add savings"><button class="btn">Add</button></form>
<form method="post" data-confirm="Remove this goal?"><?= csrf() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $g['id'] ?>"><button class="link">Remove</button></form></div></div>
<?php endforeach; if (!$goals) echo '<div class="card">No goals yet. Add your first below.</div>'; ?>
<form method="post" class="card narrow" data-validate><h2 style="margin-top:0">New goal</h2><?= csrf() ?>
<label>Name<input name="name" required maxlength="100" placeholder="e.g. Laptop, School fees"></label>
<label>Type<select name="kind"><option value="general">General goal</option><option value="emergency">Emergency fund<?= $efSuggest>0?' (3 months of spending is about '.e(number_format($efSuggest)).')':'' ?></option></select></label>
<label>Target amount<input type="number" name="target" min="1" step="0.01" required></label><label>Already saved<input type="number" name="saved" min="0" step="0.01" value="0"></label>
<label>Deadline<input type="date" name="date" required></label><label>Priority<select name="priority"><option value="1">High</option><option value="2" selected>Medium</option><option value="3">Low</option></select></label><button class="btn">Save goal</button></form>
<?php page_foot();
