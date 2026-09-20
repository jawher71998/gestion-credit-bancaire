<?php

/**
 * Calcule la mensualité d'un crédit (formule d'amortissement classique).
 */
function calculerMensualite(float $montant, int $dureeMois, float $tauxAnnuel): float
{
    $tauxMensuel = ($tauxAnnuel / 100) / 12;
    if ($tauxMensuel == 0) {
        return $montant / $dureeMois;
    }
    return ($montant * $tauxMensuel) / (1 - pow(1 + $tauxMensuel, -$dureeMois));
}

/**
 * Génère la référence définitive d'une demande une fois son ID connu,
 * au format CR-AAAA-0001, et la sauvegarde en base.
 */
function genererReference(PDO $pdo, int $idDemande): string
{
    $reference = sprintf('CR-%s-%04d', date('Y'), $idDemande);
    $stmt = $pdo->prepare('UPDATE demandes_credit SET reference = ? WHERE id_demande = ?');
    $stmt->execute([$reference, $idDemande]);
    return $reference;
}

/** Libellés lisibles pour les statuts de demande. */
function libelleStatut(string $statut): string
{
    return match ($statut) {
        'brouillon' => 'Brouillon',
        'soumise' => 'Soumise',
        'en_etude_agent' => 'En étude (agent)',
        'en_etude_chef' => "En étude (chef d'agence)",
        'validee' => 'Validée',
        'refusee' => 'Refusée',
        'annulee' => 'Annulée',
        default => $statut,
    };
}
