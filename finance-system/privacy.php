<?php require 'config.php'; require_login(); $uid=$_SESSION['uid']; $u=cur_u($pdo,$uid);
if ($_SERVER['REQUEST_METHOD']==='POST') { check_csrf(); $a=$_POST['action'] ?? '';
  if ($a==='ai') { $pdo->prepare('UPDATE users SET ai_share=? WHERE id=?')->execute([empty($_POST['ai_share'])?0:1,$uid]); flash('Privacy choice saved.'); header('Location: privacy.php'); exit; }
  if ($a==='export') { $out=['exported'=>date('c'),'profile'=>array_diff_key($u,['password_hash'=>1,'totp_secret'=>1])];
    foreach (['accounts','transactions','recurring','budgets','goals','debts','assets','rules','tuition','networth_history'] as $t) { $s=$pdo->prepare("SELECT * FROM $t WHERE user_id=?"); $s->execute([$uid]); $out[$t]=$s->fetchAll(); }
    foreach ($out['transactions'] as &$r) $r['notes']=dec($r['notes']); unset($r); audit($pdo,$uid,'data_export');
    header('Content-Type: application/json'); header('Content-Disposition: attachment; filename=fintrack-my-data.json'); echo json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE); exit; }
  if ($a==='delete') { if (password_verify($_POST['password'] ?? '',$u['password_hash']) && ($_POST['confirm'] ?? '')==='DELETE') {
      $s=$pdo->prepare('SELECT receipt FROM transactions WHERE user_id=? AND receipt IS NOT NULL'); $s->execute([$uid]); foreach ($s->fetchAll(PDO::FETCH_COLUMN) as $f) if (preg_match('/^[a-f0-9]{32}\.\w+$/',$f)) @unlink(__DIR__.'/uploads/'.$f);
      audit($pdo,null,'account_deleted','user #'.$uid); $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$uid]); $_SESSION=[]; session_destroy(); session_start(); flash('Your account and all its data were deleted.'); header('Location: login.php'); exit; }
    flash('Password or confirmation word was wrong. Nothing was deleted.'); header('Location: privacy.php'); exit; } }
page_head('Privacy'); ?><h1>Privacy &amp; your data</h1>
<form method="post" class="card narrow"><?= csrf() ?><input type="hidden" name="action" value="ai"><h2 style="margin-top:0">AI features</h2>
<label><input type="checkbox" name="ai_share" value="1" style="display:inline;width:auto" <?= $u['ai_share']?'checked':'' ?>> Let FinTrack send my category totals to the AI assistant</label><p class="note">Names, emails and transaction descriptions are never sent. Turn this off and the assistant uses simple on-site rules only.</p><button class="btn">Save</button></form>
<form method="post" class="card narrow"><?= csrf() ?><input type="hidden" name="action" value="export"><h2 style="margin-top:0">Download my data</h2><p>Get everything FinTrack holds about you as a JSON file.</p><button class="btn alt">Download</button></form>
<form method="post" class="card narrow"><?= csrf() ?><input type="hidden" name="action" value="delete"><h2 style="margin-top:0;color:var(--exp)">Delete my account</h2><p>This permanently removes your account, transactions, receipts and everything else. It cannot be undone.</p>
<label>Password<input type="password" name="password" required></label><label>Type DELETE to confirm<input name="confirm" required autocomplete="off"></label><button class="btn" style="background:var(--exp)">Delete everything</button></form><?php page_foot();
