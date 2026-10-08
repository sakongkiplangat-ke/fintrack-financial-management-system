<?php require 'config.php'; require_login(); $uid=$_SESSION['uid']; $err=[];
if ($_SERVER['REQUEST_METHOD']==='POST') {
  check_csrf();
  if ($_POST['action']==='profile') {
    $n=trim($_POST['name']); if ($n==='') $err[]='Name is required.';
    else { $pdo->prepare('UPDATE users SET name=? WHERE id=?')->execute([$n,$uid]); $_SESSION['name']=$n; flash('Profile updated.'); header('Location: account.php'); exit; }
  } else {
    $u=$pdo->prepare('SELECT password_hash FROM users WHERE id=?'); $u->execute([$uid]); $h=$u->fetchColumn();
    if (!password_verify($_POST['current'],$h)) $err[]='Current password is incorrect.';
    elseif ($m=strong_pw($_POST['new'])) $err[]=$m;
    else { $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($_POST['new'],PASSWORD_DEFAULT),$uid]); audit($pdo,$uid,'password_changed'); flash('Password changed.'); header('Location: account.php'); exit; }
  }
}
page_head('Account'); ?>
<h1>Manage account</h1><?php foreach ($err as $x) echo '<div class="alert bad">'.e($x).'</div>'; ?>
<div class="grid two">
<form method="post" class="card"><h2>Profile</h2><?= csrf() ?><input type="hidden" name="action" value="profile"><label>Name<input name="name" required value="<?= e($_SESSION['name']) ?>"></label><button class="btn">Update</button></form>
<form method="post" class="card"><h2>Change password</h2><?= csrf() ?><input type="hidden" name="action" value="password"><label>Current password<input type="password" name="current" required></label><label>New password<input type="password" name="new" minlength="8" required></label><button class="btn">Change</button></form></div>
<?php page_foot();
