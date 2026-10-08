<?php require 'config.php';
$err=''; $done=false; $link='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
  check_csrf();
  if (($_SESSION['fp_t'] ?? 0) > time()-20) $err='Please wait a few seconds before trying again.';
  else {
    $_SESSION['fp_t']=time();
    $email=strtolower(trim($_POST['email'] ?? ''));
    $st=$pdo->prepare('SELECT id,name FROM users WHERE email=?'); $st->execute([$email]); $u=$st->fetch();
    if ($u) {
      $token=bin2hex(random_bytes(32));   // only a hash of this is stored
      $pdo->prepare('DELETE FROM password_resets WHERE user_id=?')->execute([$u['id']]);
      $pdo->prepare('INSERT INTO password_resets(user_id,token_hash,expires_at) VALUES(?,?,DATE_ADD(NOW(), INTERVAL 1 HOUR))')
          ->execute([$u['id'], hash('sha256',$token)]);
      $link=base_url().'/reset.php?token='.$token;
      if (RESET_DEV_MODE) error_log('FinTrack reset link for '.$email.': '.$link);
      else @mail($email,'FinTrack password reset',
        "Hi ".$u['name'].",\n\nUse this link to reset your FinTrack password (valid for 1 hour):\n".$link."\n\nIf you did not ask for this, you can ignore this email.",
        'From: no-reply@'.($_SERVER['HTTP_HOST'] ?? 'localhost'));
    }
    $done=true;   // same message whether or not the email exists
  }
}
page_head('Forgot password', false); ?>
<form method="post" class="card auth"><h1>Forgot password</h1>
<?php if ($err) echo '<div class="alert bad">'.e($err).'</div>';
if ($done) {
  echo '<div class="alert ok" style="animation:none">If that email is registered, a reset link has been sent. It expires in 1 hour.</div>';
  if (RESET_DEV_MODE && $link) echo '<div class="alert bad" style="word-break:break-all"><b>Dev mode (no email server):</b> <a href="'.e($link).'">Open reset link</a></div>';
}
echo csrf(); ?>
<p style="color:var(--mut)">Enter your account email and we will send you a link to choose a new password.</p>
<label>Email<input type="email" name="email" required></label>
<button class="btn">Send reset link</button><p><a href="login.php">Back to login</a></p></form>
<?php page_foot();
