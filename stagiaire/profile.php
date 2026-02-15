<?php
// stagiaire/profil.php  ✅ Fixed (no raw PHP printed), variables defined

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// DB connection must define $conn (PDO) OR $pdo (PDO)
require_once __DIR__ . "/../config/connection.php"; // <-- change if needed

// Support both variable names
$db = null;
if (isset($pdo) && $pdo instanceof PDO) $db = $pdo;
if (!$db && isset($conn) && $conn instanceof PDO) $db = $conn;
if (!$db) { die("Erreur: connexion DB introuvable (PDO)."); }

if (!isset($_SESSION["id_utilisateur"])) {
  header("Location: ../authentification/login.php");
  exit;
}

$idUtilisateur = (int)$_SESSION["id_utilisateur"];

// Load user + stagiaire
$stmtUser = $db->prepare("
  SELECT 
    u.id_utilisateur, u.nom, u.email, u.role, u.date_inscription,
    s.id_stagiaire, s.niveau_etude, s.filiere, s.ville AS ville_stagiaire, s.cv_path
  FROM utilisateur u
  LEFT JOIN stagiaire s ON s.id_utilisateur = u.id_utilisateur
  WHERE u.id_utilisateur = ?
  LIMIT 1
");
$stmtUser->execute([$idUtilisateur]);
$user = $stmtUser->fetch(PDO::FETCH_ASSOC);

if (!$user) {
  header("Location: ../authentification/logout.php");
  exit;
}

$idStagiaire = isset($user["id_stagiaire"]) ? (int)$user["id_stagiaire"] : 0;

function safe($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$success = null;
$error = null;

// Handle update
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $niveau = trim($_POST["niveau_etude"] ?? "");
  $filiere = trim($_POST["filiere"] ?? "");
  $ville = trim($_POST["ville_stagiaire"] ?? "");

  try {
    if ($idStagiaire <= 0) {
      // Create stagiaire row if missing
      $ins = $db->prepare("INSERT INTO stagiaire (id_utilisateur, niveau_etude, filiere, ville, cv) VALUES (?, ?, ?, ?, '')");
      $ins->execute([$idUtilisateur, $niveau, $filiere, $ville]);
    } else {
      $upd = $db->prepare("UPDATE stagiaire SET niveau_etude=?, filiere=?, ville=? WHERE id_stagiaire=?");
      $upd->execute([$niveau, $filiere, $ville, $idStagiaire]);
    }

    // Reload user
    $stmtUser->execute([$idUtilisateur]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);
    $idStagiaire = isset($user["id_stagiaire"]) ? (int)$user["id_stagiaire"] : 0;

    $success = "Profil stagiaire mis à jour ✅";
  } catch (Exception $ex) {
    $error = "Erreur lors de la mise à jour.";
  }
}

$active = basename($_SERVER["PHP_SELF"]);
$initial = strtoupper(substr($user["nom"] ?? "S", 0, 1));
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Mon profil — Stagiaire</title>

  <!-- Bootstrap + Icons -->
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
      --shadow: 0 26px 70px rgba(220, 19, 19, 0.32);
      --brand:#3b82f6;          /* professional blue */
      --brand2:#22c55e;         /* subtle success */
      --brand3:#06b6d4;         /* calm teal */
      --warn:#f59e0b;
      --danger:#fb7185;
    }

    body{
      min-height:100vh;
      color: var(--text);
      background:
        radial-gradient(900px 600px at 8% 0%, rgba(59,130,246,.18), transparent 58%),
        radial-gradient(900px 600px at 92% 10%, rgba(6,182,212,.12), transparent 60%),
        radial-gradient(900px 700px at 50% 110%, rgba(34,197,94,.10), transparent 62%),
        linear-gradient(180deg, var(--bg0), var(--bg1));
      overflow-x:hidden;
    }

    /* Floating blobs */
    .blob{
      position: fixed;
      width: 380px; height: 380px;
      filter: blur(46px);
      opacity: .28;
      border-radius: 999px;
      z-index:-1;
      animation: floaty 22s ease-in-out infinite;
    }
    .blob.b1{ left:-180px; top:-120px; background: rgba(96,165,250,.55); }
    .blob.b2{ right:-220px; top:40px; background: rgba(167,139,250,.55); animation-delay: -5s; }
    .blob.b3{ left: 30%; bottom:-260px; background: rgba(34,197,94,.45); animation-delay: -9s; }
    @keyframes floaty{
      0%,100%{ transform: translate3d(0,0,0) scale(1); }
      50%{ transform: translate3d(0,24px,0) scale(1.06); }
    }

    .wrap{
      max-width: 1180px;
      margin: 0 auto;
      padding: 22px 16px 48px;
    }

    /* Top creative header */
    .top{
      position: relative;
      background: rgba(255,255,255,.06);
      border: 1px solid rgba(255,255,255,.14);
      border-radius: 24px;
      box-shadow: var(--shadow);
      backdrop-filter: blur(105px);
      overflow: hidden;
    }
    .top::before{
      content:"";
      position:absolute; inset:0;
      background:
        radial-gradient(600px 240px at 10% 0%, rgba(59,130,246,.22), transparent 60%),
        radial-gradient(600px 240px at 90% 0%, rgba(6,182,212,.16), transparent 60%);
      opacity:.9;
      pointer-events:none;
    }
    .top-inner{
      position: relative;
      padding: 16px 18px 14px;
    }
    .row1{
      display:flex; align-items:center; justify-content:space-between; gap:14px;
    }

    .identity{ display:flex; align-items:center; gap:14px; min-width: 0; }
    .avatar{
      width: 52px; height: 52px; border-radius: 18px;
      display:grid; place-items:center;
      font-weight: 800; font-size: 18px;
      background: linear-gradient(135deg, rgba(59,130,246,.9), rgba(6,182,212,.85));
      border: 1px solid rgba(255,255,255,.22);
      box-shadow: 0 16px 40px rgba(0,0,0,.25);
    }
    .who{ min-width:0; }
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
    .badge-pill i{ color: rgba(59,130,246,.95); }

    .actions{ display:flex; gap:10px; flex-wrap:wrap; justify-content:flex-end; }
    .btn-ghost{
      border-radius: 999px;
      border: 1px solid rgba(255,255,255,.18);
      background: rgba(255,255,255,.06);
      color: var(--text);
      font-weight: 400;
      padding: 10px 14px;
      display:inline-flex; align-items:center; gap:8px;
      transition: transform .12s ease, background .12s ease, border-color .12s ease;
      text-decoration:none;
    }
    .btn-ghost:hover{
      transform: translateY(-1px);
      background: rgba(255,255,255,.10);
      border-color: rgba(255,255,255,.26);
      color: var(--text);
    }
    .btn-dangerish{
      background: rgba(251,113,133,.10);
      border-color: rgba(251,113,133,.22);
    }
    .btn-dangerish:hover{
      background: rgba(251,113,133,.14);
      border-color: rgba(251,113,133,.30);
    }

    /* Tabs */
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
      border: 1px solid transparent;
      background: rgba(0,0,0,.12);
      color: var(--muted);
      font-weight: 400;
      font-size: .92rem;
      display:inline-flex; align-items:center; gap:9px;
      transition: all .12s ease;
    }
    .tab:hover{
      background: rgba(255,255,255,.08);
      border-color: rgba(255,255,255,.14);
      color: var(--text);
    }
    .tab.active{
      background: rgba(255,255,255,.10);
      border-color: rgba(255,255,255,.18);
      color: var(--text);
      box-shadow: 0 14px 30px rgba(0,0,0,.14);
    }

    /* Content cards */
    .cardx{
      background: rgba(255,255,255,.06);
      border: 1px solid rgba(255,255,255,.14);
      border-radius: 24px;
      box-shadow: var(--shadow);
      backdrop-filter: blur(14px);
      overflow: hidden;
    }
    .card-head{
      padding: 18px 18px 12px;
      display:flex; align-items:flex-start; justify-content:space-between; gap:12px;
      border-bottom: 1px solid rgba(255,255,255,.12);
      background:
        radial-gradient(700px 160px at 10% 0%, rgba(59,130,246,.14), transparent 0%),
        radial-gradient(700px 160px at 10% 0%, rgba(59,130,246,.14), transparent 0%);
    }
    .h-title{
      margin:0;
      font-weight: 950;
      letter-spacing:.2px;
      display:flex; align-items:center; gap:10px;
    }
    .h-sub{
      margin: 6px 0 0;
      color: var(--muted);
      font-size: .95rem;
    }
    .card-body{ padding: 18px; }

    /* Form styling */
    .form-label{ color: var(--text); font-weight: 900; }
    .form-control{
      border-radius: 18px;
      padding: 12px 14px;
      background: rgba(0,0,0,.22);
      border: 1px solid rgba(255,255,255,.14);
      color: var(--text);
    }
    .form-control::placeholder{ color: rgba(255,255,255,.45); }
    .form-control:focus{
      background: rgba(0,0,0,.22);
      border-color: rgba(34,197,94,.45);
      box-shadow: 0 0 0 .2rem rgba(34,197,94,.12);
      color: var(--text);
    }
    .btn-primaryx{
      border-radius: 999px;
      font-weight: 1000;
      padding: 11px 16px;
      border: 1px solid rgba(255,255,255,.18);
      background: linear-gradient(135deg, rgba(59,130,246,.95), rgba(6,182,212,.75));
      color: #06121f;
      box-shadow: 0 16px 40px rgba(59,130,246,.18);
    }
    .btn-primaryx:hover{ filter: brightness(.98); transform: translateY(-1px); }
    .btn-softx{
      border-radius: 999px;
      font-weight: 1000;
      padding: 11px 16px;
      border: 1px solid rgba(255,255,255,.18);
      background: rgba(255,255,255,.08);
      color: var(--text);
    }
    .btn-softx:hover{ background: rgba(255,255,255,.12); transform: translateY(-1px); }

    /* Profile complétéion (creative) */
    .meter{
      width: 88px; height: 88px;
      border-radius: 999px;
      display:grid; place-items:center;
      background:
        conic-gradient(from 210deg, rgba(59,130,246,.95) var(--p), rgba(255,255,255,.10) 0);
      border: 1px solid rgba(255,255,255,.18);
      box-shadow: 0 18px 45px rgba(0,0,0,.22);
      flex: 0 0 auto;
    }
    .meter .inner{
      width: 74px; height: 74px;
      border-radius: 999px;
      background: rgba(0,0,0,.35);
      border: 1px solid rgba(255,255,255,.12);
      display:flex; flex-direction:column; align-items:center; justify-content:center;
      gap: 2px;
    }
    .meter .pct{ font-weight: 1000; font-size: 1.05rem; line-height:1; }
    .meter .lbl{ color: var(--muted); font-size: .75rem; font-weight: 850; }

    .info-tile{
      border-radius: 20px;
      border: 1px solid rgba(255,255,255,.12);
      background: rgba(0,0,0,.18);
      padding: 14px;
      display:flex; gap: 12px; align-items:flex-start;
    }
    .info-tile i{ font-size: 1.15rem; color: rgba(255,255,255,.82); margin-top: 2px; }
    .k{ color: var(--muted); font-size: .82rem; }
    .v{ font-weight: 1000; color: var(--text); word-break: break-word; }

    .tip{
      border-radius: 20px;
      padding: 14px;
      border: 1px dashed rgba(255,255,255,.24);
      background: rgba(96,165,250,.10);
      color: rgba(255,255,255,.86);
      font-weight: 350;
    }
    .footer{
      text-align:center;
      color: rgba(255,255,255,.55);
      font-size: .86rem;
      margin-top: 14px;
    }

    @media (max-width: 768px){
      .actions{ justify-content:flex-start; }
      .meter{
      width: 88px; height: 88px;
      border-radius: 999px;
      display:grid; place-items:center;
      background:
        conic-gradient(from 210deg, rgba(59,130,246,.95) var(--p), rgba(255,255,255,.10) 0);
      border: 1px solid rgba(255,255,255,.18);
      box-shadow: 0 18px 45px rgba(0,0,0,.22);
      flex: 0 0 auto;
    }
      .meter .inner{ width: 66px; height: 66px; }
    }
  
    /* RESP_FIX_V2 */
    @media (max-width: 992px){
      .row1{ flex-wrap: wrap; }
      .actions{ flex-wrap: wrap; }
    }
    @media (max-width: 768px){
      .row1{ flex-direction: column; align-items: flex-start; }
      .actions{ width: 100%; justify-content: flex-start; }
      .actions a, .actions button{
        flex: 1 1 calc(50% - 10px);
        justify-content: center;
        text-align: center;
      }
      .tabs{ grid-template-columns: repeat(2, minmax(0,1fr)); }
      .tab{ justify-content: center; }
    }
    @media (max-width: 420px){
      .actions a, .actions button{ flex: 1 1 100%; }
      .tabs{ grid-template-columns: 1fr; }
    }

