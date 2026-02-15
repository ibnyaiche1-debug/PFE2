<?php
// Stage Platform — Landing page (index.php)
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Optional: adjust these paths to match your folders
$loginUrl  = "authentification/login.php";
$logoutUrl = "authentification/logout.php";
$stagiaireHome = "stagiaire/dashboard.php";
$offresUrl = "stagiaire/offres.php";
$cvUrl = "stagiaire/cv.php";
$candidaturesUrl = "stagiaire/candidatures.php";

// Basic session flags (adapt if your project uses different keys)
$isLoggedIn = !empty($_SESSION["id_utilisateur"]);
$role = $_SESSION["role"] ?? null; // ex: 'stagiaire', 'entreprise', 'admin' (if you use it)

// If user is logged in, you can auto-redirect to dashboard by uncommenting:
// if ($isLoggedIn) { header("Location: ".$stagiaireHome); exit; }

function safe($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Stage Platform — Trouvez votre stage</title>

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
      --muted: rgba(255,255,255,.68);
      --shadow: 0 18px 45px rgba(0,0,0,.35);
      --radius: 24px;
    }
    body{
      color: var(--text);
      background:
        radial-gradient(1000px 550px at 10% 0%, rgba(99,102,241,.28), transparent 65%),
        radial-gradient(950px 520px at 90% 10%, rgba(34,197,94,.18), transparent 60%),
        radial-gradient(850px 520px at 50% 95%, rgba(59,130,246,.10), transparent 55%),
        linear-gradient(180deg, var(--bg1), var(--bg2));
      min-height: 100vh;
      overflow-x: hidden;
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
      border-radius: 18px;
      backdrop-filter: blur(10px);
    }
    .muted{ color: var(--muted); }
    .brand{
      display:flex; align-items:center; gap:12px;
    }
    .logo{
      width:42px; height:42px; border-radius:14px;
      display:grid; place-items:center;
      background: rgba(255,255,255,.08);
      border: 1px solid rgba(255,255,255,.14);
    }
    .pill{
      display:inline-flex; align-items:center; gap:.45rem;
      border:1px solid rgba(255,255,255,.14);
      background: rgba(255,255,255,.06);
      padding:.28rem .65rem;
      border-radius: 999px;
      color: rgba(255,255,255,.86);
      font-size:.85rem;
      white-space: nowrap;
    }
    .btn-ghost{
      border: 1px solid rgba(255,255,255,.16);
      background: rgba(255,255,255,.07);
      color: rgba(255,255,255,.92);
      border-radius: 999px;
    }
    .btn-ghost:hover{ background: rgba(255,255,255,.10); color: rgba(255,255,255,.97); }
    .btn-primary, .btn-success, .btn-info{
      border-radius: 999px;
    }
    .hero-title{
      font-weight: 800;
      letter-spacing: -0.4px;
      line-height: 1.05;
    }
    .feature{
      display:flex; gap:12px;
      padding: 16px;
      border-radius: 18px;
      border: 1px solid rgba(255,255,255,.12);
      background: rgba(255,255,255,.06);
      height: 100%;
    }
    .feature i{ font-size: 1.25rem; opacity: .95; }
    .stat{
      padding: 14px 16px;
      border-radius: 18px;
      border: 1px solid rgba(255,255,255,.12);
      background: rgba(255,255,255,.06);
    }
    .mono{ font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono","Courier New", monospace; }
    .divider{
      height:1px;
      background: rgba(255,255,255,.10);
      margin: 18px 0;
    }
    .blob{
      position:absolute; inset:auto;
      width: 520px; height: 520px;
      border-radius: 50%;
      filter: blur(50px);
      opacity: .25;
      pointer-events:none;
    }
    .blob.a{ background: #6366f1; top:-140px; left:-140px; }
    .blob.b{ background: #22c55e; top:-160px; right:-180px; }
  

    /* Responsive */

    @media (max-width: 992px){
      .wrap{ padding: 16px 12px 32px !important; }
      .top{ padding: 16px !important; }
      .top .actions{ width: 100%; justify-content: flex-start !important; flex-wrap: wrap; }
      .tabs{ padding: 12px 14px 14px !important; }
      .grid{ grid-template-columns: 1fr !important; }
      .cards{ grid-template-columns: 1fr !important; }
      .filters{ flex-wrap: wrap; }
      .search{ width: 100% !important; }
      .search input, .search .form-control{ width: 100% !important; }
      .btn{ white-space: nowrap; }
    }
    @media (max-width: 576px){
      .top h1{ font-size: 1.25rem !important; }
      .pill{ font-size: .82rem !important; }
      .btn{ width: 100%; }
      .top .actions .btn{ width: auto; }
      .offer{ padding: 14px !important; }
    }

</style>
</head>
<body>
  <div class="blob a"></div>
  <div class="blob b"></div>

  <div class="container py-4 py-md-5">
    <!-- Top bar -->
    <div class="glass p-3 p-md-4 mb-4">
      <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
        <div class="brand">
          <div class="logo"><i class="bi bi-mortarboard-fill"></i></div>
          <div>
            <div class="d-flex flex-wrap align-items-center gap-2">
              <div class="h5 mb-0 fw-bold">InternGo</div>
              <span class="pill"><i class="bi bi-stars"></i> Stages & opportunités</span>
            </div>
            <div class="muted small">Postulez plus vite avec un CV prêt et suivez vos candidatures.</div>
          </div>
        </div>

        <div class="d-flex flex-wrap gap-2">
          <?php if($isLoggedIn): ?>
            <a class="btn btn-ghost" href="<?= safe($stagiaireHome) ?>"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a class="btn btn-ghost" href="<?= safe($offresUrl) ?>"><i class="bi bi-briefcase"></i> Offres</a>
            <a class="btn btn-ghost" href="<?= safe($cvUrl) ?>"><i class="bi bi-file-earmark-pdf"></i> Mon CV</a>
            <a class="btn btn-outline-light" href="<?= safe($logoutUrl) ?>"><i class="bi bi-box-arrow-right"></i> Déconnexion</a>
          <?php else: ?>
            <a class="btn btn-ghost" href="<?= safe($loginUrl) ?>"><i class="bi bi-box-arrow-in-right"></i> Se connecter</a>
            <a class="btn btn-primary" href="<?= safe($loginUrl) ?>"><i class="bi bi-rocket-takeoff"></i> Commencer</a>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Hero -->
    <div class="row g-4 align-items-stretch">
      <div class="col-lg-7">
        <div class="glass p-4 p-md-5 h-100">
          <div class="d-flex flex-wrap gap-2 mb-3">
            <span class="pill"><i class="bi bi-shield-check"></i> Sécurisé</span>
            <span class="pill"><i class="bi bi-lightning-charge"></i> Rapide</span>
            <span class="pill"><i class="bi bi-graph-up"></i> Suivi</span>
          </div>

          <h1 class="display-5 hero-title mb-3">
            Trouvez un stage, <br class="d-none d-md-block">
            postulez avec votre CV, <br class="d-none d-md-block">
            suivez vos candidatures.
          </h1>

          <p class="muted fs-5 mb-4">
            Une plateforme simple pour explorer des offres, déposer votre CV en PDF et gérer vos candidatures en un seul endroit.
          </p>

          <div class="d-flex flex-wrap gap-2">
            <?php if($isLoggedIn): ?>
              <a class="btn btn-success px-4" href="<?= safe($offresUrl) ?>">
                <i class="bi bi-search"></i> Voir les offres
              </a>
              <a class="btn btn-info px-4" href="<?= safe($candidaturesUrl) ?>">
                <i class="bi bi-send-check"></i> Mes candidatures
              </a>
            <?php else: ?>
              <a class="btn btn-primary px-4" href="<?= safe($loginUrl) ?>">
                <i class="bi bi-box-arrow-in-right"></i> Se connecter
              </a>
              <a class="btn btn-ghost px-4" href="index.php">
                <i class="bi bi-info-circle"></i> Comment ça marche
              </a>
            <?php endif; ?>
          </div>

          <div class="divider"></div>

          <div class="row g-3">
            <div class="col-md-4">
              <div class="stat">
                <div class="muted small">CV</div>
                <div class="fw-semibold"><i class="bi bi-file-earmark-pdf"></i> PDF (2MB)</div>
              </div>
            </div>
            <div class="col-md-4">
              <div class="stat">
                <div class="muted small">Candidatures</div>
                <div class="fw-semibold"><i class="bi bi-check2-circle"></i> Suivi statut</div>
              </div>
            </div>
            <div class="col-md-4">
              <div class="stat">
                <div class="muted small">Offres</div>
                <div class="fw-semibold"><i class="bi bi-briefcase"></i> Recherche facile</div>
              </div>
            </div>
          </div>

        </div>
        <div class="text-center muted small mt-4">
      © <?= date("Y") ?> InternGo 
    </div>
      </div>

      <div class="col-lg-5">
        <div class="glass p-4 p-md-5 h-100">
          <h2 class="h5 fw-bold mb-3"><i class="bi bi-list-check"></i> Comment ça marche</h2>

          <div class="d-grid gap-3" id="how">
            <div class="feature">
              <i class="bi bi-person-check"></i>
              <div>
                <div class="fw-semibold">1) Connectez-vous</div>
                <div class="muted small">Accédez à votre espace stagiaire et complétez votre profil.</div>
              </div>
            </div>

            <div class="feature">
              <i class="bi bi-upload"></i>
              <div>
                <div class="fw-semibold">2) Uploadez votre CV</div>
                <div class="muted small">Ajoutez votre CV en PDF dans <span class="mono">Mon CV</span>.</div>
              </div>
            </div>

            <div class="feature">
              <i class="bi bi-send"></i>
              <div>
                <div class="fw-semibold">3) Postulez</div>
                <div class="muted small">Postulez aux offres avec votre CV et suivez vos candidatures.</div>
              </div>
            </div>
          </div>

          <div class="divider"></div>
        </div>
      </div>
    </div>
    
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
