<?php

class Bailleur
{
    public int    $id;
    public string $nom;
    public string $telephone;
    public string $email;
    public ?string $adresse;
    public ?string $photo;

    private function __construct(array $row)
    {
        $this->id        = (int)$row['id'];
        $this->nom       = $row['nom'];
        $this->telephone = $row['telephone'];
        $this->email     = $row['email'] ?? '';
        $this->adresse   = $row['adresse'] ?? null;
        $this->photo     = $row['photo_bailleur'] ?? null;
    }

    public static function find(PDO $pdo, int $id): ?self
    {
        $stmt = $pdo->prepare("SELECT * FROM bailleurs WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? new self($row) : null;
    }

    /** @return self[] */
    public static function all(PDO $pdo): array
    {
        $rows = $pdo->query("SELECT * FROM bailleurs ORDER BY nom")->fetchAll();
        return array_map(fn($r) => new self($r), $rows);
    }

    /** @return array{bailleur: self, nb_maisons: int, nb_contrats_actifs: int}[] */
    public static function withStats(PDO $pdo): array
    {
        $sql = "SELECT b.*,
                       COUNT(DISTINCT m.id)                                   AS nb_maisons,
                       COUNT(DISTINCT CASE WHEN c.statut_contrat='actif' THEN c.id END) AS nb_contrats_actifs
                FROM bailleurs b
                LEFT JOIN maisons m  ON m.bailleur_id = b.id
                LEFT JOIN contrats c ON c.maison_id   = m.id
                GROUP BY b.id
                ORDER BY b.nom";
        return array_map(fn($r) => [
            'bailleur'          => new self($r),
            'nb_maisons'        => (int)$r['nb_maisons'],
            'nb_contrats_actifs'=> (int)$r['nb_contrats_actifs'],
        ], $pdo->query($sql)->fetchAll());
    }

    public function totalVerse(PDO $pdo): float
    {
        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(e.montant_recu), 0)
             FROM encaissements e
             JOIN contrats c ON e.contrat_id = c.id
             JOIN maisons  m ON c.maison_id  = m.id
             WHERE m.bailleur_id = ?"
        );
        $stmt->execute([$this->id]);
        return (float)$stmt->fetchColumn();
    }
}
