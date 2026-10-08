<?php require 'config.php';
$err=''; $ip=$_SERVER['REMOTE_ADDR'] ?? ''; $ua=substr($_SERVER['HTTP_USER_AGENT'] ?? '',0,200);
function finish_login($pdo,$u,$ip,$ua){
  $seen=$pdo->prepare('SELECT COUNT(*) FROM login_log WHERE user_id=? AND ok=1 AND ip=?'); $seen->execute([$u['id'],$ip]); $isNew=!$seen->fetchColumn();
  $any=$pdo->prepare('SELECT COUNT(*) FROM login_log WHERE user_id=? AND ok=1'); $any->execute([$u['id']]); $hadAny=(bool)$any->fetchColumn();
  $pdo->prepare('INSERT INTO login_log(user_id,email,ip,ua,ok) VALUES(?,?,?,?,1)')->execute([$u['id'],$u['email'],$ip,$ua]);
  session_regenerate_id(true); load_session_user($u); unset($_SESSION['pending']); audit($pdo,$u['id'],'login');
  if ($isNew && $hadAny) { audit($pdo,$u['id'],'login_alert','New device/location '.$ip);
    if (!RESET_DEV_MODE) @mail($u['email'],'New FinTrack login','We noticed a login to your FinTrack account from a new location ('.$ip.'). If this was not you, change your password now.');
    flash('Security notice: this login came from a new location ('.$ip.'). If this was not you, change your password.'); }
  header('Location: dashboard.php'); exit;
}
if ($_SERVER['REQUEST_METHOD']==='POST') {
  check_csrf();
  if (!empty($_SESSION['pending']) && isset($_POST['code'])) {
    $u=cur_u($pdo,(int)$_SESSION['pending']);
    if ($u && totp_ok($u['totp_secret'],$_POST['code'])) finish_login($pdo,$u,$ip,$ua);
    $err='That code is not correct.'; $pdo->prepare('INSERT INTO login_log(user_id,email,ip,ua,ok) VALUES(?,?,?,?,0)')->execute([$u['id'] ?? null,'2fa',$ip,$ua]);
  } else {
    $f=$pdo->prepare('SELECT COUNT(*) FROM login_log WHERE ip=? AND ok=0 AND at>DATE_SUB(NOW(),INTERVAL 15 MINUTE)'); $f->execute([$ip]);
    if ($f->fetchColumn()>=5) $err='Too many failed attempts. Please wait 15 minutes and try again.';
    else {
      $email=strtolower(trim($_POST['email'])); $st=$pdo->prepare('SELECT * FROM users WHERE email=?'); $st->execute([$email]); $u=$st->fetch();
      if ($u && password_verify($_POST['password'],$u['password_hash'])) {
        if ($u['totp_secret']) { session_regenerate_id(true); $_SESSION['pending']=$u['id']; }
        else finish_login($pdo,$u,$ip,$ua);
      } else { $err='Invalid email or password.'; $pdo->prepare('INSERT INTO login_log(user_id,email,ip,ua,ok) VALUES(?,?,?,?,0)')->execute([$u['id'] ?? null,substr($email,0,150),$ip,$ua]); }
    }
  }
}
page_head('Login', false); ?>
<?php if (!empty($_SESSION['pending'])): ?>
<form method="post" class="card auth"><h1>Two-step check</h1>
<?php if ($err) echo '<div class="alert bad">'.e($err).'</div>'; echo csrf(); ?>
<p class="note">Open your authenticator app and type the 6-digit code for FinTrack.</p>
<label>Code<input name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required autofocus></label>
<button class="btn">Verify</button> <a href="logout.php">Cancel</a></form>
<?php else: ?>
<form method="post" class="card auth"><h1>FinTrack Login</h1>
<?php if ($err) echo '<div class="alert bad">'.e($err).'</div>'; echo csrf(); ?>
<label>Email<input type="email" name="email" required></label>
<label>Password<input type="password" name="password" required></label>
<button class="btn">Log in</button><p><a href="forgot.php">Forgot password?</a></p><p>New here? <a href="register.php">Create an account</a></p></form>
<?php endif; page_foot();
