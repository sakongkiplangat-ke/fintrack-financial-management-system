<?php require 'config.php'; require_login(); $uid=$_SESSION['uid'];

// ---- 1. Summarise the last 3 calendar months (category totals only) ----
$from=date('Y-m-01', strtotime('-2 months')); $to=date('Y-m-t');
$mo=$pdo->prepare('SELECT COUNT(DISTINCT DATE_FORMAT(tx_date,"%Y-%m")) FROM transactions WHERE user_id=? AND tx_date BETWEEN ? AND ?');
$mo->execute([$uid,$from,$to]); $months=max(1,(int)$mo->fetchColumn());
$st=$pdo->prepare('SELECT type,category,SUM(amount) s FROM transactions WHERE user_id=? AND tx_date BETWEEN ? AND ? GROUP BY type,category');
$st->execute([$uid,$from,$to]);
$inc=[]; $exp=[];
foreach ($st as $r) { $avg=round((float)$r['s']/$months,2); if ($r['type']==='income') $inc[$r['category']]=$avg; else $exp[$r['category']]=$avg; }
$incTotal=array_sum($inc); $expTotal=array_sum($exp);

// ---- 2. Basic rule-based budget (used when no API key, or if the AI call fails) ----
function basic_budget($inc, $exp, $saveTarget) {
  $income=array_sum($inc); $fixedCats=['rent','utilities','school fees'];
  $save=$saveTarget>0 ? $saveTarget : round($income*0.2,2);
  $avail=max(0,$income-$save); $fixed=0; $flex=0;
  foreach ($exp as $c=>$v) { if (in_array(strtolower($c),$fixedCats)) $fixed+=$v; else $flex+=$v; }
  $f=$flex>0 ? max(0.3,min(1,($avail-$fixed)/$flex)) : 1;
  $rows=[];
  foreach ($exp as $c=>$v) {
    $isFixed=in_array(strtolower($c),$fixedCats); $sug=$isFixed?$v:round($v*$f,2);
    $rows[]=['category'=>$c,'current'=>$v,'suggested'=>$sug,
      'tip'=>$isFixed?'Usually fixed. Keep it paid on time.':($f<1?'Try to cut this by about '.round((1-$f)*100).'%.':'Within your means. Keep it up.')];
  }
  return ['summary'=>'Basic mode (rule-based): your monthly savings goal is Ksh '.number_format($save,2).'; the rest is shared across your categories.',
    'budget'=>$rows,'savings_tips'=>['Move your savings out as soon as income arrives, before spending.','Review your biggest flexible category each week.'],
    'warning'=>array_sum($exp)>$income?'Your spending is higher than your income. Cutting spending comes first.':null,'mode'=>'basic'];
}

// ---- 3. AI budget (server-side call, so the key never reaches the browser) ----
function ai_budget($inc, $exp, $saveTarget, $goal) {
  $sys='You are a careful budgeting assistant for a personal finance app used in Kenya. All amounts are in Kenyan shillings (Ksh). '
      .'Give practical, realistic advice. You are not a licensed financial advisor, so do not promise returns or recommend specific investments. '
      .'Reply with ONLY a JSON object, no markdown, in this exact shape: '
      .'{"summary":string,"budget":[{"category":string,"current":number,"suggested":number,"tip":string}],"savings_tips":[string],"warning":string|null}. '
      .'Include every expense category provided. Keep tips under 20 words. Suggested limits plus the savings target must not exceed average monthly income. '
      .'Set warning to a short message if spending already exceeds income, otherwise null.';
  $data=['avg_monthly_income_by_category'=>$inc,'avg_monthly_expenses_by_category'=>$exp,
         'monthly_savings_target'=>$saveTarget>0?$saveTarget:null,'user_goal'=>$goal!==''?$goal:null];
  $body=json_encode(['model'=>AI_MODEL,'max_tokens'=>1500,'system'=>$sys,
    'messages'=>[['role'=>'user','content'=>'Build my monthly budget from this data: '.json_encode($data)]]]);
  $ch=curl_init('https://api.anthropic.com/v1/messages');
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_TIMEOUT=>45,CURLOPT_POSTFIELDS=>$body,
    CURLOPT_HTTPHEADER=>['content-type: application/json','x-api-key: '.AI_KEY,'anthropic-version: 2023-06-01']]);
  $res=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE);
  if ($res===false) error_log('AI budget cURL error: '.curl_error($ch));
  curl_close($ch);
  if ($res===false || $code!==200) return null;
  $txt=json_decode($res,true)['content'][0]['text'] ?? '';
  $txt=trim(preg_replace('/^```(?:json)?\s*|\s*```$/','',trim($txt)));
  $d=json_decode($txt,true);
  if (!is_array($d) || !isset($d['budget']) || !is_array($d['budget'])) return null;
  $d['mode']='ai'; return $d;
}

