<?php
require_once __DIR__."/../includes/auth.php";
require_once __DIR__."/../config/db.php";
require_once __DIR__."/../includes/helpers.php";
require_role(['admin']);

$action=$_GET['action']??'list';
$id=(int)($_GET['id']??0);

$groups=$pdo->query("
SELECT g.id, CONCAT(f.nom,'/',n.nom,'/',g.nom) label
FROM groupes g 
JOIN niveaux n ON n.id=g.niveau_id
JOIN filieres f ON f.id=n.filiere_id
")->fetchAll();

if($_SERVER['REQUEST_METHOD']==='POST'){
  $nom=$_POST['nom'];
  $email=$_POST['email'];
  $role=$_POST['role'];
  $groupe=$_POST['groupe_id']?:null;
  if($action==='add'){
    $hash=password_hash($_POST['password'],PASSWORD_BCRYPT);
    $pdo->prepare("INSERT INTO users(nom,email,password_hash,role,groupe_id) VALUES (?,?,?,?,?)")
    ->execute([$nom,$email,$hash,$role,$groupe]);
  }elseif($action==='edit'){
    $pdo->prepare("UPDATE users SET nom=?,email=?,role=?,groupe_id=? WHERE id=?")
    ->execute([$nom,$email,$role,$groupe,$id]);
  }
  redirect("/edt/admin/users.php");
}

if($action==='delete'){
  $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
  redirect("/edt/admin/users.php");
}

$users=$pdo->query("SELECT * FROM users ORDER BY role,nom")->fetchAll();
$title="Utilisateurs";
require "../includes/header.php";
?>

<a class="btn btn-dark mb-3" href="?action=add">+ Ajouter</a>

<?php if($action!=='list'): ?>
<form method="post" class="card p-4">
<input name="nom" class="form-control mb-2" placeholder="Nom" required>
<input name="email" class="form-control mb-2" placeholder="Email" required>
<select name="role" class="form-select mb-2">
<option>admin</option><option>teacher</option><option>student</option>
</select>
<select name="groupe_id" class="form-select mb-2">
<option value="">-- groupe --</option>
<?php foreach($groups as $g): ?>
<option value="<?= $g['id']?>"><?= e($g['label'])?></option>
<?php endforeach ?>
</select>
<?php if($action==='add'): ?>
<input type="password" name="password" class="form-control mb-2" required>
<?php endif ?>
<button class="btn btn-success">Enregistrer</button>
</form>
<?php else: ?>
<table class="table table-bordered">
<?php foreach($users as $u): ?>
<tr>
<td><?=e($u['nom'])?></td>
<td><?=e($u['email'])?></td>
<td><?=e($u['role'])?></td>
<td>
<a href="?action=edit&id=<?=$u['id']?>">✏️</a>
<a href="?action=delete&id=<?=$u['id']?>" onclick="return confirm('Supprimer ?')">🗑</a>
</td>
</tr>
<?php endforeach ?>
</table>
<?php endif ?>
<?php require "../includes/footer.php"; ?>
