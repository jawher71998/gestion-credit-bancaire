<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/notifications_widget.php';
exigerRole(['client']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifierTokenCSRF($_POST['csrf_token'] ?? null)) {
    if (isset($_POST['tout_marquer'])) {
        $pdo->prepare('UPDATE notifications SET lu = 1 WHERE id_utilisateur = ?')
            ->execute([$_SESSION['id_utilisateur']]);
    } elseif (isset($_POST['marquer_id'])) {
        $pdo->prepare('UPDATE notifications SET lu = 1 WHERE id_notification = ? AND id_utilisateur = ?')
            ->execute([(int) $_POST['marquer_id'], $_SESSION['id_utilisateur']]);
    }
    header('Location: notifications.php');
    exit;
}

$stmt = $pdo->prepare('
    SELECT n.*, dc.reference
    FROM notifications n
    LEFT JOIN demandes_credit dc ON dc.id_demande = n.id_demande
    WHERE n.id_utilisateur = ?
    ORDER BY n.date_creation DESC
');
$stmt->execute([$_SESSION['id_utilisateur']]);
$notifications = $stmt->fetchAll();

$csrfToken = genererTokenCSRF();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Notifications — Financement</title>
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
    <div style="display:flex; justify-content:space-between; align-items:center;">
      <h1>Notifications</h1>
      <?php if (!empty($notifications)): ?>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <button type="submit" name="tout_marquer" value="1" class="btn btn-outline btn-small">Tout marquer comme lu</button>
      </form>
      <?php endif; ?>
    </div>

    <div class="notif-list">
      <?php foreach ($notifications as $n): ?>
        <div class="notif-item <?= $n['lu'] ? '' : 'notif-item--unread' ?>">
          <span class="notif-icon notif-icon--<?= htmlspecialchars($n['type']) ?>">
            <?= $n['type'] === 'succes' ? '✓' : ($n['type'] === 'erreur' ? '✕' : 'i') ?>
          </span>
          <div class="notif-body">
            <p>
              <?= htmlspecialchars($n['message']) ?>
              <?php if ($n['reference']): ?>
                — <a href="demande_detail.php?id=<?= $n['id_demande'] ?>">voir le dossier</a>
              <?php endif; ?>
            </p>
            <span class="notif-date"><?= (new DateTime($n['date_creation']))->format('d/m/Y H:i') ?></span>
          </div>
          <?php if (!$n['lu']): ?>
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="marquer_id" value="<?= $n['id_notification'] ?>">
            <button type="submit" class="btn btn-ghost btn-small">Marquer lu</button>
          </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
      <?php if (empty($notifications)): ?>
        <p class="hero-note">Aucune notification pour le moment.</p>
      <?php endif; ?>
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
