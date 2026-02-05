<?php
require_once "../includes/auth.php";
require_once "../config/db.php";
require_role(['admin']);

if($_SERVER['REQUEST_METHOD']==='POST'){
  $pdo->prepare("INSERT INTO filieres(nom) VALUES (?)")->execute([$_POST['nom']]);
}
if(isset($_GET['del'])){
  $pdo->prepare("DELETE FROM filieres WHERE id=?")->execute([$_GET['del']]);
}
$f=$pdo->query("SELECT * FROM filieres")->fetchAll();
require "../includes/header.php";
?>
<form method="post" class="mb-3">
<input name="nom" class="form-control" placeholder="Nouvelle filière">
</form>
<table class="table">
<?php foreach($f as $x): ?>
<tr><td><?=e($x['nom'])?></td>
<td><a href="?del=<?=$x['id']?>">🗑</a></td></tr>
<?php endforeach ?>
</table>
<?php require "../includes/footer.php"; ?>
