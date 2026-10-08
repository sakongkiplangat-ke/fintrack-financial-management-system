<?php require 'config.php'; require_login(); $uid=$_SESSION['uid']; $u=cur_u($pdo,$uid);
$quick=['Create a budget for me','Analyse my spending','Give me recommendations','Summarise this month','Summarise this week','Where am I overspending?','How can I save more?','Make me a personalised financial plan'];
function local_answer($pdo,$uid,$q){ $c=ai_context($pdo,$uid); $st=safe_to_spend($pdo,$uid); $a=avg_stats($pdo,$uid); $ql=strtolower($q); $cats=$c['avg_monthly_spending_by_category']; arsort($cats); $top=array_slice($cats,0,3,true);
  $list=function($arr){ $o=[]; foreach ($arr as $k=>$v) $o[]="$k (".money($v).')'; return implode(', ',$o) ?: 'nothing recorded yet'; };
  if (preg_match('/afford|buy/',$ql) && preg_match('/[\d,]+(\.\d+)?/',$q,$m)) { $p=(float)str_replace(',','',$m[0]); $after=$st['month']-$p; return $after>=0?"Yes, it fits. You have ".money($st['month'])." safe to spend this month and would have ".money($after)." left.":"Not this month. You have ".money($st['month'])." safe to spend and this costs ".money($p).'. '.($a['net']>0?'At your usual saving of '.money($a['net']).' a month you could afford it in about '.ceil($p/$a['net']).' month(s).':''); }
  if (preg_match('/overspend|too much/',$ql)) return 'Your biggest spending categories each month are: '.$list($top).'. Compare them with your budgets on the Budgets page; any that pass 100% are where you are overspending.';
  if (preg_match('/save/',$ql)) { $o=[]; foreach ($top as $k=>$v) $o[]="Trimming $k by 10% would free ".money($v*0.1)." a month"; return ($a['net']>0?'You save about '.money($a['net']).' a month. ':'You are spending more than you earn, so start by cutting spending. ').implode('. ',$o).'. Moving savings out as soon as income arrives also helps.'; }
  if (preg_match('/week/',$ql)) return 'This week you can safely spend about '.money($st['week']).', and '.money($st['today']).' today.';
  if (preg_match('/month|summar/',$ql)) return 'This month: income '.money($c['this_month_income']).', spending '.money($c['this_month_spending']).'. You have '.money($st['month']).' safe to spend for the rest of the month after bills and goals.';
  if (preg_match('/budget/',$ql)) return 'A simple budget from your habits: '.$list(array_map(fn($v)=>round($v*0.95,2),$top)).'. Open Budgets and press "Suggest budgets" to create limits for every category.';
  return 'Your average month: income '.money($c['avg_monthly_income']).', spending mostly on '.$list($top).'. Ask me about affordability, overspending, saving, or a summary.'; }
$_SESSION['chat']=$_SESSION['chat'] ?? [];
if ($_SERVER['REQUEST_METHOD']==='POST') { check_csrf(); $q=mb_substr(trim($_POST['q'] ?? ''),0,500);
  if (isset($_POST['clear'])) $_SESSION['chat']=[];
  elseif ($q!=='') {
    if (($_SESSION['ai_t'] ?? 0)>time()-6) $ans='Please wait a few seconds before asking again.';
    else { $_SESSION['ai_t']=time(); $ans=null;
      if (is_premium() && $u['ai_share'] && AI_KEY!=='') $ans=ai_call('You are FinTrack\'s friendly financial assistant for everyday people, especially students and young earners in Kenya. Use ONLY the JSON data given about the user. Be concrete, use the user\'s currency, keep it under 180 words, and use short paragraphs or a short list. You are not a licensed financial adviser: never promise returns or recommend specific investments. If data is missing, say what to record.','Question: '.$q."\nMy data: ".json_encode(ai_context($pdo,$uid)),700);
      $mode=$ans?'ai':'basic'; if (!$ans) $ans=local_answer($pdo,$uid,$q); }
    $_SESSION['chat'][]=['me',$q]; $_SESSION['chat'][]=['ai',$ans,$mode ?? 'basic']; $_SESSION['chat']=array_slice($_SESSION['chat'],-16); }
  header('Location: assistant.php'); exit; }
page_head('AI assistant'); ?><h1>AI financial assistant</h1>
<?php if (!is_premium() || AI_KEY==='' || !$u['ai_share']): ?><div class="alert info">Basic mode: answers use simple rules on your numbers. <?= !is_premium()?'AI answers are a <a href="plans.php">Premium</a> feature. ':'' ?><?= AI_KEY===''?'The site owner has not set an API key. ':'' ?><?= !$u['ai_share']?'You turned off AI data sharing in <a href="privacy.php">Privacy</a>.':'' ?></div><?php endif; ?>
<div class="card chat"><?php foreach ($_SESSION['chat'] as $m) echo '<div class="msg '.$m[0].'">'.e($m[1]).'</div>'; if (!$_SESSION['chat']) echo '<p class="note">Ask anything about your money, or tap a suggestion below.</p>'; ?></div>
<form method="post" class="card filters"><?= csrf() ?><input name="q" maxlength="500" placeholder='e.g. Can I afford a 25,000 phone?' required><button class="btn">Ask</button></form>
<div class="card"><?php foreach ($quick as $q) echo '<form method="post" style="display:inline">'.csrf().'<input type="hidden" name="q" value="'.e($q).'"><button class="btn alt" style="margin:3px">'.e($q).'</button></form>'; ?>
<form method="post" style="display:inline"><?= csrf() ?><input type="hidden" name="clear" value="1"><input type="hidden" name="q" value=""><button class="link" style="margin-left:8px">Clear chat</button></form></div>
<p class="note">Only category totals and goal names are sent to the AI, never your name, email or descriptions. This is guidance, not professional financial advice.</p><?php page_foot();
