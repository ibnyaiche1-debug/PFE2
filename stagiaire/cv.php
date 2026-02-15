<?php
// Mon CV — page design pro + upload/remplacement/suppression (PDF uniquement)
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . "/../config/connection.php";

$db = $pdo ?? $conn ?? null;
if (!$db) die("Connexion DB introuvable.");

if (!isset($_SESSION["id_utilisateur"])) {
  header("Location: ../authentification/login.php");
  exit;
}

$idUtilisateur = (int)($_SESSION["id_utilisateur"] ?? 0);

// CSRF
if (empty($_SESSION["csrf_token"])) {
  $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION["csrf_token"];

$stmt = $db->prepare("SELECT id_stagiaire, cv_path FROM stagiaire WHERE id_utilisateur=? LIMIT 1");
$stmt->execute([$idUtilisateur]);
$stagiaire = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$stagiaire) die("Stagiaire introuvable.");

$idStagiaire = (int)$stagiaire["id_stagiaire"];
$currentCV = $stagiaire["cv_path"] ?? null;

$message = "";
$error = "";

// Helpers
function safe($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function humanSize($bytes) {
  $bytes = (int)$bytes;
  if ($bytes < 1024) return $bytes . " B";
  if ($bytes < 1024*1024) return round($bytes/1024, 1) . " KB";
  return round($bytes/(1024*1024), 2) . " MB";
}

// Upload / replace CV
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "upload") {
  if (!hash_equals($csrf, $_POST["csrf_token"] ?? "")) {
    $error = "Action refusée (CSRF). Rechargez la page et réessayez.";
  } elseif (!isset($_FILES["cv"])) {
    $error = "Veuillez sélectionner un fichier.";
  } else {
    $file = $_FILES["cv"];

    if (($file["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
      $error = "Erreur upload.";
    } else {
      // Validate PDF (extension + mime best-effort)
      $ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
      if ($ext !== "pdf") {
        $error = "Format PDF uniquement.";
      } elseif (($file["size"] ?? 0) > 2 * 1024 * 1024) {
        $error = "Le fichier ne doit pas dépasser 2MB.";
      } else {
        $newName = "cv_" . $idStagiaire . "_" . time() . "_" . bin2hex(random_bytes(4)) . ".pdf";
        $uploadDir = __DIR__ . "/../uploads/cv/";
        $uploadPath = $uploadDir . $newName;

        if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);

        if (move_uploaded_file($file["tmp_name"], $uploadPath)) {
          // Delete old CV if exists
          if (!empty($currentCV)) {
            $oldAbs = __DIR__ . "/../" . $currentCV;
            if (is_file($oldAbs)) @unlink($oldAbs);
          }

          $relativePath = "uploads/cv/" . $newName;

          $stmtUp = $db->prepare("UPDATE stagiaire SET cv_path=? WHERE id_stagiaire=?");
          $stmtUp->execute([$relativePath, $idStagiaire]);

          $currentCV = $relativePath;
          $message = "CV enregistré avec succès ✅";
        } else {
          $error = "Impossible de sauvegarder le fichier (permissions dossier uploads/cv).";
        }
      }
    }
  }
}

// Delete CV
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "delete") {
  if (!hash_equals($csrf, $_POST["csrf_token"] ?? "")) {
    $error = "Action refusée (CSRF). Rechargez la page et réessayez.";
  } else {
    if (!empty($currentCV)) {
      $abs = __DIR__ . "/../" . $currentCV;
      if (is_file($abs)) @unlink($abs);
    }

    $stmtDel = $db->prepare("UPDATE stagiaire SET cv_path=NULL WHERE id_stagiaire=?");
    $stmtDel->execute([$idStagiaire]);

    $currentCV = null;
    $message = "CV supprimé ✅";
  }
}

