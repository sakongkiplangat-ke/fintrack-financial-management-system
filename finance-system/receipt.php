<?php require 'config.php'; require_login();
$s=$pdo->prepare('SELECT receipt FROM transactions WHERE id=? AND user_id=?'); $s->execute([(int)($_GET['id'] ?? 0),$_SESSION['uid']]); $f=$s->fetchColumn();
if (!$f || !preg_match('/^[a-f0-9]{32}\.(jpg|png|webp|pdf)$/',$f) || !is_file(__DIR__.'/uploads/'.$f)) { http_response_code(404); die('No receipt.'); }
$ext=substr($f,-3); header('Content-Type: '.['jpg'=>'image/jpeg','png'=>'image/png','ebp'=>'image/webp','pdf'=>'application/pdf'][$ext] ?? 'application/octet-stream');
header('Content-Disposition: inline; filename="receipt.'.$ext.'"'); readfile(__DIR__.'/uploads/'.$f);
