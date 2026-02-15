<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . "/../config/connection.php"; // adjust if needed

// Support both $pdo and $conn as PDO
$db = null;
if (isset($pdo) && $pdo instanceof PDO) $db = $pdo;
if (!$db && isset($conn) && $conn instanceof PDO) $db = $conn;
if (!$db) { die("Erreur: connexion DB introuvable (PDO)."); }

if (!isset($_SESSION["id_utilisateur"])) {
  header("Location: ../authentification/login.php");
  exit;
}

$idUtilisateur = (int)$_SESSION["id_utilisateur"];

// CSRF
if (empty($_SESSION["csrf_token"])) {
  $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION["csrf_token"];

// Load user + stagiaire (for header display)
$stmtUser = $db->prepare("
  SELECT 
    u.id_utilisateur, u.nom, u.email, u.role,
    s.id_stagiaire, s.cv_path
  FROM utilisateur u
  LEFT JOIN stagiaire s ON s.id_utilisateur = u.id_utilisateur
  WHERE u.id_utilisateur = ?
  LIMIT 1
");
$stmtUser->execute([$idUtilisateur]);
$user = $stmtUser->fetch(PDO::FETCH_ASSOC);

if (!$user || empty($user["id_stagiaire"])) {
  header("Location: ../authentification/logout.php");
  exit;
}

$idStagiaire = (int)$user["id_stagiaire"];
$initial = strtoupper(substr($user["nom"] ?? "S", 0, 1));

function safe($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function badgeClass($s){
  $s = strtolower((string)$s);
  if (str_contains($s, "accept") || str_contains($s, "valid")) return "bg-success";
  if (str_contains($s, "refus") || str_contains($s, "rejet")) return "bg-danger";
  if (str_contains($s, "attente") || str_contains($s, "pending")) return "bg-warning text-dark";
  return "bg-secondary";
}

$success = "";
$error = "";

// Delete candidature + delete its CV file if stored in candidature.cv_path
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "delete") {
  if (!hash_equals($csrf, $_POST["csrf_token"] ?? "")) {
    $error = "Action refusée (CSRF). Rechargez la page.";
  } else {
    $idCand = (int)($_POST["id_candidature"] ?? 0);

    // fetch cv_path for ownership
    $stmtGet = $db->prepare("SELECT cv_path FROM candidature WHERE id_candidature=? AND id_stagiaire=? LIMIT 1");
    $stmtGet->execute([$idCand, $idStagiaire]);
    $row = $stmtGet->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
      $error = "Suppression impossible.";
    } else {
      // delete file (best-effort)
      if (!empty($row["cv_path"])) {
        $abs = __DIR__ . "/../" . $row["cv_path"];
        if (is_file($abs)) @unlink($abs);
      }

      $stmtDel = $db->prepare("DELETE FROM candidature WHERE id_candidature=? AND id_stagiaire=? LIMIT 1");
      $stmtDel->execute([$idCand, $idStagiaire]);
      $success = "Candidature supprimée ✅";
    }
  }
}

// Load candidatures list
$stmt = $db->prepare("
  SELECT
    c.id_candidature, c.date_candidature, c.statut_candidature, c.cv_path,
    o.titre, o.ville, o.domaine,
    e.nom_entreprise
  FROM candidature c
  JOIN offre_stage o ON o.id_offre = c.id_offre
  LEFT JOIN entreprise e ON e.id_entreprise = o.id_entreprise
  WHERE c.id_stagiaire = ?
  ORDER BY COALESCE(c.date_candidature,'1970-01-01') DESC, c.id_candidature DESC
");
$stmt->execute([$idStagiaire]);
$candidatures = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Mes candidatures</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

  <style>
    :root{
      --bg0:#0b1220;
      --bg1:#0a1020;
      --card: rgba(255,255,255,.07);
      --stroke: rgba(255,255,255,.14);
      --text: rgba(255,255,255,.92);
      --muted: rgba(255,255,255,.68);
      --shadow: 0 26px 70px rgba(0,0,0,.32);
      --brand:#3b82f6;
      --brand3:#06b6d4;
    }
    body{
      min-height:100vh;
      color: var(--text);
      background:
        radial-gradient(1000px 520px at 10% 0%, rgba(59,130,246,.28), transparent 65%),
        radial-gradient(900px 520px at 90% 10%, rgba(6,182,212,.18), transparent 60%),
        linear-gradient(180deg, var(--bg0), var(--bg1));
    }
    .wrap{ max-width: 1160px; margin: 0 auto; padding: 18px 14px 36px; }
    .panel{
      background: var(--card);
      border: 1px solid var(--stroke);
      border-radius: 26px;
      box-shadow: var(--shadow);
      backdrop-filter: blur(10px);
    }
    .head{ padding: 22px; }
    .head-row{ display:flex; align-items:center; justify-content:space-between; gap:18px; flex-wrap:wrap; }
    .identity{ display:flex; gap:14px; align-items:center; min-width: 260px; }
    .avatar{
      width: 52px; height: 52px; border-radius: 18px;
      display:grid; place-items:center;
      font-weight: 800; font-size: 18px;
      background: linear-gradient(135deg, rgba(59,130,246,.9), rgba(6,182,212,.85));
      border: 1px solid rgba(255,255,255,.22);
      box-shadow: 0 16px 40px rgba(0,0,0,.25);
    }
    .title{
      margin:0;
      font-weight: 850;
      letter-spacing: .2px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .subtitle{
      margin: 2px 0 0;
      color: var(--muted);
      font-size: .95rem;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .badge-pill{
      display:inline-flex; align-items:center; gap:8px;
      padding: 6px 12px;
      border-radius: 999px;
      border: 1px solid rgba(255,255,255,.18);
      background: rgba(255,255,255,.07);
      color: var(--text);
      font-weight: 550;
      font-size: .82rem;
      margin-top: 8px;
    }
    .actions{ display:flex; gap:10px; align-items:center; flex-wrap:wrap; justify-content:flex-end; }
    .btn-ghost{
      display:inline-flex; align-items:center; gap:10px;
      padding: 10px 14px;
      border-radius: 999px;
      border: 1px solid rgba(255,255,255,.14);
      background: rgba(255,255,255,.06);
      color: rgba(255,255,255,.92);
      text-decoration:none;
      transition: .15s ease;
      white-space: nowrap;
    }
    .btn-ghost:hover{ transform: translateY(-1px); background: rgba(255,255,255,.10); color: rgba(255,255,255,.98); }
    .btn-dangerish{ border-color: rgba(239,68,68,.32); background: rgba(239,68,68,.10); }
    .tabs{
      position: relative;
      display:flex; gap:10px; flex-wrap:wrap;
      padding: 12px 18px 16px;
      border-top: 1px solid rgba(255,255,255,.12);
    }
    .tab{
      text-decoration:none;
      padding: 10px 12px;
      border-radius: 14px;
      background: rgba(0,0,0,.12);
      color: var(--muted);
      font-weight: 400;
      font-size: .92rem;
      display:inline-flex; align-items:center; gap:9px;
      transition: all .12s ease;
    }
    .tab:hover{ background: rgba(255,255,255,.07); }
    .tab.active{
      background: rgba(59,130,246,.18);
      border-color: rgba(59,130,246,.35);
      color: rgba(255,255,255,.95);
    }

    .cardx{
      background: rgba(255,255,255,.06);
      border: 1px solid rgba(255,255,255,.12);
      border-radius: 22px;
      box-shadow: 0 22px 55px rgba(0,0,0,.22);
      backdrop-filter: blur(10px);
      padding: 18px;
    }

    /* Responsive table: scroll on small + stack on very small if data-label exists */
    .table-wrap{ overflow-x:auto; }
    table.table{ margin:0; }
    @media (max-width: 768px){
      .head-row{ flex-direction: column; align-items: flex-start; }
      .actions{ width:100%; justify-content:flex-start; }
      .actions a{ flex: 1 1 calc(50% - 10px); justify-content:center; }
      .tabs{ grid-template-columns: repeat(2, minmax(0,1fr)); }
      .title{ font-size: 34px; }
    }
    @media (max-width: 420px){
      .actions a{ flex: 1 1 100%; }
      .tabs{ grid-template-columns: 1fr; }
    }
    /* optional stacked table view */
    @media (max-width: 520px){
      table.table.resp-stack thead{ display:none; }
      table.table.resp-stack tbody, table.table.resp-stack tr, table.table.resp-stack td{
        display:block; width:100%;
      }
      table.table.resp-stack tr{
        border: 1px solid rgba(255,255,255,.12);
        border-radius: 16px;
        margin-bottom: 12px;
        overflow:hidden;
      }
      table.table.resp-stack td{
        padding-left: 48%;
        position:relative;
        border: none;
        border-bottom: 1px solid rgba(255,255,255,.10);
        white-space: normal;
      }
      table.table.resp-stack td:last-child{ border-bottom: none; }
      table.table.resp-stack td::before{
        content: attr(data-label);
        position:absolute;
        left: 12px;
        top: 10px;
        width: 42%;
        color: rgba(255,255,255,.70);
        font-weight: 700;
      }
    }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="panel">
      <div class="head">
        <div class="head-row">
          <div class="identity">
            <div class="avatar"><?= safe($initial) ?></div>
            <div>
              <h1 class="title">Candidatures</h1>
              <div class="subtitle"><?= safe($user["nom"] ?? "Stagiaire") ?> — <?= safe($user["email"] ?? "") ?></div>
              <div class="badge-pill"><i class="bi bi-send-check"></i> Suivez vos candidatures</div>
            </div>
          </div>

          <div class="actions">
            <a class="btn-ghost" href="../stagiaire/cv.php"><i class="bi bi-paperclip"></i> Mon CV</a>
            <a class="btn-ghost" href="../stagiaire/offres.php"><i class="bi bi-briefcase"></i> Offres</a>
            <a class="btn-ghost btn-dangerish" href="../authentification/logout.php"><i class="bi bi-box-arrow-right"></i> Déconnexion</a>
          </div>
        </div>
      </div>

      <div class="tabs">
        <a class="tab" href="../stagiaire/dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <a class="tab" href="../stagiaire/profile.php"><i class="bi bi-person-badge"></i> Profil</a>
        <a class="tab active" href="../stagiaire/candidatures.php"><i class="bi bi-file-earmark-text"></i> Candidatures</a>
      </div>
    </div>

    <div class="mt-3 cardx">
      <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2 mb-3">
        <div>
          <div class="h4 m-0 fw-bold"><i class="bi bi-file-earmark-text"></i> Mes candidatures</div>
          <div class="text-white-50"><?= count($candidatures) ?> candidature(s)</div>
        </div>
        <a class="btn btn-outline-light" href="../stagiaire/offres.php"><i class="bi bi-arrow-left"></i> Retour aux offres</a>
      </div>

      <?php if ($success): ?>
        <div class="alert alert-success"><?= safe($success) ?></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="alert alert-danger"><?= safe($error) ?></div>
      <?php endif; ?>

      <?php if (empty($candidatures)): ?>
        <div class="alert alert-warning m-0">Vous n'avez encore postulé à aucune offre.</div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table table-dark table-striped align-middle resp-stack">
            <thead>
              <tr>
                <th>Offre</th>
                <th>Entreprise</th>
                <th>Ville</th>
                <th>Date</th>
                <th>Statut</th>
                <th>CV</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($candidatures as $c): ?>
                <tr>
                  <td data-label="Offre" class="fw-semibold"><?= safe($c["titre"] ?? "—") ?></td>
                  <td data-label="Entreprise"><?= safe($c["nom_entreprise"] ?? "—") ?></td>
                  <td data-label="Ville"><?= safe($c["ville"] ?? "—") ?></td>
                  <td data-label="Date"><?= safe($c["date_candidature"] ?? "—") ?></td>
                  <td data-label="Statut">
                    <span class="badge <?= badgeClass($c["statut_candidature"] ?? "") ?>"><?= safe($c["statut_candidature"] ?? "—") ?></span>
                  </td>
                  <td data-label="CV">
                    <?php if (!empty($c["cv_path"])): ?>
                      <a class="btn btn-sm btn-outline-info" href="../<?= safe($c["cv_path"]) ?>" target="_blank">
                        <i class="bi bi-file-earmark-pdf"></i> Voir
                      </a>
                    <?php else: ?>
                      <span class="text-white-50">—</span>
                    <?php endif; ?>
                  </td>
                  <td data-label="Action">
                    <form method="POST" class="m-0" onsubmit="return confirm('Supprimer cette candidature ?')">
                      <input type="hidden" name="csrf_token" value="<?= safe($csrf) ?>">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id_candidature" value="<?= (int)$c["id_candidature"] ?>">
                      <button type="submit" class="btn btn-sm btn-danger">
                        <i class="bi bi-trash"></i> Supprimer
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <div class="text-center text-white-50 small mt-4">
      © <?= date("Y") ?> InternGo
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
