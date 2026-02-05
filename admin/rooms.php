<?php
require_once "../includes/auth.php";
require_once "../config/db.php";
require_role(['admin']);

if($_SERVER['REQUEST_METHOD']==='POST'){
 $pdo->prepare("INSERT INTO rooms(nom,capacite) VALUES (?,?)")
 ->execute([$_POST['nom'],$_POST['cap']]);
}
$r=$pdo->query("SELECT * FROM rooms")->fetchAll();
require "../includes/header.php";
?>
<form method="post" class="card p-3 mb-3">
<input name="nom" class="form-control mb-2" placeholder="Salle">
<input name="cap" class="form-control" placeholder="Capacité">
<button class="btn btn-dark mt-2">Ajouter</button>
</form>
<table class="table">
<?php foreach($r as $x): ?><tr><td><?=$x['nom']?></td><td><?=$x['capacite']?></td></tr><?php endforeach ?>
</table>
<?php require "../includes/footer.php"; ?>
