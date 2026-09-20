<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/credit.php';
require_once __DIR__ . '/includes/notifications_widget.php';
exigerRole(['client']);

$typesCredit = $pdo->query('SELECT * FROM types_credit WHERE actif = 1 ORDER BY id_type_credit')->fetchAll();

$stmtUser = $pdo->prepare('SELECT * FROM utilisateurs WHERE id_utilisateur = ?');
$stmtUser->execute([$_SESSION['id_utilisateur']]);
$utilisateur = $stmtUser->fetch();

$documentsRequis = [
    'cin' => ['label' => 'Copie de la CIN', 'obligatoire' => true],
    'fiche_paie' => ['label' => 'Dernière fiche de paie', 'obligatoire' => true],
    'attestation_travail' => ['label' => 'Attestation de travail', 'obligatoire' => false],
    'releve_bancaire' => ['label' => 'Relevé bancaire (3 derniers mois)', 'obligatoire' => false],
];

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

        $manquants = [];
        foreach ($documentsRequis as $cle => $info) {
            if ($info['obligatoire'] && empty($_FILES['documents']['name'][$cle])) {
                $manquants[] = $info['label'];
            }
        }

        if (!$type) {
            $erreur = 'Type de crédit invalide.';
        } elseif ($montant < $type['montant_min'] || $montant > $type['montant_max']) {
            $erreur = 'Le montant doit être compris entre ' . number_format($type['montant_min'], 0, ',', ' ')
                . ' et ' . number_format($type['montant_max'], 0, ',', ' ') . ' DT.';
        } elseif ($duree < $type['duree_min_mois'] || $duree > $type['duree_max_mois']) {
            $erreur = 'La durée doit être comprise entre ' . $type['duree_min_mois'] . ' et ' . $type['duree_max_mois'] . ' mois.';
        } elseif ($manquants) {
            $erreur = 'Documents manquants : ' . implode(', ', $manquants);
        } else {
            $mensualite = calculerMensualite($montant, $duree, $type['taux_interet']);
            $tauxEndettement = $revenuDeclare > 0 ? round(($mensualite / $revenuDeclare) * 100, 2) : 100;

            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare('
                    INSERT INTO demandes_credit
                        (reference, id_client, id_type_credit, montant_demande, duree_mois, taux_applique,
                         mensualite_estimee, revenu_mensuel_declare, taux_endettement, objet_credit,
                         garantie_type, garantie_description, garantie_valeur, statut)
                    VALUES ("TEMP", ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "soumise")
                ');
                $stmt->execute([
                    $_SESSION['id_utilisateur'], $idType, $montant, $duree, $type['taux_interet'],
                    $mensualite, $revenuDeclare, $tauxEndettement, $objet,
                    $garantieType, $garantieDescription ?: null, $garantieValeur,
                ]);

                $idDemande = (int) $pdo->lastInsertId();
                $reference = genererReference($pdo, $idDemande);

                $dossier = __DIR__ . '/uploads/' . $idDemande . '/';
                if (!is_dir($dossier)) {
                    mkdir($dossier, 0755, true);
                }

                $extensionsAutorisees = ['pdf', 'jpg', 'jpeg', 'png'];
                $stmtDoc = $pdo->prepare('
                    INSERT INTO documents_justificatifs (id_demande, type_document, nom_fichier, chemin_fichier)
                    VALUES (?, ?, ?, ?)
                ');

                foreach ($_FILES['documents']['name'] as $typeDoc => $nomOriginal) {
                    if (empty($nomOriginal)) {
                        continue;
                    }
                    $tmpPath = $_FILES['documents']['tmp_name'][$typeDoc];
                    $extension = strtolower(pathinfo($nomOriginal, PATHINFO_EXTENSION));

                    if (!in_array($extension, $extensionsAutorisees, true)) {
                        throw new RuntimeException("Format non autorisé pour $nomOriginal (pdf, jpg, png uniquement).");
                    }
                    if ($_FILES['documents']['size'][$typeDoc] > 5 * 1024 * 1024) {
                        throw new RuntimeException("Le fichier $nomOriginal dépasse 5 Mo.");
                    }

                    $nomFichier = $typeDoc . '_' . uniqid() . '.' . $extension;
                    if (!move_uploaded_file($tmpPath, $dossier . $nomFichier)) {
                        throw new RuntimeException("Échec de l'envoi de $nomOriginal.");
                    }

                    $stmtDoc->execute([$idDemande, $typeDoc, $nomOriginal, 'uploads/' . $idDemande . '/' . $nomFichier]);
                }

                $stmtNotif = $pdo->prepare('
                    INSERT INTO notifications (id_utilisateur, id_demande, message, type)
                    VALUES (?, ?, ?, "succes")
                ');
                $stmtNotif->execute([
                    $_SESSION['id_utilisateur'], $idDemande, "Votre demande $reference a été soumise avec succès.",
                ]);

                $pdo->commit();
                header('Location: suivi.php?demande=' . urlencode($reference));
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $erreur = $e->getMessage();
            }
        }
    }
}

