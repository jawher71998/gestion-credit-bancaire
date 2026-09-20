<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/credit.php';
require_once __DIR__ . '/includes/notifications_widget.php';
exigerRole(['client']);

$stmt = $pdo->prepare('
    SELECT dc.*, tc.nom AS nom_type
    FROM demandes_credit dc
    JOIN types_credit tc ON tc.id_type_credit = dc.id_type_credit
    WHERE dc.id_client = ?
    ORDER BY dc.date_demande DESC
');
$stmt->execute([$_SESSION['id_utilisateur']]);
$demandes = $stmt->fetchAll();

$referenceConfirmee = $_GET['demande'] ?? null;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mes demandes — Financement</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<header class="site-header">
  <div class="wrap header-inner">
    <a href="suivi.php" class="brand">
      <img src="assets/img/logo-zitouna.png" alt="Banque Zitouna" class="brand-logo">
      <span class="brand-name">Financement<span class="brand-dot">.</span></span>
    </a>
    <nav class="main-nav">
      <a href="suivi.php">Mes demandes</a>
      <a href="demande.php">Nouvelle demande</a>
    </nav>
    <div style="display:flex; align-items:center; gap:16px;">
      <?php afficherClocheNotifications($pdo, $_SESSION['id_utilisateur']); ?>
      <a href="logout.php" class="btn btn-ghost btn-small">Déconnexion</a>
    </div>
  </div>
</header>

<main class="admin-page">
  <div class="wrap">
    <h1>Bonjour, <?= htmlspecialchars($_SESSION['prenom']) ?> 👋</h1>

    <?php if ($referenceConfirmee): ?>
      <div class="alert alert-success">
        Votre demande <strong><?= htmlspecialchars($referenceConfirmee) ?></strong> a bien été soumise. Un conseiller va l'étudier sous peu.
      </div>
    <?php endif; ?>

    <?php if (!empty($_GET['annulee'])): ?>
      <div class="alert alert-success">Votre demande a bien été annulée.</div>
    <?php endif; ?>

    <div class="admin-table-wrap" style="margin-top:24px;">
      <div style="display:flex; justify-content:space-between; align-items:center;">
        <h2>Mes demandes de crédit</h2>
        <a href="demande.php" class="btn btn-primary btn-small">+ Nouvelle demande</a>
      </div>

      <table class="admin-table">
        <thead>
          <tr>
            <th>Référence</th>
            <th>Type</th>
            <th>Montant</th>
            <th>Durée</th>
            <th>Mensualité</th>
            <th>Statut</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($demandes as $d): ?>
          <tr>
            <td><a href="demande_detail.php?id=<?= $d['id_demande'] ?>"><?= htmlspecialchars($d['reference']) ?></a></td>
            <td><?= htmlspecialchars($d['nom_type']) ?></td>
            <td><?= number_format($d['montant_demande'], 0, ',', ' ') ?> DT</td>
            <td><?= (int)$d['duree_mois'] ?> mois</td>
            <td><?= number_format($d['mensualite_estimee'], 0, ',', ' ') ?> DT</td>
            <td><span class="badge badge--<?= htmlspecialchars($d['statut']) ?>"><?= htmlspecialchars(libelleStatut($d['statut'])) ?></span></td>
            <td><?= (new DateTime($d['date_demande']))->format('d/m/Y') ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($demandes)): ?>
          <tr><td colspan="7">Vous n'avez pas encore de demande de crédit.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
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
