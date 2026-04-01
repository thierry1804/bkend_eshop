<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Api\ApiView;
use App\Entity\Depense;
use App\Repository\BudgetItemRepository;
use App\Repository\DepenseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/depenses')]
class DepenseController extends AbstractController
{
    use JsonApiTrait;

    private const ITEMS_PER_PAGE = 50;

    public function __construct(
        private readonly DepenseRepository $repository,
        private readonly BudgetItemRepository $budgetItemRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('', name: 'api_depenses_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        [$after, $before, $strictAfter, $strictBefore] = $this->parseDateBounds($request);
        $categorieCode = $request->query->get('categorieCode');
        $produit = $request->query->get('produit');
        $order = $request->query->all('order');
        $orderField = \is_array($order) ? ($order['date'] ?? $order['montant'] ?? null) : null;
        $orderField = \in_array($orderField, ['date', 'montant'], true) ? $orderField : 'date';
        $orderDir = $request->query->get('orderDir', 'desc');
        $page = max(1, (int) $request->query->get('page', 1));
        $itemsPerPage = min(100, max(1, (int) $request->query->get('itemsPerPage', self::ITEMS_PER_PAGE)));

        $result = $this->repository->findFiltered(
            $after,
            $before,
            $strictAfter,
            $strictBefore,
            \is_string($categorieCode) ? $categorieCode : null,
            \is_string($produit) ? $produit : null,
            $orderField,
            \is_string($orderDir) ? $orderDir : 'desc',
            $page,
            $itemsPerPage,
        );

        $member = array_map(static fn (Depense $d) => ApiView::depense($d), $result['items']);

        return new JsonResponse([
            'member' => $member,
            'total' => $result['total'],
            'page' => $page,
            'itemsPerPage' => $itemsPerPage,
        ]);
    }

    #[Route('/{id}', name: 'api_depenses_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getOne(int $id): JsonResponse
    {
        $d = $this->repository->find($id);
        if (!$d instanceof Depense) {
            throw new NotFoundHttpException('Dépense introuvable.');
        }

        return $this->jsonData(ApiView::depense($d));
    }

    #[Route('', name: 'api_depenses_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = $this->decodeJson($request);
        $d = new Depense();
        $this->applyDepense($d, $data, true);
        $errors = $this->validator->validate($d);
        if (\count($errors) > 0) {
            return $this->jsonViolations($errors);
        }
        $this->entityManager->persist($d);
        $this->entityManager->flush();

        return $this->jsonData(ApiView::depense($d), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_depenses_replace', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function replace(int $id, Request $request): JsonResponse
    {
        $d = $this->repository->find($id);
        if (!$d instanceof Depense) {
            throw new NotFoundHttpException('Dépense introuvable.');
        }
        $this->applyDepense($d, $this->decodeJson($request), true);
        $errors = $this->validator->validate($d);
        if (\count($errors) > 0) {
            return $this->jsonViolations($errors);
        }
        $this->entityManager->flush();

        return $this->jsonData(ApiView::depense($d));
    }

    #[Route('/{id}', name: 'api_depenses_patch', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function patch(int $id, Request $request): JsonResponse
    {
        $d = $this->repository->find($id);
        if (!$d instanceof Depense) {
            throw new NotFoundHttpException('Dépense introuvable.');
        }
        $this->applyDepense($d, $this->decodeJson($request), false);
        $errors = $this->validator->validate($d);
        if (\count($errors) > 0) {
            return $this->jsonViolations($errors);
        }
        $this->entityManager->flush();

        return $this->jsonData(ApiView::depense($d));
    }

    #[Route('/{id}', name: 'api_depenses_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        $d = $this->repository->find($id);
        if (!$d instanceof Depense) {
            throw new NotFoundHttpException('Dépense introuvable.');
        }
        $this->entityManager->remove($d);
        $this->entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @return array{0: ?\DateTimeImmutable, 1: ?\DateTimeImmutable, 2: ?\DateTimeImmutable, 3: ?\DateTimeImmutable}
     */
    private function parseDateBounds(Request $request): array
    {
        $bag = $request->query->all('date');
        $bag = \is_array($bag) ? $bag : [];
        $after = isset($bag['after']) ? $this->parseDay($bag['after']) : null;
        $before = isset($bag['before']) ? $this->parseDay($bag['before']) : null;
        $strictAfter = isset($bag['strictly_after']) ? $this->parseDay($bag['strictly_after']) : null;
        $strictBefore = isset($bag['strictly_before']) ? $this->parseDay($bag['strictly_before']) : null;

        return [$after, $before, $strictAfter, $strictBefore];
    }

    private function parseDay(mixed $v): ?\DateTimeImmutable
    {
        if (!\is_string($v) || '' === $v) {
            return null;
        }
        try {
            return new \DateTimeImmutable($v);
        } catch (\Exception) {
            throw new BadRequestHttpException(sprintf('Date invalide: %s', $v));
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function applyDepense(Depense $d, array $data, bool $setAll): void
    {
        if ($setAll || isset($data['date'])) {
            if (isset($data['date'])) {
                $d->setDate(new \DateTimeImmutable((string) $data['date']));
            }
        }
        if ($setAll || isset($data['produit'])) {
            $d->setProduit(isset($data['produit']) ? (string) $data['produit'] : $d->getProduit());
        }
        if (\array_key_exists('budgetItemId', $data) || ($setAll && \array_key_exists('budgetItemId', $data))) {
            if (null === ($data['budgetItemId'] ?? null)) {
                $d->setBudgetItem(null);
            } else {
                $bi = $this->budgetItemRepository->find((int) $data['budgetItemId']);
                if (null === $bi) {
                    throw new NotFoundHttpException('Poste budgétaire introuvable.');
                }
                $d->setBudgetItem($bi);
            }
        }
        if ($setAll || \array_key_exists('categorieCode', $data)) {
            $d->setCategorieCode(\array_key_exists('categorieCode', $data) ? (string) $data['categorieCode'] : $d->getCategorieCode());
        }
        if ($setAll || \array_key_exists('quantite', $data)) {
            $d->setQuantite(isset($data['quantite']) ? (float) $data['quantite'] : $d->getQuantite());
        }
        if ($setAll || \array_key_exists('unite', $data)) {
            $d->setUnite(isset($data['unite']) ? (string) $data['unite'] : null);
        }
        if ($setAll || isset($data['prixUnitaire'])) {
            $d->setPrixUnitaire(isset($data['prixUnitaire']) ? (int) $data['prixUnitaire'] : $d->getPrixUnitaire());
        }
        if ($setAll || \array_key_exists('note', $data)) {
            $d->setNote(\array_key_exists('note', $data) && null !== $data['note'] ? (string) $data['note'] : null);
        }
        $d->computeMontant();
    }
}
