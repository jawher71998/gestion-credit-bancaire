<?php
require_once __DIR__ . '/includes/auth.php';

if (estConnecte()) {
    header('Location: ' . urlTableauDeBord(roleActuel()));
    exit;
}

$erreur = '';
$inscriptionOk = isset($_GET['inscription']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifierTokenCSRF($_POST['csrf_token'] ?? null)) {
        $erreur = 'Session expirée, veuillez réessayer.';
    } else {
        $utilisateur = connecterUtilisateur($pdo, trim($_POST['email']), $_POST['mot_de_passe']);
        if ($utilisateur) {
            header('Location: ' . urlTableauDeBord($utilisateur['role']));
            exit;
        }
        $erreur = 'Email ou mot de passe incorrect.';
    }
}

$csrfToken = genererTokenCSRF();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Connexion — Financement</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<header class="site-header">
  <div class="wrap header-inner">
    <a href="index.html" class="brand">
      <img src="assets/img/logo-zitouna.png" alt="Banque Zitouna" class="brand-logo">
      <span class="brand-name">Financement<span class="brand-dot">.</span></span>
    </a>
    <nav class="main-nav">
      <a href="inscription.php">Créer un compte client</a>
    </nav>
  </div>
</header>

<main class="auth-page">
  <div class="wrap auth-wrap">
    <div class="auth-card">
      <h1>Se connecter</h1>
      <p class="auth-lede">Accédez à votre espace client, agent ou administrateur.</p>

      <?php if ($inscriptionOk): ?>
        <div class="alert alert-success">Compte créé. Vous pouvez maintenant vous connecter.</div>
      <?php endif; ?>

      <?php if ($erreur): ?>
        <div class="alert alert-error"><?= htmlspecialchars($erreur) ?></div>
      <?php endif; ?>

      <form method="post" class="form-grid">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

        <div class="field field--full">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" required autofocus>
        </div>

        <div class="field field--full">
          <label for="mot_de_passe">Mot de passe</label>
          <div class="password-field">
            <input type="password" id="mot_de_passe" name="mot_de_passe" required>
            <button type="button" class="toggle-password" data-target="mot_de_passe" aria-label="Afficher le mot de passe">👁</button>
          </div>
        </div>

        <button type="submit" class="btn btn-primary btn-block field--full">Se connecter</button>
      </form>

      <p class="auth-footnote">Pas encore de compte ? <a href="inscription.php">Inscrivez-vous</a></p>
    </div>
  </div>
</main>

<script src="assets/js/password-toggle.js"></script>
<footer class="site-footer-mini">
  <div class="wrap">
    <p>© 2026 Banque Zitouna — Projet de Fin d'Études, BTS Informatique de Gestion</p>
  </div>
</footer>
</body>
</html>