// ---- 4. Handle the form ----
$err=''; $res=null; $note='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
  check_csrf();
  $save=max(0,(float)($_POST['save'] ?? 0)); $goal=mb_substr(trim($_POST['goal'] ?? ''),0,120);
  if (($_SESSION['ai_t'] ?? 0) > time()-15) $err='Please wait a few seconds before asking again.';
  elseif (!$inc || !$exp) $err='Record at least one income and one expense in the last 3 months first.';
  else {
    $_SESSION['ai_t']=time();
    if (AI_KEY!=='' && is_premium() && cur_u($pdo,$uid)['ai_share']) { $res=ai_budget($inc,$exp,$save,$goal); if (!$res) $note='The AI service could not be reached, so a basic budget is shown instead.'; }
    if (!$res) { $res=basic_budget($inc,$exp,$save); if (!is_premium()) $note='AI budgets are a Premium feature, so a basic rule-based budget is shown.'; elseif (AI_KEY==='') $note='No API key is set, so a basic rule-based budget is shown.'; }
  }
}
page_head('AI Budget'); ?>
<h1>AI Budget helper</h1>
<div class="card">
<p>Based on your last <?= $months ?> month(s) of records: average income <b class="pos"><?= money($incTotal) ?></b> and average spending <b class="neg"><?= money($expTotal) ?></b> per month.</p>
<form method="post" onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='Thinking...'">
<?= csrf() ?>
<label>Monthly savings target (Ksh, optional)<input type="number" name="save" min="0" step="0.01" placeholder="e.g. 5000"></label>
<label>What do you want to achieve? (optional)<input name="goal" maxlength="120" placeholder="e.g. save for school fees, cut airtime costs"></label>
<button class="btn">Get budget advice</button></form>
<p style="font-size:.8rem;color:var(--mut)">Privacy: only category totals are sent to the AI, never your name, email or descriptions. Suggestions are guidance, not professional financial advice.</p>
</div>
<?php if ($err) echo '<div class="alert bad">'.e($err).'</div>'; if ($note) echo '<div class="alert ok">'.e($note).'</div>';
if ($res): ?>
<div class="card"><h2 style="margin-top:0">Your suggested budget</h2><p><?= e($res['summary'] ?? '') ?></p>
<?php if (!empty($res['warning'])) echo '<div class="alert bad">'.e($res['warning']).'</div>'; ?></div>
<div class="card scroll"><table><tr><th>Category</th><th class="r">Now / month</th><th class="r">Suggested limit</th><th>Tip</th></tr>
<?php foreach ($res['budget'] as $b): if (!is_array($b)) continue; ?><tr><td><?= e($b['category'] ?? '') ?></td><td class="r"><?= money($b['current'] ?? 0) ?></td><td class="r"><b><?= money($b['suggested'] ?? 0) ?></b></td><td style="white-space:normal"><?= e($b['tip'] ?? '') ?></td></tr>
<?php endforeach; ?></table></div>
<?php if (!empty($res['savings_tips'])): ?><div class="card"><b>Savings tips</b><ul><?php foreach ($res['savings_tips'] as $t) echo '<li>'.e($t).'</li>'; ?></ul></div><?php endif; ?>
<?php endif; page_foot();
