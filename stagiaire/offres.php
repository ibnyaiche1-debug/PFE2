<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }


// CSRF token
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
require_once __DIR__ . "/../config/connection.php"; // <-- change if needed

// Support both $conn and $pdo (PDO)
$db = null;
if (isset($conn) && $conn instanceof PDO) $db = $conn;
if (!$db && isset($pdo) && $pdo instanceof PDO) $db = $pdo;
if (!$db) { die("Erreur: connexion DB introuvable (PDO)."); }

if (!isset($_SESSION["id_utilisateur"])) {
  header("Location: ../authentification/login.php");
  exit;
}

$idUtilisateur = (int)$_SESSION["id_utilisateur"];

// Load user + stagiaire (for header display)
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

function safe($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
$initial = strtoupper(substr($user["nom"] ?? "S", 0, 1));

// OFFRES LOGIC (kept simple + safe)
$q = trim($_GET["q"] ?? "");
$sql = "
  SELECT 
    o.id_offre, o.titre, o.domaine, o.duree, o.ville, o.date_publication, o.statut,
    e.nom_entreprise
  FROM offre_stage o
  LEFT JOIN entreprise e ON e.id_entreprise = o.id_entreprise
";
$params = [];
if ($q !== "") {
  $sql .= " WHERE o.titre LIKE ? OR o.domaine LIKE ? OR o.ville LIKE ? OR e.nom_entreprise LIKE ? ";
  $like = "%".$q."%";
  $params = [$like,$like,$like,$like];
}
$sql .= " ORDER BY COALESCE(o.date_publication,'1970-01-01') DESC, o.id_offre DESC LIMIT 50";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$offers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?><!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Offres — Stagiaire</title>

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
        radial-gradient(900px 600px at 8% 0%, rgba(59,130,246,.18), transparent 58%),
        radial-gradient(900px 600px at 92% 10%, rgba(6,182,212,.12), transparent 60%),
        radial-gradient(900px 700px at 50% 110%, rgba(34,197,94,.10), transparent 62%),
        linear-gradient(180deg, var(--bg0), var(--bg1));
      overflow-x:hidden;
    }

    .blob{
      position: fixed;
      width: 380px; height: 380px;
      filter: blur(46px);
      opacity: .28;
      border-radius: 999px;
      z-index:-1;
      animation: floaty 22s ease-in-out infinite;
    }
    .blob.b1{ left:-160px; top:-120px; background: rgba(59,130,246,.55); }
    .blob.b2{ right:-220px; top:30px; background: rgba(6,182,212,.50); animation-delay: -6s; }
    .blob.b3{ left: 35%; bottom:-240px; background: rgba(34,197,94,.36); animation-delay: -10s; }
    @keyframes floaty{
      0%,100%{ transform: translate3d(0,0,0) scale(1); }
      50%{ transform: translate3d(0,18px,0) scale(1.05); }
    }

    .wrap{ max-width: 1180px; margin: 0 auto; padding: 22px 16px 48px; }

    .top{
      position: inherit; top: 14px; z-index: 20;
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
    .top-inner{ position: relative; padding: 16px 18px 14px; }
    .row1{ display:flex; align-items:center; justify-content:space-between; gap:14px; }

    .identity{ display:flex; align-items:center; gap:14px; min-width: 0; }
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
    .badge-pill i{ color: rgba(59,130,246,.95); }

    .actions{ display:flex; gap:10px; align-items:center; flex-wrap:wrap; justify-content:flex-end; }
    .btn-ghost{
      border-radius: 999px;
      border: 1px solid rgba(255,255,255,.18);
      background: rgba(255,255,255,.06);
      color: var(--text);
      font-weight: 400;
      padding: 10px 14px;
      display:inline-flex; align-items:center; gap:8px;
      text-decoration:none;
      transition: transform .12s ease, background .12s ease, border-color .12s ease;
      text-decoration:none;
    }
    .btn-ghost:hover{
      transform: translateY(-1px);
      background: rgba(255,255,255,.10);
      border-color: rgba(255,255,255,.26);
      color: var(--text);
    }

    .btn-ghost.active{
      background: rgba(255,255,255,.10);
      border-color: rgba(255,255,255,.22);
      color: var(--text);
      box-shadow: 0 14px 30px rgba(0,0,0,.14);
    }

    /* Apply (CV upload) mini form */
    .apply-form{
      margin-top: 10px;
      display:flex;
      flex-direction: column;
      gap:10px;
      padding: 12px;
      border-radius: 18px;
      border: 1px solid rgba(255,255,255,.14);
      background: rgba(255,255,255,.06);
    }
    .apply-form .apply-label{
      font-size: .88rem;
      color: var(--muted);
      display:flex;
      align-items:center;
      gap:8px;
    }
    .apply-form .cv-file{
      background: rgba(255,255,255,.06) !important;
      border: 1px solid rgba(255,255,255,.14) !important;
      color: rgba(255,255,255,.92) !important;
      border-radius: 14px !important;
      padding: .45rem .6rem;
    }
    .apply-form .cv-file::file-selector-button{
      border-radius: 12px;
      border: 1px solid rgba(255,255,255,.18);
      background: rgba(255,255,255,.08);
      color: rgba(255,255,255,.90);
      padding: .35rem .7rem;
      margin-right: 12px;
    }
    .btn-apply{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      gap:10px;
      border: 1px solid rgba(99,102,241,.30);
      background: rgba(99,102,241,.18);
      color: rgba(255,255,255,.95);
      border-radius: 14px;
      padding: .55rem .85rem;
      font-weight: 600;
      transition: all .12s ease;
    }
    .btn-apply:hover{
      transform: translateY(-1px);
      background: rgba(99,102,241,.24);
      border-color: rgba(99,102,241,.40);
      color: rgba(255,255,255,.98);
    }

    .mono{ font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
    .btn-dangerish{
      background: rgba(251,113,133,.10);
      border-color: rgba(251,113,133,.22);
    }
    .btn-dangerish:hover{
      background: rgba(251,113,133,.14);
      border-color: rgba(251,113,133,.30);
    }

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
    .card-head{
      padding: 18px 18px 12px;
      border-bottom: 1px solid rgba(255,255,255,.12);
      display:flex; align-items:flex-start; justify-content:space-between; gap:12px;
      background:
        radial-gradient(700px 160px at 10% 0%, rgba(59,130,246,.14), transparent 0%),
        radial-gradient(700px 160px at 90% 0%, rgba(59,130,246,.14), transparent 0%);
    }
    .h-title{
      margin:0;
      font-weight: 950;
      letter-spacing:.2px;
      display:flex; align-items:center; gap:10px;
    }
    .h-sub{ margin: 6px 0 0; color: var(--muted); font-size: .95rem; }
    .card-body{ padding: 18px; }

    .searchbar{
      display:flex; gap:10px; flex-wrap:wrap;
      padding: 14px;
      border-radius: 20px;
      background: rgba(0,0,0,.18);
      border: 1px solid rgba(255,255,255,.12);
    }
    .form-control{
      padding: 12px 14px;
      background: rgba(0,0,0,.22);
      border: 1px solid rgba(255,255,255,.14);
      color: var(--text);
    }
    .form-control::placeholder{ color: rgba(255,255,255,.45); }
    .form-control:focus{
      background: rgba(0,0,0,.22);
      border-color: rgba(59,130,246,.55);
      box-shadow: 0 0 0 .2rem rgba(59,130,246,.12);
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
      white-space: nowrap;
    }
    .btn-primaryx:hover{ filter: brightness(.98); transform: translateY(-1px); }
    .pill{
      display:inline-flex; align-items:center; gap:8px;
      padding: 6px 10px;
      border-radius: 999px;
      background: rgba(255,255,255,.08);
      border: 1px solid rgba(255,255,255,.12);
      color: var(--muted);
      font-weight: 850;
      font-size: .82rem;
    }

    .offer{
      border-radius: 22px;
      border: 1px solid rgba(255,255,255,.12);
      background: rgba(0,0,0,.14);
      padding: 14px;
      transition: transform .12s ease, background .12s ease, border-color .12s ease;
    }
    .offer:hover{
      transform: translateY(-2px);
      background: rgba(0,0,0,.18);
      border-color: rgba(255,255,255,.18);
    }
    .offer-title{ font-weight: 1000; margin:0; }
    .meta{ color: var(--muted); font-size: .92rem; }
    .meta strong{ color: rgba(255,255,255,.86); font-weight: 900; }
    .tag{
      display:inline-flex; align-items:center; gap:8px;
      padding: 6px 10px;
      border-radius: 999px;
      border: 1px solid rgba(255,255,255,.14);
      background: rgba(255,255,255,.06);
      color: rgba(255,255,255,.84);
      font-weight: 850;
      font-size: .82rem;
      margin-right: 8px;
      margin-top: 8px;
    }
    .tag i{ color: rgba(6,182,212,.9); }
    .footer{
      text-align:center;
      color: rgba(255,255,255,.55);
      font-size: .86rem;
      margin-top: 14px;
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


    /* NAVBAR_CANDIDATURE_STYLE */
    .panel{
      background: rgba(255,255,255,.07);
      border: 1px solid rgba(255,255,255,.14);
      border-radius: 26px;
      box-shadow: 0 26px 70px rgba(0,0,0,.32);
      backdrop-filter: blur(10px);
    }
    .head{ padding: 22px; }
    .head-row{ display:flex; align-items:center; justify-content:space-between; gap:18px; flex-wrap:wrap; }
    .actions{ display:flex; gap:10px; align-items:center; flex-wrap:wrap; justify-content:flex-end; }
    .btn-ghost{ white-space: nowrap; }
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
    @media (max-width: 768px){
      .head-row{ flex-direction: column; align-items: flex-start; }
      .actions{ width: 100%; justify-content:flex-start; }
      .actions a{ flex: 1 1 calc(50% - 10px); justify-content:center; text-align:center; }
      .tabs{ grid-template-columns: repeat(2, minmax(0,1fr)); }
    }
    @media (max-width: 420px){
      .actions a{ flex: 1 1 100%; }
      .tabs{ grid-template-columns: 1fr; }
    }

</style>
</head>

<body>
  <div class="blob b1"></div>
  <div class="blob b2"></div>
  <div class="blob b3"></div>

  <div class="wrap">

    
    <div class="panel">
      <div class="head">
        <div class="head-row">
          <div class="identity">
            <div class="avatar"><?= safe($initial) ?></div>
            <div>
              <h1 class="title">Offres</h1>
              <div class="subtitle"><?= safe($user["nom"] ?? "Stagiaire") ?> — <?= safe($user["email"] ?? "") ?></div>
              <div class="badge-pill"><i class="bi bi-briefcase-fill"></i> Explorer les opportunités</div>
            </div>
          </div>
          
          <div class="actions">
            <a class="btn-ghost" href="../stagiaire/cv.php"><i class="bi bi-paperclip"></i> Mon CV</a>
            <a class="btn-ghost" href="../stagiaire/candidatures.php"><i class="bi bi-send-check"></i> Candidatures</a>
            <a class="btn-ghost btn-dangerish" href="../authentification/logout.php"><i class="bi bi-box-arrow-right"></i> Déconnexion</a>
          </div>
        </div>
      </div>
      <div class="tabs ">
        <a class="tab" href="../stagiaire/dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <a class="tab" href="../stagiaire/profile.php"><i class="bi bi-person-badge"></i> Profil</a>
        <a class="tab active" href="../stagiaire/offres.php"><i class="bi bi-briefcase"></i> Offres</a>
      </div>
    </div>


    <div class="row g-3 mt-3">
      <div class="col-12">
        <div class="cardx">
          <div class="card-head">
            <h2 class="h-title"><i class="bi bi-search"></i> Recherche & résultats</h2>
            <div class="h-sub">Filtrer par titre, domaine, ville ou entreprise.</div>
          </div>

          <div class="card-body">
            <form class="searchbar" method="get">
              <div class="flex-grow-1">
                <input class="form-control" name="q" value="<?= safe($q) ?>" placeholder="Ex: Développeur, Data, Casablanca, Orange..." />
              </div>
              <button class="btn-primaryx" type="submit"><i class="bi bi-funnel"></i> Rechercher</button>
              <?php if (!empty($q)): ?>
                <a class="btn-ghost" href="../stagiaire/offres.php"><i class="bi bi-x-circle"></i> Effacer</a>
              <?php endif; ?>
            </form>

            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mt-3">
              <div class="pill"><i class="bi bi-list-ul"></i> <?= (int)count($offers) ?> résultat(s)</div>
              <div class="pill"><i class="bi bi-clock"></i> Les plus récentes en premier</div>
            </div>

            <div class="mt-3 d-grid gap-3">
              <?php if (empty($offers)): ?>
                <div class="alert alert-warning" style="border-radius:18px;background:rgba(245,158,11,.12);border-color:rgba(245,158,11,.22);color:rgba(255,255,255,.9);">
                  <i class="bi bi-exclamation-circle-fill me-1"></i>
                  Aucune offre trouvée. Essayez un autre mot-clé.
                </div>
              <?php else: ?>
                <?php foreach ($offers as $o): ?>
                  <div class="offer">
                    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                      <div style="min-width: 260px;">
                        <h3 class="offer-title"><?= safe($o["titre"] ?? "—") ?></h3>
                        <div class="meta mt-1">
                          <strong><?= safe($o["nom_entreprise"] ?? "—") ?></strong>
                          • <?= safe($o["ville"] ?? "—") ?>
                          • <?= safe($o["duree"] ?? "—") ?>
                        </div>
                        <div class="mt-2">
                          <span class="tag"><i class="bi bi-tags"></i> <?= safe($o["domaine"] ?? "—") ?></span>
                          <?php if (!empty($o["date_publication"])): ?>
                            <span class="tag"><i class="bi bi-calendar3"></i> Publié: <?= safe($o["date_publication"]) ?></span>
                          <?php endif; ?>
                          <?php if (!empty($o["statut"])): ?>
                            <span class="pill"><i class="bi bi-info-circle"></i> <?= safe($o["statut"]) ?></span>
                          <?php endif; ?>
                        </div>
                      </div>

                      <div class="d-flex gap-2 align-items-center">

                        <form method="POST" action="candidatures.php" enctype="multipart/form-data" class="apply-form">
                          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                          <input type="hidden" name="action" value="apply">
                          <input type="hidden" name="id_offre" value="<?= $o['id_offre'] ?>">

                          <div class="apply-label">
                            <i class="bi bi-paperclip"></i>
                            <span>Joindre votre CV <span class="mono" style="opacity:.8">(PDF · max 2MB)</span></span>
                          </div>

                          <input type="file" name="cv" accept=".pdf,application/pdf" class="cv-file" required>

                          <button type="submit" class="btn-apply">
                            <i class="bi bi-send"></i> Postuler
                          </button>
                        </form>

                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
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
