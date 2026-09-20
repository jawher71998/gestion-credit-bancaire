<?php

function compterNotificationsNonLues(PDO $pdo, int $idUtilisateur): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE id_utilisateur = ? AND lu = 0');
    $stmt->execute([$idUtilisateur]);
    return (int) $stmt->fetchColumn();
}

/** Affiche le bouton "cloche" avec le badge du nombre de notifications non lues. */
function afficherClocheNotifications(PDO $pdo, int $idUtilisateur, string $baseUrl = ''): void
{
    $nb = compterNotificationsNonLues($pdo, $idUtilisateur);
    echo '<a href="' . $baseUrl . 'notifications.php" class="notif-bell" aria-label="Notifications">';
    echo '🔔';
    if ($nb > 0) {
        echo '<span class="notif-badge">' . ($nb > 9 ? '9+' : $nb) . '</span>';
    }
    echo '</a>';
}
