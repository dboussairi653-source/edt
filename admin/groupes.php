<?php
require_once "../includes/auth.php";
require_once "../config/db.php";
require_role(['admin']);

$n=$pdo->query("SELECT * FROM niveaux")->fetchAll();
if($_SERVER['REQUEST_METHOD']==='POST'){
 $pdo->prepare("INSERT INTO groupes(niveau_id,nom) VALUES (?,?)")
 ->execute([$_POST['niveau'],$_POST['nom']]);
}
$g=$pdo->query("
SELECT g.nom,n.nom niv FROM groupes g JOIN niveaux n ON n.id=g.niveau_id
")->fetchAll();
require "../includes/header.php";
?>
<form method="post" class="card p-3 mb-3">
<select name="niveau" class="form-select">
<?php foreach($n as $x): ?><option value="<?=$x['id']?>"><?=$x['nom']?></option><?php endforeach ?>
</select>
<input name="nom" class="form-control mt-2" placeholder="Groupe">
<button class="btn btn-dark mt-2">Ajouter</button>
</form>
<table class="table">
<?php foreach($g as $x): ?><tr><td><?=$x['niv']?></td><td><?=$x['nom']?></td></tr><?php endforeach ?>
</table>
<?php require "../includes/footer.php"; ?>
