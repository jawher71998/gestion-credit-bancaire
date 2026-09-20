<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/credit.php';
require_once __DIR__ . '/includes/notifications_widget.php';
exigerRole(['client']);

$idDemande = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('
    SELECT dc.* FROM demandes_credit dc
    WHERE dc.id_demande = ? AND dc.id_client = ?
');
$stmt->execute([$idDemande, $_SESSION['id_utilisateur']]);
$demande = $stmt->fetch();

if (!$demande || $demande['statut'] !== 'soumise') {
    header('Location: suivi.php');
    exit;
}

$typesCredit = $pdo->query('SELECT * FROM types_credit WHERE actif = 1 ORDER BY id_type_credit')->fetchAll();

$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifierTokenCSRF($_POST['csrf_token'] ?? null)) {
        $erreur = 'Session expirée, veuillez réessayer.';
    } else {
        $idType = (int) $_POST['id_type_credit'];
        $montant = (float) $_POST['montant'];
        $duree = (int) $_POST['duree'];
        $objet = trim($_POST['objet_credit']);
        $revenuDeclare = (float) $_POST['revenu_mensuel_declare'];
        $garantieType = $_POST['garantie_type'];
        $garantieDescription = trim($_POST['garantie_description'] ?? '');
        $garantieValeur = ($_POST['garantie_valeur'] ?? '') !== '' ? (float) $_POST['garantie_valeur'] : null;

        $stmtType = $pdo->prepare('SELECT * FROM types_credit WHERE id_type_credit = ?');
        $stmtType->execute([$idType]);
        $type = $stmtType->fetch();

        if (!$type) {
            $erreur = 'Type de crédit invalide.';
        } elseif ($montant < $type['montant_min'] || $montant > $type['montant_max']) {
            $erreur = 'Le montant doit être compris entre ' . number_format($type['montant_min'], 0, ',', ' ')
                . ' et ' . number_format($type['montant_max'], 0, ',', ' ') . ' DT.';
        } elseif ($duree < $type['duree_min_mois'] || $duree > $type['duree_max_mois']) {
            $erreur = 'La durée doit être comprise entre ' . $type['duree_min_mois'] . ' et ' . $type['duree_max_mois'] . ' mois.';
        } else {
            $mensualite = calculerMensualite($montant, $duree, $type['taux_interet']);
            $tauxEndettement = $revenuDeclare > 0 ? round(($mensualite / $revenuDeclare) * 100, 2) : 100;

            $pdo->prepare('
                UPDATE demandes_credit SET
                    id_type_credit = ?, montant_demande = ?, duree_mois = ?, taux_applique = ?,
                    mensualite_estimee = ?, revenu_mensuel_declare = ?, taux_endettement = ?, objet_credit = ?,
                    garantie_type = ?, garantie_description = ?, garantie_valeur = ?
                WHERE id_demande = ?
            ')->execute([
                $idType, $montant, $duree, $type['taux_interet'], $mensualite, $revenuDeclare,
                $tauxEndettement, $objet, $garantieType, $garantieDescription ?: null, $garantieValeur,
                $idDemande,
            ]);

            header('Location: demande_detail.php?id=' . $idDemande . '&modifiee=1');
            exit;
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
<title>Modifier ma demande — Financement</title>
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
    </nav>
    <div style="display:flex; align-items:center; gap:16px;">
      <?php afficherClocheNotifications($pdo, $_SESSION['id_utilisateur']); ?>
      <a href="logout.php" class="btn btn-ghost btn-small">Déconnexion</a>
    </div>
  </div>
</header>

<main class="auth-page">
  <div class="wrap auth-wrap">
    <div class="auth-card auth-card--wide">
      <h1>Modifier ma demande <?= htmlspecialchars($demande['reference']) ?></h1>
      <p class="auth-lede">Vous pouvez modifier votre demande tant qu'un agent ne l'a pas encore étudiée.</p>

      <?php if ($erreur): ?>
        <div class="alert alert-error"><?= htmlspecialchars($erreur) ?></div>
      <?php endif; ?>

      <form method="post" class="form-grid">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

        <div class="field field--full">
          <label for="id_type_credit">Type de financement</label>
          <select id="id_type_credit" name="id_type_credit" required>
            <?php foreach ($typesCredit as $t): ?>
              <option value="<?= $t['id_type_credit'] ?>" <?= $t['id_type_credit'] == $demande['id_type_credit'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($t['nom']) ?> (<?= $t['taux_interet'] ?>&nbsp;%)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label for="montant">Montant souhaité (DT)</label>
          <input type="number" id="montant" name="montant" min="1000" step="100" value="<?= htmlspecialchars($demande['montant_demande']) ?>" required>
        </div>
        <div class="field">
          <label for="duree">Durée (mois)</label>
          <input type="number" id="duree" name="duree" min="6" step="1" value="<?= htmlspecialchars($demande['duree_mois']) ?>" required>
        </div>

        <div class="field field--full">
          <label for="objet_credit">Objet du crédit</label>
          <input type="text" id="objet_credit" name="objet_credit" value="<?= htmlspecialchars($demande['objet_credit']) ?>" required>
        </div>

        <div class="field">
          <label for="revenu_mensuel_declare">Revenu mensuel net déclaré (DT)</label>
          <input type="number" id="revenu_mensuel_declare" name="revenu_mensuel_declare" min="0" step="1" value="<?= htmlspecialchars($demande['revenu_mensuel_declare']) ?>" required>
        </div>
        <div class="field">
          <label for="garantie_type">Garantie proposée</label>
          <select id="garantie_type" name="garantie_type" required>
            <?php foreach (['aucune' => 'Aucune', 'domiciliation_salaire' => 'Domiciliation de salaire', 'caution' => 'Caution', 'nantissement' => 'Nantissement', 'hypotheque' => 'Hypothèque'] as $val => $label): ?>
              <option value="<?= $val ?>" <?= $demande['garantie_type'] === $val ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label for="garantie_valeur">Valeur estimée de la garantie (DT)</label>
          <input type="number" id="garantie_valeur" name="garantie_valeur" min="0" step="100" value="<?= htmlspecialchars($demande['garantie_valeur'] ?? '') ?>">
        </div>
        <div class="field">
          <label for="garantie_description">Précisions sur la garantie</label>
          <input type="text" id="garantie_description" name="garantie_description" value="<?= htmlspecialchars($demande['garantie_description'] ?? '') ?>">
        </div>

        <button type="submit" class="btn btn-primary btn-block field--full">Enregistrer les modifications</button>
        <a href="demande_detail.php?id=<?= $idDemande ?>" class="btn btn-outline btn-block field--full">Annuler la modification</a>
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
