<?php
require_once "../includes/auth.php";
require_once "../config/db.php";
require_role(['admin']);

$f=$pdo->query("SELECT * FROM filieres")->fetchAll();
if($_SERVER['REQUEST_METHOD']==='POST'){
 $pdo->prepare("INSERT INTO niveaux(filiere_id,nom) VALUES (?,?)")
 ->execute([$_POST['filiere'],$_POST['nom']]);
}
$n=$pdo->query("
SELECT n.id,n.nom,f.nom filiere FROM niveaux n JOIN filieres f ON f.id=n.filiere_id
")->fetchAll();
require "../includes/header.php";
?>
<form method="post" class="card p-3 mb-3">
<select name="filiere" class="form-select mb-2">
<?php foreach($f as $x): ?><option value="<?=$x['id']?>"><?=$x['nom']?></option><?php endforeach ?>
</select>
<input name="nom" class="form-control" placeholder="Niveau">
<button class="btn btn-dark mt-2">Ajouter</button>
</form>
<table class="table">
<?php foreach($n as $x): ?>
<tr><td><?=e($x['filiere'])?></td><td><?=e($x['nom'])?></td></tr>
<?php endforeach ?>
</table>
<?php require "../includes/footer.php"; ?>
