<?php // Receipt scanning / OCR via Claude vision. Premium. Returns JSON {merchant,total,date,category}
require 'config.php'; require_login(); header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD']!=='POST') { http_response_code(405); exit; } check_csrf();
if (!is_premium()) { echo json_encode(['error'=>'Receipt scanning is a Premium feature.']); exit; }
if (AI_KEY==='') { echo json_encode(['error'=>'Add your API key in config.php to enable receipt scanning.']); exit; }
$f=$_FILES['receipt'] ?? null; if (!$f || $f['error']!==UPLOAD_ERR_OK || $f['size']>4000000) { echo json_encode(['error'=>'Choose an image under 4 MB.']); exit; }
$mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']); if (!in_array($mime,['image/jpeg','image/png','image/webp'])) { echo json_encode(['error'=>'Use a JPG, PNG or WebP photo.']); exit; }
if (($_SESSION['scan_t'] ?? 0)>time()-5) { echo json_encode(['error'=>'Please wait a few seconds.']); exit; } $_SESSION['scan_t']=time();
$txt=ai_call('You read shopping receipts. Reply with ONLY a JSON object: {"merchant":string,"total":number,"date":"YYYY-MM-DD" or null,"category":one of ["Food","Transport","Utilities","Airtime","Rent","School fees","Health","Entertainment","Other"]}. No markdown.','Extract the receipt details.',300,['type'=>$mime,'data'=>base64_encode(file_get_contents($f['tmp_name']))]);
$d=$txt?json_decode(trim(preg_replace('/^```(?:json)?\s*|\s*```$/','',trim($txt))),true):null;
echo json_encode(is_array($d)?$d:['error'=>'Could not read that receipt. Enter the details by hand.']);
