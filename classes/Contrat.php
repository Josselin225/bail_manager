<?php

class Contrat
{
    public int     $id;
    public int     $locataire_id;
    public int     $maison_id;
    public float   $loyer_mensuel;
    public string  $date_debut;
    public ?string $date_fin;
    public ?string $date_prochain_loyer;
    public string  $statut_contrat;
    public float   $caution;

    private function __construct(array $row)
    {
        $this->id                  = (int)$row['id'];
        $this->locataire_id        = (int)$row['locataire_id'];
        $this->maison_id           = (int)$row['maison_id'];
        $this->loyer_mensuel       = (float)$row['loyer_mensuel'];
        $this->date_debut          = $row['date_debut'];
        $this->date_fin            = $row['date_fin'] ?? null;
        $this->date_prochain_loyer = $row['date_prochain_loyer'] ?? null;
        $this->statut_contrat      = $row['statut_contrat'];
        $this->caution             = (float)($row['caution'] ?? 0);
    }

    public static function find(PDO $pdo, int $id): ?self
    {
        $stmt = $pdo->prepare("SELECT * FROM contrats WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? new self($row) : null;
    }

    /** @return self[] */
    public static function byLocataire(PDO $pdo, int $locataire_id): array
    {
        $stmt = $pdo->prepare("SELECT * FROM contrats WHERE locataire_id = ? ORDER BY statut_contrat, date_debut DESC");
        $stmt->execute([$locataire_id]);
        return array_map(fn($r) => new self($r), $stmt->fetchAll());
    }

    /** Contrats actifs dont l'échéance est dans $jours jours ou moins. @return self[] */
    public static function imminents(PDO $pdo, int $jours = 7): array
    {
        $stmt = $pdo->prepare(
            "SELECT * FROM contrats
             WHERE statut_contrat = 'actif'
               AND date_prochain_loyer <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
             ORDER BY date_prochain_loyer"
        );
        $stmt->execute([$jours]);
        return array_map(fn($r) => new self($r), $stmt->fetchAll());
    }

    public function estActif(): bool
    {
        return $this->statut_contrat === 'actif';
    }

    public function joursRestants(): int
    {
        if (!$this->date_prochain_loyer) {
            return PHP_INT_MAX;
        }
        $diff = (new DateTime($this->date_prochain_loyer))->diff(new DateTime('today'));
        return $diff->invert ? -$diff->days : $diff->days;
    }

    public function updateLoyer(PDO $pdo, float $nouveau_loyer): void
    {
        $stmt = $pdo->prepare("UPDATE contrats SET loyer_mensuel = ? WHERE id = ?");
        $stmt->execute([$nouveau_loyer, $this->id]);
        $this->loyer_mensuel = $nouveau_loyer;
    }

    public function resilier(PDO $pdo, string $date_fin): void
    {
        $stmt = $pdo->prepare("UPDATE contrats SET statut_contrat = 'termine', date_fin = ? WHERE id = ?");
        $stmt->execute([$date_fin, $this->id]);
        $this->statut_contrat = 'termine';
        $this->date_fin       = $date_fin;
    }
}
