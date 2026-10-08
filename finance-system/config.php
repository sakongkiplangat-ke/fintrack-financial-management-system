<?php
ini_set('session.use_strict_mode','1'); ini_set('session.cookie_httponly','1');
session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax','secure'=>!empty($_SERVER['HTTPS'])]);
session_start();
header('X-Frame-Options: DENY'); header('X-Content-Type-Options: nosniff'); header('Referrer-Policy: same-origin');
$GLOBALS['t0']=microtime(true);
const DB_HOST='localhost', DB_NAME='finance_db', DB_USER='root', DB_PASS='';
// AI budget helper: paste your Anthropic API key between the quotes (or set the ANTHROPIC_API_KEY environment variable).
// Never share or upload this file with a real key in it. With no key, budget.php still works in basic (rule-based) mode.
define('AI_KEY', getenv('ANTHROPIC_API_KEY') ?: '');
const AI_MODEL='claude-sonnet-5-5';
// Password reset: XAMPP cannot send email by default, so in dev mode the reset link is shown on screen and written to the PHP error log.
// Set to false on a real server where mail() works, so the link is emailed instead and never shown.
const RESET_DEV_MODE=true;
try {
  $pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS,
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
} catch (PDOException $e) { die('Database connection failed. Import database.sql and check config.php.'); }

function base_url(){ $s=(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off')?'https':'http'; return $s.'://'.($_SERVER['HTTP_HOST'] ?? 'localhost').rtrim(dirname($_SERVER['SCRIPT_NAME']),'/\\'); }
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function money($n){ $c=$_SESSION['cur'] ?? 'KES'; return ($c==='KES'?'Ksh':$c).' '.number_format((float)$n, 2); }
function require_login(){ global $pdo;
  if (empty($_SESSION['uid']) || (isset($_SESSION['seen']) && time()-$_SESSION['seen']>1800)) { $_SESSION=[]; header('Location: login.php'); exit; }
  $_SESSION['seen']=time(); date_default_timezone_set($_SESSION['tz'] ?? 'Africa/Nairobi');
  static $ran=false; if (!$ran) { $ran=true; run_recurring($pdo,$_SESSION['uid']); } }
function csrf(){ if (empty($_SESSION['t'])) $_SESSION['t']=bin2hex(random_bytes(16)); return '<input type="hidden" name="csrf" value="'.$_SESSION['t'].'">'; }
function check_csrf(){ if (!hash_equals($_SESSION['t'] ?? '', $_POST['csrf'] ?? '')) die('Invalid request.'); }
function flash($m=null){ if ($m!==null) { $_SESSION['f']=$m; return; } $f=$_SESSION['f'] ?? ''; unset($_SESSION['f']); return $f; }

function totals($pdo, $uid, $from='', $to=''){
  $sql="SELECT type, SUM(amount) s FROM transactions WHERE user_id=?"; $p=[$uid];
  if ($from) { $sql.=" AND tx_date>=?"; $p[]=$from; }
  if ($to)   { $sql.=" AND tx_date<=?"; $p[]=$to; }
  $st=$pdo->prepare($sql." GROUP BY type"); $st->execute($p);
  $r=['income'=>0,'expense'=>0];
  foreach ($st as $row) $r[$row['type']]=(float)$row['s'];
  $r['balance']=$r['income']-$r['expense']; return $r;
}

function page_head($title, $auth=true){
  echo '<!DOCTYPE html><html lang="'.e($_SESSION['lang'] ?? 'en').'"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
     .'<meta name="theme-color" content="#0f766e"><link rel="manifest" href="manifest.json">'
     .'<title>'.e($title).' | FinTrack</title><link rel="stylesheet" href="assets/style.css"></head><body>';
  if ($auth) {
    $m=function($label,$items){ $h='<details class="dd"><summary>'.e($label).'</summary><div>'; foreach($items as $u=>$l) $h.='<a href="'.$u.'">'.e($l).'</a>'; return $h.'</div></details>'; };
    echo '<header class="nav"><a class="brand" href="dashboard.php">FinTrack</a><nav>'
       .'<a href="dashboard.php">'.t('dashboard').'</a><a href="transactions.php">'.t('transactions').'</a><a href="form.php">'.t('add').'</a>'
       .$m(t('plan'),['accounts.php'=>t('accounts'),'budgets.php'=>t('budgets'),'goals.php'=>t('goals'),'recurring.php'=>t('recurring'),'debts.php'=>t('debts'),'networth.php'=>t('networth'),'rules.php'=>'Rules','import.php'=>'Import CSV','groups.php'=>'Shared budgets','student.php'=>'Student mode'])
       .$m(t('insights'),['insights.php'=>t('insights'),'forecast.php'=>t('forecast'),'analytics.php'=>t('analytics'),'simulator.php'=>t('simulator'),'alerts.php'=>t('alerts')])
       .$m('AI',['assistant.php'=>t('assistant'),'budget.php'=>t('aibudget')])
       .'<a href="report.php">'.t('reports').'</a>'
       .$m(t('account'),array_filter(['settings.php'=>t('settings'),'security.php'=>t('security'),'privacy.php'=>'Privacy & data','plans.php'=>'Plans','support.php'=>'Support & feedback','account.php'=>'Change password',(!empty($_SESSION['admin'])?'admin.php':'')=>'Admin'],fn($k)=>$k!=='',ARRAY_FILTER_USE_KEY))
       .'<a href="logout.php">'.t('logout').'</a></nav></header>';
  }
  echo '<main class="wrap">';
  if ($m=flash()) echo '<div class="alert ok">'.e($m).'</div>';
}
function page_foot(){ echo '</main><script src="assets/app.js"></script></body></html>';
  global $pdo; if (isset($pdo) && mt_rand(1,10)===1) { try { $pdo->prepare("INSERT INTO app_log(kind,page,ms) VALUES('page',?,?)")->execute([basename($_SERVER['SCRIPT_NAME']),(int)((microtime(true)-$GLOBALS['t0'])*1000)]); } catch (Throwable $x) {} } }
set_error_handler(function($no,$str,$file,$line){ global $pdo; if (!($no & (E_WARNING|E_USER_ERROR|E_NOTICE))) return false;
  try { if (isset($pdo)) $pdo->prepare("INSERT INTO app_log(kind,page,detail) VALUES('error',?,?)")->execute([basename($file),substr("$str (line $line)",0,255)]); } catch (Throwable $x) {} return false; });
require __DIR__.'/lib.php';
