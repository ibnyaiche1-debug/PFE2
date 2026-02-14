<?php
require_once __DIR__ . '/../config/connection.php';
require_once __DIR__ . '/../includes/authentification.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Fallback if $BASE_URL is not defined by includes
if (!isset($BASE_URL) || !$BASE_URL) {
    // If script path is like /stage_platform/authentification/login.php => BASE_URL becomes /stage_platform
    $BASE_URL = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/');
}


$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST["email"];
    $pw = $_POST["mot_de_passe"];

    $stmt = $pdo->prepare("SELECT * FROM utilisateur WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($pw, $user["mot_de_passe"])) {
        $_SESSION["id_utilisateur"] = $user["id_utilisateur"]; // expected by isLoggedIn()/dashboards
        $_SESSION["user_id"] = $user["id_utilisateur"]; // optional alias
        $_SESSION["role"] = $user["role"];

        if ($user["role"] == "admin") {
            header("Location: $BASE_URL/admin/dashboard.php");
        } elseif ($user["role"] == "entreprise") {
            header("Location: $BASE_URL/entreprise/dashboard.php");
        } elseif ($user["role"] == "stagiaire") {
            header("Location: $BASE_URL/stagiaire/dashboard.php");
        }
        exit();
    } else {
        $error = "Email ou mot de passe incorrect.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Connexion | InternGo</title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <!-- Your CSS (same design as login.php) -->
    <link rel="stylesheet" href="<?= htmlspecialchars($BASE_URL) ?>/assets/style.css">
</head>

<body>
    <div class="blob one"></div>
    <div class="blob two"></div>
    <div class="blob three"></div>

    <div class="wrap">
        <div class="container py-4 py-md-5">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <a class="brand" href="<?= $BASE_URL ?>/index.php">
                    <span class="brand-badge"><i class="bi bi-briefcase-fill"></i></span>
                    <div>
                        <div class="fw-bold">InternGo</div>
                        <div class="small muted">Étudiants • Entreprises • Admin</div>
                    </div>
                </a>

                <a class="chip" href="<?= htmlspecialchars($BASE_URL) ?>/authentification/register.php">
                    <i class="bi bi-person-plus"></i>
                    Créer un compte
                </a>
            </div>

            <div class="row g-4 align-items-stretch justify-content-center">
                <div class="col-12 col-lg-11 col-xl-10">
                    <div class="glass p-4 p-md-5">
                        <div class="row g-4 align-items-center">

                            <!-- Left -->
                            <div class="col-12 col-lg-6">
                                <span class="chip mb-3"><i class="bi bi-stars"></i> Trouvez un stage rapidement</span>
                                <h1 class="title display-6 mb-2">Connectez-vous et gérez votre parcours.</h1>
                                <p class="muted mb-4">
                                    Recherchez des offres, postulez, suivez vos candidatures — ou publiez vos stages côté entreprise.
                                </p>

                                <div class="divider my-4"></div>

                                <div class="d-flex gap-3 align-items-start mb-3">
                                    <i class="bi bi-check2-circle fs-5" style="color:#22c55e;"></i>
                                    <div>
                                        <div class="fw-semibold">Simple & rapide</div>
                                        <div class="small muted">Interface claire, facile à utiliser.</div>
                                    </div>
                                </div>

                                <div class="d-flex gap-3 align-items-start mb-3">
                                    <i class="bi bi-shield-check fs-5" style="color:#38bdf8;"></i>
                                    <div>
                                        <div class="fw-semibold">Sécurisé</div>
                                        <div class="small muted">Accès selon le rôle + données protégées.</div>
                                    </div>
                                </div>

                                <div class="d-flex gap-3 align-items-start">
                                    <i class="bi bi-graph-up-arrow fs-5" style="color:#a78bfa;"></i>
                                    <div>
                                        <div class="fw-semibold">Suivi</div>
                                        <div class="small muted">Consultez vos statuts et activités.</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Right: Login -->
                            <div class="col-12 col-lg-6">
                                <div class="p-3 p-md-4 glass glass-inner">
                                    <h2 class="h4 fw-bold mb-1">Connexion</h2>
                                    <p class="muted mb-4">Accédez à votre espace.</p>

                                    <?php if (!empty($error)) : ?>
                                        <div class="alert d-flex align-items-center gap-2" role="alert">
                                            <i class="bi bi-exclamation-triangle-fill"></i>
                                            <div><?= htmlspecialchars($error) ?></div>
                                        </div>
                                    <?php endif; ?>

                                    <form method="POST" action="">
                                        <div class="mb-3">
                                            <label class="form-label small muted">Email</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                                <input
                                                    type="email"
                                                    name="email"
                                                    class="form-control"
                                                    placeholder="nom@email.com"
                                                    required
                                                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                                >
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label small muted">Mot de passe</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                                <input
                                                    type="password"
                                                    name="mot_de_passe"
                                                    class="form-control"
                                                    placeholder="Votre mot de passe"
                                                    required
                                                    id="pwd"
                                                >
                                                <button
                                                    class="btn btn-outline-light"
                                                    type="button"
                                                    id="togglePwd"
                                                    style="border-color: rgba(255,255,255,.18);"
                                                    aria-label="Afficher/masquer le mot de passe"
                                                >
                                                    <i class="bi bi-eye" id="eyeIcon"></i>
                                                </button>
                                            </div>
                                        </div>

                                        <button type="submit" class="btn btn-cool w-100">
                                            <i class="bi bi-box-arrow-in-right me-1"></i> Se connecter
                                        </button>

                                        <div class="text-center mt-3 small">
                                            <span class="muted">Pas de compte ?</span>
                                            <a class="link" href="<?= htmlspecialchars($BASE_URL) ?>/authentification/register.php">Créer un compte</a>
                                        </div>
                                    </form>
                                </div>

                                <div class="text-center small muted mt-3">
                                    Astuce: utilisez un mot de passe fort pour votre sécurité.
                                </div>
                            </div>

                        </div>
                    </div>

                    <div class="text-center small muted mt-4">
                        © <?= date('Y') ?> InternGo
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Your JS (same as login.php) -->
    <script src="<?= $BASE_URL ?>/assets/script.js"></script>
</body>
</html>
