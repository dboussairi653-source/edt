<?php
require_once "../includes/auth.php";
require_once "../config/db.php";
require_role(['teacher']);

$u = current_user();

$st = $pdo->prepare("
  SELECT te.*,
         m.code, m.titre,
         r.nom AS room_name, r.type AS room_type,
         COALESCE(g.nom,'(commun niveau)') AS group_name
  FROM timetable_entries te
  JOIN modules m ON m.id=te.module_id
  JOIN rooms r ON r.id=te.room_id
  LEFT JOIN groupes g ON g.id=te.group_id
  WHERE te.teacher_id=?
  ORDER BY te.week_start DESC, te.day_of_week, te.start_time
");
$st->execute([(int)$u['id']]);

require "../includes/header.php";
?>
<h4>Mon emploi du temps</h4>
<table class="table table-bordered">
  <thead>
    <tr>
      <th>Semaine</th><th>Jour</th><th>Heure</th><th>Module</th><th>Type</th><th>Salle</th><th>Groupe</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach($st as $x): ?>
    <tr>
      <td><?= e($x['week_start']) ?></td>
      <td><?= e(day_name((int)$x['day_of_week'])) ?></td>
      <td><?= e(substr($x['start_time'],0,5)) ?>-<?= e(substr($x['end_time'],0,5)) ?></td>
      <td><?= e($x['code']." - ".$x['titre']) ?></td>
      <td><?= e(strtoupper($x['kind'])) ?></td>
      <td><?= e($x['room_name']) ?></td>
      <td><?= e($x['group_name']) ?></td>
    </tr>
  <?php endforeach ?>
  </tbody>
</table>
<?php require "../includes/footer.php"; ?>
