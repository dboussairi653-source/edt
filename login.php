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

  if (!$u || $pass !== $u['password_hash']) {
    $error = "Email ou mot de passe incorrect.";
  } else {
    unset($u['password_hash']);
    $_SESSION['user'] = $u;
    redirect("/edt/index.php");
  }
}

$title = "Connexion";
$body_class = "page-login"; // ✅ pour activer le style login + animations
$hide_back_button = true;
require __DIR__ . "/includes/header.php";
?>

<div class="login-wrap">
  <div class="login-card">

    <!-- LEFT: hero (futuriste) -->
    <div class="login-hero">
      <span class="spark s1"></span><span class="spark s2"></span><span class="spark s3"></span><span class="spark s4"></span>

      <h2>EDT</h2>
      <p>Une architecture numérique conçue pour la gestion
académique moderne.</p>

      <div class="hero-badges">
        <span>✨ Clarté</span>
        <span>⚡ Structure</span>
        <span>🔒 Sécurisé</span>
      </div>
    </div>

    <!-- RIGHT: form -->
    <div class="login-form">
      <h1>Connexion</h1> <!-- ✅ centré via login.css -->

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

        <!-- ✅ bouton stylé login -->
        <button class="btn-login" type="submit">Se connecter</button>
      </form>
    </div>

  </div>
</div>

<?php require __DIR__ . "/includes/footer.php"; ?>