// Compute current CV metadata (if exists)
$cvExists = false;
$cvSize = null;
$cvBasename = null;
if (!empty($currentCV)) {
  $abs = __DIR__ . "/../" . $currentCV;
  if (is_file($abs)) {
    $cvExists = true;
    $cvSize = filesize($abs);
    $cvBasename = basename($abs);
  }
}
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Mon CV</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

  <style>
    :root{
      --bg1:#0b1220;
      --bg2:#070b13;
      --card: rgba(255,255,255,.06);
      --card2: rgba(255,255,255,.08);
      --stroke: rgba(255,255,255,.12);
      --text: rgba(255,255,255,.92);
      --muted: rgba(255,255,255,.65);
      --shadow: 0 18px 45px rgba(0,0,0,.35);
      --radius: 22px;
    }
    body{
      color: var(--text);
      background:
        radial-gradient(1000px 550px at 10% 0%, rgba(99,102,241,.25), transparent 65%),
        radial-gradient(900px 520px at 90% 10%, rgba(34,197,94,.18), transparent 60%),
        linear-gradient(180deg, var(--bg1), var(--bg2));
      min-height: 100vh;
    }
    .glass{
      background: var(--card);
      border: 1px solid var(--stroke);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      backdrop-filter: blur(10px);
    }
    .glass-soft{
      background: var(--card2);
      border: 1px solid var(--stroke);
      border-radius: var(--radius);
      backdrop-filter: blur(10px);
    }
    .muted{ color: var(--muted); }
    .brand-dot{
      width: 10px; height: 10px; border-radius: 50%;
      background: #22c55e;
      box-shadow: 0 0 0 4px rgba(34,197,94,.15);
      display:inline-block;
    }
    .btn-ghost{
      border: 1px solid rgba(255,255,255,.16);
      background: rgba(255,255,255,.07);
      color: rgba(255,255,255,.92);
    }
    .btn-ghost:hover{
      background: rgba(255,255,255,.10);
      color: rgba(255,255,255,.95);
    }
    .form-control, .form-select{
      background: rgba(255,255,255,.06) !important;
      border: 1px solid rgba(255,255,255,.14) !important;
      color: rgba(255, 255, 255, 0.92) !important;
      border-radius: 14px !important;
    }
    .form-control::file-selector-button{
      border-radius: 12px;
      border: 1px solid rgba(255,255,255,.18);
      background: rgba(255,255,255,.08);
      color: rgba(55, 49, 138, 0.9);
      margin-right: 12px;
    }
    .form-text{ color: rgba(255,255,255,.65) !important; }
    .divider{
      height:1px;
      background: rgba(255,255,255,.10);
      margin: 18px 0;
    }
    .kpi{
      display:flex; gap:12px; align-items:center;
      padding: 14px 16px;
      border-radius: 16px;
      border: 1px solid rgba(255,255,255,.12);
      background: rgba(255,255,255,.06);
    }
    .kpi i{ font-size: 1.25rem; opacity: .9; }
    .mono{ font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
  

    /* Responsive */

    @media (max-width: 992px){
      .container{ padding-left: 12px !important; padding-right: 12px !important; }
      .glass{ border-radius: 18px; }
      .kpi{ flex-direction: column; align-items: flex-start !important; }
    }
    @media (max-width: 576px){
      .btn{ width: 100%; }
      .d-flex.gap-2{ width: 100%; }
      .d-flex.gap-2 > *{ flex: 1 1 auto; }
    }

</style>
</head>

<body>
  <div class="container py-4 py-md-5" style="max-width: 980px;">
    <!-- Header -->
    <div class="glass p-3 p-md-4 mb-4">
      <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
        <div>
          <div class="d-flex align-items-center gap-2">
            <span class="brand-dot"></span>
            <span class="pill muted" style="border:1px solid rgba(255,255,255,.14); background:rgba(255,255,255,.06); padding:.25rem .6rem; border-radius:999px;">
              <i class="bi bi-person-badge"></i> Stagiaire
            </span>
          </div>
          <h1 class="h3 fw-bold mt-2 mb-1"><i class="bi bi-file-earmark-pdf"></i> Mon CV</h1>
          <div class="muted">Ajoute, remplace ou supprime ton CV. Format accepté : <strong>PDF</strong> (max <strong>2MB</strong>).</div>
        </div>

        <div class="d-flex gap-2">
          <a href="profile.php" class="btn btn-ghost">
            <i class="bi bi-arrow-left"></i> Profil
          </a>
          <a href="offres.php" class="btn btn-ghost">
            <i class="bi bi-briefcase"></i> Offres
          </a>
        </div>
      </div>
    </div>

    <?php if($message): ?>
      <div class="alert alert-success glass-soft border-0"><?= safe($message) ?></div>
    <?php endif; ?>
    <?php if($error): ?>
      <div class="alert alert-danger glass-soft border-0"><?= safe($error) ?></div>
    <?php endif; ?>

    <div class="row g-4">
      <!-- Left: Current CV -->
      <div class="col-lg-5">
        <div class="glass p-3 p-md-4 h-100">
          <h2 class="h5 fw-semibold mb-3"><i class="bi bi-shield-check"></i> CV actuel</h2>

          <?php if($cvExists): ?>
            <div class="kpi mb-3">
              <i class="bi bi-file-earmark-pdf"></i>
              <div class="flex-grow-1">
                <div class="fw-semibold"><?= safe($cvBasename) ?></div>
                <div class="muted small">Taille : <?= safe(humanSize($cvSize)) ?> · Stocké : <span class="mono"><?= safe($currentCV) ?></span></div>
              </div>
            </div>

            <div class="d-flex flex-wrap gap-2">
              <a class="btn btn-info" href="../<?= safe($currentCV) ?>" target="_blank">
                <i class="bi bi-eye"></i> Voir CV
              </a>

              <form method="POST" class="m-0" onsubmit="return confirm('Supprimer votre CV ?')">
                <input type="hidden" name="csrf_token" value="<?= safe($csrf) ?>">
                <input type="hidden" name="action" value="delete">
                <button type="submit" class="btn btn-danger">
                  <i class="bi bi-trash"></i> Supprimer
                </button>
              </form>
            </div>

            <div class="divider"></div>
            <div class="muted small">
              Astuce : vous pouvez le <strong>remplacer</strong> à droite en uploadant un nouveau PDF.
            </div>
          <?php else: ?>
            <div class="glass-soft p-3">
              <div class="d-flex gap-2 align-items-start">
                <i class="bi bi-info-circle fs-5"></i>
                <div>
                  <div class="fw-semibold">Aucun CV enregistré</div>
                  <div class="muted small">Upload ton CV pour pouvoir postuler plus rapidement.</div>
                </div>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Right: Upload / Replace -->
      <div class="col-lg-7">
        <div class="glass p-3 p-md-4">
          <h2 class="h5 fw-semibold mb-3"><i class="bi bi-upload"></i> <?= $cvExists ? "Remplacer le CV" : "Uploader un CV" ?></h2>

          <form method="POST" enctype="multipart/form-data" class="glass-soft p-3 p-md-4">
            <input type="hidden" name="csrf_token" value="<?= safe($csrf) ?>">
            <input type="hidden" name="action" value="upload">

            <label class="form-label mb-2">Sélectionner un fichier (PDF)</label>
            <input class="form-control" type="file" name="cv" accept=".pdf,application/pdf" required>

            <div class="form-text mt-2">
              • PDF uniquement · • Taille max 2MB · • Le CV précédent sera automatiquement supprimé.
            </div>

            <div class="d-flex flex-wrap gap-2 mt-4">
              <button class="btn btn-primary" type="submit">
                <i class="bi bi-check2-circle"></i> Enregistrer
              </button>
              <a class="btn btn-ghost" href="dashboard.php">
                <i class="bi bi-speedometer2"></i> Dashboard
              </a>
            </div>
          </form>

          <div class="divider"></div>

          <div class="row g-3">
            <div class="col-md-6">
              <div class="glass-soft p-3">
                <div class="fw-semibold mb-1"><i class="bi bi-lightning-charge"></i> Recommandation</div>
                <div class="muted small">Utilise un PDF clair (1–2 pages) avec tes coordonnées et ton parcours.</div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="glass-soft p-3">
                <div class="fw-semibold mb-4"><i class="bi bi-lock"></i> Sécurité</div>
                <div class="muted small">Le fichier est bien stocké et Sécurisé et protégé via </div>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>

    <div class="text-center muted small mt-4">
      © <?= date("Y") ?> · InternGo
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
