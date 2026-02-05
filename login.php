<?php
declare(strict_types=1);
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/includes/helpers.php";
require_once __DIR__ . "/includes/auth.php";

if (!empty($_SESSION['user'])) redirect("/edt/index.php");

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim($_POST['email'] ?? '');
  $pass  = $_POST['password'] ?? '';

  $stmt = $pdo->prepare("SELECT id, nom, email, password_hash, role, groupe_id FROM users WHERE email = ?");
  $stmt->execute([$email]);
  $u = $stmt->fetch();

  if (!$u || !password_verify($pass, $u['password_hash'])) {
    $error = "Email ou mot de passe incorrect.";
  } else {
    unset($u['password_hash']);
    $_SESSION['user'] = $u;
    redirect("/edt/index.php");
  }
}

$title = "Connexion";
require __DIR__ . "/includes/header.php";
?>
<div class="row justify-content-center">
  <div class="col-md-5">
    <div class="card p-4">
      <h4 class="mb-3">Connexion</h4>
      <?php if ($error): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
      <?php endif; ?>
      <form method="post" class="vstack gap-3">
        <div>
          <label class="form-label">Email</label>
          <input class="form-control" type="email" name="email" required>
        </div>
        <div>
          <label class="form-label">Mot de passe</label>
          <input class="form-control" type="password" name="password" required>
        </div>
        <button class="btn btn-dark w-100">Se connecter</button>
        <div class="text-muted small">
          Admin: <b>admin@admin.com</b> / <b>Admin@123</b>
        </div>
      </form>
    </div>
  </div>
</div>
<?php require __DIR__ . "/includes/footer.php"; ?>
