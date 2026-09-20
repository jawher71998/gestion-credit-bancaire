<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/credit.php';
exigerRole(['agent', 'chef_agence']);

$role = roleActuel();
$statutCible = $role === 'chef_agence' ? 'en_etude_chef' : 'soumise';

$stmt = $pdo->prepare("
    SELECT dc.*, tc.nom AS nom_type, u.nom AS client_nom, u.prenom AS client_prenom
    FROM demandes_credit dc
    JOIN types_credit tc ON tc.id_type_credit = dc.id_type_credit
    JOIN utilisateurs u ON u.id_utilisateur = dc.id_client
    WHERE dc.statut = ?
    ORDER BY dc.date_demande ASC
");
$stmt->execute([$statutCible]);
$demandes = $stmt->fetchAll();

$stmtRecentes = $pdo->prepare("
    SELECT dc.reference, dc.statut, dc.montant_demande, wv.decision, wv.date_decision
    FROM workflow_validations wv
    JOIN demandes_credit dc ON dc.id_demande = wv.id_demande
    WHERE wv.id_validateur = ?
    ORDER BY wv.date_decision DESC
    LIMIT 5
");
$stmtRecentes->execute([$_SESSION['id_utilisateur']]);
$decisionsRecentes = $stmtRecentes->fetchAll();

$stmtStats = $pdo->prepare("
    SELECT
        COUNT(*) AS total_decisions,
        SUM(CASE WHEN decision = 'approuve' THEN 1 ELSE 0 END) AS total_approuvees
    FROM workflow_validations
    WHERE id_validateur = ?
");
$stmtStats->execute([$_SESSION['id_utilisateur']]);
$statsPerso = $stmtStats->fetch();
$tauxApprobationPerso = $statsPerso['total_decisions'] > 0
    ? round($statsPerso['total_approuvees'] / $statsPerso['total_decisions'] * 100, 1)
    : 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Demandes à traiter — Espace agent</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<header class="site-header">
  <div class="wrap header-inner">
    <a href="liste_demandes.php" class="brand">
      <img src="../assets/img/logo-zitouna.png" alt="Banque Zitouna" class="brand-logo">
      <span class="brand-name">Financement<span class="brand-dot">.</span>
        <small><?= $role === 'chef_agence' ? "Chef d'agence" : 'Agent' ?></small>
      </span>
    </a>
    <nav class="main-nav">
      <a href="liste_demandes.php">Demandes à traiter</a>
    </nav>
    <a href="../logout.php" class="btn btn-ghost btn-small">Déconnexion</a>
  </div>
</header>

<main class="admin-page">
  <div class="wrap">
    <h1>Bonjour, <?= htmlspecialchars($_SESSION['prenom']) ?> 👋</h1>

    <?php if (!empty($_GET['traite'])): ?>
      <div class="alert alert-success">Dossier <strong><?= htmlspecialchars($_GET['traite']) ?></strong> traité avec succès.</div>
    <?php endif; ?>

    <p class="hero-note">
      <?= $role === 'chef_agence'
        ? "Ces demandes dépassent 20 000 DT et nécessitent votre validation finale."
        : "Ces demandes viennent d'être soumises et attendent votre première étude." ?>
    </p>

    <div class="stats-grid stats-grid--compact">
      <div class="stat-card">
        <span class="stat-value"><?= count($demandes) ?></span>
        <span class="stat-label">Dossiers à traiter</span>
      </div>
      <div class="stat-card">
        <span class="stat-value"><?= (int) $statsPerso['total_decisions'] ?></span>
        <span class="stat-label">Décisions prises</span>
      </div>
      <div class="stat-card">
        <span class="stat-value"><?= $tauxApprobationPerso ?>%</span>
        <span class="stat-label">Taux d'approbation personnel</span>
      </div>
    </div>

    <div class="admin-table-wrap" style="margin-top:24px;">
      <h2>À traiter (<?= count($demandes) ?>)</h2>
      <table class="admin-table">
        <thead>
          <tr>
            <th>Référence</th>
            <th>Client</th>
            <th>Type</th>
            <th>Montant</th>
            <th>Mensualité</th>
            <th>Taux d'endettement</th>
            <th>Date</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($demandes as $d): ?>
          <tr>
            <td><?= htmlspecialchars($d['reference']) ?></td>
            <td><?= htmlspecialchars($d['client_prenom'] . ' ' . $d['client_nom']) ?></td>
            <td><?= htmlspecialchars($d['nom_type']) ?></td>
            <td><?= number_format($d['montant_demande'], 0, ',', ' ') ?> DT</td>
            <td><?= number_format($d['mensualite_estimee'], 0, ',', ' ') ?> DT</td>
            <td>
              <span class="badge <?= $d['taux_endettement'] > 40 ? 'badge--refusee' : 'badge--validee' ?>">
                <?= $d['taux_endettement'] ?>&nbsp;%
              </span>
            </td>
            <td><?= (new DateTime($d['date_demande']))->format('d/m/Y') ?></td>
            <td><a href="traiter_demande.php?id=<?= $d['id_demande'] ?>" class="btn btn-outline btn-small">Étudier</a></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($demandes)): ?>
          <tr><td colspan="8">Aucune demande en attente pour le moment.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="admin-table-wrap" style="margin-top:24px;">
      <h2>Mes dernières décisions</h2>
      <table class="admin-table">
        <thead>
          <tr><th>Référence</th><th>Montant</th><th>Décision</th><th>Date</th></tr>
        </thead>
        <tbody>
          <?php foreach ($decisionsRecentes as $r): ?>
          <tr>
            <td><?= htmlspecialchars($r['reference']) ?></td>
            <td><?= number_format($r['montant_demande'], 0, ',', ' ') ?> DT</td>
            <td><span class="badge <?= $r['decision'] === 'approuve' ? 'badge--validee' : 'badge--refusee' ?>">
              <?= $r['decision'] === 'approuve' ? 'Approuvée' : 'Rejetée' ?>
            </span></td>
            <td><?= (new DateTime($r['date_decision']))->format('d/m/Y H:i') ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($decisionsRecentes)): ?>
          <tr><td colspan="4">Aucune décision prise pour le moment.</td></tr>
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
