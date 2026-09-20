<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/workflow.php';
exigerRole(['client']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifierTokenCSRF($_POST['csrf_token'] ?? null)) {
    header('Location: suivi.php');
    exit;
}

$idDemande = (int) $_POST['id_demande'];

$stmt = $pdo->prepare('SELECT * FROM demandes_credit WHERE id_demande = ? AND id_client = ? AND statut = "soumise"');
$stmt->execute([$idDemande, $_SESSION['id_utilisateur']]);
$demande = $stmt->fetch();

if ($demande) {
    $pdo->prepare('UPDATE demandes_credit SET statut = "annulee" WHERE id_demande = ?')->execute([$idDemande]);
    notifier($pdo, $_SESSION['id_utilisateur'], $idDemande, "Votre demande {$demande['reference']} a été annulée.", 'info');
}

header('Location: suivi.php?annulee=1');
exit;
