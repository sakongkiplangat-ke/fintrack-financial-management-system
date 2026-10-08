<?php
// FinTrack shared logic. Loaded by config.php.
const CURRENCIES=['KES'=>'Kenyan shilling','USD'=>'US dollar','EUR'=>'Euro','GBP'=>'British pound','UGX'=>'Ugandan shilling','TZS'=>'Tanzanian shilling','RWF'=>'Rwandan franc','NGN'=>'Nigerian naira','GHS'=>'Ghanaian cedi','ZAR'=>'South African rand','EGP'=>'Egyptian pound','INR'=>'Indian rupee','CNY'=>'Chinese yuan','JPY'=>'Japanese yen','AED'=>'UAE dirham','CAD'=>'Canadian dollar','AUD'=>'Australian dollar','BRL'=>'Brazilian real'];
const COUNTRIES=['Kenya','Uganda','Tanzania','Rwanda','Ethiopia','Nigeria','Ghana','South Africa','Egypt','United States','Canada','United Kingdom','Germany','France','India','China','Japan','United Arab Emirates','Australia','Brazil','Other'];
const LANGS=['en'=>'English','sw'=>'Kiswahili','fr'=>'Français'];
const PROFILES=['student'=>'Student','employee'=>'Employee','freelancer'=>'Freelancer','business'=>'Business','other'=>'Other'];
const ACCOUNT_TYPES=['bank'=>'Bank account','cash'=>'Cash','savings'=>'Savings account','credit_card'=>'Credit card','mobile_wallet'=>'Mobile wallet (e.g. M-Pesa)'];

function t($k){ static $d=[
 'en'=>['dashboard'=>'Dashboard','transactions'=>'Transactions','add'=>'Add','plan'=>'Plan','accounts'=>'Accounts','budgets'=>'Budgets','goals'=>'Goals','recurring'=>'Recurring','debts'=>'Debts','networth'=>'Net worth','insights'=>'Insights','forecast'=>'Forecast','analytics'=>'Analytics','simulator'=>'Simulator','alerts'=>'Alerts','assistant'=>'AI assistant','aibudget'=>'AI budget','reports'=>'Reports','account'=>'Account','settings'=>'Settings','security'=>'Security','logout'=>'Logout'],
 'sw'=>['dashboard'=>'Dashibodi','transactions'=>'Miamala','add'=>'Ongeza','plan'=>'Mipango','accounts'=>'Akaunti','budgets'=>'Bajeti','goals'=>'Malengo','recurring'=>'Yanayojirudia','debts'=>'Madeni','networth'=>'Thamani halisi','insights'=>'Maarifa','forecast'=>'Utabiri','analytics'=>'Uchambuzi','simulator'=>'Kiigaji','alerts'=>'Tahadhari','assistant'=>'Msaidizi wa AI','aibudget'=>'Bajeti ya AI','reports'=>'Ripoti','account'=>'Akaunti yangu','settings'=>'Mipangilio','security'=>'Usalama','logout'=>'Toka'],
 'fr'=>['dashboard'=>'Tableau de bord','transactions'=>'Transactions','add'=>'Ajouter','plan'=>'Planifier','accounts'=>'Comptes','budgets'=>'Budgets','goals'=>'Objectifs','recurring'=>'Récurrents','debts'=>'Dettes','networth'=>'Patrimoine','insights'=>'Aperçus','forecast'=>'Prévisions','analytics'=>'Analyses','simulator'=>'Simulateur','alerts'=>'Alertes','assistant'=>'Assistant IA','aibudget'=>'Budget IA','reports'=>'Rapports','account'=>'Compte','settings'=>'Paramètres','security'=>'Sécurité','logout'=>'Déconnexion']];
 $l=$_SESSION['lang'] ?? 'en'; return $d[$l][$k] ?? $d['en'][$k] ?? $k; }

