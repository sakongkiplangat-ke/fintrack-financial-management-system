<?php require 'config.php';
$err=[]; $name=$email='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
  check_csrf();
  $name=trim($_POST['name']); $cu=array_key_exists($_POST['currency'] ?? '',CURRENCIES)?$_POST['currency']:'KES'; $co=in_array($_POST['country'] ?? '',COUNTRIES)?$_POST['country']:'Other'; $lg=array_key_exists($_POST['lang'] ?? '',LANGS)?$_POST['lang']:'en'; $tz=in_array($_POST['tz'] ?? '',DateTimeZone::listIdentifiers())?$_POST['tz']:'Africa/Nairobi'; $pt=array_key_exists($_POST['ptype'] ?? '',PROFILES)?$_POST['ptype']:'other'; $email=strtolower(trim($_POST['email'])); $pw=$_POST['password']; 
  if ($name==='') $err[]='Name is required.';
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err[]='Enter a valid email.';
  if ($m=strong_pw($pw)) $err[]=$m;
  if ($pw!==$_POST['confirm']) $err[]='Passwords do not match.';
  if (!$err) {
    $c=$pdo->prepare('SELECT id FROM users WHERE email=?'); $c->execute([$email]);
    if ($c->fetch()) $err[]='That email is already registered.';
    else {
      $pdo->prepare('INSERT INTO users(name,email,password_hash,currency,country,lang,timezone,profile_type) VALUES(?,?,?,?,?,?,?,?)')->execute([$name,$email,password_hash($pw,PASSWORD_DEFAULT),$cu,$co,$lg,$tz,$pt]);
      audit($pdo,(int)$pdo->lastInsertId(),'register');
      flash('Account created. Please log in.'); header('Location: login.php'); exit;
    }
  }
}
page_head('Register', false); ?>
<form method="post" class="card auth" data-validate><h1>Create account</h1>
<?php foreach ($err as $x) echo '<div class="alert bad">'.e($x).'</div>'; echo csrf(); ?>
<label>Full name<input name="name" required value="<?= e($name) ?>"></label>
<label>Email<input type="email" name="email" required value="<?= e($email) ?>"></label>
<label>I am a<select name="ptype"><?php foreach (PROFILES as $k=>$v) echo '<option value="'.$k.'">'.$v.'</option>'; ?></select></label>
<label>Country<select name="country"><?php foreach (COUNTRIES as $c) echo '<option'.($c==='Kenya'?' selected':'').'>'.e($c).'</option>'; ?></select></label>
<label>Currency<select name="currency"><?php foreach (CURRENCIES as $k=>$v) echo '<option value="'.$k.'">'.$k.' - '.e($v).'</option>'; ?></select></label>
<label>Language<select name="lang"><?php foreach (LANGS as $k=>$v) echo '<option value="'.$k.'">'.e($v).'</option>'; ?></select></label>
<label>Time zone<select name="tz"><?php foreach (DateTimeZone::listIdentifiers() as $z) echo '<option'.($z==='Africa/Nairobi'?' selected':'').'>'.e($z).'</option>'; ?></select></label>
<label>Password (8+ characters, with upper, lower and a number)<input type="password" name="password" minlength="8" required></label>
<label>Confirm password<input type="password" name="confirm" required></label>
<button class="btn">Register</button><p>Have an account? <a href="login.php">Log in</a></p></form>
<?php page_foot();
