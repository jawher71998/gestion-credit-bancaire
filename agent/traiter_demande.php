<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/credit.php';
require_once __DIR__ . '/../includes/workflow.php';
exigerRole(['agent', 'chef_agence']);

$role = roleActuel();
$idDemande = (int) ($_GET['id'] ?? 0);
$erreur = '';

$stmt = $pdo->prepare('
    SELECT dc.*, tc.nom AS nom_type, tc.mode_financement,
           u.nom AS client_nom, u.prenom AS client_prenom, u.email AS client_email,
           u.telephone AS client_telephone, u.cin, u.profession, u.situation_familiale
    FROM demandes_credit dc
    JOIN types_credit tc ON tc.id_type_credit = dc.id_type_credit
    JOIN utilisateurs u ON u.id_utilisateur = dc.id_client
    WHERE dc.id_demande = ?
');
$stmt->execute([$idDemande]);
$demande = $stmt->fetch();

if (!$demande) {
    header('Location: liste_demandes.php');
    exit;
}

$statutAttendu = $role === 'chef_agence' ? 'en_etude_chef' : 'soumise';
$peutTraiter = $demande['statut'] === $statutAttendu;

$stmtDocs = $pdo->prepare('SELECT * FROM documents_justificatifs WHERE id_demande = ?');
$stmtDocs->execute([$idDemande]);
$documents = $stmtDocs->fetchAll();

$stmtHistorique = $pdo->prepare('
    SELECT wv.*, u.nom, u.prenom FROM workflow_validations wv
    JOIN utilisateurs u ON u.id_utilisateur = wv.id_validateur
    WHERE wv.id_demande = ? ORDER BY wv.date_decision ASC
');
$stmtHistorique->execute([$idDemande]);
$historique = $stmtHistorique->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $peutTraiter) {
    if (!verifierTokenCSRF($_POST['csrf_token'] ?? null)) {
        $erreur = 'Session expirée, veuillez réessayer.';
    } else {
        $decision = $_POST['decision'] === 'approuve' ? 'approuve' : 'rejete';
        $commentaire = trim($_POST['commentaire']);

        if ($decision === 'rejete' && $commentaire === '') {
            $erreur = 'Un motif est obligatoire en cas de refus.';
        } else {
            try {
                if ($role === 'chef_agence') {
                    traiterDecisionChef($pdo, $idDemande, $_SESSION['id_utilisateur'], $decision, $commentaire);
                } else {
                    traiterDecisionAgent($pdo, $idDemande, $_SESSION['id_utilisateur'], $decision, $commentaire);
                }
                header('Location: liste_demandes.php?traite=' . urlencode($demande['reference']));
                exit;
            } catch (Exception $e) {
                $erreur = $e->getMessage();
            }
        }
    }
}

$csrfToken = genererTokenCSRF();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dossier <?= htmlspecialchars($demande['reference']) ?> — Étude</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<header class="site-header">
  <div class="wrap header-inner">
    <a href="liste_demandes.php" class="brand">
      <img src="../assets/img/logo-zitouna.png" alt="Banque Zitouna" class="brand-logo">
      <span class="brand-name">Financement<span class="brand-dot">.</span></span>
    </a>
    <nav class="main-nav">
      <a href="liste_demandes.php">← Retour à la liste</a>
    </nav>
    <a href="../logout.php" class="btn btn-ghost btn-small">Déconnexion</a>
  </div>
</header>

<main class="admin-page">
  <div class="wrap">
    <h1>Dossier <?= htmlspecialchars($demande['reference']) ?></h1>
    <p class="hero-note">
      Statut actuel :
      <span class="badge badge--<?= htmlspecialchars($demande['statut']) ?>"><?= htmlspecialchars(libelleStatut($demande['statut'])) ?></span>
    </p>

    <?php if (!$peutTraiter): ?>
      <div class="alert alert-error">
        Ce dossier n'est pas (ou plus) en attente de votre niveau de validation.
      </div>
    <?php endif; ?>

    <?php if ($erreur): ?>
      <div class="alert alert-error"><?= htmlspecialchars($erreur) ?></div>
    <?php endif; ?>

    <div class="admin-layout" style="grid-template-columns: 1fr 400px;">
      <div>
        <div class="admin-table-wrap">
          <h2>Client</h2>
          <p>
            <strong><?= htmlspecialchars($demande['client_prenom'] . ' ' . $demande['client_nom']) ?></strong><br>
            CIN : <?= htmlspecialchars($demande['cin']) ?> ·
            <?= htmlspecialchars($demande['profession']) ?> ·
            <?= htmlspecialchars(ucfirst($demande['situation_familiale'])) ?><br>
            <?= htmlspecialchars($demande['client_email']) ?> · <?= htmlspecialchars($demande['client_telephone']) ?>
          </p>
        </div>

        <div class="admin-table-wrap" style="margin-top:20px;">
          <h2>Détails du financement</h2>
          <table class="admin-table">
            <tr><td>Type</td><td><?= htmlspecialchars($demande['nom_type']) ?> (<?= htmlspecialchars($demande['mode_financement']) ?>)</td></tr>
            <tr><td>Objet</td><td><?= htmlspecialchars($demande['objet_credit']) ?></td></tr>
            <tr><td>Montant demandé</td><td><?= number_format($demande['montant_demande'], 0, ',', ' ') ?> DT</td></tr>
            <tr><td>Durée</td><td><?= $demande['duree_mois'] ?> mois</td></tr>
            <tr><td>Taux appliqué</td><td><?= $demande['taux_applique'] ?> %</td></tr>
            <tr><td>Mensualité estimée</td><td><?= number_format($demande['mensualite_estimee'], 0, ',', ' ') ?> DT</td></tr>
            <tr><td>Revenu déclaré</td><td><?= number_format($demande['revenu_mensuel_declare'], 0, ',', ' ') ?> DT</td></tr>
            <tr>
              <td>Taux d'endettement</td>
              <td>
                <span class="badge <?= $demande['taux_endettement'] > 40 ? 'badge--refusee' : 'badge--validee' ?>">
                  <?= $demande['taux_endettement'] ?> %<?= $demande['taux_endettement'] > 40 ? ' — au-dessus du seuil de 40 %' : '' ?>
                </span>
              </td>
            </tr>
            <tr><td>Garantie</td><td><?= htmlspecialchars($demande['garantie_type']) ?><?= $demande['garantie_description'] ? ' — ' . htmlspecialchars($demande['garantie_description']) : '' ?></td></tr>
          </table>
        </div>

        <div class="admin-table-wrap" style="margin-top:20px;">
          <h2>Documents justificatifs</h2>
          <?php if (empty($documents)): ?>
            <p>Aucun document joint.</p>
          <?php else: ?>
            <ul>
              <?php foreach ($documents as $doc): ?>
                <li><a href="../<?= htmlspecialchars($doc['chemin_fichier']) ?>" target="_blank"><?= htmlspecialchars($doc['nom_fichier']) ?></a>
                  — <span class="hero-note"><?= htmlspecialchars($doc['type_document']) ?></span></li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>

        <?php if (!empty($historique)): ?>
        <div class="admin-table-wrap" style="margin-top:20px;">
          <h2>Historique du dossier</h2>
          <table class="admin-table">
            <thead><tr><th>Niveau</th><th>Décideur</th><th>Décision</th><th>Commentaire</th><th>Date</th></tr></thead>
            <tbody>
              <?php foreach ($historique as $h): ?>
              <tr>
                <td><?= $h['niveau'] === 'chef_agence' ? "Chef d'agence" : 'Agent' ?></td>
                <td><?= htmlspecialchars($h['prenom'] . ' ' . $h['nom']) ?></td>
                <td><span class="badge <?= $h['decision'] === 'approuve' ? 'badge--validee' : 'badge--refusee' ?>"><?= $h['decision'] === 'approuve' ? 'Approuvée' : 'Rejetée' ?></span></td>
                <td><?= htmlspecialchars($h['commentaire'] ?: '—') ?></td>
                <td><?= (new DateTime($h['date_decision']))->format('d/m/Y H:i') ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>

      <div>
        <?php if ($peutTraiter): ?>
        <div class="auth-card">
          <h2>Votre décision</h2>
          <form method="post" class="form-grid">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <div class="field field--full">
              <label for="commentaire">Commentaire (obligatoire en cas de refus)</label>
              <textarea id="commentaire" name="commentaire" rows="4" style="width:100%; padding:11px 12px; border:1px solid var(--border); border-radius:4px; font-family:var(--font-body); font-size:14.5px;"></textarea>
            </div>

            <button type="submit" name="decision" value="approuve" class="btn btn-primary btn-block field--full">
              ✓ Approuver le dossier
            </button>
            <button type="submit" name="decision" value="rejete" class="btn btn-outline btn-block field--full">
              ✕ Refuser le dossier
            </button>
          </form>
        </div>
        <?php else: ?>
        <div class="auth-card">
          <p class="hero-note">Ce dossier a déjà été traité ou n'est pas encore à votre niveau.</p>
          <a href="liste_demandes.php" class="btn btn-outline btn-block">Retour à la liste</a>
        </div>
        <?php endif; ?>
      </div>
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