function audit($pdo,$uid,$action,$detail=''){ try { $pdo->prepare('INSERT INTO audit_log(user_id,action,detail,ip) VALUES(?,?,?,?)')->execute([$uid,$action,substr($detail,0,255),$_SERVER['REMOTE_ADDR'] ?? '']); } catch (Throwable $x) {} }
function is_premium(){ return ($_SESSION['plan'] ?? 'free')==='premium'; }
function require_premium($what){ if (!is_premium()) { page_head($what); echo '<div class="card"><h1>'.e($what).'</h1><p>This is a Premium feature.</p><p><a class="btn" href="plans.php">See plans</a></p></div>'; page_foot(); exit; } }
function strong_pw($p){ if (strlen($p)<8) return 'Password must be at least 8 characters.'; if (!preg_match('/[a-z]/',$p)||!preg_match('/[A-Z]/',$p)||!preg_match('/\d/',$p)) return 'Password needs an uppercase letter, a lowercase letter and a number.'; return ''; }
function load_session_user($u){ $_SESSION['uid']=$u['id']; $_SESSION['name']=$u['name']; $_SESSION['cur']=$u['currency']; $_SESSION['lang']=$u['lang']; $_SESSION['tz']=$u['timezone']; $_SESSION['plan']=$u['plan']; $_SESSION['admin']=(int)$u['is_admin']; $_SESSION['ptype']=$u['profile_type']; $_SESSION['seen']=time(); }
function cur_u($pdo,$uid){ $s=$pdo->prepare('SELECT * FROM users WHERE id=?'); $s->execute([$uid]); return $s->fetch(); }

// ---- Encryption of sensitive text (notes) with AES-256-GCM. Key lives in .enckey (back it up, never share it) ----
function enc_key(){ static $k; if ($k) return $k; $f=__DIR__.'/.enckey'; if (!is_file($f)) file_put_contents($f,bin2hex(random_bytes(32))); return $k=hex2bin(trim(file_get_contents($f))); }
function enc($s){ if ($s===''||$s===null||!function_exists('openssl_encrypt')) return $s; $iv=random_bytes(12); $c=openssl_encrypt($s,'aes-256-gcm',enc_key(),OPENSSL_RAW_DATA,$iv,$tag); return 'enc:'.base64_encode($iv.$tag.$c); }
function dec($s){ if (!is_string($s)||strncmp($s,'enc:',4)) return $s; $r=base64_decode(substr($s,4)); $p=openssl_decrypt(substr($r,28),'aes-256-gcm',enc_key(),OPENSSL_RAW_DATA,substr($r,0,12),substr($r,12,16)); return $p===false?'':$p; }