$csrfToken = genererTokenCSRF();
$typeParam = $_GET['type'] ?? '';
$montantParam = $_GET['montant'] ?? '';
$dureeParam = $_GET['duree'] ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Nouvelle demande de crédit — Financement</title>
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
      <h1>Nouvelle demande de crédit</h1>
      <p class="auth-lede">Remplissez ce formulaire et joignez vos justificatifs. Vous pourrez suivre l'avancement depuis votre espace.</p>

      <?php if ($erreur): ?>
        <div class="alert alert-error"><?= htmlspecialchars($erreur) ?></div>
      <?php endif; ?>

      <form method="post" enctype="multipart/form-data" class="form-grid" id="form-demande">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

        <div class="field field--full">
          <label for="id_type_credit">Type de financement</label>
          <select id="id_type_credit" name="id_type_credit" required>
            <?php foreach ($typesCredit as $t): ?>
              <option value="<?= $t['id_type_credit'] ?>"
                data-taux="<?= $t['taux_interet'] ?>"
                data-min="<?= $t['montant_min'] ?>"
                data-max="<?= $t['montant_max'] ?>"
                <?= (string)$t['id_type_credit'] === (string)$typeParam ? 'selected' : '' ?>>
                <?= htmlspecialchars($t['nom']) ?> (<?= $t['taux_interet'] ?>&nbsp;%)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label for="montant">Montant souhaité (DT)</label>
          <input type="number" id="montant" name="montant" min="1000" step="100"
                 value="<?= htmlspecialchars($montantParam ?: '50000') ?>" required>
        </div>
        <div class="field">
          <label for="duree">Durée (mois)</label>
          <input type="number" id="duree" name="duree" min="6" step="1"
                 value="<?= htmlspecialchars($dureeParam ?: '60') ?>" required>
        </div>

        <div class="field field--full">
          <label for="objet_credit">Objet du crédit</label>
          <input type="text" id="objet_credit" name="objet_credit" placeholder="ex : achat appartement, achat véhicule" required>
        </div>

        <div class="field field--full">
          <div class="panel-result" id="apercu-mensualite">
            <span class="result-label">Mensualité estimée</span>
            <span class="result-value"><span id="mensualite-apercu">—</span> <small>DT / mois</small></span>
          </div>
        </div>

        <div class="field">
          <label for="revenu_mensuel_declare">Revenu mensuel net déclaré (DT)</label>
          <input type="number" id="revenu_mensuel_declare" name="revenu_mensuel_declare" min="0" step="1"
                 value="<?= htmlspecialchars($utilisateur['revenu_mensuel'] ?? '') ?>" required>
        </div>
        <div class="field">
          <label for="garantie_type">Garantie proposée</label>
          <select id="garantie_type" name="garantie_type" required>
            <option value="aucune">Aucune</option>
            <option value="domiciliation_salaire">Domiciliation de salaire</option>
            <option value="caution">Caution</option>
            <option value="nantissement">Nantissement</option>
            <option value="hypotheque">Hypothèque</option>
          </select>
        </div>

        <div class="field">
          <label for="garantie_valeur">Valeur estimée de la garantie (DT)</label>
          <input type="number" id="garantie_valeur" name="garantie_valeur" min="0" step="100">
        </div>
        <div class="field">
          <label for="garantie_description">Précisions sur la garantie</label>
          <input type="text" id="garantie_description" name="garantie_description" placeholder="optionnel">
        </div>

        <div class="field field--full">
          <h3 class="doc-title">Pièces justificatives</h3>
          <div class="doc-upload-list">
            <?php foreach ($documentsRequis as $cle => $info): ?>
              <div class="doc-upload-item">
                <label for="doc-<?= $cle ?>">
                  <?= htmlspecialchars($info['label']) ?>
                  <?= $info['obligatoire'] ? '<span class="doc-required">obligatoire</span>' : '<span class="doc-optional">optionnel</span>' ?>
                </label>
                <input type="file" id="doc-<?= $cle ?>" name="documents[<?= $cle ?>]" accept=".pdf,.jpg,.jpeg,.png">
              </div>
            <?php endforeach; ?>
          </div>
          <p class="field-hint">Formats acceptés : PDF, JPG, PNG — 5 Mo maximum par fichier.</p>
        </div>

        <button type="submit" class="btn btn-primary btn-block field--full">Soumettre ma demande</button>
      </form>
    </div>
  </div>
</main>

<script>
(function () {
  const typeSelect = document.getElementById('id_type_credit');
  const montantInput = document.getElementById('montant');
  const dureeInput = document.getElementById('duree');
  const mensualiteEl = document.getElementById('mensualite-apercu');

  function calculerMensualite(montant, dureeMois, tauxAnnuel) {
    const tauxMensuel = (tauxAnnuel / 100) / 12;
    if (tauxMensuel === 0) return montant / dureeMois;
    return (montant * tauxMensuel) / (1 - Math.pow(1 + tauxMensuel, -dureeMois));
  }

  function majApercu() {
    const option = typeSelect.options[typeSelect.selectedIndex];
    const taux = parseFloat(option.dataset.taux);
    const montant = parseFloat(montantInput.value) || 0;
    const duree = parseInt(dureeInput.value, 10) || 1;
    const mensualite = calculerMensualite(montant, duree, taux);
    mensualiteEl.textContent = Math.round(mensualite).toLocaleString('fr-FR');
  }

  [typeSelect, montantInput, dureeInput].forEach(el => el.addEventListener('input', majApercu));
  majApercu();
})();
</script>

<footer class="site-footer-mini">
  <div class="wrap">
    <p>© 2026 Banque Zitouna — Projet de Fin d'Études, BTS Informatique de Gestion</p>
  </div>
</footer>
</body>
</html>
