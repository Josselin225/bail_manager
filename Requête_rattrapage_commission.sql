INSERT INTO mouvements_caisse_entreprise (type_mouvement, montant, commentaire, date_operation)
SELECT 
    'Rattrapage Commission 10%', 
    (montant / 0.9 * 0.1), -- Recalcul des 10% à partir du net bailleur
    CONCAT('Commission sur opération #', id),
    date_operation
FROM compte_courant_bailleur 
WHERE type_operation = 'loyer_encaisse';