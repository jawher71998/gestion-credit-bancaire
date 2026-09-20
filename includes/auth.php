<?php
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Inscription d'un client (seul rôle qui peut s'inscrire librement).
 * Retourne ['success' => bool, 'message' => string]
 */
function enregistrerClient(PDO $pdo, array $data): array
{
    // Vérifie que l'email n'est pas déjà utilisé
    $stmt = $pdo->prepare('SELECT id_utilisateur FROM utilisateurs WHERE email = ?');
    $stmt->execute([$data['email']]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'Cet email est déjà utilisé.'];
    }

    // Vérifie que le CIN n'est pas déjà utilisé
    $stmt = $pdo->prepare('SELECT id_utilisateur FROM utilisateurs WHERE cin = ?');
    $stmt->execute([$data['cin']]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'Ce numéro de CIN est déjà enregistré.'];
    }

    $motDePasseHache = password_hash($data['mot_de_passe'], PASSWORD_DEFAULT);

    $stmt = $pdo->prepare('
        INSERT INTO utilisateurs
            (nom, prenom, email, telephone, mot_de_passe, role,
             cin, date_naissance, adresse, profession, revenu_mensuel, situation_familiale)
        VALUES
            (?, ?, ?, ?, ?, "client", ?, ?, ?, ?, ?, ?)
    ');

    $stmt->execute([
        $data['nom'],
        $data['prenom'],
        $data['email'],
        $data['telephone'],
        $motDePasseHache,
        $data['cin'],
        $data['date_naissance'],
        $data['adresse'],
        $data['profession'],
        $data['revenu_mensuel'],
        $data['situation_familiale'],
    ]);

    return ['success' => true, 'message' => 'Compte créé avec succès.'];
}

/**
 * Création d'un compte agent / chef_agence / admin par un administrateur.
 */
function creerUtilisateurInterne(PDO $pdo, array $data): array
{
    $stmt = $pdo->prepare('SELECT id_utilisateur FROM utilisateurs WHERE email = ?');
    $stmt->execute([$data['email']]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'Cet email est déjà utilisé.'];
    }

    $motDePasseHache = password_hash($data['mot_de_passe'], PASSWORD_DEFAULT);

    $stmt = $pdo->prepare('
        INSERT INTO utilisateurs (nom, prenom, email, telephone, mot_de_passe, role, agence)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $data['nom'],
        $data['prenom'],
        $data['email'],
        $data['telephone'],
        $motDePasseHache,
        $data['role'],
        $data['agence'],
    ]);

    return ['success' => true, 'message' => 'Utilisateur créé avec succès.'];
}

/**
 * Modification d'un compte agent / chef_agence / admin existant par l'administrateur.
 */
function modifierUtilisateurInterne(PDO $pdo, int $idUtilisateur, array $data): array
{
    $stmt = $pdo->prepare('SELECT id_utilisateur FROM utilisateurs WHERE email = ? AND id_utilisateur != ?');
    $stmt->execute([$data['email'], $idUtilisateur]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'Cet email est déjà utilisé par un autre compte.'];
    }

    $stmt = $pdo->prepare('
        UPDATE utilisateurs
        SET nom = ?, prenom = ?, email = ?, telephone = ?, role = ?, agence = ?, statut = ?
        WHERE id_utilisateur = ?
    ');
    $stmt->execute([
        $data['nom'],
        $data['prenom'],
        $data['email'],
        $data['telephone'],
        $data['role'],
        $data['agence'],
        $data['statut'],
        $idUtilisateur,
    ]);

    return ['success' => true, 'message' => 'Utilisateur modifié avec succès.'];
}

/**
 * Tentative de connexion. Retourne l'utilisateur (sans mot de passe) ou null.
 */
function connecterUtilisateur(PDO $pdo, string $email, string $motDePasse): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM utilisateurs WHERE email = ? AND statut = "actif"');
    $stmt->execute([$email]);
    $utilisateur = $stmt->fetch();

    if (!$utilisateur || !password_verify($motDePasse, $utilisateur['mot_de_passe'])) {
        return null;
    }

    $_SESSION['id_utilisateur'] = $utilisateur['id_utilisateur'];
    $_SESSION['role'] = $utilisateur['role'];
    $_SESSION['nom'] = $utilisateur['nom'];
    $_SESSION['prenom'] = $utilisateur['prenom'];

    $update = $pdo->prepare('UPDATE utilisateurs SET derniere_connexion = NOW() WHERE id_utilisateur = ?');
    $update->execute([$utilisateur['id_utilisateur']]);

    unset($utilisateur['mot_de_passe']);
    return $utilisateur;
}

function deconnecterUtilisateur(): void
{
    $_SESSION = [];
    session_destroy();
}

function estConnecte(): bool
{
    return isset($_SESSION['id_utilisateur']);
}

function roleActuel(): ?string
{
    return $_SESSION['role'] ?? null;
}

/**
 * Bloque l'accès à la page si l'utilisateur n'est pas connecté,
 * ou n'a pas l'un des rôles autorisés. Redirige vers login.php sinon.
 */
function exigerRole(array $rolesAutorises): void
{
    if (!estConnecte() || !in_array(roleActuel(), $rolesAutorises, true)) {
        header('Location: /site-credit/login.php');
        exit;
    }
}

/** Retourne l'URL du tableau de bord adapté au rôle, après connexion. */
function urlTableauDeBord(string $role): string
{
    return match ($role) {
        'admin' => '/site-credit/admin/dashboard.php',
        'agent', 'chef_agence' => '/site-credit/agent/liste_demandes.php',
        default => '/site-credit/suivi.php',
    };
}

function genererTokenCSRF(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifierTokenCSRF(?string $token): bool
{
    return !empty($token) && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}
