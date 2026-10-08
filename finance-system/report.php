<?php require 'config.php'; require_login(); $uid=$_SESSION['uid'];
$from=$_GET['from'] ?? date('Y-m-01'); $to=$_GET['to'] ?? date('Y-m-t'); $type=$_GET['type'] ?? 'summary';
$types=['summary'=>'Summary','income'=>'Income','expense'=>'Expenses','budget'=>'Budgets','savings'=>'Savings','debt'=>'Debt','networth'=>'Net worth'];
if (!isset($types[$type])) $type='summary';
function report_data($pdo,$uid,$type,$from,$to){ $m='money';
  switch ($type) {
    case 'income': case 'expense': $s=$pdo->prepare('SELECT tx_date,category,description,amount FROM transactions WHERE user_id=? AND type=? AND tx_date BETWEEN ? AND ? ORDER BY tx_date'); $s->execute([$uid,$type,$from,$to]); $rows=$s->fetchAll(PDO::FETCH_NUM); $tot=array_sum(array_column($rows,3));
      return [ucfirst($type).' report',['Date','Category','Description','Amount'],array_merge($rows,[['','','Total',$tot]])];
    case 'budget': $r=[]; foreach (budget_status($pdo,$uid) as $b) $r[]=[$b['category'],$b['period'],$b['limit'],$b['spent'],$b['left'],$b['pct'].'%']; return ['Budget report',['Category','Period','Limit','Spent','Left','Used'],$r];
    case 'savings': $r=[]; foreach (all_goals($pdo,$uid) as $g) $r[]=[$g['name'],$g['target_date'],$g['target_amount'],$g['saved_amount'],round($g['saved_amount']/max(1,$g['target_amount'])*100).'%']; $t=totals($pdo,$uid,$from,$to); $r[]=['Net saved in period','','',$t['balance'],'']; return ['Savings report',['Goal','Deadline','Target','Saved','Progress'],$r];
    case 'debt': $s=$pdo->prepare('SELECT name,kind,balance,rate,min_payment FROM debts WHERE user_id=? ORDER BY balance DESC'); $s->execute([$uid]); $rows=$s->fetchAll(PDO::FETCH_NUM); return ['Debt report',['Debt','Type','Balance','Rate %','Minimum'],$rows];
    case 'networth': $n=networth_now($pdo,$uid); return ['Net-worth report',['Item','Amount'],[['Assets',$n['assets']],['Liabilities',$n['liabilities']],['Net worth',$n['net']]]];
    default: $s=$pdo->prepare('SELECT type,category,SUM(amount) s,COUNT(*) n FROM transactions WHERE user_id=? AND tx_date BETWEEN ? AND ? GROUP BY type,category ORDER BY type,s DESC'); $s->execute([$uid,$from,$to]); $t=totals($pdo,$uid,$from,$to);
      $rows=[['Income','',$t['income'],''],['Expenses','',$t['expense'],''],['Net balance','',$t['balance'],'']]; foreach ($s as $r) $rows[]=[ucfirst($r['type']),$r['category'],$r['s'],$r['n']]; return ['Financial summary',['Type','Category','Total','Transactions'],$rows]; } }
[$title,$head,$rows]=report_data($pdo,$uid,$type,$from,$to);
$fmt=function($v){ return is_numeric($v)&&!preg_match('/^\d{4}-/',(string)$v)&&(string)$v!==''&&strpos((string)$v,'%')===false ? money($v) : (string)$v; };
$ex=$_GET['export'] ?? ''; $base='fintrack-'.$type.'-'.$from.'-to-'.$to;
if ($ex==='csv') { header('Content-Type: text/csv'); header("Content-Disposition: attachment; filename=$base.csv"); $o=fopen('php://output','w'); fputcsv($o,$head); foreach ($rows as $r) fputcsv($o,$r); exit; }
if ($ex==='xls') { header('Content-Type: application/vnd.ms-excel'); header("Content-Disposition: attachment; filename=$base.xls"); echo '<html><head><meta charset="UTF-8"></head><body><table border="1"><tr>'; foreach ($head as $h) echo '<th>'.e($h).'</th>'; echo '</tr>'; foreach ($rows as $r) { echo '<tr>'; foreach ($r as $c) echo '<td>'.e($c).'</td>'; echo '</tr>'; } echo '</table></body></html>'; exit; }
if ($ex==='pdf') { $lines=[$_SESSION['name'].' | '.($type==='networth'?'as of '.date('Y-m-d'):"$from to $to"),'']; $lines[]=implode('  |  ',$head); $lines[]=str_repeat('-',90); foreach ($rows as $r) $lines[]=implode('  |  ',array_map(fn($c)=>mb_substr($fmt($c),0,30),$r));
  header('Content-Type: application/pdf'); header("Content-Disposition: attachment; filename=$base.pdf"); echo simple_pdf('FinTrack - '.$title,$lines); exit; }
$qs=http_build_query(['type'=>$type,'from'=>$from,'to'=>$to]);
page_head('Reports'); ?><h1>Financial reports</h1>
<form class="card filters no-print" method="get"><select name="type"><?php foreach ($types as $k=>$l) echo '<option value="'.$k.'"'.($type===$k?' selected':'').'>'.$l.'</option>'; ?></select>From <input type="date" name="from" value="<?= e($from) ?>"> To <input type="date" name="to" value="<?= e($to) ?>"><button class="btn">Generate</button></form>
<p class="no-print"><a class="btn alt" href="?<?= e($qs) ?>&export=pdf">PDF</a> <a class="btn alt" href="?<?= e($qs) ?>&export=xls">Excel</a> <a class="btn alt" href="?<?= e($qs) ?>&export=csv">CSV</a> <button type="button" class="btn alt" data-print>Print</button></p>
<h2><?= e($title) ?></h2><p>Period: <b><?= e($from) ?></b> to <b><?= e($to) ?></b> &middot; Prepared for <?= e($_SESSION['name']) ?></p>
<div class="card scroll"><table><tr><?php foreach ($head as $h) echo '<th>'.e($h).'</th>'; ?></tr><?php foreach ($rows as $r) { echo '<tr>'; foreach ($r as $c) echo '<td'.(is_numeric($c)?' class="r"':'').'>'.e($fmt($c)).'</td>'; echo '</tr>'; } if (!$rows) echo '<tr><td colspan="'.count($head).'">No records.</td></tr>'; ?></table></div><?php page_foot();
