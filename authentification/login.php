<?php
// authentification/login.php

require_once __DIR__ . '/../config/connection.php';
require_once __DIR__ . '/../includes/authentification.php';

function redirectByRole(string $role): void
{
    $role = strtolower(trim($role));

    if ($role === 'stagiaire') {
        redirect('/stage_platform/stagiaire/dashboard.php');
    } elseif ($role === 'entreprise') {
        redirect('/stage_platform/entreprise/dashboard.php');
    } elseif ($role === 'admin') {
        redirect('/stage_platform/admin/dashboard.php');
    } else {
        session_destroy();
        die("Rôle invalide.");
    }
}

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    redirectByRole($_SESSION['role']);
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['mot_de_passe'] ?? '';

    if ($email === '' || $pass === '') {
        $error = "Veuillez remplir tous les champs.";
    } else {
        $stmt = $pdo->prepare("SELECT id_utilisateur, email, mot_de_passe, role FROM utilisateur WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Verify password (bcrypt)
        if ($user && password_verify($pass, $user['mot_de_passe'])) {

            // Set session values
            $_SESSION['id_utilisateur'] = $user['id_utilisateur'];
            $_SESSION['role'] = $user['role'];

            // Redirect by role
            redirectByRole($user['role']);

        } else {
            $error = "Email ou mot de passe incorrect.";
        }
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

    <!-- Your CSS -->
    <link rel="stylesheet" href="/stage_platform/assets/style.css">
</head>

<body>
    <div class="blob one"></div>
    <div class="blob two"></div>
    <div class="blob three"></div>

    <div class="wrap">
        <div class="container py-4 py-md-5">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <a class="brand" href="/stage_platform/index.php">
                    <span class="brand-badge"><i class="bi bi-briefcase-fill"></i></span>
                    <div>
                        <div class="fw-bold">InternGo</div>
                        <div class="small muted">Étudiants • Entreprises • Admin</div>
                    </div>
                </a>

                <a class="chip" href="/stage_platform/authentification/register.php">
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
                                        <div class="small muted">Interface claire, facile à postuler.</div>
                                    </div>
                                </div>

                                <div class="d-flex gap-3 align-items-start mb-3">
                                    <i class="bi bi-shield-check fs-5" style="color:#38bdf8;"></i>
                                    <div>
                                        <div class="fw-semibold">Sécurisé</div>
                                        <div class="small muted">Sessions selon le rôle + données protégées.</div>
                                    </div>
                                </div>

                                <div class="d-flex gap-3 align-items-start">
                                    <i class="bi bi-graph-up-arrow fs-5" style="color:#a78bfa;"></i>
                                    <div>
                                        <div class="fw-semibold">Suivi</div>
                                        <div class="small muted">Statuts candidatures & validation des offres.</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Right: Login -->
                            <div class="col-12 col-lg-6">
                                <div class="p-3 p-md-4 glass glass-inner">
                                    <h2 class="h4 fw-bold mb-1">Connexion</h2>
                                    <p class="muted mb-4">Accédez à votre espace.</p>

                                    <?php if ($error): ?>
                                        <div class="alert d-flex align-items-center gap-2" role="alert">
                                            <i class="bi bi-exclamation-triangle-fill"></i>
                                            <div><?php echo htmlspecialchars($error); ?></div>
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
                                                    value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
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
                                            <a class="link" href="/stage_platform/authentification/register.php">Créer un compte</a>
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
                        © <?php echo date('Y'); ?> InternGo
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Your JS -->
    <script src="/stage_platform/assets/script.js"></script>
</body>
</html>
