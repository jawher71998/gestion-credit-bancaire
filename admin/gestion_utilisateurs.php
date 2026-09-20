<?php
require_once __DIR__ . '/../includes/auth.php';
exigerRole(['admin']);

$erreur = '';
$succes = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifierTokenCSRF($_POST['csrf_token'] ?? null)) {
        $erreur = 'Session expirée, veuillez réessayer.';
    } elseif (isset($_POST['reinitialiser_id'])) {
        $idCible = (int) $_POST['reinitialiser_id'];
        $nouveauMotDePasse = 'Test@2026';
        $hash = password_hash($nouveauMotDePasse, PASSWORD_DEFAULT);
        $pdo->prepare('UPDATE utilisateurs SET mot_de_passe = ? WHERE id_utilisateur = ?')->execute([$hash, $idCible]);
        $succes = "Mot de passe réinitialisé à : $nouveauMotDePasse";
    } else {
        $resultat = creerUtilisateurInterne($pdo, [
            'nom' => trim($_POST['nom']),
            'prenom' => trim($_POST['prenom']),
            'email' => trim($_POST['email']),
            'telephone' => trim($_POST['telephone']),
            'mot_de_passe' => $_POST['mot_de_passe'],
            'role' => $_POST['role'],
            'agence' => trim($_POST['agence']),
        ]);

        if ($resultat['success']) {
            $succes = $resultat['message'];
        } else {
            $erreur = $resultat['message'];
        }
    }
}

$utilisateurs = $pdo->query('
    SELECT id_utilisateur, nom, prenom, email, role, agence, statut, date_creation
    FROM utilisateurs
    WHERE role != "client"
    ORDER BY date_creation DESC
')->fetchAll();

$csrfToken = genererTokenCSRF();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gestion des utilisateurs — Administration</title>
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

<main class="admin-page">
  <div class="wrap">
    <h1>Gestion des utilisateurs internes</h1>
    <p class="hero-note">Les clients s'inscrivent eux-mêmes. Créez ici les comptes agents, chefs d'agence et administrateurs.</p>

    <?php if ($succes): ?><div class="alert alert-success"><?= htmlspecialchars($succes) ?></div><?php endif; ?>
    <?php if (!empty($_GET['modifie'])): ?><div class="alert alert-success">Utilisateur modifié avec succès.</div><?php endif; ?>
    <?php if ($erreur): ?><div class="alert alert-error"><?= htmlspecialchars($erreur) ?></div><?php endif; ?>

    <div class="admin-layout">
      <div class="auth-card">
        <h2>Nouveau compte interne</h2>
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

          <div class="field field--full">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required>
          </div>

          <div class="field">
            <label for="telephone">Téléphone</label>
            <input type="tel" id="telephone" name="telephone" required>
          </div>
          <div class="field">
            <label for="role">Rôle</label>
            <select id="role" name="role" required>
              <option value="agent">Agent</option>
              <option value="chef_agence">Chef d'agence</option>
              <option value="admin">Administrateur</option>
            </select>
          </div>

          <div class="field field--full">
            <label for="agence">Agence de rattachement</label>
            <input type="text" id="agence" name="agence" placeholder="ex: Agence Zitouna Tunis Centre" required>
          </div>

          <div class="field field--full">
            <label for="mot_de_passe">Mot de passe temporaire</label>
            <div class="password-field">
              <input type="password" id="mot_de_passe" name="mot_de_passe" minlength="8" required>
              <button type="button" class="toggle-password" data-target="mot_de_passe" aria-label="Afficher le mot de passe">👁</button>
            </div>
          </div>

          <button type="submit" class="btn btn-primary btn-block field--full">Créer le compte</button>
        </form>
      </div>

      <div class="admin-table-wrap">
        <h2>Comptes internes existants</h2>
        <table class="admin-table">
          <thead>
            <tr>
              <th>Nom</th>
              <th>Email</th>
              <th>Rôle</th>
              <th>Agence</th>
              <th>Statut</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($utilisateurs as $u): ?>
            <tr>
              <td><?= htmlspecialchars($u['prenom'] . ' ' . $u['nom']) ?></td>
              <td><?= htmlspecialchars($u['email']) ?></td>
              <td><span class="badge badge--<?= htmlspecialchars($u['role']) ?>"><?= htmlspecialchars($u['role']) ?></span></td>
              <td><?= htmlspecialchars($u['agence'] ?? '—') ?></td>
              <td><?= htmlspecialchars($u['statut']) ?></td>
              <td>
                <div style="display:flex; gap:8px;">
                  <a href="modifier_utilisateur.php?id=<?= $u['id_utilisateur'] ?>" class="btn btn-outline btn-small">Modifier</a>
                  <form method="post" onsubmit="return confirm('Réinitialiser le mot de passe de <?= htmlspecialchars($u['email']) ?> à Test@2026 ?');">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="reinitialiser_id" value="<?= $u['id_utilisateur'] ?>">
                    <button type="submit" class="btn btn-ghost btn-small">Réinitialiser mdp</button>
                  </form>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($utilisateurs)): ?>
            <tr><td colspan="6">Aucun compte interne pour le moment.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</main>

<script src="../assets/js/password-toggle.js"></script>
<footer class="site-footer-mini">
  <div class="wrap">
    <p>© 2026 Banque Zitouna — Projet de Fin d'Études, BTS Informatique de Gestion</p>
  </div>
</footer>
</body>
</html>
