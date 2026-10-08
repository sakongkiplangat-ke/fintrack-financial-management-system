<?php require 'config.php'; require_login(); $uid=$_SESSION['uid']; $u=cur_u($pdo,$uid);
if ($_SERVER['REQUEST_METHOD']==='POST') { check_csrf();
  $n=mb_substr(trim($_POST['name'] ?? ''),0,100); $cu=array_key_exists($_POST['currency'] ?? '',CURRENCIES)?$_POST['currency']:'KES'; $co=in_array($_POST['country'] ?? '',COUNTRIES)?$_POST['country']:'Other';
  $lg=array_key_exists($_POST['lang'] ?? '',LANGS)?$_POST['lang']:'en'; $tz=in_array($_POST['tz'] ?? '',DateTimeZone::listIdentifiers())?$_POST['tz']:'Africa/Nairobi'; $pt=array_key_exists($_POST['ptype'] ?? '',PROFILES)?$_POST['ptype']:'other';
  if ($n==='') flash('Name is required.'); else {
    $pdo->prepare('UPDATE users SET name=?,currency=?,country=?,lang=?,timezone=?,profile_type=?,low_balance=?,org_name=? WHERE id=?')->execute([$n,$cu,$co,$lg,$tz,$pt,max(0,(float)($_POST['low'] ?? 0)),mb_substr(trim($_POST['org'] ?? ''),0,100),$uid]);
    load_session_user(cur_u($pdo,$uid)); audit($pdo,$uid,'settings'); flash('Settings saved.'); }
  header('Location: settings.php'); exit; }
page_head('Settings'); ?><h1>Settings</h1>
<form method="post" class="card narrow"><?= csrf() ?>
<label>Name<input name="name" required maxlength="100" value="<?= e($u['name']) ?>"></label>
<label>I am a<select name="ptype"><?php foreach (PROFILES as $k=>$v) echo '<option value="'.$k.'"'.($u['profile_type']===$k?' selected':'').'>'.$v.'</option>'; ?></select></label>
<label>Country<select name="country"><?php foreach (COUNTRIES as $c) echo '<option'.($u['country']===$c?' selected':'').'>'.e($c).'</option>'; ?></select></label>
<label>Currency<select name="currency"><?php foreach (CURRENCIES as $k=>$v) echo '<option value="'.$k.'"'.($u['currency']===$k?' selected':'').'>'.$k.' - '.e($v).'</option>'; ?></select></label>
<label>Language<select name="lang"><?php foreach (LANGS as $k=>$v) echo '<option value="'.$k.'"'.($u['lang']===$k?' selected':'').'>'.e($v).'</option>'; ?></select></label>
<label>Time zone<select name="tz"><?php foreach (DateTimeZone::listIdentifiers() as $z) echo '<option'.($u['timezone']===$z?' selected':'').'>'.e($z).'</option>'; ?></select></label>
<label>Low-balance alert level<input type="number" step="0.01" min="0" name="low" value="<?= e($u['low_balance']) ?>"></label>
<label>University or organisation <span class="note">(optional)</span><input name="org" maxlength="100" value="<?= e($u['org_name']) ?>"></label>
<button class="btn">Save settings</button>
<p class="note">Currency here only changes how amounts are labelled. FinTrack does not convert between currencies. Menu translation covers English, Kiswahili and French.</p></form><?php page_foot();