</style>
</head>

<body>
  <div class="blob b1"></div>
  <div class="blob b2"></div>
  <div class="blob b3"></div>

  <div class="wrap">

    <!-- Creative Top -->
    <div class="top">
      <div class="top-inner">
        <div class="row1">
          <div class="identity">
            <div class="avatar"><?= safe($initial) ?></div>
            <div class="who">
              <h1 class="title">Profile</h1>
              <div class="subtitle"><?= safe($user["nom"] ?? "Stagiaire") ?> — <?= safe($user["email"] ?? "") ?></div>
              <div class="badge-pill"><i class="bi bi-briefcase-fill"></i> Profil stagiaire • Espace carrière</div>
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
        <a class="tab active" href="../stagiaire/profile.php"><i class="bi bi-person-badge"></i> Profil</a>
        <a class="tab" href="../stagiaire/candidatures.php"><i class="bi bi-send-check"></i> Candidatures</a>
      </div>
    </div>

    <?php
      // Simple profile complétéion (creative UI only)
      $filled = 0; $total = 3;
      if (!empty($user["niveau_etude"])) $filled++;
      if (!empty($user["filiere"])) $filled++;
      if (!empty($user["ville_stagiaire"])) $filled++;
      $pct = (int) round(($total ? ($filled/$total) : 0) * 100);
      $pdeg = max(0, min(100, $pct)) . "%";
    ?>

    <div class="row g-3 mt-2">
      <!-- Left: Form -->
      <div class="col-lg-7 mt-5">
        <div class="cardx">
          <div class="card-head">
            <div>
              <h2 class="h-title"><i class="bi bi-sliders2-vertical"></i> Mon profil stagiaire</h2>
              <div class="h-sub">Renseigne tes infos pour rendre ton dossier plus attractif.</div>
            </div>

            <div class="meter" style="--p: <?= safe($pdeg) ?>;">
              <div class="inner">
                <div class="pct"><?= (int)$pct ?>%</div>
                <div class="lbl">complété</div>
              </div>
            </div>
          </div>

          <div class="card-body">
            <?php if ($success): ?>
              <div class="alert alert-success d-flex align-items-center gap-2" style="border-radius:18px; background: rgba(34,197,94,.14); border-color: rgba(34,197,94,.22); color: rgba(255,255,255,.9);">
                <i class="bi bi-check-circle-fill"></i> <div><?= safe($success) ?></div>
              </div>
            <?php endif; ?>
            <?php if ($error): ?>
              <div class="alert alert-danger d-flex align-items-center gap-2" style="border-radius:18px; background: rgba(251,113,133,.14); border-color: rgba(251,113,133,.22); color: rgba(255,255,255,.9);">
                <i class="bi bi-exclamation-triangle-fill"></i> <div><?= safe($error) ?></div>
              </div>
            <?php endif; ?>

            <form method="post" class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Niveau d’étude</label>
                <input class="form-control" name="niveau_etude" value="<?= safe($user["niveau_etude"] ?? "") ?>" placeholder="Ex: Bac+3, Master..." />
              </div>
              <div class="col-md-6">
                <label class="form-label">Filière</label>
                <input class="form-control" name="filiere" value="<?= safe($user["filiere"] ?? "") ?>" placeholder="Ex: Développement, Réseaux..." />
              </div>
              <div class="col-12">
                <label class="form-label">Ville</label>
                <input class="form-control" name="ville_stagiaire" value="<?= safe($user["ville_stagiaire"] ?? "") ?>" placeholder="Ex: Casablanca..." />
              </div>

              <div class="col-12 d-flex flex-wrap gap-2 mt-5">
                <button class="btn btn-primaryx" type="submit">
                  <i class="bi bi-save2 me-1"></i> Enregistrer
                </button>
                <a class="btn btn-softx" href="../stagiaire/cv.php">
                  <i class="bi bi-paperclip me-1"></i> Ajouter / Mettre à jour mon CV
                </a>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- Right: Account -->
      <div class="col-lg-5">
        <div class="cardx">
          <div class="card-head">
            <div>
              <h2 class="h-title"><i class="bi bi-shield-lock"></i> Identité & compte</h2>
              <div class="h-sub">Lecture seule — infos du compte utilisateur.</div>
            </div>
          </div>

          <div class="card-body">
            <div class="d-grid gap-2">
              <div class="info-tile">
                <i class="bi bi-person"></i>
                <div>
                  <div class="k">Nom</div>
                  <div class="v"><?= safe($user["nom"] ?? "—") ?></div>
                </div>
              </div>
              <div class="info-tile">
                <i class="bi bi-envelope"></i>
                <div>
                  <div class="k">Email</div>
                  <div class="v"><?= safe($user["email"] ?? "—") ?></div>
                </div>
              </div>
              <div class="info-tile">
                <i class="bi bi-badge-ad"></i>
                <div>
                  <div class="k">Rôle</div>
                  <div class="v"><?= safe($user["role"] ?? "stagiaire") ?></div>
                </div>
              </div>
            </div>

            <div class="tip mt-5">
              <i class="bi bi-lightbulb-fill me-1"></i>
              Conseil: un CV + profil complété augmente les chances d’être sélectionné.
              <div class="mt-2">
                <a class="btn-ghost" href="../stagiaire/candidatures.php"><i class="bi bi-send-check"></i> Voir mes candidatures</a>
              </div>
            </div>
          </div>
        </div>

        <div class="footer">© <?= date("Y"); ?> InternGo</div>
      </div>
    </div>

  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
