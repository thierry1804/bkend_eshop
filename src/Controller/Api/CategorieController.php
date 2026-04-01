<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Api\ApiView;
use App\Entity\Categorie;
use App\Repository\CategorieRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/categories')]
class CategorieController extends AbstractController
{
    use JsonApiTrait;

    public function __construct(
        private readonly CategorieRepository $repository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('', name: 'api_categories_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $rows = $this->repository->findBy([], ['ordre' => 'ASC', 'code' => 'ASC']);

        return $this->jsonList(array_map(static fn (Categorie $c) => ApiView::categorie($c), $rows));
    }

    #[Route('/{id}', name: 'api_categories_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getOne(int $id): JsonResponse
    {
        $c = $this->repository->find($id);
        if (!$c instanceof Categorie) {
            throw new NotFoundHttpException('Catégorie introuvable.');
        }

        return $this->jsonData(ApiView::categorie($c));
    }

    #[Route('', name: 'api_categories_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = $this->decodeJson($request);
        $c = new Categorie();
        $this->applyCategorie($c, $data);
        $errors = $this->validator->validate($c);
        if (\count($errors) > 0) {
            return $this->jsonViolations($errors);
        }
        $this->entityManager->persist($c);
        $this->entityManager->flush();

        return $this->jsonData(ApiView::categorie($c), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_categories_replace', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function replace(int $id, Request $request): JsonResponse
    {
        $c = $this->repository->find($id);
        if (!$c instanceof Categorie) {
            throw new NotFoundHttpException('Catégorie introuvable.');
        }
        $this->applyCategorie($c, $this->decodeJson($request));
        $errors = $this->validator->validate($c);
        if (\count($errors) > 0) {
            return $this->jsonViolations($errors);
        }
        $this->entityManager->flush();

        return $this->jsonData(ApiView::categorie($c));
    }

    #[Route('/{id}', name: 'api_categories_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        $c = $this->repository->find($id);
        if (!$c instanceof Categorie) {
            throw new NotFoundHttpException('Catégorie introuvable.');
        }
        $this->entityManager->remove($c);
        $this->entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function applyCategorie(Categorie $c, array $data): void
    {
        if (isset($data['code'])) {
            $c->setCode((string) $data['code']);
        }
        if (isset($data['libelle'])) {
            $c->setLibelle((string) $data['libelle']);
        }
        if (\array_key_exists('couleur', $data)) {
            $c->setCouleur((string) $data['couleur']);
        }
        if (\array_key_exists('icone', $data)) {
            $c->setIcone(null !== $data['icone'] ? (string) $data['icone'] : null);
        }
        if (isset($data['ordre'])) {
            $c->setOrdre((int) $data['ordre']);
        }
    }

    /**
     * @param list<mixed> $items
     */
    private function jsonList(array $items): JsonResponse
    {
        return new JsonResponse($items, Response::HTTP_OK, [], false);
    }
}
