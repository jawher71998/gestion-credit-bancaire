<?php
require_once __DIR__ . '/includes/auth.php';

if (estConnecte()) {
    header('Location: ' . urlTableauDeBord(roleActuel()));
    exit;
}

$erreur = '';
$succes = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifierTokenCSRF($_POST['csrf_token'] ?? null)) {
        $erreur = 'Session expirée, veuillez réessayer.';
    } elseif ($_POST['mot_de_passe'] !== $_POST['confirmation']) {
        $erreur = 'Les mots de passe ne correspondent pas.';
    } elseif (strlen($_POST['mot_de_passe']) < 8) {
        $erreur = 'Le mot de passe doit contenir au moins 8 caractères.';
    } else {
        $resultat = enregistrerClient($pdo, [
            'nom' => trim($_POST['nom']),
            'prenom' => trim($_POST['prenom']),
            'email' => trim($_POST['email']),
            'telephone' => trim($_POST['telephone']),
            'mot_de_passe' => $_POST['mot_de_passe'],
            'cin' => trim($_POST['cin']),
            'date_naissance' => $_POST['date_naissance'],
            'adresse' => trim($_POST['adresse']),
            'profession' => trim($_POST['profession']),
            'revenu_mensuel' => $_POST['revenu_mensuel'],
            'situation_familiale' => $_POST['situation_familiale'],
        ]);

        if ($resultat['success']) {
            header('Location: login.php?inscription=ok');
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
<title>Créer un compte — Financement</title>
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
      <a href="login.php">Déjà client ? Se connecter</a>
    </nav>
  </div>
</header>

<main class="auth-page">
  <div class="wrap auth-wrap">
    <div class="auth-card auth-card--wide">
      <h1>Créer votre espace client</h1>
      <p class="auth-lede">Renseignez vos informations pour déposer votre première demande de financement.</p>

      <?php if ($erreur): ?>
        <div class="alert alert-error"><?= htmlspecialchars($erreur) ?></div>
      <?php endif; ?>

      <form method="post" class="form-grid">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

        <div class="field">
          <label for="nom">Nom</label>
          <input type="text" id="nom" name="nom" required>
        </div>
        <div class="field">
          <label for="prenom">Prénom</label>
          <input type="text" id="prenom" name="prenom" required>
        </div>

        <div class="field">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" required>
        </div>
        <div class="field">
          <label for="telephone">Téléphone</label>
          <input type="tel" id="telephone" name="telephone" required>
        </div>

        <div class="field">
          <label for="cin">Numéro CIN</label>
          <input type="text" id="cin" name="cin" required>
        </div>
        <div class="field">
          <label for="date_naissance">Date de naissance</label>
          <input type="date" id="date_naissance" name="date_naissance" required>
        </div>

        <div class="field field--full">
          <label for="adresse">Adresse</label>
          <input type="text" id="adresse" name="adresse" required>
        </div>

        <div class="field">
          <label for="profession">Profession</label>
          <input type="text" id="profession" name="profession" required>
        </div>
        <div class="field">
          <label for="revenu_mensuel">Revenu mensuel net (DT)</label>
          <input type="number" id="revenu_mensuel" name="revenu_mensuel" min="0" step="1" required>
        </div>

        <div class="field field--full">
          <label for="situation_familiale">Situation familiale</label>
          <select id="situation_familiale" name="situation_familiale" required>
            <option value="celibataire">Célibataire</option>
            <option value="marie">Marié(e)</option>
            <option value="divorce">Divorcé(e)</option>
            <option value="veuf">Veuf/Veuve</option>
          </select>
        </div>

        <div class="field">
          <label for="mot_de_passe">Mot de passe</label>
          <div class="password-field">
            <input type="password" id="mot_de_passe" name="mot_de_passe" minlength="8" required>
            <button type="button" class="toggle-password" data-target="mot_de_passe" aria-label="Afficher le mot de passe">👁</button>
          </div>
        </div>
        <div class="field">
          <label for="confirmation">Confirmer le mot de passe</label>
          <div class="password-field">
            <input type="password" id="confirmation" name="confirmation" minlength="8" required>
            <button type="button" class="toggle-password" data-target="confirmation" aria-label="Afficher le mot de passe">👁</button>
          </div>
        </div>

        <button type="submit" class="btn btn-primary btn-block field--full">Créer mon compte</button>
      </form>
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
