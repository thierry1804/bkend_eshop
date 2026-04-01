<?php

declare(strict_types=1);

namespace App\Controller\Api;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolationListInterface;

trait JsonApiTrait
{
    /**
     * @return array<string, mixed>
     */
    protected function decodeJson(Request $request): array
    {
        $raw = $request->getContent();
        if ('' === $raw) {
            return [];
        }
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return \is_array($decoded) ? $decoded : [];
    }

    protected function jsonViolations(ConstraintViolationListInterface $violations): JsonResponse
    {
        $out = [];
        foreach ($violations as $v) {
            $out[] = ['field' => $v->getPropertyPath(), 'message' => $v->getMessage()];
        }

        return new JsonResponse(['violations' => $out], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function jsonData(array $data, int $status = Response::HTTP_OK): JsonResponse
    {
        return new JsonResponse($data, $status, [], false);
    }
}
