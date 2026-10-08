<?php require 'config.php'; require_login(); $uid=$_SESSION['uid']; $al=build_alerts($pdo,$uid);
if (isset($_GET['json'])) { header('Content-Type: application/json'); echo json_encode($al); exit; }
page_head('Alerts'); ?><h1>Alerts</h1>
<p class="note">Budget limits, low balance, upcoming bills, savings goals, unusual spending and recurring payments. Set your low-balance level in <a href="settings.php">Settings</a>.</p>
<?php foreach ($al as $a) echo '<div class="alert '.($a[0]==='bad'?'bad':($a[0]==='ok'?'ok':($a[0]==='warn'?'warn':'info'))).'">'.e($a[1]).'</div>'; if (!$al) echo '<div class="card">All clear. Nothing needs your attention.</div>'; ?>
<p><button class="btn alt" id="enable-notify" type="button">Turn on browser notifications</button></p><?php page_foot();
