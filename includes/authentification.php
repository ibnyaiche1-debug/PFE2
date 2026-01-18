<?php
// includes/authentification.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn(): bool
{
    return isset($_SESSION['id_utilisateur'], $_SESSION['role']);
}

function redirect(string $path): void
{
    header("Location: $path");
    exit;
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        redirect('/stage_platform/authentification/login.php');
    }
}

/**
 * Roles (NEW DB): stagiaire | entreprise | admin
 */
function requireRole(string $role): void
{
    requireLogin();

    $sessionRole = strtolower(trim($_SESSION['role'] ?? ''));
    $wantedRole  = strtolower(trim($role));

    if ($sessionRole !== $wantedRole) {
        http_response_code(403);
        die("Accès refusé.");
    }
}
