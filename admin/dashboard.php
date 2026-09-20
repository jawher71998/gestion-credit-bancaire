<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/credit.php';
exigerRole(['admin']);

$total = (int) $pdo->query('SELECT COUNT(*) FROM demandes_credit')->fetchColumn();
$validees = (int) $pdo->query("SELECT COUNT(*) FROM demandes_credit WHERE statut = 'validee'")->fetchColumn();
$refusees = (int) $pdo->query("SELECT COUNT(*) FROM demandes_credit WHERE statut = 'refusee'")->fetchColumn();
$enAttente = (int) $pdo->query("SELECT COUNT(*) FROM demandes_credit WHERE statut IN ('soumise','en_etude_agent','en_etude_chef')")->fetchColumn();

$montantValide = (float) $pdo->query("SELECT COALESCE(SUM(montant_demande),0) FROM demandes_credit WHERE statut = 'validee'")->fetchColumn();

$tauxAcceptation = ($validees + $refusees) > 0 ? round($validees / ($validees + $refusees) * 100, 1) : 0;

$parType = $pdo->query("
    SELECT tc.nom, COUNT(*) AS nb, COALESCE(SUM(dc.montant_demande),0) AS montant
    FROM demandes_credit dc
    JOIN types_credit tc ON tc.id_type_credit = dc.id_type_credit
    GROUP BY tc.nom
    ORDER BY nb DESC
")->fetchAll();

$parStatut = $pdo->query("
    SELECT statut, COUNT(*) AS nb
    FROM demandes_credit
    GROUP BY statut
")->fetchAll();

$parMois = $pdo->query("
    SELECT DATE_FORMAT(date_demande, '%Y-%m') AS mois, COUNT(*) AS nb
    FROM demandes_credit
    WHERE date_demande >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY mois
    ORDER BY mois
")->fetchAll();

$nbClients = (int) $pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE role = 'client'")->fetchColumn();

$labelsType = json_encode(array_column($parType, 'nom'));
$dataType = json_encode(array_map('intval', array_column($parType, 'nb')));

$labelsStatut = json_encode(array_map('libelleStatut', array_column($parStatut, 'statut')));
$dataStatut = json_encode(array_map('intval', array_column($parStatut, 'nb')));

$labelsMois = json_encode(array_column($parMois, 'mois'));
$dataMois = json_encode(array_map('intval', array_column($parMois, 'nb')));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tableau de bord — Administration</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
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
    <h1>Bonjour, <?= htmlspecialchars($_SESSION['prenom']) ?> 👋</h1>
    <p class="hero-note">Vue d'ensemble de l'activité de la plateforme.</p>

    <div class="stats-grid">
      <div class="stat-card">
        <span class="stat-value"><?= $total ?></span>
        <span class="stat-label">Demandes totales</span>
      </div>
      <div class="stat-card stat-card--accent">
        <span class="stat-value"><?= number_format($montantValide, 0, ',', ' ') ?> DT</span>
        <span class="stat-label">Montant total validé</span>
      </div>
      <div class="stat-card">
        <span class="stat-value"><?= $tauxAcceptation ?>%</span>
        <span class="stat-label">Taux d'acceptation</span>
      </div>
      <div class="stat-card">
        <span class="stat-value"><?= $enAttente ?></span>
        <span class="stat-label">Dossiers en attente</span>
      </div>
      <div class="stat-card">
        <span class="stat-value"><?= $nbClients ?></span>
        <span class="stat-label">Clients inscrits</span>
      </div>
    </div>

    <div class="charts-grid">
      <div class="admin-table-wrap">
        <h2>Demandes par statut</h2>
        <canvas id="chartStatut" height="220"></canvas>
      </div>
      <div class="admin-table-wrap">
        <h2>Demandes par type de crédit</h2>
        <canvas id="chartType" height="220"></canvas>
      </div>
    </div>

    <div class="admin-table-wrap" style="margin-top:24px;">
      <h2>Évolution des demandes (6 derniers mois)</h2>
      <canvas id="chartMois" height="90"></canvas>
    </div>

    <div class="admin-table-wrap" style="margin-top:24px;">
      <h2>Détail par type de crédit</h2>
      <table class="admin-table">
        <thead><tr><th>Type</th><th>Nombre de demandes</th><th>Montant total demandé</th></tr></thead>
        <tbody>
          <?php foreach ($parType as $t): ?>
          <tr>
            <td><?= htmlspecialchars($t['nom']) ?></td>
            <td><?= (int)$t['nb'] ?></td>
            <td><?= number_format($t['montant'], 0, ',', ' ') ?> DT</td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>

<script>
const forest = '#0B4D3C';
const gold = '#B08D57';
const sage = '#DCE5DE';
const ink = '#4E5C52';

new Chart(document.getElementById('chartStatut'), {
  type: 'doughnut',
  data: {
    labels: <?= $labelsStatut ?>,
    datasets: [{
      data: <?= $dataStatut ?>,
      backgroundColor: [forest, gold, sage, '#8A2E1E', '#D9C6A0', '#B9C9BF', '#7A5A2E']
    }]
  },
  options: { plugins: { legend: { position: 'bottom', labels: { color: ink } } } }
});

new Chart(document.getElementById('chartType'), {
  type: 'bar',
  data: {
    labels: <?= $labelsType ?>,
    datasets: [{
      label: 'Nombre de demandes',
      data: <?= $dataType ?>,
      backgroundColor: forest
    }]
  },
  options: {
    plugins: { legend: { display: false } },
    scales: { y: { beginAtZero: true, ticks: { color: ink } }, x: { ticks: { color: ink } } }
  }
});

new Chart(document.getElementById('chartMois'), {
  type: 'line',
  data: {
    labels: <?= $labelsMois ?>,
    datasets: [{
      label: 'Demandes soumises',
      data: <?= $dataMois ?>,
      borderColor: forest,
      backgroundColor: 'rgba(11,77,60,0.1)',
      fill: true,
      tension: 0.3
    }]
  },
  options: {
    plugins: { legend: { display: false } },
    scales: { y: { beginAtZero: true, ticks: { color: ink } }, x: { ticks: { color: ink } } }
  }
});
</script>

<footer class="site-footer-mini">
  <div class="wrap">
    <p>© 2026 Banque Zitouna — Projet de Fin d'Études, BTS Informatique de Gestion</p>
  </div>
</footer>
</body>
</html>
