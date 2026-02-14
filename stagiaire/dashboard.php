<?php
// stagiaire/dashboard.php  ✅ Professional UI version (logic preserved)

// ✅ Start session safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ✅ Require your DB connection (must define $conn as PDO)
require_once __DIR__ . "/../config/connection.php"; // <-- change if your file is elsewhere

// ✅ Auth guard
if (!isset($_SESSION["id_utilisateur"])) {
    header("Location: ../authentification/login.php");
    exit;
}

$idUtilisateur = (int) $_SESSION["id_utilisateur"];

// ✅ Load user + stagiaire info
$stmt = $pdo->prepare("
    SELECT 
        u.id_utilisateur, u.nom, u.email, u.role, u.date_inscription,
        s.id_stagiaire, s.niveau_etude, s.filiere, s.ville AS ville_stagiaire, s.cv
    FROM utilisateur u
    LEFT JOIN stagiaire s ON s.id_utilisateur = u.id_utilisateur
    WHERE u.id_utilisateur = ?
    LIMIT 1
");
$stmt->execute([$idUtilisateur]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    // If user row is missing, force logout
    header("Location: ../authentification/logout.php");
    exit;
}

$idStagiaire = isset($user["id_stagiaire"]) ? (int)$user["id_stagiaire"] : 0;

// ✅ Stats: candidatures
$stats = [
    "total" => 0,
    "en_attente" => 0,
    "acceptee" => 0,
    "refusee" => 0
];

if ($idStagiaire > 0) {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) AS total,
            SUM(statut_candidature = 'en_attente') AS en_attente,
            SUM(statut_candidature = 'acceptée') AS acceptee,
            SUM(statut_candidature = 'refusée') AS refusee
        FROM candidature
        WHERE id_stagiaire = ?
    ");
    $stmt->execute([$idStagiaire]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $stats["total"]      = (int)($row["total"] ?? 0);
        $stats["en_attente"] = (int)($row["en_attente"] ?? 0);
        $stats["acceptee"]   = (int)($row["acceptee"] ?? 0);
        $stats["refusee"]    = (int)($row["refusee"] ?? 0);
    }
}

