<?php
require_once __DIR__ . '/../config/connection.php';
require_once __DIR__ . '/../includes/authentification.php';

requireRole('stagiaire');

$id_utilisateur = $_SESSION['id_utilisateur'];

// 1) Get stagiaire id (id_stagiaire)
$stmt = $pdo->prepare("SELECT id_stagiaire FROM stagiaire WHERE id_utilisateur = ? LIMIT 1");
$stmt->execute([$id_utilisateur]);
$stagiaireRow = $stmt->fetch();

$id_stagiaire = $stagiaireRow['id_stagiaire'] ?? null;

// Defaults
$totalApps = 0;
$recentApps = [];

if ($id_stagiaire) {
    // 2) Total applications count
    $stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM candidature WHERE id_stagiaire = ?");
    $stmt->execute([$id_stagiaire]);
    $totalApps = (int)($stmt->fetch()['total'] ?? 0);

    // 3) Recent applications (latest 5)
    $stmt = $pdo->prepare("
        SELECT 
            c.date_candidature,
            c.statut_candidature,
            o.titre,
            o.ville
        FROM candidature c
        JOIN offre_stage o ON o.id_offre = c.id_offre
        WHERE c.id_stagiaire = ?
        ORDER BY c.date_candidature DESC
        LIMIT 5
    ");
    $stmt->execute([$id_stagiaire]);
    $recentApps = $stmt->fetchAll();
}

// Helper for badge class
function statusBadgeClass(string $status): string
{
    return match ($status) {
        'acceptée' => 'bg-success',
        'refusée'  => 'bg-danger',
        default    => 'bg-warning text-dark', // en_attente
    };
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard Stagiaire | InternGo</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="/stage_platform/assets/style.css">
</head>

<body>
  <div class="blob one"></div>
  <div class="blob two"></div>
  <div class="blob three"></div>

  <div class="wrap">
    <div class="container py-4 py-md-5">

      <!-- Top bar -->
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <a class="brand" href="/stage_platform/index.php">
          <span class="brand-badge"><i class="bi bi-briefcase-fill"></i></span>
          <div>
            <div class="fw-bold">InternGo</div>
            <div class="small muted">Espace Stagiaire</div>
          </div>
        </a>

        <div class="d-flex gap-2">
          <a class="chip" href="/stage_platform/authentification/logout.php">
            <i class="bi bi-box-arrow-right"></i> Déconnexion
          </a>
        </div>
      </div>

      <!-- Welcome + quick actions -->
      <div class="glass p-4 p-md-5 mb-4">
        <div class="row g-4 align-items-center">
          <div class="col-12 col-lg-7">
            <span class="chip mb-3"><i class="bi bi-person-badge"></i> Dashboard</span>
            <h1 class="title display-6 mb-2">Bienvenue 👋</h1>
            <p class="muted mb-0">
              Ici vous pouvez gérer votre recherche de stage, suivre vos candidatures et mettre à jour votre profil.
            </p>
          </div>

          <div class="col-12 col-lg-5">
            <div class="d-grid gap-2">
              <a class="btn btn-cool w-100" href="/stage_platform/offres/list.php">
                <i class="bi bi-search me-1"></i> Search internships
              </a>
              <a class="btn btn-outline-light w-100" href="/stage_platform/stagiaire/upload_cv.php" style="border-color: rgba(255,255,255,.18);">
                <i class="bi bi-file-earmark-arrow-up me-1"></i> Upload CV
              </a>
              <a class="btn btn-outline-light w-100" href="/stage_platform/stagiaire/profile.php" style="border-color: rgba(255,255,255,.18);">
                <i class="bi bi-pencil-square me-1"></i> Edit profile
              </a>
            </div>
          </div>
        </div>
      </div>

      <!-- Stats cards -->
      <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
          <div class="glass p-4 h-100">
            <div class="d-flex align-items-center justify-content-between">
              <div>
                <div class="muted small">Applications sent</div>
                <div class="h2 fw-bold mb-0"><?php echo $totalApps; ?></div>
              </div>
              <div class="brand-badge">
                <i class="bi bi-send-fill"></i>
              </div>
            </div>
          </div>
        </div>

        <div class="col-12 col-md-4">
          <div class="glass p-4 h-100">
            <div class="d-flex align-items-center justify-content-between">
              <div>
                <div class="muted small">Status tracking</div>
                <div class="fw-semibold">en attente / acceptée / refusée</div>
              </div>
              <div class="brand-badge">
                <i class="bi bi-activity"></i>
              </div>
            </div>
          </div>
        </div>

        <div class="col-12 col-md-4">
          <div class="glass p-4 h-100">
            <div class="d-flex align-items-center justify-content-between">
              <div>
                <div class="muted small">Quick tip</div>
                <div class="fw-semibold">Upload your CV (PDF)</div>
              </div>
              <div class="brand-badge">
                <i class="bi bi-filetype-pdf"></i>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Recent applications -->
      <div class="glass p-4 p-md-5">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
          <h2 class="h5 fw-bold mb-0">Recent applications</h2>
          <a class="link" href="/stage_platform/stagiaire/my_applications.php">
            View all <i class="bi bi-arrow-right"></i>
          </a>
        </div>

        <?php if (!$id_stagiaire): ?>
          <div class="alert d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-info-circle-fill"></i>
            <div>
              Votre profil stagiaire n’est pas encore créé. (Il sera créé automatiquement lors de l’inscription.)
            </div>
          </div>
        <?php endif; ?>

        <?php if (empty($recentApps)): ?>
          <p class="muted mb-0">
            No applications yet. Start by searching internships and applying.
          </p>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table align-middle table-borderless mb-0">
              <thead>
                <tr class="muted small">
                  <th>Offer</th>
                  <th>City</th>
                  <th>Date</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($recentApps as $app): ?>
                <tr style="border-top: 1px solid rgba(255,255,255,.10);">
                  <td class="fw-semibold"><?php echo htmlspecialchars($app['titre']); ?></td>
                  <td class="muted"><?php echo htmlspecialchars($app['ville']); ?></td>
                  <td class="muted"><?php echo htmlspecialchars($app['date_candidature'] ?? ''); ?></td>
                  <td>
                    <?php
                      $status = $app['statut_candidature'] ?? 'en_attente';
                      $badgeClass = statusBadgeClass($status);
                    ?>
                    <span class="badge <?php echo $badgeClass; ?>">
                      <?php echo htmlspecialchars($status); ?>
                    </span>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

      <div class="text-center small muted mt-4">
        © <?php echo date('Y'); ?> InternGo — Espace Stagiaire
      </div>

    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="/stage_platform/assets/script.js"></script>
</body>
</html>
