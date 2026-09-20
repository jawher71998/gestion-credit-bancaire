<?php
require_once __DIR__ . '/../includes/auth.php';
exigerRole(['admin']);

$idUtilisateur = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM utilisateurs WHERE id_utilisateur = ? AND role != "client"');
$stmt->execute([$idUtilisateur]);
$utilisateur = $stmt->fetch();

if (!$utilisateur) {
    header('Location: gestion_utilisateurs.php');
    exit;
}

$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifierTokenCSRF($_POST['csrf_token'] ?? null)) {
        $erreur = 'Session expirée, veuillez réessayer.';
    } else {
        $resultat = modifierUtilisateurInterne($pdo, $idUtilisateur, [
            'nom' => trim($_POST['nom']),
            'prenom' => trim($_POST['prenom']),
            'email' => trim($_POST['email']),
            'telephone' => trim($_POST['telephone']),
            'role' => $_POST['role'],
            'agence' => trim($_POST['agence']),
            'statut' => $_POST['statut'],
        ]);

        if ($resultat['success']) {
            header('Location: gestion_utilisateurs.php?modifie=1');
            exit;
        }
        $erreur = $resultat['message'];
    }
}

$csrfToken = genererTokenCSRF();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Modifier un utilisateur — Administration</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<header class="site-header">
  <div class="wrap header-inner">
    <a href="dashboard.php" class="brand">
      <img src="../assets/img/logo-zitouna.png" alt="Banque Zitouna" class="brand-logo">
      <span class="brand-name">Financement<span class="brand-dot">.</span> <small>Admin</small></span>
    </a>
    <nav class="main-nav">
      <a href="dashboard.php">Tableau de bord</a>
      <a href="gestion_utilisateurs.php">Utilisateurs</a>
    </nav>
    <a href="../logout.php" class="btn btn-ghost btn-small">Déconnexion</a>
  </div>
</header>

<main class="auth-page">
  <div class="wrap auth-wrap">
    <div class="auth-card">
      <h1>Modifier l'utilisateur</h1>
      <p class="auth-lede"><?= htmlspecialchars($utilisateur['prenom'] . ' ' . $utilisateur['nom']) ?></p>

      <?php if ($erreur): ?>
        <div class="alert alert-error"><?= htmlspecialchars($erreur) ?></div>
      <?php endif; ?>

      <form method="post" class="form-grid">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

        <div class="field">
          <label for="nom">Nom</label>
          <input type="text" id="nom" name="nom" value="<?= htmlspecialchars($utilisateur['nom']) ?>" required>
        </div>
        <div class="field">
          <label for="prenom">Prénom</label>
          <input type="text" id="prenom" name="prenom" value="<?= htmlspecialchars($utilisateur['prenom']) ?>" required>
        </div>

        <div class="field field--full">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" value="<?= htmlspecialchars($utilisateur['email']) ?>" required>
        </div>

        <div class="field">
          <label for="telephone">Téléphone</label>
          <input type="tel" id="telephone" name="telephone" value="<?= htmlspecialchars($utilisateur['telephone'] ?? '') ?>" required>
        </div>
        <div class="field">
          <label for="role">Rôle</label>
          <select id="role" name="role" required>
            <option value="agent" <?= $utilisateur['role'] === 'agent' ? 'selected' : '' ?>>Agent</option>
            <option value="chef_agence" <?= $utilisateur['role'] === 'chef_agence' ? 'selected' : '' ?>>Chef d'agence</option>
            <option value="admin" <?= $utilisateur['role'] === 'admin' ? 'selected' : '' ?>>Administrateur</option>
          </select>
        </div>

        <div class="field">
          <label for="agence">Agence de rattachement</label>
          <input type="text" id="agence" name="agence" value="<?= htmlspecialchars($utilisateur['agence'] ?? '') ?>">
        </div>
        <div class="field">
          <label for="statut">Statut du compte</label>
          <select id="statut" name="statut" required>
            <option value="actif" <?= $utilisateur['statut'] === 'actif' ? 'selected' : '' ?>>Actif</option>
            <option value="inactif" <?= $utilisateur['statut'] === 'inactif' ? 'selected' : '' ?>>Inactif</option>
            <option value="suspendu" <?= $utilisateur['statut'] === 'suspendu' ? 'selected' : '' ?>>Suspendu</option>
          </select>
        </div>

        <button type="submit" class="btn btn-primary btn-block field--full">Enregistrer les modifications</button>
        <a href="gestion_utilisateurs.php" class="btn btn-outline btn-block field--full">Annuler</a>
      </form>
    </div>
  </div>
</main>

<footer class="site-footer-mini">
  <div class="wrap">
    <p>© 2026 Banque Zitouna — Projet de Fin d'Études, BTS Informatique de Gestion</p>
  </div>
</footer>
</body>
</html>
