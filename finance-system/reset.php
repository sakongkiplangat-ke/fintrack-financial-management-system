<?php require 'config.php';
$token=$_GET['token'] ?? $_POST['token'] ?? '';
$row=false;
if (is_string($token) && preg_match('/^[a-f0-9]{64}$/',$token)) {
  $st=$pdo->prepare('SELECT user_id FROM password_resets WHERE token_hash=? AND expires_at>NOW()');
  $st->execute([hash('sha256',$token)]); $row=$st->fetch();
}
$err=[];
if ($row && $_SERVER['REQUEST_METHOD']==='POST') {
  check_csrf();
  $pw=$_POST['password'] ?? '';
  if ($m=strong_pw($pw)) $err[]=$m;
  if ($pw!==($_POST['confirm'] ?? '')) $err[]='Passwords do not match.';
  if (!$err) {
    $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($pw,PASSWORD_DEFAULT),$row['user_id']]);
    $pdo->prepare('DELETE FROM password_resets WHERE user_id=?')->execute([$row['user_id']]);   // link works only once
    flash('Password updated. Please log in.'); header('Location: login.php'); exit;
  }
}
page_head('Reset password', false);
if (!$row): ?>
<div class="card auth"><h1>Reset password</h1><div class="alert bad">This reset link is invalid or has expired.</div>
<p><a class="btn" href="forgot.php">Request a new link</a></p></div>
<?php else: ?>
<form method="post" class="card auth" data-validate><h1>Choose a new password</h1>
<?php foreach ($err as $x) echo '<div class="alert bad">'.e($x).'</div>'; echo csrf(); ?>
<input type="hidden" name="token" value="<?= e($token) ?>">
<label>New password<input type="password" name="password" minlength="8" required></label>
<label>Confirm new password<input type="password" name="confirm" required></label>
<button class="btn">Update password</button></form>
<?php endif; page_foot();
