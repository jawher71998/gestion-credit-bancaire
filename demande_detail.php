<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/credit.php';
require_once __DIR__ . '/includes/notifications_widget.php';
exigerRole(['client']);

$idDemande = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('
    SELECT dc.*, tc.nom AS nom_type, tc.mode_financement
    FROM demandes_credit dc
    JOIN types_credit tc ON tc.id_type_credit = dc.id_type_credit
    WHERE dc.id_demande = ? AND dc.id_client = ?
');
$stmt->execute([$idDemande, $_SESSION['id_utilisateur']]);
$demande = $stmt->fetch();

if (!$demande) {
    header('Location: suivi.php');
    exit;
}

$stmtDocs = $pdo->prepare('SELECT * FROM documents_justificatifs WHERE id_demande = ?');
$stmtDocs->execute([$idDemande]);
$documents = $stmtDocs->fetchAll();

$echeancier = [];
if ($demande['statut'] === 'validee') {
    $stmtEch = $pdo->prepare('SELECT * FROM echeancier WHERE id_demande = ? ORDER BY numero_echeance ASC');
    $stmtEch->execute([$idDemande]);
    $echeancier = $stmtEch->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dossier <?= htmlspecialchars($demande['reference']) ?> — Financement</title>
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
    <p><a href="suivi.php" class="hero-note">← Retour à mes demandes</a></p>
    <h1>Dossier <?= htmlspecialchars($demande['reference']) ?></h1>
    <p class="hero-note">
      Statut :
      <span class="badge badge--<?= htmlspecialchars($demande['statut']) ?>"><?= htmlspecialchars(libelleStatut($demande['statut'])) ?></span>
    </p>

    <?php if (!empty($_GET['modifiee'])): ?>
      <div class="alert alert-success">Votre demande a bien été mise à jour.</div>
    <?php endif; ?>

    <?php if ($demande['statut'] === 'refusee' && $demande['motif_refus']): ?>
      <div class="alert alert-error"><strong>Motif du refus :</strong> <?= htmlspecialchars($demande['motif_refus']) ?></div>
    <?php elseif ($demande['statut'] === 'validee'): ?>
      <div class="alert alert-success">Félicitations, votre financement est validé ! Retrouvez votre échéancier de remboursement ci-dessous.</div>
    <?php elseif (in_array($demande['statut'], ['soumise', 'en_etude_agent', 'en_etude_chef'], true)): ?>
      <div class="alert alert-success" style="background: var(--forest-tint);">Votre dossier est en cours d'étude par nos équipes.</div>
    <?php endif; ?>

    <?php if ($demande['statut'] === 'soumise'): ?>
      <div style="display:flex; gap:12px; margin-bottom:20px;">
        <a href="modifier_demande.php?id=<?= $demande['id_demande'] ?>" class="btn btn-outline btn-small">Modifier ma demande</a>
        <form method="post" action="annuler_demande.php" onsubmit="return confirm('Annuler définitivement cette demande ?');">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(genererTokenCSRF()) ?>">
          <input type="hidden" name="id_demande" value="<?= $demande['id_demande'] ?>">
          <button type="submit" class="btn btn-ghost btn-small">Annuler ma demande</button>
        </form>
      </div>
      <p class="hero-note">Vous pouvez modifier ou annuler cette demande tant qu'un agent ne l'a pas encore étudiée.</p>
    <?php endif; ?>

    <div class="admin-table-wrap" style="margin-top:20px;">
      <h2>Détails du financement</h2>
      <table class="admin-table">
        <tr><td>Type</td><td><?= htmlspecialchars($demande['nom_type']) ?></td></tr>
        <tr><td>Objet</td><td><?= htmlspecialchars($demande['objet_credit']) ?></td></tr>
        <tr><td>Montant</td><td><?= number_format($demande['montant_demande'], 0, ',', ' ') ?> DT</td></tr>
        <tr><td>Durée</td><td><?= $demande['duree_mois'] ?> mois</td></tr>
        <tr><td>Taux</td><td><?= $demande['taux_applique'] ?> %</td></tr>
        <tr><td>Mensualité</td><td><?= number_format($demande['mensualite_estimee'], 0, ',', ' ') ?> DT</td></tr>
        <tr><td>Date de la demande</td><td><?= (new DateTime($demande['date_demande']))->format('d/m/Y') ?></td></tr>
      </table>
    </div>

    <div class="admin-table-wrap" style="margin-top:20px;">
      <h2>Mes documents</h2>
      <?php if (empty($documents)): ?>
        <p class="hero-note">Aucun document joint.</p>
      <?php else: ?>
        <ul>
          <?php foreach ($documents as $doc): ?>
            <li><a href="<?= htmlspecialchars($doc['chemin_fichier']) ?>" target="_blank"><?= htmlspecialchars($doc['nom_fichier']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>

    <?php if (!empty($echeancier)): ?>
    <div class="admin-table-wrap" style="margin-top:20px;">
      <h2>Échéancier de remboursement</h2>
      <table class="admin-table">
        <thead>
          <tr><th>#</th><th>Date</th><th>Capital</th><th>Intérêts</th><th>Total</th><th>Statut</th></tr>
        </thead>
        <tbody>
          <?php foreach ($echeancier as $e): ?>
          <tr>
            <td><?= $e['numero_echeance'] ?></td>
            <td><?= (new DateTime($e['date_echeance']))->format('m/Y') ?></td>
            <td><?= number_format($e['montant_capital'], 2, ',', ' ') ?> DT</td>
            <td><?= number_format($e['montant_interet'], 2, ',', ' ') ?> DT</td>
            <td><strong><?= number_format($e['montant_total'], 2, ',', ' ') ?> DT</strong></td>
            <td><span class="badge <?= $e['statut_paiement'] === 'payee' ? 'badge--validee' : 'badge--soumise' ?>">
              <?= $e['statut_paiement'] === 'payee' ? 'Payée' : ($e['statut_paiement'] === 'en_retard' ? 'En retard' : 'À venir') ?>
            </span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</main>

<footer class="site-footer-mini">
  <div class="wrap">
    <p>© 2026 Banque Zitouna — Projet de Fin d'Études, BTS Informatique de Gestion</p>
  </div>
</footer>
</body>
</html>