// ---- Two-factor authentication (TOTP, works with Google Authenticator / Authy) ----
function b32enc($s){ $a='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $b=''; foreach (str_split($s) as $c) $b.=str_pad(decbin(ord($c)),8,'0',STR_PAD_LEFT); $o=''; foreach (str_split($b,5) as $x) $o.=$a[bindec(str_pad($x,5,'0'))]; return $o; }
function b32dec($s){ $a='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $b=''; foreach (str_split(strtoupper($s)) as $c) { $p=strpos($a,$c); if ($p!==false) $b.=str_pad(decbin($p),5,'0',STR_PAD_LEFT); } $o=''; foreach (str_split($b,8) as $x) if (strlen($x)==8) $o.=chr(bindec($x)); return $o; }
function totp($secret,$t=null){ $t=intdiv($t ?? time(),30); $h=hash_hmac('sha1',pack('N2',0,$t),b32dec($secret),true); $o=ord($h[19])&15; $v=((ord($h[$o])&127)<<24)|(ord($h[$o+1])<<16)|(ord($h[$o+2])<<8)|ord($h[$o+3]); return str_pad((string)($v%1000000),6,'0',STR_PAD_LEFT); }
function totp_ok($secret,$code){ $code=preg_replace('/\D/','',(string)$code); for ($i=-1;$i<=1;$i++) if (hash_equals(totp($secret,time()+30*$i),$code)) return true; return false; }

// ---- Recurring & scheduled transactions ----
function next_due($d,$f){ $m=['daily'=>'+1 day','weekly'=>'+1 week','monthly'=>'+1 month','yearly'=>'+1 year','once'=>'+100 years']; return date('Y-m-d',strtotime($d.' '.$m[$f])); }
function run_recurring($pdo,$uid){
  try {
    $st=$pdo->prepare('SELECT * FROM recurring WHERE user_id=? AND active=1 AND next_date<=?'); $st->execute([$uid,date('Y-m-d')]);
    foreach ($st->fetchAll() as $r) { $d=$r['next_date']; $n=0;
      while ($d<=date('Y-m-d') && $n++<60) {
        $pdo->prepare('INSERT INTO transactions(user_id,type,category,amount,description,tx_date,account_id) VALUES(?,?,?,?,?,?,?)')
            ->execute([$uid,$r['type'],$r['category'],$r['amount'],trim($r['description'].' (auto)'),$d,$r['account_id']]);
        $d=next_due($d,$r['frequency']); if ($r['frequency']==='once') break;
      }
      $pdo->prepare('UPDATE recurring SET next_date=?,active=? WHERE id=?')->execute([$d,$r['frequency']==='once'?0:1,$r['id']]);
    }
  } catch (Throwable $x) {}
}

// ---- Automatic categorisation: your own rules first, then a built-in keyword list ----
function auto_category($pdo,$uid,$text){
  $text=strtolower($text); if ($text==='') return '';
  $s=$pdo->prepare('SELECT keyword,category FROM rules WHERE user_id=?'); $s->execute([$uid]);
  foreach ($s as $r) if (strpos($text,strtolower($r['keyword']))!==false) return $r['category'];
  $built=['Food'=>['naivas','carrefour','quickmart','supermarket','restaurant','kfc','pizza','cafe','java','chicken','lunch','dinner','groceries','mama mboga'],
    'Transport'=>['uber','bolt','matatu','fuel','petrol','shell','totalenergies','rubis','taxi','fare','sgr','parking'],
    'Utilities'=>['kplc','kenya power','water','nairobi water','electricity','wifi','zuku','safaricom home','internet'],
    'Airtime'=>['airtime','safaricom','airtel','bundles','data bundle'],
    'Rent'=>['rent','landlord'],'School fees'=>['tuition','school fees','fees','university','college'],
    'Salary'=>['salary','payroll','wages'],'Entertainment'=>['netflix','showmax','spotify','cinema','dstv','movie'],
    'Health'=>['pharmacy','hospital','clinic','chemist','doctor']];
  foreach ($built as $c=>$ks) foreach ($ks as $k) if (strpos($text,$k)!==false) return $c;
  return '';
}

// ---- Stats helpers ----
function avg_stats($pdo,$uid){
  $f=date('Y-m-01',strtotime('first day of -3 months')); $t=date('Y-m-t',strtotime('last day of last month'));
  $m=$pdo->prepare('SELECT COUNT(DISTINCT DATE_FORMAT(tx_date,"%Y-%m")) FROM transactions WHERE user_id=? AND tx_date BETWEEN ? AND ?'); $m->execute([$uid,$f,$t]); $n=max(1,(int)$m->fetchColumn());
  $a=totals($pdo,$uid,$f,$t); $inc=$a['income']/$n; $exp=$a['expense']/$n;
  return ['months'=>$n,'inc'=>$inc,'exp'=>$exp,'net'=>$inc-$exp,'has'=>($a['income']+$a['expense'])>0,'from'=>$f,'to'=>$t];
}
function account_balances($pdo,$uid){
  $s=$pdo->prepare("SELECT a.*, a.opening_balance + COALESCE((SELECT SUM(CASE t.type WHEN 'income' THEN t.amount ELSE -t.amount END) FROM transactions t WHERE t.account_id=a.id),0) AS bal FROM accounts a WHERE a.user_id=? ORDER BY a.id");
  $s->execute([$uid]); return $s->fetchAll();
}
function unassigned_net($pdo,$uid){ $s=$pdo->prepare("SELECT COALESCE(SUM(CASE type WHEN 'income' THEN amount ELSE -amount END),0) FROM transactions WHERE user_id=? AND account_id IS NULL"); $s->execute([$uid]); return (float)$s->fetchColumn(); }
function total_cash($pdo,$uid){ $t=unassigned_net($pdo,$uid); foreach (account_balances($pdo,$uid) as $a) $t+=(float)$a['bal']; return $t; }
function cat_total($pdo,$uid,$cat,$f,$t){
  $sql="SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id=? AND type='expense' AND tx_date BETWEEN ? AND ?"; $p=[$uid,$f,$t];
  if ($cat!=='' && strtolower($cat)!=='all') { $sql.=' AND category=?'; $p[]=$cat; }
  $s=$pdo->prepare($sql); $s->execute($p); return (float)$s->fetchColumn();
}

// ---- Budgets: weekly / monthly / semester / custom, category or overall, with rollover ----
function budget_range($b,$back=0){
  if ($b['period']==='weekly') return [date('Y-m-d',strtotime("monday this week -$back week")),date('Y-m-d',strtotime("sunday this week -$back week"))];
  if ($b['period']==='monthly') return [date('Y-m-01',strtotime("first day of -$back month")),date('Y-m-t',strtotime("first day of -$back month"))];
  return [$b['start_date'] ?: date('Y-m-01'), $b['end_date'] ?: date('Y-m-t')];
}
function budget_status($pdo,$uid){
  $s=$pdo->prepare('SELECT * FROM budgets WHERE user_id=? ORDER BY category'); $s->execute([$uid]); $out=[];
  foreach ($s->fetchAll() as $b) {
    [$f,$t]=budget_range($b); $limit=(float)$b['amount']; $carry=0;
    if ($b['rollover'] && in_array($b['period'],['weekly','monthly'])) { [$pf,$pt]=budget_range($b,1); $carry=max(0,$limit-cat_total($pdo,$uid,$b['category'],$pf,$pt)); }
    $spent=cat_total($pdo,$uid,$b['category'],$f,$t); $eff=$limit+$carry;
    $out[]=$b+['from'=>$f,'to'=>$t,'carry'=>$carry,'limit'=>$eff,'spent'=>$spent,'left'=>$eff-$spent,'pct'=>$eff>0?round($spent/$eff*100):0];
  }
  return $out;
}

// ---- Goals ----
function goal_need($g){ $ml=max(0.1,(strtotime($g['target_date'])-time())/(30.44*86400)); $need=max(0,(float)$g['target_amount']-(float)$g['saved_amount']); return ['months'=>$ml,'need'=>$need,'monthly'=>$need/$ml]; }
function all_goals($pdo,$uid){ $s=$pdo->prepare('SELECT * FROM goals WHERE user_id=? ORDER BY priority,target_date'); $s->execute([$uid]); return $s->fetchAll(); }

// ---- Safe to spend: today / this week / this month ----
function upcoming_bills($pdo,$uid,$days=30){
  $s=$pdo->prepare("SELECT * FROM recurring WHERE user_id=? AND active=1 AND type='expense' AND next_date BETWEEN ? AND ? ORDER BY next_date");
  $s->execute([$uid,date('Y-m-d'),date('Y-m-d',strtotime("+$days days"))]); return $s->fetchAll();
}
function safe_to_spend($pdo,$uid){
  $a=avg_stats($pdo,$uid); $cur=totals($pdo,$uid,date('Y-m-01'),date('Y-m-t')); $today=date('Y-m-d');
  $todaySpent=totals($pdo,$uid,$today,$today)['expense']; $expInc=max($a['inc'],$cur['income']);
  $bills=0; foreach (upcoming_bills($pdo,$uid,31) as $b) if ($b['next_date']<=date('Y-m-t') && $b['next_date']>$today) $bills+=(float)$b['amount'];
  $goals=0; foreach (all_goals($pdo,$uid) as $g) $goals+=min(goal_need($g)['need'],goal_need($g)['monthly']);
  $daysLeft=(int)date('t')-(int)date('j')+1; $weekDays=min($daysLeft,8-(int)date('N'));
  $pool=$expInc-($cur['expense']-$todaySpent)-$bills-$goals; $daily=$pool/$daysLeft;
  return ['month'=>$pool-$todaySpent,'week'=>max(0,$daily*$weekDays-$todaySpent),'today'=>max(0,$daily-$todaySpent),'daily'=>$daily,'bills'=>$bills,'goals'=>$goals,'expInc'=>$expInc,'daysLeft'=>$daysLeft];
}

// ---- Alerts ----
function build_alerts($pdo,$uid){
  $al=[]; $u=cur_u($pdo,$uid);
  foreach (budget_status($pdo,$uid) as $b) {
    if ($b['pct']>=100) $al[]=['bad','Budget limit reached: '.$b['category'].' is at '.$b['pct'].'% ('.money($b['spent']).' of '.money($b['limit']).').'];
    elseif ($b['pct']>=80) $al[]=['warn','Budget warning: '.$b['category'].' has used '.$b['pct'].'% of its limit.'];
  }
  $cash=total_cash($pdo,$uid); if ($cash<(float)$u['low_balance']) $al[]=['bad','Low balance: you have '.money($cash).' (your alert level is '.money($u['low_balance']).').'];
  foreach (upcoming_bills($pdo,$uid,7) as $b) { $d=(int)((strtotime($b['next_date'])-strtotime(date('Y-m-d')))/86400); $al[]=['warn','Upcoming bill: '.($b['description']?:$b['category']).', '.money($b['amount']).' '.($d===0?'is due today':'in '.$d.' day(s)').'.']; }
  foreach (all_goals($pdo,$uid) as $g) { $n=goal_need($g); $pct=(float)$g['target_amount']>0?(float)$g['saved_amount']/(float)$g['target_amount']*100:0;
    if ($pct>=100) $al[]=['ok','Goal reached: '.$g['name'].'.'];
    elseif ($n['months']<=1.5) $al[]=['warn','Savings goal "'.$g['name'].'" is due soon and you still need '.money($n['need']).'.']; }
  $s=$pdo->prepare("SELECT t.* FROM transactions t WHERE t.user_id=? AND t.type='expense' AND t.tx_date>=? ORDER BY t.amount DESC LIMIT 20"); $s->execute([$uid,date('Y-m-d',strtotime('-7 days'))]);
  foreach ($s as $t) { $q=$pdo->prepare("SELECT AVG(amount),COUNT(*) FROM transactions WHERE user_id=? AND type='expense' AND category=? AND tx_date<? AND id<>?"); $q->execute([$uid,$t['category'],$t['tx_date'],$t['id']]); [$avg,$n]=$q->fetch(PDO::FETCH_NUM);
    if ($n>=3 && $avg>0 && $t['amount']>3*$avg) $al[]=['warn','Unusual spending: '.money($t['amount']).' on '.$t['category'].' ('.$t['tx_date'].') is over 3 times your usual '.money($avg).'.']; }
  $r=$pdo->prepare("SELECT * FROM recurring WHERE user_id=? AND active=1 AND type='expense'"); $r->execute([$uid]);
  $nRec=$r->rowCount(); if ($nRec) $al[]=['info','You have '.$nRec.' recurring payment(s) set up. Review them on the Recurring page.'];
  return $al;
}

// ---- Financial health score (0-100) ----
function health_score($pdo,$uid){
  $a=avg_stats($pdo,$uid); $parts=[];
  $rate=$a['inc']>0?$a['net']/$a['inc']:0; $parts['Savings rate']=[round(30*max(0,min(1,$rate/0.2))),30];
  $parts['Spending within income']=[$a['inc']>0?round(20*max(0,min(1,$a['inc']/max(1,$a['exp'])))):0,20];
  $bs=budget_status($pdo,$uid); $ok=count(array_filter($bs,fn($b)=>$b['pct']<=100)); $parts['Budget adherence']=[$bs?round(20*$ok/count($bs)):10,20];
  $d=$pdo->prepare('SELECT COALESCE(SUM(min_payment),0) FROM debts WHERE user_id=?'); $d->execute([$uid]); $dm=(float)$d->fetchColumn();
  $parts['Debt load']=[$dm<=0?15:($a['inc']>0?round(15*max(0,1-min(1,$dm/$a['inc']/0.4))):0),15];
  $e=$pdo->prepare("SELECT COALESCE(SUM(saved_amount),0) FROM goals WHERE user_id=? AND kind='emergency'"); $e->execute([$uid]); $ef=(float)$e->fetchColumn();
  $parts['Emergency fund']=[$a['exp']>0?round(15*min(1,$ef/(3*$a['exp']))):0,15];
  $score=array_sum(array_column($parts,0)); return ['score'=>$score,'parts'=>$parts,'label'=>$score>=80?'Excellent':($score>=60?'Good':($score>=40?'Fair':'Needs attention'))];
}

// ---- Debt payoff simulation (snowball = smallest balance first, avalanche = highest rate first) ----
function debt_plan($debts,$extra,$method){
  foreach ($debts as &$d) { $d['b']=(float)$d['balance']; $d['r']=(float)$d['rate']/1200; $d['m']=(float)$d['min_payment']; $d['done']=null; } unset($d);
  $month=0; $int=0; $sched=[];
  while (true) {
    if (!array_filter($debts,fn($d)=>$d['b']>0.005) || $month>=600) break; $month++;
    foreach ($debts as &$d) if ($d['b']>0) { $i=$d['b']*$d['r']; $d['b']+=$i; $int+=$i; } unset($d);
    $pool=$extra;
    foreach ($debts as &$d) { if ($d['b']>0) { $d['b']-=min($d['m'],$d['b']); } else $pool+=$d['m']; } unset($d);
    $idx=array_keys(array_filter($debts,fn($d)=>$d['b']>0.005));
    usort($idx,fn($x,$y)=>$method==='avalanche'?$debts[$y]['r']<=>$debts[$x]['r']:$debts[$x]['b']<=>$debts[$y]['b']);
    foreach ($idx as $i) { if ($pool<=0) break; $p=min($pool,$debts[$i]['b']); $debts[$i]['b']-=$p; $pool-=$p; }
    foreach ($debts as &$d) if ($d['b']<=0.005 && $d['done']===null) { $d['b']=0; $d['done']=$month; } unset($d);
    if ($month<=12) $sched[]=['month'=>$month,'balance'=>array_sum(array_column($debts,'b'))];
  }
  $never=(bool)array_filter($debts,fn($d)=>$d['b']>0.005);
  return ['months'=>$month,'interest'=>$int,'sched'=>$sched,'never'=>$never,'free'=>$never?null:date('Y-m-d',strtotime("+$month months")),'debts'=>$debts];
}
function monthly_payment($principal,$rate,$months){ $r=$rate/1200; return $r>0?$principal*$r/(1-pow(1+$r,-$months)):$principal/max(1,$months); }

// ---- Net worth ----
function networth_now($pdo,$uid){
  $assets=max(0,total_cash($pdo,$uid)); $liab=0; $neg=min(0,total_cash($pdo,$uid)); $liab+=-$neg;
  $s=$pdo->prepare('SELECT kind,SUM(value) v FROM assets WHERE user_id=? GROUP BY kind'); $s->execute([$uid]); foreach ($s as $r) { if ($r['kind']==='asset') $assets+=(float)$r['v']; else $liab+=(float)$r['v']; }
  $d=$pdo->prepare('SELECT COALESCE(SUM(balance),0) FROM debts WHERE user_id=?'); $d->execute([$uid]); $liab+=(float)$d->fetchColumn();
  return ['assets'=>$assets,'liabilities'=>$liab,'net'=>$assets-$liab];
}
function snapshot_networth($pdo,$uid){ $n=networth_now($pdo,$uid); $pdo->prepare('REPLACE INTO networth_history(user_id,snap_date,assets,liabilities) VALUES(?,?,?,?)')->execute([$uid,date('Y-m-d'),$n['assets'],$n['liabilities']]); return $n; }

// ---- AI (server-side; key never reaches the browser). Returns text, or null if no key / failure ----
function ai_call($system,$user,$max=1200,$image=null){
  if (AI_KEY==='') return null;
  $content=$image?[['type'=>'image','source'=>['type'=>'base64','media_type'=>$image['type'],'data'=>$image['data']]],['type'=>'text','text'=>$user]]:$user;
  $ch=curl_init('https://api.anthropic.com/v1/messages');
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_TIMEOUT=>60,
    CURLOPT_POSTFIELDS=>json_encode(['model'=>AI_MODEL,'max_tokens'=>$max,'system'=>$system,'messages'=>[['role'=>'user','content'=>$content]]]),
    CURLOPT_HTTPHEADER=>['content-type: application/json','x-api-key: '.AI_KEY,'anthropic-version: 2023-06-01']]);
  $res=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
  if ($res===false || $code!==200) return null;
  return json_decode($res,true)['content'][0]['text'] ?? null;
}
// Only category totals and goal names are shared with the AI, never names, emails or descriptions.
function ai_context($pdo,$uid){
  $a=avg_stats($pdo,$uid); $cats=[]; $s=$pdo->prepare("SELECT category,SUM(amount) FROM transactions WHERE user_id=? AND type='expense' AND tx_date BETWEEN ? AND ? GROUP BY category"); $s->execute([$uid,$a['from'],date('Y-m-t')]);
  foreach ($s->fetchAll(PDO::FETCH_KEY_PAIR) as $c=>$v) $cats[$c]=round($v/$a['months'],2);
  $g=[]; foreach (all_goals($pdo,$uid) as $x) $g[]=['goal'=>$x['name'],'target'=>(float)$x['target_amount'],'saved'=>(float)$x['saved_amount'],'by'=>$x['target_date']];
  $st=safe_to_spend($pdo,$uid); $cm=totals($pdo,$uid,date('Y-m-01'),date('Y-m-t'));
  return ['currency'=>$_SESSION['cur'] ?? 'KES','avg_monthly_income'=>round($a['inc'],2),'avg_monthly_spending_by_category'=>$cats,'this_month_income'=>$cm['income'],'this_month_spending'=>$cm['expense'],'safe_to_spend_this_month'=>round($st['month'],2),'goals'=>$g];
}

