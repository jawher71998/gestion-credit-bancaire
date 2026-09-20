<?php

/** Montant au-delà duquel la validation du chef d'agence est requise. */
function seuilValidationChef(): float
{
    return 20000.0;
}

function notifier(PDO $pdo, int $idUtilisateur, int $idDemande, string $message, string $type = 'info'): void
{
    $stmt = $pdo->prepare('
        INSERT INTO notifications (id_utilisateur, id_demande, message, type)
        VALUES (?, ?, ?, ?)
    ');
    $stmt->execute([$idUtilisateur, $idDemande, $message, $type]);
}

/**
 * Génère l'échéancier de remboursement d'une demande validée
 * (amortissement à mensualité constante).
 */
function genererEcheancier(PDO $pdo, array $demande): void
{
    $montant = (float) $demande['montant_demande'];
    $duree = (int) $demande['duree_mois'];
    $tauxAnnuel = (float) $demande['taux_applique'];
    $mensualite = (float) $demande['mensualite_estimee'];
    $tauxMensuel = ($tauxAnnuel / 100) / 12;

    $capitalRestant = $montant;
    $dateEcheance = new DateTime('first day of next month');

    $stmt = $pdo->prepare('
        INSERT INTO echeancier (id_demande, numero_echeance, date_echeance, montant_capital, montant_interet, montant_total)
        VALUES (?, ?, ?, ?, ?, ?)
    ');

    for ($i = 1; $i <= $duree; $i++) {
        $interet = $tauxMensuel > 0 ? $capitalRestant * $tauxMensuel : 0;
        $capital = $mensualite - $interet;

        if ($i === $duree) {
            // dernière échéance : absorbe l'écart d'arrondi
            $capital = $capitalRestant;
            $montantTotal = $capital + $interet;
        } else {
            $montantTotal = $mensualite;
        }

        $stmt->execute([
            $demande['id_demande'], $i, $dateEcheance->format('Y-m-d'),
            round($capital, 2), round($interet, 2), round($montantTotal, 2),
        ]);

        $capitalRestant -= $capital;
        $dateEcheance->modify('+1 month');
    }
}

/**
 * Traite la décision d'un agent sur une demande "soumise".
 */
function traiterDecisionAgent(PDO $pdo, int $idDemande, int $idAgent, string $decision, string $commentaire): void
{
    $stmt = $pdo->prepare('SELECT * FROM demandes_credit WHERE id_demande = ? AND statut = "soumise"');
    $stmt->execute([$idDemande]);
    $demande = $stmt->fetch();
    if (!$demande) {
        throw new RuntimeException('Cette demande n\'est plus en attente de traitement par un agent.');
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare('
            INSERT INTO workflow_validations (id_demande, id_validateur, niveau, decision, commentaire, date_decision)
            VALUES (?, ?, "agent", ?, ?, NOW())
        ')->execute([$idDemande, $idAgent, $decision, $commentaire]);

        if ($decision === 'rejete') {
            $pdo->prepare('UPDATE demandes_credit SET statut = "refusee", motif_refus = ? WHERE id_demande = ?')
                ->execute([$commentaire, $idDemande]);
            notifier($pdo, $demande['id_client'], $idDemande,
                "Votre demande {$demande['reference']} a été refusée par l'agence.", 'erreur');

        } elseif ((float) $demande['montant_demande'] > seuilValidationChef()) {
            $pdo->prepare('UPDATE demandes_credit SET statut = "en_etude_chef" WHERE id_demande = ?')
                ->execute([$idDemande]);
            notifier($pdo, $demande['id_client'], $idDemande,
                "Votre demande {$demande['reference']} est transmise au chef d'agence pour validation finale.", 'info');

        } else {
            $pdo->prepare('UPDATE demandes_credit SET statut = "validee" WHERE id_demande = ?')
                ->execute([$idDemande]);
            $demande['statut'] = 'validee';
            genererEcheancier($pdo, $demande);
            notifier($pdo, $demande['id_client'], $idDemande,
                "Bonne nouvelle : votre demande {$demande['reference']} a été validée !", 'succes');
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Traite la décision d'un chef d'agence sur une demande "en_etude_chef".
 */
function traiterDecisionChef(PDO $pdo, int $idDemande, int $idChef, string $decision, string $commentaire): void
{
    $stmt = $pdo->prepare('SELECT * FROM demandes_credit WHERE id_demande = ? AND statut = "en_etude_chef"');
    $stmt->execute([$idDemande]);
    $demande = $stmt->fetch();
    if (!$demande) {
        throw new RuntimeException('Cette demande n\'est plus en attente de validation par le chef d\'agence.');
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare('
            INSERT INTO workflow_validations (id_demande, id_validateur, niveau, decision, commentaire, date_decision)
            VALUES (?, ?, "chef_agence", ?, ?, NOW())
        ')->execute([$idDemande, $idChef, $decision, $commentaire]);

        if ($decision === 'rejete') {
            $pdo->prepare('UPDATE demandes_credit SET statut = "refusee", motif_refus = ? WHERE id_demande = ?')
                ->execute([$commentaire, $idDemande]);
            notifier($pdo, $demande['id_client'], $idDemande,
                "Votre demande {$demande['reference']} a été refusée par la direction de l'agence.", 'erreur');
        } else {
            $pdo->prepare('UPDATE demandes_credit SET statut = "validee" WHERE id_demande = ?')
                ->execute([$idDemande]);
            $demande['statut'] = 'validee';
            genererEcheancier($pdo, $demande);
            notifier($pdo, $demande['id_client'], $idDemande,
                "Bonne nouvelle : votre demande {$demande['reference']} a été validée !", 'succes');
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}
