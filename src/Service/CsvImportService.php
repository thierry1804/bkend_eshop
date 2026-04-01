<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\BudgetItem;
use App\Entity\Categorie;
use App\Entity\Depense;
use App\Repository\CategorieRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Import CSV des dépenses et des lignes budgétaires.
 */
class CsvImportService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CategorieRepository $categorieRepository,
    ) {
    }

    /**
     * @return array{imported: int, errors: list<string>}
     */
    public function importDepenses(string $csvContent): array
    {
        $imported = 0;
        $errors = [];
        $lineNo = 0;
        foreach ($this->splitLines($csvContent) as $line) {
            ++$lineNo;
            $line = trim($line);
            if ('' === $line) {
                continue;
            }
            $cols = str_getcsv($line);
            if (\count($cols) < 5) {
                $errors[] = sprintf('Ligne %d : au moins 5 colonnes attendues.', $lineNo);

                continue;
            }
            $dateStr = trim((string) $cols[0]);
            $produit = trim((string) $cols[1]);
            $qStr = trim((string) $cols[2]);
            $unite = trim((string) $cols[3]);
            $puStr = trim((string) $cols[4]);
            $categorieCode = isset($cols[5]) ? trim((string) $cols[5]) : 'food';
            try {
                $date = new \DateTimeImmutable($dateStr);
            } catch (\Exception) {
                $errors[] = sprintf('Ligne %d : date invalide (%s).', $lineNo, $dateStr);

                continue;
            }
            $quantite = (float) str_replace(',', '.', $qStr);
            $prixUnitaire = (int) round((float) str_replace(',', '.', $puStr));
            if ($quantite <= 0 || $prixUnitaire < 0) {
                $errors[] = sprintf('Ligne %d : quantité ou prix invalide.', $lineNo);

                continue;
            }
            if (!$this->categorieExists($categorieCode)) {
                $errors[] = sprintf('Ligne %d : catégorie inconnue (%s).', $lineNo, $categorieCode);

                continue;
            }
            $d = new Depense();
            $d->setDate($date);
            $d->setProduit($produit);
            $d->setCategorieCode($categorieCode);
            $d->setQuantite($quantite);
            $d->setUnite('' === $unite ? null : $unite);
            $d->setPrixUnitaire($prixUnitaire);
            $d->computeMontant();
            $this->entityManager->persist($d);
            ++$imported;
        }
        $this->entityManager->flush();

        return ['imported' => $imported, 'errors' => $errors];
    }

    /**
     * @return array{imported: int, errors: list<string>}
     */
    public function importBudgetItems(string $csvContent): array
    {
        $imported = 0;
        $errors = [];
        $lineNo = 0;
        foreach ($this->splitLines($csvContent) as $line) {
            ++$lineNo;
            $line = trim($line);
            if ('' === $line) {
                continue;
            }
            $cols = str_getcsv($line);
            if (\count($cols) < 6) {
                $errors[] = sprintf('Ligne %d : au moins 6 colonnes attendues.', $lineNo);

                continue;
            }
            $nom = trim((string) $cols[0]);
            $categorieCode = trim((string) $cols[1]);
            $periodicite = trim((string) $cols[2]);
            $qStr = trim((string) $cols[3]);
            $unite = trim((string) $cols[4]);
            $puStr = trim((string) $cols[5]);
            $allowed = ['MOIS', 'SEMAINE', '2_SEMAINES', 'TRIMESTRE', 'ANNEE'];
            if (!\in_array($periodicite, $allowed, true)) {
                $errors[] = sprintf('Ligne %d : périodicité invalide.', $lineNo);

                continue;
            }
            $cat = $this->categorieRepository->findOneBy(['code' => $categorieCode]);
            if (!$cat instanceof Categorie) {
                $errors[] = sprintf('Ligne %d : catégorie inconnue (%s).', $lineNo, $categorieCode);

                continue;
            }
            $quantite = (float) str_replace(',', '.', $qStr);
            $prixUnitaire = (int) round((float) str_replace(',', '.', $puStr));
            if ($quantite <= 0 || $prixUnitaire < 0) {
                $errors[] = sprintf('Ligne %d : quantité ou prix invalide.', $lineNo);

                continue;
            }
            $b = new BudgetItem();
            $b->setNom($nom);
            $b->setCategorie($cat);
            $b->setPeriodicite($periodicite);
            $b->setQuantite($quantite);
            $b->setUnite('' === $unite ? null : $unite);
            $b->setPrixUnitaire($prixUnitaire);
            if (isset($cols[6])) {
                $v = strtolower(trim((string) $cols[6]));
                $b->setActif(\in_array($v, ['1', 'true', 'oui', 'yes'], true));
            }
            $this->entityManager->persist($b);
            ++$imported;
        }
        $this->entityManager->flush();

        return ['imported' => $imported, 'errors' => $errors];
    }

    /**
     * @return list<string>
     */
    private function splitLines(string $csvContent): array
    {
        $csvContent = str_replace(["\r\n", "\r"], "\n", $csvContent);

        return explode("\n", $csvContent);
    }

    private function categorieExists(string $code): bool
    {
        return null !== $this->categorieRepository->findOneBy(['code' => $code]);
    }
}
