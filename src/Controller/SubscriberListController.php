<?php

namespace App\Controller;

use App\Repository\SubscriberListRepository;
use App\Service\SubscriberListService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/lists')]
class SubscriberListController extends ApiController
{
    public function __construct(
        private SubscriberListService $listService,
        private SubscriberListRepository $listRepository,
    ) {}

    #[Route('', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $lists = $this->listRepository->findAllWithCounts();

        $data = array_map(fn($list) => [
            'id' => $list->getId(),
            'name' => $list->getName(),
            'description' => $list->getDescription(),
            'isDefault' => $list->isDefault(),
            'subscriberCount' => $list->getSubscriberCount(),
            'createdAt' => $list->getCreatedAt()->format('c'),
            'updatedAt' => $list->getUpdatedAt()->format('c'),
        ], $lists);

        return $this->jsonResponse($data);
    }

    #[Route('/{id}', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id, Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = max(1, min(100, (int) $request->query->get('limit', 20)));

        $result = $this->listRepository->findByIdWithSubscribers($id, $page, $limit);
        if (!$result) {
            return $this->errorResponse('List not found.', 404);
        }

        $list = $result['list'];
        $subscribers = array_map(fn($s) => [
            'id' => $s->getId(),
            'email' => $s->getEmail(),
            'firstName' => $s->getFirstName(),
            'lastName' => $s->getLastName(),
            'status' => $s->getStatus()->value,
            'subscribedAt' => $s->getSubscribedAt()->format('c'),
        ], $result['subscribers']);

        $data = [
            'id' => $list->getId(),
            'name' => $list->getName(),
            'description' => $list->getDescription(),
            'isDefault' => $list->isDefault(),
            'subscriberCount' => $list->getSubscriberCount(),
            'createdAt' => $list->getCreatedAt()->format('c'),
            'updatedAt' => $list->getUpdatedAt()->format('c'),
            'subscribers' => $subscribers,
        ];

        return new JsonResponse([
            'data' => $data,
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $result['total'],
                'totalPages' => (int) ceil($result['total'] / $limit),
            ],
        ]);
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['name'])) {
            return $this->errorResponse('Name is required.', 422);
        }

        $list = $this->listService->create(
            $data['name'],
            $data['description'] ?? null,
            $data['isDefault'] ?? false,
        );

        return $this->jsonResponse([
            'id' => $list->getId(),
            'name' => $list->getName(),
            'description' => $list->getDescription(),
            'isDefault' => $list->isDefault(),
            'subscriberCount' => $list->getSubscriberCount(),
            'createdAt' => $list->getCreatedAt()->format('c'),
            'updatedAt' => $list->getUpdatedAt()->format('c'),
        ], 201);
    }

    #[Route('/{id}', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $list = $this->listRepository->find($id);
        if (!$list) {
            return $this->errorResponse('List not found.', 404);
        }

        $data = json_decode($request->getContent(), true);
        $list = $this->listService->update($list, $data);

        return $this->jsonResponse([
            'id' => $list->getId(),
            'name' => $list->getName(),
            'description' => $list->getDescription(),
            'isDefault' => $list->isDefault(),
            'subscriberCount' => $list->getSubscriberCount(),
            'createdAt' => $list->getCreatedAt()->format('c'),
            'updatedAt' => $list->getUpdatedAt()->format('c'),
        ]);
    }

    #[Route('/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        $list = $this->listRepository->find($id);
        if (!$list) {
            return $this->errorResponse('List not found.', 404);
        }

        $this->listService->delete($list);
        return $this->jsonResponse(['message' => 'List deleted.']);
    }
}
