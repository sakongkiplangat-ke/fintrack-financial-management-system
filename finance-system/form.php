<?php require 'config.php'; require_login(); $uid=$_SESSION['uid'];
$id=(int)($_GET['id'] ?? $_POST['id'] ?? 0);
$tx=['type'=>($_GET['type'] ?? '')==='expense'?'expense':'income','category'=>'','amount'=>'','description'=>'','tx_date'=>date('Y-m-d'),'account_id'=>'','notes'=>'','receipt'=>null];
if ($id) { $s=$pdo->prepare('SELECT * FROM transactions WHERE id=? AND user_id=?'); $s->execute([$id,$uid]); $tx=$s->fetch(); if (!$tx) die('Record not found.'); $tx['notes']=dec($tx['notes']); }
$accts=account_balances($pdo,$uid); $err=[];
if ($_SERVER['REQUEST_METHOD']==='POST') {
  check_csrf();
  $tx=['type'=>$_POST['type']==='expense'?'expense':'income','category'=>trim($_POST['category']),'amount'=>$_POST['amount'],'description'=>trim($_POST['description']),'tx_date'=>$_POST['tx_date'],
       'account_id'=>(int)($_POST['account_id'] ?? 0) ?: null,'notes'=>trim($_POST['notes'] ?? ''),'receipt'=>$tx['receipt'] ?? null];
  if ($tx['category']==='' || strtolower($tx['category'])==='auto') $tx['category']=auto_category($pdo,$uid,$tx['description']);
  if ($tx['category']==='') $err[]='Category is required (or describe the purchase so it can be sorted automatically).';
  if (!is_numeric($tx['amount']) || $tx['amount']<=0) $err[]='Amount must be greater than zero.';
  $d=DateTime::createFromFormat('Y-m-d',$tx['tx_date']); if (!$d || $d->format('Y-m-d')!==$tx['tx_date']) $err[]='Enter a valid date.';
  if ($tx['account_id']) { $c=$pdo->prepare('SELECT COUNT(*) FROM accounts WHERE id=? AND user_id=?'); $c->execute([$tx['account_id'],$uid]); if (!$c->fetchColumn()) $tx['account_id']=null; }
  $splits=[]; foreach (($_POST['split_cat'] ?? []) as $i=>$sc) { $sa=(float)($_POST['split_amt'][$i] ?? 0); if (trim($sc)!=='' && $sa>0) $splits[]=[trim($sc),$sa]; }
  if ($splits && !$id && abs(array_sum(array_column($splits,1))-(float)$tx['amount'])>0.01) $err[]='Split amounts must add up to the total ('.money($tx['amount']).').';
  $f=$_FILES['receipt'] ?? null;
  if (!$err && $f && $f['error']===UPLOAD_ERR_OK) {
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']); $ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','application/pdf'=>'pdf'][$mime] ?? null;
    if (!$ext || $f['size']>3000000) $err[]='Receipt must be a JPG, PNG, WebP or PDF under 3 MB.';
    else { $name=bin2hex(random_bytes(16)).'.'.$ext; if (!is_dir(__DIR__.'/uploads')) mkdir(__DIR__.'/uploads',0755); move_uploaded_file($f['tmp_name'],__DIR__.'/uploads/'.$name); $tx['receipt']=$name; }
  }
  if (!$err) {
    $notes=enc($tx['notes']);
    if ($id) { $pdo->prepare('UPDATE transactions SET type=?,category=?,amount=?,description=?,tx_date=?,account_id=?,notes=?,receipt=? WHERE id=? AND user_id=?')
        ->execute([$tx['type'],$tx['category'],$tx['amount'],$tx['description'],$tx['tx_date'],$tx['account_id'],$notes,$tx['receipt'],$id,$uid]); }
    else {
      $ins=$pdo->prepare('INSERT INTO transactions(user_id,type,category,amount,description,tx_date,account_id,notes,receipt,split_group) VALUES(?,?,?,?,?,?,?,?,?,?)');
      if ($splits) { $g=bin2hex(random_bytes(8)); foreach ($splits as $sp) $ins->execute([$uid,$tx['type'],$sp[0],$sp[1],$tx['description'],$tx['tx_date'],$tx['account_id'],$notes,$tx['receipt'],$g]); }
      else $ins->execute([$uid,$tx['type'],$tx['category'],$tx['amount'],$tx['description'],$tx['tx_date'],$tx['account_id'],$notes,$tx['receipt'],null]);
    }
    audit($pdo,$uid,$id?'tx_update':'tx_add',$tx['type'].' '.$tx['amount']);
    flash($id?'Transaction updated.':($splits?'Split transaction saved.':'Transaction saved.')); header('Location: transactions.php'); exit;
  }
}
page_head($id?'Edit transaction':'Add transaction'); ?>
<form method="post" enctype="multipart/form-data" class="card narrow" data-validate><h1><?= $id?'Edit':'Add' ?> transaction</h1>
<?php foreach ($err as $x) echo '<div class="alert bad">'.e($x).'</div>'; echo csrf(); ?>
<input type="hidden" name="id" value="<?= $id ?>">
<label>Type<select name="type"><option value="income" <?= $tx['type']==='income'?'selected':'' ?>>Income</option><option value="expense" <?= $tx['type']==='expense'?'selected':'' ?>>Expense</option></select></label>
<label>Amount (<?= e($_SESSION['cur'] ?? 'KES') ?>)<input type="number" name="amount" step="0.01" min="0.01" required value="<?= e($tx['amount']) ?>"></label>
<label>Description<input name="description" maxlength="255" value="<?= e($tx['description']) ?>" placeholder="e.g. Naivas groceries"></label>
<label>Category <span class="note">(leave blank or type "auto" to sort it by your rules)</span><input name="category" list="cats" maxlength="50" value="<?= e($tx['category']) ?>"></label>
<datalist id="cats"><option>Salary</option><option>Sales</option><option>Freelance</option><option>Allowance</option><option>Donations</option><option>Rent</option><option>Food</option><option>Transport</option><option>Utilities</option><option>School fees</option><option>Airtime</option><option>Health</option><option>Entertainment</option><option>Other</option></datalist>
<label>Account<select name="account_id"><option value="">No account</option><?php foreach ($accts as $a) echo '<option value="'.$a['id'].'"'.((int)$tx['account_id']===(int)$a['id']?' selected':'').'>'.e($a['name']).'</option>'; ?></select></label>
<label>Date<input type="date" name="tx_date" required value="<?= e($tx['tx_date']) ?>"></label>
<label>Notes (stored encrypted)<textarea name="notes" rows="2" maxlength="1000"><?= e($tx['notes']) ?></textarea></label>
<label>Receipt (JPG, PNG, WebP or PDF)<input type="file" name="receipt" id="receipt" accept="image/*,application/pdf"></label>
<p><button type="button" class="btn alt" id="scan" data-token="<?= e($_SESSION['t'] ?? '') ?>">Scan receipt (Premium)</button> <span class="note" id="scanmsg"></span></p>
<?php if (!$id): ?><details class="card"><summary><b>Split this transaction</b> across categories</summary>
<p class="note">Fill these in only if one payment covers several categories. The amounts must add up to the total above.</p>
<?php for ($i=0;$i<3;$i++): ?><div class="filters"><input name="split_cat[]" placeholder="Category"><input type="number" step="0.01" min="0" name="split_amt[]" placeholder="Amount"></div><?php endfor; ?></details><?php endif; ?>
<button class="btn">Save</button> <a class="btn alt" href="transactions.php">Cancel</a></form>
<?php page_foot();
