<?php require 'config.php'; require_login(); $uid=$_SESSION['uid']; $accts=account_balances($pdo,$uid); $msg='';
if ($_SERVER['REQUEST_METHOD']==='POST') { check_csrf(); $f=$_FILES['csv'] ?? null;
  if (!$f || $f['error']!==UPLOAD_ERR_OK || $f['size']>2000000) $msg='Choose a CSV file under 2 MB.';
  else { $h=fopen($f['tmp_name'],'r'); $head=array_map(fn($x)=>strtolower(trim($x,"\xEF\xBB\xBF \t")),fgetcsv($h) ?: []); $ix=fn($n)=>array_search($n,$head);
    $di=$ix('date'); $de=$ix('description'); $am=$ix('amount'); $ty=$ix('type'); $acc=(int)($_POST['account_id'] ?? 0) ?: null; $ok=$dup=$bad=0;
    if ($di===false||$am===false) $msg='The file needs a header row with at least: date, description, amount (and optionally type).';
    else { $chk=$pdo->prepare('SELECT COUNT(*) FROM transactions WHERE user_id=? AND tx_date=? AND amount=? AND description=?');
      $ins=$pdo->prepare('INSERT INTO transactions(user_id,type,category,amount,description,tx_date,account_id) VALUES(?,?,?,?,?,?,?)'); $n=0;
      while (($r=fgetcsv($h))!==false && $n++<2000) { $d=date_create(trim($r[$di] ?? '')); $amt=(float)str_replace([',',' '],'',$r[$am] ?? 0); $desc=mb_substr(trim($r[$de] ?? ''),0,255);
        if (!$d || $amt==0) { $bad++; continue; }
        $type=$ty!==false&&strtolower(trim($r[$ty] ?? ''))==='income'?'income':(($ty!==false&&strtolower(trim($r[$ty] ?? ''))==='expense')?'expense':($amt<0?'expense':'income')); $amt=abs($amt); $dt=$d->format('Y-m-d');
        $chk->execute([$uid,$dt,$amt,$desc]); if ($chk->fetchColumn()) { $dup++; continue; }
        $ins->execute([$uid,$type,auto_category($pdo,$uid,$desc) ?: 'Other',$amt,$desc,$dt,$acc]); $ok++; }
      audit($pdo,$uid,'csv_import',"$ok rows"); $msg="Imported $ok transaction(s). Skipped $dup duplicate(s) and $bad unreadable row(s)."; } fclose($h); } }
page_head('Import'); ?><h1>Import bank statement (CSV)</h1>
<?php if ($msg) echo '<div class="alert ok" style="animation:none">'.e($msg).'</div>'; ?>
<form method="post" enctype="multipart/form-data" class="card narrow"><?= csrf() ?>
<p class="note">Download your statement as CSV from your bank or M-Pesa. Columns: <b>date, description, amount</b> (negative = spending) and optional <b>type</b>. Categories are filled in using your rules. Rows already in FinTrack are skipped.</p>
<label>CSV file<input type="file" name="csv" accept=".csv,text/csv" required></label>
<label>Put them in account<select name="account_id"><option value="">No account</option><?php foreach ($accts as $a) echo '<option value="'.$a['id'].'">'.e($a['name']).'</option>'; ?></select></label><button class="btn">Import</button></form>
<p class="note">Direct bank connections and live syncing need a licensed provider (for example Plaid or Open Banking), so this CSV import is the offline alternative.</p><?php page_foot();
