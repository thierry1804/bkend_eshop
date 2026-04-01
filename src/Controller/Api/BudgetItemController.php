<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Api\ApiView;
use App\Entity\BudgetItem;
use App\Repository\BudgetItemRepository;
use App\Repository\CategorieRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/budget-items')]
class BudgetItemController extends AbstractController
{
    use JsonApiTrait;

    public function __construct(
        private readonly BudgetItemRepository $repository,
        private readonly CategorieRepository $categorieRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('', name: 'api_budget_items_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $rows = $this->repository->findBy([], ['id' => 'ASC']);

        return new JsonResponse(array_map(static fn (BudgetItem $b) => ApiView::budgetItem($b), $rows));
    }

    #[Route('/{id}', name: 'api_budget_items_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getOne(int $id): JsonResponse
    {
        $b = $this->repository->find($id);
        if (!$b instanceof BudgetItem) {
            throw new NotFoundHttpException('Poste budgétaire introuvable.');
        }

        return $this->jsonData(ApiView::budgetItem($b));
    }

    #[Route('', name: 'api_budget_items_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = $this->decodeJson($request);
        if (!isset($data['categorieId'])) {
            return new JsonResponse(['error' => 'categorieId est requis.'], Response::HTTP_BAD_REQUEST);
        }
        $b = new BudgetItem();
        $this->applyBudgetItem($b, $data, true);
        $errors = $this->validator->validate($b);
        if (\count($errors) > 0) {
            return $this->jsonViolations($errors);
        }
        $this->entityManager->persist($b);
        $this->entityManager->flush();

        return $this->jsonData(ApiView::budgetItem($b), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_budget_items_replace', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function replace(int $id, Request $request): JsonResponse
    {
        $b = $this->repository->find($id);
        if (!$b instanceof BudgetItem) {
            throw new NotFoundHttpException('Poste budgétaire introuvable.');
        }
        $this->applyBudgetItem($b, $this->decodeJson($request), true);
        $errors = $this->validator->validate($b);
        if (\count($errors) > 0) {
            return $this->jsonViolations($errors);
        }
        $this->entityManager->flush();

        return $this->jsonData(ApiView::budgetItem($b));
    }

    #[Route('/{id}', name: 'api_budget_items_patch', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function patch(int $id, Request $request): JsonResponse
    {
        $b = $this->repository->find($id);
        if (!$b instanceof BudgetItem) {
            throw new NotFoundHttpException('Poste budgétaire introuvable.');
        }
        $this->applyBudgetItem($b, $this->decodeJson($request), false);
        $errors = $this->validator->validate($b);
        if (\count($errors) > 0) {
            return $this->jsonViolations($errors);
        }
        $this->entityManager->flush();

        return $this->jsonData(ApiView::budgetItem($b));
    }

    #[Route('/{id}', name: 'api_budget_items_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        $b = $this->repository->find($id);
        if (!$b instanceof BudgetItem) {
            throw new NotFoundHttpException('Poste budgétaire introuvable.');
        }
        $this->entityManager->remove($b);
        $this->entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function applyBudgetItem(BudgetItem $b, array $data, bool $setAll): void
    {
        if ($setAll || isset($data['nom'])) {
            $b->setNom(isset($data['nom']) ? (string) $data['nom'] : $b->getNom());
        }
        $catId = $data['categorieId'] ?? null;
        if (null !== $catId) {
            $cat = $this->categorieRepository->find((int) $catId);
            if (null === $cat) {
                throw new NotFoundHttpException('Catégorie introuvable.');
            }
            $b->setCategorie($cat);
        }
        if ($setAll || isset($data['periodicite'])) {
            $b->setPeriodicite(isset($data['periodicite']) ? (string) $data['periodicite'] : $b->getPeriodicite());
        }
        if ($setAll || \array_key_exists('quantite', $data)) {
            $b->setQuantite(isset($data['quantite']) ? (float) $data['quantite'] : $b->getQuantite());
        }
        if ($setAll || \array_key_exists('unite', $data)) {
            $b->setUnite(isset($data['unite']) && null !== $data['unite'] ? (string) $data['unite'] : null);
        }
        if ($setAll || isset($data['prixUnitaire'])) {
            $b->setPrixUnitaire(isset($data['prixUnitaire']) ? (int) $data['prixUnitaire'] : $b->getPrixUnitaire());
        }
        if ($setAll || \array_key_exists('frequence', $data)) {
            $raw = $data['frequence'] ?? null;
            if (null !== $raw && '' !== $raw) {
                $b->setFrequence((int) $raw);
            } elseif ($setAll) {
                $b->setFrequence(1);
            }
        }
        if ($setAll || \array_key_exists('actif', $data)) {
            $b->setActif(\array_key_exists('actif', $data) ? (bool) $data['actif'] : $b->isActif());
        }
    }
}