// ✅ Recent candidatures (with offer + entreprise)
$recentCandidatures = [];
if ($idStagiaire > 0) {
    $stmt = $pdo->prepare("
        SELECT 
            c.id_candidature, c.date_candidature, c.statut_candidature,
            o.id_offre, o.titre, o.ville AS ville_offre, o.duree, o.domaine,
            e.nom_entreprise
        FROM candidature c
        LEFT JOIN offre_stage o ON o.id_offre = c.id_offre
        LEFT JOIN entreprise e ON e.id_entreprise = o.id_entreprise
        WHERE c.id_stagiaire = ?
        ORDER BY c.id_candidature DESC
        LIMIT 6
    ");
    $stmt->execute([$idStagiaire]);
    $recentCandidatures = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ✅ Latest offers (for quick browse)
$latestOffers = [];
$stmt = $pdo->prepare("
    SELECT 
        o.id_offre, o.titre, o.ville, o.duree, o.domaine, o.date_publication, o.statut,
        e.nom_entreprise
    FROM offre_stage o
    LEFT JOIN entreprise e ON e.id_entreprise = o.id_entreprise
    ORDER BY COALESCE(o.date_publication, '1970-01-01') DESC, o.id_offre DESC
    LIMIT 6
");
$stmt->execute();
$latestOffers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ CV link (adjust folder to your uploads path if needed)
$cvRaw = trim((string)($user["cv"] ?? ""));
$cvUrl = null;
if ($cvRaw !== "") {
    if (strpos($cvRaw, "/") !== false || strpos($cvRaw, "\\") !== false) {
        $cvUrl = $cvRaw;
    } else {
        $cvUrl = "../uploads/cv/" . basename($cvRaw);
    }
}

function badgeClassForStatus($status) {
    $status = (string)$status;
    if ($status === "acceptée") return "bg-success";
    if ($status === "refusée") return "bg-danger";
    if ($status === "en_attente") return "bg-warning text-dark";
    return "bg-secondary";
}

function safe($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$initial = strtoupper(substr($user["nom"] ?? "S", 0, 1));
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Dashboard • Stagiaire</title>

  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

  <style>
    :root{
      --bg: #0b1020;
      --panel: rgba(255,255,255,.06);
      --stroke: rgba(255,255,255,.10);
      --text: rgba(255,255,255,.85);
      --muted: rgba(255,255,255,.60);
      --surface: #0f172a;
      --card: #ffffff;
      --card2: #f8fafc;
    }
    body{ background: #f3f5fb; }
    .app{
      min-height: 100vh;
      display:flex;
    }
    /* Sidebar */
    .sidebar{
      width: 300px;
      background: radial-gradient(1200px 600px at -20% -20%, rgba(99,102,241,.35), transparent 55%),
                  radial-gradient(900px 500px at 120% 20%, rgba(34,197,94,.18), transparent 45%),
                  var(--surface);
      color: var(--text);
      padding: 18px;
      position: sticky;
      top: 0;
      height: 100vh;
      border-right: 1px solid rgba(255,255,255,.06);
    }
    .brand{
      font-weight: 800;
      letter-spacing: .3px;
      color: #fff;
      line-height: 1.1;
    }
    .brand small{
      display:block;
      font-weight: 600;
      letter-spacing: .2px;
      color: var(--muted);
      margin-top: 4px;
    }
    .profile-chip{
      display:flex;
      gap: 12px;
      align-items:center;
      padding: 14px;
      border: 1px solid rgba(255,255,255,.08);
      background: rgba(255,255,255,.04);
      border-radius: 18px;
      backdrop-filter: blur(10px);
    }
    .avatar{
      width: 64px; 
      height: 64px;
      border-radius: 20px;
      display:grid; 
      place-items:center;
      background: linear-gradient(135deg, #6366f1, #4f46e5);
      border: 2px solid rgba(255,255,255,.25);
      color:#fff;
      font-weight: 900;
      font-size: 1.4rem;
      flex-shrink: 0;
    }
    .chip-name{ color:#fff; font-weight: 700; }
    .chip-sub{ color: var(--muted); font-size: .85rem; }

    .nav-title{
      margin: 18px 6px 10px;
      font-size: .78rem;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: .12em;
    }
    .side-nav a{
      display:flex;
      align-items:center;
      gap: 10px;
      padding: 10px 12px;
      border-radius: 14px;
      color: var(--text);
      text-decoration:none;
      border: 1px solid transparent;
    }
    .side-nav a:hover{
      background: rgba(255,255,255,.06);
      border-color: rgba(255,255,255,.08);
      color:#fff;
    }
    .side-nav a.active{
      background: rgba(99,102,241,.18);
      border-color: rgba(99,102,241,.22);
      color:#fff;
    }
    .side-footer{
      margin-top: 16px;
      padding: 14px;
      border-radius: 18px;
      border: 1px solid rgba(255,255,255,.08);
      background: rgba(255,255,255,.04);
      color: var(--muted);
      font-size: .9rem;
    }

    /* Main */
    .main{
      flex:1;
      padding: 22px 22px 40px;
    }
    .topbar{
      background: #ffffff;
      border: 1px solid rgba(15,23,42,.08);
      border-radius: 20px;
      padding: 14px 16px;
      display:flex;
      align-items:center;
      justify-content: space-between;
      gap: 12px;
      box-shadow: 0 14px 34px rgba(15,23,42,.06);
    }
    .hello h1{
      font-size: 1.05rem;
      margin:0;
      font-weight: 800;
      color: #0f172a;
    }
    .hello p{
      margin: 2px 0 0;
      color:#64748b;
      font-size: .9rem;
    }
    .pill{
      background: rgba(59,130,246,.10);
      color:#1d4ed8;
      border: 1px solid rgba(59,130,246,.16);
      padding: 6px 10px;
      border-radius: 999px;
      font-weight: 700;
      font-size: .85rem;
      display:inline-flex;
      align-items:center;
      gap: 8px;
      white-space: nowrap;
    }
    .kpi-grid{
      margin-top: 14px;
      display:grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 14px;
    }
    @media (max-width: 1100px){
      .sidebar{ width: 280px; }
      .kpi-grid{ grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 780px){
      .app{ flex-direction: column; }
      .sidebar{ position: relative; height: auto; width: 100%; }
      .kpi-grid{ grid-template-columns: 1fr; }
      .main{ padding: 14px; }
    }
    .kpi{
      background: #fff;
      border: 1px solid rgba(15,23,42,.08);
      border-radius: 20px;
      padding: 14px;
      box-shadow: 0 14px 34px rgba(15,23,42,.06);
      display:flex;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
      overflow:hidden;
      position:relative;
    }
    .kpi:before{
      content:"";
      position:absolute;
      inset:-80px -80px auto auto;
      width: 180px; height: 180px;
      border-radius: 999px;
      background: radial-gradient(circle at 30% 30%, rgba(59,130,246,.20), transparent 60%);
      pointer-events:none;
    }
    .kpi .label{ color:#64748b; font-size:.85rem; }
    .kpi .value{ font-size: 1.35rem; font-weight: 900; color:#0f172a; margin-top: 4px; }
    .kpi .icon{
      width: 44px; height: 44px;
      border-radius: 16px;
      display:grid; place-items:center;
      background: rgba(15,23,42,.05);
      border: 1px solid rgba(15,23,42,.08);
      color:#0f172a;
      flex-shrink: 0;
    }

    .grid-2{
      margin-top: 14px;
      display:grid;
      grid-template-columns: 2fr 1fr;
      gap: 14px;
    }
    @media (max-width: 1100px){
      .grid-2{ grid-template-columns: 1fr; }
    }

    .panel{
      background:#fff;
      border: 1px solid rgba(15,23,42,.08);
      border-radius: 20px;
      box-shadow: 0 14px 34px rgba(15,23,42,.06);
      overflow:hidden;
    }
    .panel-h{
      padding: 14px 16px;
      display:flex;
      align-items:center;
      justify-content: space-between;
      gap: 10px;
      border-bottom: 1px solid rgba(15,23,42,.06);
    }
    .panel-h .title{
      font-weight: 900;
      color:#0f172a;
      margin:0;
      font-size: 1rem;
    }
    .panel-h .sub{
      color:#64748b;
      margin:2px 0 0;
      font-size:.9rem;
    }
    .panel-b{ padding: 10px 16px 16px; }

    .table thead th{
      color:#64748b;
      font-weight: 700;
      font-size: .82rem;
      text-transform: uppercase;
      letter-spacing: .08em;
      border-bottom: 1px solid rgba(15,23,42,.08);
    }
    .table td{
      vertical-align: middle;
    }
    .truncate{ max-width: 360px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .status-dot{
      width: 8px; height: 8px;
      border-radius: 99px;
      display:inline-block;
      margin-right: 8px;
    }

    .quick-card{
      padding: 16px;
      display:flex;
      flex-direction: column;
      gap: 12px;
    }
    .mini{
      background: rgba(15,23,42,.04);
      border: 1px solid rgba(15,23,42,.07);
      border-radius: 16px;
      padding: 12px;
    }
    .mini .k{ color:#64748b; font-size:.85rem; }
    .mini .v{ font-weight: 800; color:#0f172a; }
    .btn-soft{
      background: rgba(99,102,241,.10);
      border: 1px solid rgba(99,102,241,.18);
      color:#4f46e5;
      font-weight: 800;
    }
    .btn-soft:hover{ background: rgba(99,102,241,.14); border-color: rgba(99,102,241,.24); color:#4338ca; }
    .footer-note{ color:#94a3b8; font-size: .85rem; margin-top: 14px; }
  </style>
</head>

<body>
<div class="app">

  <!-- Sidebar -->
  <aside class="sidebar">
    <div class="profile-chip mb-3">
      <div class="avatar"><?= safe($initial) ?></div>
      <div class="flex-grow-1">
        <div class="chip-name"><?= safe($user["nom"] ?? "Stagiaire") ?></div>
        <div class="chip-sub"><?= safe($user["email"] ?? "") ?></div>
      </div>
      </span>
    </div>

    <div class="brand px-2">
      Stage Platform
      <small>Dashboard Stagiaire</small>
    </div>

    <div class="nav-title">Navigation</div>
    <nav class="side-nav d-grid gap-1">
      <a class="active" href="../stagiaire/dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
      <a href="../stagiaire/profile.php"><i class="bi bi-person-badge"></i> Mon profil</a>
      <a href="../stagiaire/offres.php"><i class="bi bi-briefcase"></i> Offres</a>
      <a href="../stagiaire/candidatures.php"><i class="bi bi-file-earmark-text"></i> Candidatures</a>
      <a href="../stagiaire/cv.php"><i class="bi bi-paperclip"></i> Mon CV</a>
      <a href="../authentification/logout.php" style="color:#fecaca;">
        <i class="bi bi-box-arrow-right"></i> Déconnexion
      </a>
    </nav>

    <div class="side-footer">
      <div class="d-flex align-items-center gap-2 mb-1">
        <i class="bi bi-shield-check"></i>
        <strong style="color:#fff;">État du profil</strong>
      </div>
      <div>
        <?= ($idStagiaire > 0) ? "Profil stagiaire lié ✅" : "Profil stagiaire manquant (table stagiaire) ⚠️" ?>
      </div>
    </div>
  </aside>

  <!-- Main -->
  <main class="main">
    <!-- Topbar -->
    <div class="topbar">
      <div class="hello">
        <h1>Bienvenue, <?= safe($user["nom"] ?? "Stagiaire") ?> 👋</h1>
        <p>Gère ton profil, consulte les offres, et suis tes candidatures.</p>
      </div>

      <div class="d-flex align-items-center gap-2 flex-wrap">
        <span class="pill"><i class="bi bi-person-check"></i> Connecté</span>
        <a href="../stagiaire/profile.php" class="btn btn-outline-secondary rounded-pill">
          <i class="bi bi-gear"></i> Profil
        </a>
        <a href="../stagiaire/offres.php" class="btn btn-primary rounded-pill">
          <i class="bi bi-search"></i> Explorer les offres
        </a>
      </div>
    </div>

    <!-- KPIs -->
    <section class="kpi-grid">
      <div class="kpi">
        <div>
          <div class="label">Total candidatures</div>
          <div class="value"><?= (int)$stats["total"] ?></div>
        </div>
        <div class="icon"><i class="bi bi-collection fs-5"></i></div>
      </div>

      <div class="kpi">
        <div>
          <div class="label">En attente</div>
          <div class="value"><?= (int)$stats["en_attente"] ?></div>
        </div>
        <div class="icon"><i class="bi bi-hourglass-split fs-5"></i></div>
      </div>

      <div class="kpi">
        <div>
          <div class="label">Acceptées</div>
          <div class="value"><?= (int)$stats["acceptee"] ?></div>
        </div>
        <div class="icon"><i class="bi bi-check2-circle fs-5"></i></div>
      </div>

      <div class="kpi">
        <div>
          <div class="label">Refusées</div>
          <div class="value"><?= (int)$stats["refusee"] ?></div>
        </div>
        <div class="icon"><i class="bi bi-x-circle fs-5"></i></div>
      </div>
    </section>

    <section class="grid-2">
      <!-- Left: candidatures + offers -->
      <div class="d-grid gap-3">
        <!-- Recent candidatures -->
        <div class="panel">
          <div class="panel-h">
            <div>
              <div class="title">Mes dernières candidatures</div>
              <div class="sub">Suivi des statuts en temps réel</div>
            </div>
            <a class="btn btn-sm btn-soft rounded-pill" href="../stagiaire/candidatures.php">
              <i class="bi bi-arrow-right"></i> Voir tout
            </a>
          </div>

          <div class="panel-b">
            <?php if (!$idStagiaire): ?>
              <div class="alert alert-warning mb-0">
                Ton compte existe, mais il n’y a pas encore de ligne correspondante dans <b>stagiaire</b>.
                Crée/complète ton profil stagiaire pour activer les candidatures.
              </div>
            <?php elseif (empty($recentCandidatures)): ?>
              <div class="text-muted">Aucune candidature pour le moment.</div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table align-middle mb-0">
                  <thead>
                    <tr>
                      <th>Offre</th>
                      <th>Entreprise</th>
                      <th>Date</th>
                      <th>Statut</th>
                    </tr>
                  </thead>
                  <tbody>
                  <?php foreach ($recentCandidatures as $c): 
                    $st = $c["statut_candidature"] ?? "—";
                    $dot = ($st === "acceptée") ? "bg-success" : (($st === "refusée") ? "bg-danger" : (($st === "en_attente") ? "bg-warning" : "bg-secondary"));
                  ?>
                    <tr>
                      <td class="truncate" title="<?= safe($c["titre"] ?? "") ?>">
                        <div class="fw-bold"><?= safe($c["titre"] ?? "—") ?></div>
                        <div class="text-muted small">
                          <?= safe($c["domaine"] ?? "—") ?> • <?= safe($c["ville_offre"] ?? "—") ?> • <?= safe($c["duree"] ?? "—") ?>
                        </div>
                      </td>
                      <td><?= safe($c["nom_entreprise"] ?? "—") ?></td>
                      <td class="text-muted small"><?= safe($c["date_candidature"] ?? "—") ?></td>
                      <td>
                        <span class="status-dot <?= $dot ?>"></span>
                        <span class="badge rounded-pill <?= badgeClassForStatus($st) ?>"><?= safe($st) ?></span>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Latest offers -->
        <div class="panel">
          <div class="panel-h">
            <div>
              <div class="title">Nouvelles offres</div>
              <div class="sub">Dernières opportunités publiées</div>
            </div>
            <a class="btn btn-sm btn-outline-secondary rounded-pill" href="../stagiaire/offres.php">
              <i class="bi bi-compass"></i> Explorer
            </a>
          </div>

          <div class="panel-b">
            <?php if (empty($latestOffers)): ?>
              <div class="text-muted">Aucune offre disponible.</div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table align-middle mb-0">
                  <thead>
                    <tr>
                      <th>Titre</th>
                      <th>Entreprise</th>
                      <th>Ville</th>
                      <th>Durée</th>
                    </tr>
                  </thead>
                  <tbody>
                  <?php foreach ($latestOffers as $o): ?>
                    <tr>
                      <td class="truncate" title="<?= safe($o["titre"] ?? "") ?>">
                        <div class="fw-bold"><?= safe($o["titre"] ?? "—") ?></div>
                        <div class="text-muted small"><?= safe($o["domaine"] ?? "—") ?></div>
                      </td>
                      <td><?= safe($o["nom_entreprise"] ?? "—") ?></td>
                      <td><?= safe($o["ville"] ?? "—") ?></td>
                      <td><?= safe($o["duree"] ?? "—") ?></td>
                    </tr>
                  <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Right: profile + actions -->
      <div class="d-grid gap-3">
        <div class="panel">
          <div class="panel-h">
            <div>
              <div class="title">Profil stagiaire</div>
              <div class="sub">Données depuis la base</div>
            </div>
            <span class="pill"><i class="bi bi-database-check"></i> DB</span>
          </div>

          <div class="quick-card">
            <div class="mini">
              <div class="k">Niveau d’étude</div>
              <div class="v"><?= safe($user["niveau_etude"] ?? "—") ?></div>
            </div>
            <div class="mini">
              <div class="k">Filière</div>
              <div class="v"><?= safe($user["filiere"] ?? "—") ?></div>
            </div>
            <div class="mini">
              <div class="k">Ville</div>
              <div class="v"><?= safe($user["ville_stagiaire"] ?? "—") ?></div>
            </div>

            <div class="d-grid gap-2">
              <?php if ($cvUrl): ?>
                <a class="btn btn-outline-secondary rounded-pill" target="_blank" href="<?= safe($cvUrl) ?>">
                  <i class="bi bi-file-earmark-pdf"></i> Voir mon CV
                </a>
              <?php else: ?>
                <a class="btn btn-outline-secondary rounded-pill" href="../stagiaire/cv.php">
                  <i class="bi bi-upload"></i> Ajouter mon CV
                </a>
              <?php endif; ?>

              <a class="btn btn-primary rounded-pill" href="../stagiaire/profile.php">
                <i class="bi bi-pencil-square"></i> Modifier le profil
              </a>

              <a class="btn btn-soft rounded-pill" href="../stagiaire/candidatures.php">
                <i class="bi bi-clipboard-check"></i> Suivre mes candidatures
              </a>
            </div>

            <div class="footer-note">
              Astuce: si tu es redirigé vers login, vérifie que <b>$_SESSION["id_utilisateur"]</b> existe après connexion.
            </div>
          </div>
        </div>

        <div class="panel">
          <div class="panel-h">
            <div>
              <div class="title">Actions rapides</div>
              <div class="sub">Accès direct aux pages principales</div>
            </div>
          </div>
          <div class="panel-b">
            <div class="d-grid gap-2">
              <a href="../stagiaire/offres.php" class="btn btn-outline-secondary rounded-pill">
                <i class="bi bi-search"></i> Consulter les offres
              </a>
              <a href="../stagiaire/candidatures.php" class="btn btn-outline-secondary rounded-pill">
                <i class="bi bi-file-earmark-text"></i> Mes candidatures
              </a>
              <a href="../authentification/logout.php" class="btn btn-outline-danger rounded-pill">
                <i class="bi bi-box-arrow-right"></i> Déconnexion
              </a>
            </div>
          </div>
        </div>

      </div>
    </section>

    <div class="mt-4 text-center text-muted small">
      © <?= date("Y"); ?> Stage Platform • Dashboard Stagiaire
    </div>
  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
