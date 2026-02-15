<?php
// authentification/register.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Load BASE_URL (optional)
$BASE_URL = $BASE_URL ?? null;
foreach ([__DIR__ . '/../config/paths.php', __DIR__ . '/../paths.php', __DIR__ . '/../config.php'] as $p) {
    if (!$BASE_URL && file_exists($p)) { require_once $p; }
}
if ($BASE_URL && preg_match('~^https?://~i', $BASE_URL)) {
    // Normalize FULL URL to PATH only
    $parts = parse_url($BASE_URL);
    $BASE_URL = $parts['path'] ?? '';
}
$BASE_URL = rtrim((string)$BASE_URL, '/');
if (!$BASE_URL) { $BASE_URL = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/'); }

// Load DB connection (expects $pdo as in your connection.php)
foreach ([__DIR__ . '/../config/connection.php', __DIR__ . '/../connection.php'] as $p) {
    if (file_exists($p)) { require_once $p; break; }
}
if (!isset($pdo) || !($pdo instanceof PDO)) { die("Database connection failed"); }

function redirect(string $path): void {
    global $BASE_URL;
    $path = ltrim($path, '/');
    header("Location: " . rtrim($BASE_URL, '/') . "/" . $path);
    exit;
}
function isLoggedIn(): bool {
    return isset($_SESSION['id_utilisateur']) && (int)$_SESSION['id_utilisateur'] > 0;
}

function redirectByRole(string $role): void
{
    $role = strtolower(trim($role));

    if ($role === 'stagiaire') {
        redirect('stagiaire/dashboard.php');
    } elseif ($role === 'entreprise') {
        redirect('entreprise/dashboard.php');
    } elseif ($role === 'admin') {
        redirect('admin/dashboard.php');
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
    $fullName = trim($_POST['nom'] ?? $_POST['nom_complet'] ?? $_POST['full_name'] ?? '');
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

                    // Insert into utilisateur (schema stage_platform.sql expects `nom`, `email`, `mot_de_passe`, `role`)
                    $hash = password_hash($pass, PASSWORD_BCRYPT);

                    // Name to store depends on role: entreprise => nom_entreprise, otherwise full name
                    $nameToStore = ($role === 'entreprise') ? $nomEntreprise : $fullName;
                    if ($nameToStore === '') {
                        throw new Exception("Nom obligatoire");
                    }

                    $insertStmt = $pdo->prepare(
                        "INSERT INTO utilisateur (nom, email, mot_de_passe, role, date_inscription) VALUES (?, ?, ?, ?, CURDATE())"
                    );
                    $insertStmt->execute([$nameToStore, $email, $hash, $role]);

                    $newUserId = (int) $pdo->lastInsertId();

                    // ✅ Insert into stagiaire (FIXED):
// Your DB design stores identity/login in `utilisateur`.
// The `stagiaire` table should usually only reference `id_utilisateur` + profile fields (niveau_etude, filiere, ville, cv...).
if ($role === 'stagiaire') {
    // Detect available columns to avoid SQL errors if schema differs
    $cols = ["id_utilisateur"];
    $vals = [$newUserId];

    // Common optional columns (based on your screenshots)
    if (tableHasColumn($pdo, "stagiaire", "niveau_etude")) { $cols[] = "niveau_etude"; $vals[] = ""; }
    if (tableHasColumn($pdo, "stagiaire", "filiere"))      { $cols[] = "filiere";      $vals[] = ""; }
    if (tableHasColumn($pdo, "stagiaire", "ville"))        { $cols[] = "ville";        $vals[] = ""; }
    if (tableHasColumn($pdo, "stagiaire", "cv"))           { $cols[] = "cv";           $vals[] = ""; }
    if (tableHasColumn($pdo, "stagiaire", "date_inscription")) { $cols[] = "date_inscription"; $vals[] = date("Y-m-d"); }

    $placeholders = implode(", ", array_fill(0, count($cols), "?"));
    $sqlSt = "INSERT INTO stagiaire (" . implode(", ", $cols) . ") VALUES (" . $placeholders . ")";
    $stagiaireStmt = $pdo->prepare($sqlSt);
    $stagiaireStmt->execute($vals);
}// Insert into entreprise table
                    if ($role === 'entreprise') {
                        $entrepriseStmt = $pdo->prepare(
                            "INSERT INTO entreprise (id_utilisateur, nom_entreprise, secteur_activite, ville, description)
                             VALUES (?, ?, ?, ?, ?)"
                        );
                        $entrepriseStmt->execute([$newUserId, $nomEntreprise, $secteurActivite, $ville, $description]);
                    }

                    $pdo->commit();

                    $success = "Compte créé avec succès.";
                    header("Location: $BASE_URL/authentification/login.php");
                    exit;
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
