<?php

class Encaissement
{
    public int    $id;
    public int    $contrat_id;
    public float  $montant_recu;
    public string $date_encaissement;
    public string $periode_concernee;
    public string $mode_paiement;
    public string $reference_recu;

    private function __construct(array $row)
    {
        $this->id                = (int)$row['id'];
        $this->contrat_id        = (int)$row['contrat_id'];
        $this->montant_recu      = (float)$row['montant_recu'];
        $this->date_encaissement = $row['date_encaissement'];
        $this->periode_concernee = $row['periode_concernee'];
        $this->mode_paiement     = $row['mode_paiement'];
        $this->reference_recu    = $row['reference_recu'] ?? '';
    }

    public static function find(PDO $pdo, int $id): ?self
    {
        $stmt = $pdo->prepare("SELECT * FROM encaissements WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? new self($row) : null;
    }

    /** @return self[] */
    public static function byContrat(PDO $pdo, int $contrat_id, int $limit = 50): array
    {
        $stmt = $pdo->prepare(
            "SELECT * FROM encaissements WHERE contrat_id = ? ORDER BY date_encaissement DESC LIMIT ?"
        );
        $stmt->bindValue(1, $contrat_id, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit,      PDO::PARAM_INT);
        $stmt->execute();
        return array_map(fn($r) => new self($r), $stmt->fetchAll());
    }

    /** @return self[] */
    public static function recent(PDO $pdo, int $limit = 30): array
    {
        $stmt = $pdo->prepare("SELECT * FROM encaissements ORDER BY date_encaissement DESC LIMIT ?");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map(fn($r) => new self($r), $stmt->fetchAll());
    }

    /**
     * Totaux mensuels pour une année donnée.
     * @return array<int, float>  clé = numéro du mois (1–12), valeur = total encaissé
     */
    public static function monthlySummary(PDO $pdo, int $year): array
    {
        $stmt = $pdo->prepare(
            "SELECT MONTH(date_encaissement) AS mois, SUM(montant_recu) AS total
             FROM encaissements
             WHERE YEAR(date_encaissement) = ?
             GROUP BY mois"
        );
        $stmt->execute([$year]);
        $result = array_fill(1, 12, 0.0);
        foreach ($stmt->fetchAll() as $row) {
            $result[(int)$row['mois']] = (float)$row['total'];
        }
        return $result;
    }

    public function montantFormate(): string
    {
        return number_format($this->montant_recu, 0, ',', ' ') . ' FCFA';
    }
}
