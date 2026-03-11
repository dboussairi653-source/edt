<?php
require_once __DIR__."/../includes/auth.php";
require_once __DIR__."/../config/db.php";
require_once __DIR__."/../includes/helpers.php";
require_role(['admin']);

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

$filieres = $pdo->query("SELECT id, nom FROM filieres ORDER BY nom")->fetchAll();

$selected_filiere_id = (int)($_GET['filiere_id'] ?? 0);
$selected_niveau_id  = (int)($_GET['niveau_id'] ?? 0);

$niveaux = [];
if ($selected_filiere_id > 0) {
  $st = $pdo->prepare("SELECT id, nom FROM niveaux WHERE filiere_id=? ORDER BY nom");
  $st->execute([$selected_filiere_id]);
  $niveaux = $st->fetchAll();
}

$groupes = [];
if ($selected_niveau_id > 0) {
  $st = $pdo->prepare("SELECT id, nom FROM groupes WHERE niveau_id=? ORDER BY nom");
  $st->execute([$selected_niveau_id]);
  $groupes = $st->fetchAll();
}

$editUser = null;
if ($action === 'edit' && $id > 0) {
  $st = $pdo->prepare("SELECT * FROM users WHERE id=?");
  $st->execute([$id]);
  $editUser = $st->fetch();

  if ($editUser) {
    // Student: derive filiere/niveau from groupe
    if (!empty($editUser['groupe_id'])) {
      $st = $pdo->prepare("
        SELECT f.id AS filiere_id, n.id AS niveau_id
        FROM groupes g
        JOIN niveaux n ON n.id=g.niveau_id
        JOIN filieres f ON f.id=n.filiere_id
        WHERE g.id=?
      ");
      $st->execute([(int)$editUser['groupe_id']]);
      $x = $st->fetch();
      if ($x) {
        $selected_filiere_id = (int)$x['filiere_id'];
        $selected_niveau_id  = (int)$x['niveau_id'];
      }
    }

    // Teacher: filiere directly from users.filiere_id
    if (($editUser['role'] ?? '') === 'teacher') {
      $selected_filiere_id = (int)($editUser['filiere_id'] ?? $selected_filiere_id);
      $selected_niveau_id = 0; // teacher doesn't need niveau
    }

    // refresh cascade for student
    $niveaux = [];
    if ($selected_filiere_id > 0) {
      $st = $pdo->prepare("SELECT id, nom FROM niveaux WHERE filiere_id=? ORDER BY nom");
      $st->execute([$selected_filiere_id]);
      $niveaux = $st->fetchAll();
    }

    $groupes = [];
    if ($selected_niveau_id > 0) {
      $st = $pdo->prepare("SELECT id, nom FROM groupes WHERE niveau_id=? ORDER BY nom");
      $st->execute([$selected_niveau_id]);
      $groupes = $st->fetchAll();
    }
  }
}

$err = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $nom   = trim($_POST['nom'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $role  = $_POST['role'] ?? 'student';

  if ($role === 'admin') $role = 'teacher'; // block admin

  $filiere_id_post = (int)($_POST['filiere_id_post'] ?? 0);
  $groupe_id_post  = (int)($_POST['groupe_id'] ?? 0);

  if ($nom === '' || $email === '') $err = "Nom et email obligatoires.";

  if ($err === '' && $role === 'teacher' && $filiere_id_post <= 0) {
    $err = "Pour un prof: choisis une filière.";
  }
  if ($err === '' && $role === 'student' && $groupe_id_post <= 0) {
    $err = "Pour un étudiant: choisis filière, niveau puis groupe.";
  }

  if ($err === '') {
    if ($action === 'add') {
      $pass = (string)($_POST['password'] ?? '');
      if ($pass === '') $err = "Mot de passe obligatoire.";
      else {
        $hash = password_hash($pass, PASSWORD_BCRYPT);

        $groupe_to_save = null;
        if ($role === 'student') $groupe_to_save = $groupe_id_post;

        $filiere_to_save = null;
        if ($role === 'teacher') $filiere_to_save = $filiere_id_post;

        $pdo->prepare("INSERT INTO users(nom,email,password_hash,role,filiere_id,groupe_id) VALUES (?,?,?,?,?,?)")
          ->execute([$nom,$email,$hash,$role,$filiere_to_save,$groupe_to_save]);

        redirect("/edt/admin/users.php");
      }
    }

    if ($action === 'edit' && $id > 0) {
      $groupe_to_save = null;
      if ($role === 'student') $groupe_to_save = $groupe_id_post;

      $filiere_to_save = null;
      if ($role === 'teacher') $filiere_to_save = $filiere_id_post;

      $pdo->prepare("UPDATE users SET nom=?,email=?,role=?,filiere_id=?,groupe_id=? WHERE id=?")
        ->execute([$nom,$email,$role,$filiere_to_save,$groupe_to_save,$id]);

      redirect("/edt/admin/users.php");
    }
  }
}

if ($action === 'delete' && $id > 0) {
  $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
  redirect("/edt/admin/users.php");
}

// list (teacher uses u.filiere_id ; student uses groupe->niveau->filiere)
$users = $pdo->query("
  SELECT u.*,
         g.nom AS groupe,
         ns.nom AS niveau_student,
         fs.nom AS filiere_student,
         ft.nom AS filiere_teacher
  FROM users u
  LEFT JOIN groupes g ON g.id=u.groupe_id
  LEFT JOIN niveaux ns ON ns.id=g.niveau_id
  LEFT JOIN filieres fs ON fs.id=ns.filiere_id
  LEFT JOIN filieres ft ON ft.id=u.filiere_id
  ORDER BY u.role, u.nom
")->fetchAll();

$title="Utilisateurs";
require "../includes/header.php";
?>

<a class="btn btn-dark mb-3" href="?action=add">+ Ajouter</a>

<?php if($err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endif; ?>

<?php if($action!=='list'): ?>
  <?php
    $default_role  = $editUser['role'] ?? 'teacher';
    if ($default_role === 'admin') $default_role = 'teacher';
    $default_name  = $editUser['nom'] ?? '';
    $default_email = $editUser['email'] ?? '';
    $default_groupe= (int)($editUser['groupe_id'] ?? 0);
    $default_teacher_filiere = (int)($editUser['filiere_id'] ?? $selected_filiere_id);
  ?>

  <!-- GET cascade for STUDENT only -->
  <form method="get" class="card p-3 mb-3">
    <input type="hidden" name="action" value="<?= e($action) ?>">
    <input type="hidden" name="id" value="<?= (int)$id ?>">

    <div class="row g-2">
      <div class="col-md-4">
        <label class="form-label">Filière</label>
        <select name="filiere_id" class="form-select" onchange="this.form.submit()">
          <option value="0">-- choisir --</option>
          <?php foreach($filieres as $f): ?>
            <option value="<?= (int)$f['id'] ?>" <?= ((int)$f['id']===$selected_filiere_id)?'selected':'' ?>>
              <?= e($f['nom']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-4">
        <label class="form-label">Niveau (étudiant)</label>
        <select name="niveau_id" id="niveauGet" class="form-select" onchange="this.form.submit()" <?= $selected_filiere_id>0 ? '' : 'disabled' ?>>
          <option value="0">-- niveau --</option>
          <?php foreach($niveaux as $n): ?>
            <option value="<?= (int)$n['id'] ?>" <?= ((int)$n['id']===$selected_niveau_id)?'selected':'' ?>>
              <?= e($n['nom']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-4 d-flex align-items-end">
        <a class="btn btn-outline-secondary w-100" href="?action=<?= e($action) ?>&id=<?= (int)$id ?>">Reset filtres</a>
      </div>
    </div>
  </form>

  <form method="post" class="card p-4" id="userForm">
    <input name="nom" class="form-control mb-2" placeholder="Nom" required value="<?= e($default_name) ?>">
    <input name="email" class="form-control mb-2" placeholder="Email" required value="<?= e($default_email) ?>">

    <select name="role" class="form-select mb-2" id="roleSelect">
      <option value="teacher" <?= $default_role==='teacher'?'selected':'' ?>>teacher</option>
      <option value="student" <?= $default_role==='student'?'selected':'' ?>>student</option>
    </select>

    <!-- teacher filiere -->
    <div id="teacherBox">
      <label class="form-label">Filière (prof)</label>
      <select name="filiere_id_post" class="form-select mb-2" id="teacherFiliere">
        <option value="0">-- choisir --</option>
        <?php foreach($filieres as $f): ?>
          <option value="<?= (int)$f['id'] ?>" <?= ((int)$f['id']===$default_teacher_filiere)?'selected':'' ?>>
            <?= e($f['nom']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <div class="text-muted small mb-2">Prof = seulement filière (peut enseigner plusieurs niveaux).</div>
    </div>

    <!-- student selection -->
    <div id="studentBox">
      <label class="form-label">Groupe (étudiant)</label>
      <select name="groupe_id" class="form-select mb-2" id="groupeSelect">
        <option value="0">-- choisir --</option>
        <?php foreach($groupes as $g): ?>
          <option value="<?= (int)$g['id'] ?>" <?= ((int)$g['id']===$default_groupe)?'selected':'' ?>>
            <?= e($g['nom']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <div class="text-muted small mb-2">Étudiant = choisir filière + niveau + groupe.</div>
    </div>

    <?php if($action==='add'): ?>
      <input type="password" name="password" class="form-control mb-2" required placeholder="Mot de passe">
    <?php endif; ?>

    <button class="btn btn-success">Enregistrer</button>
  </form>

  <script>
    document.addEventListener("DOMContentLoaded", () => {
      const role = document.getElementById("roleSelect");
      const teacherBox = document.getElementById("teacherBox");
      const studentBox = document.getElementById("studentBox");
      const groupeSelect = document.getElementById("groupeSelect");
      const niveauGet = document.getElementById("niveauGet");

      function apply() {
        const isTeacher = role.value === "teacher";
        teacherBox.style.display = isTeacher ? "" : "none";
        studentBox.style.display = isTeacher ? "none" : "";

        if (!groupeSelect) return;

        if (isTeacher) {
          groupeSelect.value = "0";
          groupeSelect.disabled = true;
          return;
        }

        // student
        const niveauOk = (niveauGet && niveauGet.value !== "0" && !niveauGet.disabled);
        groupeSelect.disabled = !niveauOk;
      }

      role.addEventListener("change", apply);
      if (niveauGet) niveauGet.addEventListener("change", apply);

      apply();
    });
  </script>

<?php else: ?>

<table class="table table-bordered">
  <thead>
    <tr>
      <th>Nom</th><th>Email</th><th>Rôle</th><th>Filière</th><th>Niveau</th><th>Groupe</th><th>Actions</th>
    </tr>
  </thead>
  <?php foreach($users as $u): ?>
    <?php
      $filiere = $u['role']==='teacher' ? ($u['filiere_teacher'] ?? '') : ($u['filiere_student'] ?? '');
      $niveau  = $u['role']==='student' ? ($u['niveau_student'] ?? '') : '';
      $groupe  = $u['role']==='student' ? ($u['groupe'] ?? '') : '';
    ?>
    <tr>
      <td><?= e($u['nom']) ?></td>
      <td><?= e($u['email']) ?></td>
      <td><?= e($u['role']) ?></td>
      <td><?= e((string)$filiere) ?></td>
      <td><?= e((string)$niveau) ?></td>
      <td><?= e((string)$groupe) ?></td>
      <td>
        <a href="?action=edit&id=<?= (int)$u['id'] ?>">✏️</a>
        <a href="?action=delete&id=<?= (int)$u['id'] ?>" onclick="return confirm('Supprimer ?')">🗑</a>
      </td>
    </tr>
  <?php endforeach ?>
</table>

<?php endif; ?>

<?php require "../includes/footer.php"; ?>