// ---- Minimal PDF writer (text only) for report export ----
function pdf_esc($s){ $s=function_exists('iconv')?@iconv('UTF-8','windows-1252//TRANSLIT//IGNORE',(string)$s):preg_replace('/[^\x20-\x7e]/','?',(string)$s); return str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$s); }
function simple_pdf($title,$lines){
  $pages=array_chunk($lines,48) ?: [[]]; $n=count($pages); $o=[];
  $o[1]='<< /Type /Catalog /Pages 2 0 R >>'; $kids=''; for ($i=0;$i<$n;$i++) $kids.=(5+$i*2).' 0 R ';
  $o[2]="<< /Type /Pages /Kids [$kids] /Count $n >>"; $o[3]='<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>'; $o[4]='<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';
  foreach ($pages as $i=>$pl) {
    $s='BT /F2 16 Tf 40 800 Td ('.pdf_esc($title).') Tj ET BT /F1 10 Tf 40 775 Td 14 TL '; foreach ($pl as $l) $s.='('.pdf_esc($l).') Tj T* '; $s.='ET';
    $o[5+$i*2]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents '.(6+$i*2).' 0 R >>';
    $o[6+$i*2]='<< /Length '.strlen($s)." >>\nstream\n$s\nendstream";
  }
  ksort($o); $out="%PDF-1.4\n"; $off=[]; foreach ($o as $k=>$v) { $off[$k]=strlen($out); $out.="$k 0 obj\n$v\nendobj\n"; }
  $x=strlen($out); $out.="xref\n0 ".(count($o)+1)."\n0000000000 65535 f \n"; foreach ($o as $k=>$v) $out.=sprintf("%010d 00000 n \n",$off[$k]);
  return $out."trailer\n<< /Size ".(count($o)+1)." /Root 1 0 R >>\nstartxref\n$x\n%%EOF";
}
function bar($pct,$cls=''){ $pct=max(0,min(100,$pct)); return '<div class="meter"><i class="'.$cls.'" style="width:'.$pct.'%"></i></div>'; }
