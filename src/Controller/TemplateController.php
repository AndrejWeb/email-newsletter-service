<?php

namespace App\Controller;

use App\Repository\TemplateRepository;
use App\Service\TemplateService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/templates')]
class TemplateController extends ApiController
{
    public function __construct(
        private TemplateService $templateService,
        private TemplateRepository $templateRepository,
    ) {}

    #[Route('', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $templates = $this->templateRepository->findAllOrderedByDate();

        $data = array_map(fn($t) => [
            'id' => $t->getId(),
            'name' => $t->getName(),
            'subject' => $t->getSubject(),
            'content' => $t->getContent(),
            'blockCount' => is_array($t->getContent()) ? count($t->getContent()) : 0,
            'category' => $t->getCategory(),
            'isDefault' => $t->isDefault(),
            'createdAt' => $t->getCreatedAt()->format('c'),
            'updatedAt' => $t->getUpdatedAt()->format('c'),
        ], $templates);

        return $this->jsonResponse($data);
    }

    #[Route('/{id}', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): JsonResponse
    {
        $template = $this->templateRepository->find($id);
        if (!$template) {
            return $this->errorResponse('Template not found.', 404);
        }

        return $this->jsonResponse([
            'id' => $template->getId(),
            'name' => $template->getName(),
            'subject' => $template->getSubject(),
            'content' => $template->getContent(),
            'htmlContent' => $template->getHtmlContent(),
            'category' => $template->getCategory(),
            'isDefault' => $template->isDefault(),
            'createdAt' => $template->getCreatedAt()->format('c'),
            'updatedAt' => $template->getUpdatedAt()->format('c'),
        ]);
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['name'])) {
            return $this->errorResponse('Name is required.', 422);
        }

        $template = $this->templateService->create(
            $data['name'],
            $data['subject'] ?? null,
            $data['content'] ?? [],
            $data['category'] ?? 'general',
        );

        return $this->jsonResponse([
            'id' => $template->getId(),
            'name' => $template->getName(),
            'subject' => $template->getSubject(),
            'content' => $template->getContent(),
            'htmlContent' => $template->getHtmlContent(),
            'category' => $template->getCategory(),
            'isDefault' => $template->isDefault(),
            'createdAt' => $template->getCreatedAt()->format('c'),
            'updatedAt' => $template->getUpdatedAt()->format('c'),
        ], 201);
    }

    #[Route('/{id}', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $template = $this->templateRepository->find($id);
        if (!$template) {
            return $this->errorResponse('Template not found.', 404);
        }

        $data = json_decode($request->getContent(), true);
        $template = $this->templateService->update($template, $data);

        return $this->jsonResponse([
            'id' => $template->getId(),
            'name' => $template->getName(),
            'subject' => $template->getSubject(),
            'content' => $template->getContent(),
            'htmlContent' => $template->getHtmlContent(),
            'category' => $template->getCategory(),
            'isDefault' => $template->isDefault(),
            'createdAt' => $template->getCreatedAt()->format('c'),
            'updatedAt' => $template->getUpdatedAt()->format('c'),
        ]);
    }

    #[Route('/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        $template = $this->templateRepository->find($id);
        if (!$template) {
            return $this->errorResponse('Template not found.', 404);
        }

        $this->templateService->delete($template);
        return $this->jsonResponse(['message' => 'Template deleted.']);
    }

    #[Route('/{id}/preview', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function preview(int $id): Response
    {
        $template = $this->templateRepository->find($id);
        if (!$template) {
            return $this->errorResponse('Template not found.', 404);
        }

        $html = $this->templateService->preview($template->getContent());

        return new Response($html, 200, ['Content-Type' => 'text/html']);
    }

    #[Route('/{id}/duplicate', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function duplicate(int $id): JsonResponse
    {
        $template = $this->templateRepository->find($id);
        if (!$template) {
            return $this->errorResponse('Template not found.', 404);
        }

        $copy = $this->templateService->duplicate($template);

        return $this->jsonResponse([
            'id' => $copy->getId(),
            'name' => $copy->getName(),
            'subject' => $copy->getSubject(),
            'content' => $copy->getContent(),
            'htmlContent' => $copy->getHtmlContent(),
            'category' => $copy->getCategory(),
            'isDefault' => $copy->isDefault(),
            'createdAt' => $copy->getCreatedAt()->format('c'),
            'updatedAt' => $copy->getUpdatedAt()->format('c'),
        ], 201);
    }
}
