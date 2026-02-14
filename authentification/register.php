<?php 
// authentification/register.php

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



function redirectByRole(string $role): void
{
    global $BASE_URL;
$role = strtolower(trim($role));

    if ($role === 'stagiaire') {
        redirect($BASE_URL . '/stagiaire/dashboard.php');
    } elseif ($role === 'entreprise') {
        redirect($BASE_URL . '/entreprise/dashboard.php');
    } elseif ($role === 'admin') {
        redirect($BASE_URL . '/admin/dashboard.php');
    } else {
        session_destroy();
        die("Rôle invalide.");
    }
}

if (isLoggedIn()) {
    redirectByRole($_SESSION['role']);
}

function userTableHasFullNameColumn(PDO $pdo): bool
{
    try {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_SCHEMA = DATABASE() 
               AND TABLE_NAME = 'utilisateur' 
               AND COLUMN_NAME IN ('nom_complet', 'full_name')"
        );
        $stmt->execute();
        return (int) $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

function tableHasColumn(PDO $pdo, string $table, string $column): bool
{
    try {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?"
        );
        $stmt->execute([$table, $column]);
        return (int) $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['nom_complet'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['mot_de_passe'] ?? '';
    $role  = strtolower(trim($_POST['role'] ?? ''));

    // Entreprise fields
    $nomEntreprise    = trim($_POST['nom_entreprise'] ?? '');
    $secteurActivite  = trim($_POST['secteur_activite'] ?? '');
    $ville            = trim($_POST['ville'] ?? '');
    $description      = trim($_POST['description'] ?? '');

    if ($email === '' || $pass === '' || $role === '') {
        $error = "Veuillez remplir tous les champs.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Email invalide.";
    } elseif (!in_array($role, ['stagiaire', 'entreprise'], true)) {
        $error = "Rôle invalide.";
    } elseif (strlen($pass) < 8) {
        $error = "Le mot de passe doit contenir au moins 8 caractères.";
    } elseif ($role === 'stagiaire' && $fullName === '') {
        $error = "Veuillez remplir le nom complet.";
    } elseif ($role === 'entreprise' && ($nomEntreprise === '' || $secteurActivite === '' || $ville === '' || $description === '')) {
        $error = "Veuillez remplir tous les champs de l'entreprise.";
    } else {
        try {
            $pdo->beginTransaction();

            // Check duplicate email in utilisateur
            $stmt = $pdo->prepare("SELECT id_utilisateur FROM utilisateur WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);

            if ($stmt->fetch()) {
                $error = "Cet email est déjà utilisé.";
                $pdo->rollBack();
            } else {
                // Optional: also check stagiaire email if that column exists
                if (tableHasColumn($pdo, 'stagiaire', 'email')) {
                    $stmt2 = $pdo->prepare("SELECT id_utilisateur FROM stagiaire WHERE email = ? LIMIT 1");
                    $stmt2->execute([$email]);
                    if ($stmt2->fetch()) {
                        $error = "Cet email est déjà utilisé.";
                        $pdo->rollBack();
                    }
                }

                if ($error === '') {
                    $hash = password_hash($pass, PASSWORD_BCRYPT);
                    $hasFullName = userTableHasFullNameColumn($pdo);

                    // Insert into utilisateur (kept like your original logic)
                    if ($hasFullName) {
                        $nameToStore = ($role === 'entreprise') ? $nomEntreprise : $fullName;
                        $insertStmt = $pdo->prepare(
                            "INSERT INTO utilisateur (nom, email, mot_de_passe, role) VALUES (?, ?, ?, ?)"
                        );
                        $insertStmt->execute([$nameToStore, $email, $hash, $role]);
                    } else {
                        $insertStmt = $pdo->prepare(
                            "INSERT INTO utilisateur (email, mot_de_passe, role) VALUES (?, ?, ?)"
                        );
                        $insertStmt->execute([$email, $hash, $role]);
                    }

                    $newUserId = (int) $pdo->lastInsertId();

                    // ✅ Insert into stagiaire using EXACT columns you provided:
                    // id_utilisateur, nom, email, mot_de_passe, role, date_inscription
                    if ($role === 'stagiaire') {
                        $stagiaireStmt = $pdo->prepare(
                            "INSERT INTO stagiaire (id_utilisateur, nom, email, mot_de_passe, role, date_inscription)
                             VALUES (?, ?, ?, ?, ?, NOW())"
                        );
                        $stagiaireStmt->execute([$newUserId, $fullName, $email, $hash, $role]);
                    }

                    // Insert into entreprise table
                    if ($role === 'entreprise') {
                        $entrepriseStmt = $pdo->prepare(
                            "INSERT INTO entreprise (id_utilisateur, nom_entreprise, secteur_activite, ville, description)
                             VALUES (?, ?, ?, ?, ?)"
                        );
                        $entrepriseStmt->execute([$newUserId, $nomEntreprise, $secteurActivite, $ville, $description]);
                    }

                    $pdo->commit();

                    $success = "Compte créé avec succès.";
                    redirect($BASE_URL . '/authentification/login.php');
                }
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Erreur lors de la création du compte. Veuillez réessayer.";
            // DEV DEBUG:
            // $error = "Erreur: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Inscription | InternGo</title>

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

                <a class="chip" href="/stage_platform/authentification/login.php">
                    <i class="bi bi-box-arrow-in-right"></i>
                    Se connecter
                </a>
            </div>

            <div class="row g-4 align-items-stretch justify-content-center">
                <div class="col-12 col-lg-11 col-xl-10">
                    <div class="glass p-4 p-md-5">
                        <div class="row g-4 align-items-center">

                            <!-- Left -->
                            <div class="col-12 col-lg-6">
                                <span class="chip mb-3"><i class="bi bi-stars"></i> Créez votre compte</span>
                                <h1 class="title display-6 mb-2">Rejoignez InternGo en quelques étapes.</h1>
                                <p class="muted mb-4">
                                    Accédez aux offres de stages, gérez vos candidatures ou publiez vos annonces.
                                </p>

                                <div class="divider my-4"></div>

                                <div class="d-flex gap-3 align-items-start mb-3">
                                    <i class="bi bi-check2-circle fs-5" style="color:#22c55e;"></i>
                                    <div>
                                        <div class="fw-semibold">Rapide</div>
                                        <div class="small muted">Inscription en moins d’une minute.</div>
                                    </div>
                                </div>

                                <div class="d-flex gap-3 align-items-start mb-3">
                                    <i class="bi bi-shield-check fs-5" style="color:#38bdf8;"></i>
                                    <div>
                                        <div class="fw-semibold">Sécurisé</div>
                                        <div class="small muted">Mot de passe chiffré.</div>
                                    </div>
                                </div>

                                <div class="d-flex gap-3 align-items-start">
                                    <i class="bi bi-graph-up-arrow fs-5" style="color:#a78bfa;"></i>
                                    <div>
                                        <div class="fw-semibold">Efficace</div>
                                        <div class="small muted">Accédez à votre dashboard.</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Right: Register -->
                            <div class="col-12 col-lg-6">
                                <div class="p-3 p-md-4 glass glass-inner">
                                    <h2 class="h4 fw-bold mb-1">Inscription</h2>
                                    <p class="muted mb-4">Créez votre espace.</p>

                                    <?php if ($error): ?>
                                        <div class="alert d-flex align-items-center gap-2" role="alert">
                                            <i class="bi bi-exclamation-triangle-fill"></i>
                                            <div><?php echo htmlspecialchars($error); ?></div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($success): ?>
                                        <div class="alert alert-success d-flex align-items-center gap-2" role="alert">
                                            <i class="bi bi-check-circle-fill"></i>
                                            <div><?php echo htmlspecialchars($success); ?></div>
                                        </div>
                                    <?php endif; ?>

                                    <form method="POST" action="">
                                        <!-- Stagiaire field -->
                                        <div class="mb-3" id="stagiaireFields">
                                            <label class="form-label small muted">Nom complet</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="bi bi-person"></i></span>
                                                <input
                                                    type="text"
                                                    name="nom_complet"
                                                    class="form-control"
                                                    placeholder="Votre nom complet"
                                                    value="<?php echo htmlspecialchars($_POST['nom_complet'] ?? ''); ?>"
                                                >
                                            </div>
                                        </div>

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
                                                    placeholder="Au moins 8 caractères"
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

                                        <div class="mb-3">
                                            <label class="form-label small muted">Rôle</label>
                                            <select name="role" class="form-select" required id="roleSelect">
                                                <option value="">Sélectionnez un rôle</option>
                                                <option value="stagiaire" <?php echo (($_POST['role'] ?? '') === 'stagiaire') ? 'selected' : ''; ?>>Stagiaire</option>
                                                <option value="entreprise" <?php echo (($_POST['role'] ?? '') === 'entreprise') ? 'selected' : ''; ?>>Entreprise</option>
                                            </select>
                                        </div>

                                        <!-- Entreprise fields -->
                                        <div id="entrepriseFields" style="display:none;">
                                            <div class="mb-3">
                                                <label class="form-label small muted">Nom de l’entreprise</label>
                                                <div class="input-group">
                                                    <span class="input-group-text"><i class="bi bi-building"></i></span>
                                                    <input
                                                        type="text"
                                                        name="nom_entreprise"
                                                        class="form-control"
                                                        placeholder="Nom de votre entreprise"
                                                        value="<?php echo htmlspecialchars($_POST['nom_entreprise'] ?? ''); ?>"
                                                    >
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label small muted">Secteur d’activité</label>
                                                <div class="input-group">
                                                    <span class="input-group-text"><i class="bi bi-briefcase"></i></span>
                                                    <input
                                                        type="text"
                                                        name="secteur_activite"
                                                        class="form-control"
                                                        placeholder="Ex: Informatique, Finance..."
                                                        value="<?php echo htmlspecialchars($_POST['secteur_activite'] ?? ''); ?>"
                                                    >
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label small muted">Ville</label>
                                                <div class="input-group">
                                                    <span class="input-group-text"><i class="bi bi-geo-alt"></i></span>
                                                    <input
                                                        type="text"
                                                        name="ville"
                                                        class="form-control"
                                                        placeholder="Ex: Casablanca"
                                                        value="<?php echo htmlspecialchars($_POST['ville'] ?? ''); ?>"
                                                    >
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label small muted">Description</label>
                                                <textarea
                                                    name="description"
                                                    class="form-control"
                                                    rows="3"
                                                    placeholder="Décrivez votre entreprise..."
                                                ><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                                            </div>
                                        </div>

                                        <button type="submit" class="btn btn-cool w-100">
                                            <i class="bi bi-person-plus me-1"></i> Créer un compte
                                        </button>

                                        <div class="text-center mt-3 small">
                                            <span class="muted">Déjà inscrit ?</span>
                                            <a class="link" href="/stage_platform/authentification/login.php">Se connecter</a>
                                        </div>
                                    </form>
                                </div>

                                <div class="text-center small muted mt-3">
                                    Astuce: utilisez un mot de passe unique.
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

    <!-- Role switch JS -->
    <script>
    document.addEventListener("DOMContentLoaded", function () {
        const roleSelect = document.getElementById("roleSelect");
        const entrepriseFields = document.getElementById("entrepriseFields");
        const stagiaireFields = document.getElementById("stagiaireFields");

        const fullNameInput = document.querySelector('input[name="nom_complet"]');
        const nomEntreprise = document.querySelector('input[name="nom_entreprise"]');
        const secteurActivite = document.querySelector('input[name="secteur_activite"]');
        const ville = document.querySelector('input[name="ville"]');
        const description = document.querySelector('textarea[name="description"]');

        function setMode(role) {
            const isEntreprise = (role === "entreprise");

            // show/hide blocks
            entrepriseFields.style.display = isEntreprise ? "block" : "none";
            stagiaireFields.style.display = isEntreprise ? "block" : "block"; // keep UI same

            // required rules
            if (isEntreprise) {
                if (nomEntreprise) nomEntreprise.required = true;
                if (secteurActivite) secteurActivite.required = true;
                if (ville) ville.required = true;
                if (description) description.required = true;

                if (fullNameInput) fullNameInput.required = false;
                stagiaireFields.style.display = "none";
            } else {
                if (nomEntreprise) nomEntreprise.required = false;
                if (secteurActivite) secteurActivite.required = false;
                if (ville) ville.required = false;
                if (description) description.required = false;

                if (fullNameInput) fullNameInput.required = true;
                stagiaireFields.style.display = "block";
            }
        }

        setMode(roleSelect.value);

        roleSelect.addEventListener("change", function () {
            setMode(this.value);
        });
    });
    </script>
</body>
</html>
