<?php

namespace App\Controller;

use App\Service\ImageService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/images')]
class ImageController extends AbstractController
{
    public function __construct(
        private ImageService $imageService,
        private RateLimiterFactory $imageUploadLimiter,
        private string $apiKey,
    ) {}

    #[Route('', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        if (!$this->isAuthenticated($request)) {
            return $this->json(['error' => 'Unauthorized.'], 401);
        }

        $limiter = $this->imageUploadLimiter->create($request->getClientIp());
        if (!$limiter->consume()->isAccepted()) {
            return $this->json(['error' => 'Too many requests. Please try again later.'], 429);
        }

        $files = $request->files->get('images');
        if (!$files) {
            $file = $request->files->get('image');
            $files = $file ? [$file] : [];
        }

        if (empty($files)) {
            return $this->json(['error' => 'No image file provided. Use "image" or "images[]" field.'], 400);
        }

        // Ensure it's always an array
        if (!is_array($files)) {
            $files = [$files];
        }

        try {
            $urls = [];
            foreach ($files as $file) {
                $urls[] = $this->imageService->upload($file);
            }

            return $this->json([
                'urls' => $urls,
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => 'Upload failed.'], 500);
        }
    }

    #[Route('/{filename}', methods: ['GET'])]
    public function show(string $filename): BinaryFileResponse|JsonResponse
    {
        try {
            $path = $this->imageService->getAbsolutePath($filename);

            $response = new BinaryFileResponse($path);
            $response->headers->set('Content-Type', 'image/webp');
            $response->headers->set('Cache-Control', 'public, max-age=31536000, immutable');

            return $response;
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        if (!$this->isAuthenticated($request)) {
            return $this->json(['error' => 'Unauthorized.'], 401);
        }

        return $this->json($this->imageService->list());
    }

    #[Route('/{filename}', methods: ['POST'])]
    public function replace(Request $request, string $filename): JsonResponse
    {
        if (!$this->isAuthenticated($request)) {
            return $this->json(['error' => 'Unauthorized.'], 401);
        }

        $limiter = $this->imageUploadLimiter->create($request->getClientIp());
        if (!$limiter->consume()->isAccepted()) {
            return $this->json(['error' => 'Too many requests. Please try again later.'], 429);
        }

        $file = $request->files->get('image');
        if (!$file) {
            return $this->json(['error' => 'No image file provided. Use "image" field.'], 400);
        }

        try {
            $url = $this->imageService->replace($filename, $file);

            return $this->json(['url' => $url]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            $code = $e->getCode() ?: 500;
            return $this->json(['error' => $e->getMessage()], $code);
        }
    }

    #[Route('/{filename}', methods: ['DELETE'])]
    public function delete(Request $request, string $filename): JsonResponse
    {
        if (!$this->isAuthenticated($request)) {
            return $this->json(['error' => 'Unauthorized.'], 401);
        }

        try {
            $this->imageService->delete($filename);

            return $this->json(['status' => 'deleted']);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            $code = $e->getCode() ?: 500;
            return $this->json(['error' => $e->getMessage()], $code);
        }
    }

    private function isAuthenticated(Request $request): bool
    {
        $token = $request->headers->get('X-API-Key');

        return $token !== null && hash_equals($this->apiKey, $token);
    }
}
