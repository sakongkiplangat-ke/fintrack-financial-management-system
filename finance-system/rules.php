<?php require 'config.php'; require_login(); $uid=$_SESSION['uid'];
if ($_SERVER['REQUEST_METHOD']==='POST') { check_csrf(); $a=$_POST['action'] ?? '';
  if ($a==='delete') $pdo->prepare('DELETE FROM rules WHERE id=? AND user_id=?')->execute([(int)$_POST['id'],$uid]);
  elseif ($a==='apply') { $n=0; $s=$pdo->prepare("SELECT id,description FROM transactions WHERE user_id=? AND (category='' OR category='Other')"); $s->execute([$uid]);
    foreach ($s->fetchAll() as $r) { $c=auto_category($pdo,$uid,$r['description']); if ($c!=='' && $c!=='Other') { $pdo->prepare('UPDATE transactions SET category=? WHERE id=? AND user_id=?')->execute([$c,$r['id'],$uid]); $n++; } } flash("Re-sorted $n transaction(s)."); }
  else { $k=mb_substr(trim($_POST['keyword'] ?? ''),0,60); $c=mb_substr(trim($_POST['category'] ?? ''),0,50); if ($k===''||$c==='') flash('Enter a keyword and a category.'); else { $pdo->prepare('INSERT INTO rules(user_id,keyword,category) VALUES(?,?,?)')->execute([$uid,$k,$c]); flash('Rule added.'); } }
  header('Location: rules.php'); exit; }
$st=$pdo->prepare('SELECT * FROM rules WHERE user_id=? ORDER BY keyword'); $st->execute([$uid]); page_head('Transaction rules'); ?>
<h1>Transaction rules</h1><p class="note">When a description contains your keyword, the transaction gets that category automatically. FinTrack also knows common names such as Naivas, Uber, KPLC and Safaricom.</p>
<div class="card scroll"><table><tr><th>If the description contains</th><th>Set category to</th><th></th></tr>
<?php foreach ($st as $r): ?><tr><td><?= e($r['keyword']) ?></td><td><?= e($r['category']) ?></td><td><form method="post"><?= csrf() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="link">Delete</button></form></td></tr><?php endforeach; ?></table></div>
<form method="post" class="card narrow"><h2 style="margin-top:0">New rule</h2><?= csrf() ?><label>Keyword<input name="keyword" required maxlength="60" placeholder="e.g. chips mwitu"></label><label>Category<input name="category" required maxlength="50" placeholder="e.g. Food"></label><button class="btn">Add rule</button></form>
<form method="post" class="card"><?= csrf() ?><input type="hidden" name="action" value="apply"><p>Re-sort old transactions that are uncategorised or marked "Other" using these rules.</p><button class="btn alt">Apply to past transactions</button></form>
<?php page_foot();
