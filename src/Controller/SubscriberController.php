<?php

namespace App\Controller;

use App\Enum\UserRole;
use App\Repository\SubscriberRepository;
use App\Repository\SubscriberListRepository;
use App\Repository\TagRepository;
use App\Service\SubscriberService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/subscribers')]
class SubscriberController extends ApiController
{
    public function __construct(
        private SubscriberService $subscriberService,
        private SubscriberRepository $subscriberRepository,
        private SubscriberListRepository $listRepository,
        private TagRepository $tagRepository,
        private LoggerInterface $logger,
    ) {}

    #[Route('/stats', methods: ['GET'], priority: 10)]
    public function stats(): JsonResponse
    {
        $counts = $this->subscriberRepository->getStatusCounts();
        return $this->jsonResponse($counts);
    }

    #[Route('/import', methods: ['POST'], priority: 10)]
    public function import(Request $request): JsonResponse
    {
        $user = $this->getCurrentUser();
        if (!$user || $user->getRole() !== UserRole::Admin) {
            return $this->errorResponse('Only administrators can import subscribers.', 403);
        }

        $file = $request->files->get('file');
        if (!$file) {
            return $this->errorResponse('No file uploaded. Use multipart/form-data with a "file" field.', 422);
        }

        $csvContent = file_get_contents($file->getPathname());
        $result = $this->subscriberService->importFromCsv($csvContent);

        return $this->jsonResponse($result);
    }

    #[Route('/export', methods: ['GET'], priority: 10)]
    public function export(Request $request): Response
    {
        $user = $this->getCurrentUser();
        if (!$user || $user->getRole() !== UserRole::Admin) {
            return $this->errorResponse('Only administrators can export subscribers.', 403);
        }

        $listId = $request->query->get('list_id') ? (int) $request->query->get('list_id') : null;

        $this->logger->info('Subscriber export', [
            'user_id' => $user->getId(),
            'user_email' => $user->getEmail(),
            'list_id' => $listId,
            'timestamp' => (new \DateTimeImmutable())->format('c'),
        ]);

        $csv = $this->subscriberService->exportToCsv($listId);

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="subscribers.csv"',
        ]);
    }

    #[Route('', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $status = $request->query->get('status');
        $listId = $request->query->get('list_id') ? (int) $request->query->get('list_id') : null;
        $tagId = $request->query->get('tag_id') ? (int) $request->query->get('tag_id') : null;
        $search = $request->query->get('search');
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = max(1, min(100, (int) $request->query->get('limit', 20)));

        $subscribers = $this->subscriberRepository->findByFilters($status, $listId, $tagId, $search, $page, $limit);
        $total = $this->subscriberRepository->countByFilters($status, $listId, $tagId, $search);

        $data = array_map(fn($s) => $this->serializeSubscriber($s), $subscribers);

        return $this->paginatedResponse($data, $page, $limit, $total);
    }

    #[Route('/{id}', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): JsonResponse
    {
        $subscriber = $this->subscriberRepository->find($id);
        if (!$subscriber) {
            return $this->errorResponse('Subscriber not found.', 404);
        }

        return $this->jsonResponse($this->serializeSubscriberDetail($subscriber));
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['email'])) {
            return $this->errorResponse('Email is required.', 422);
        }

        try {
            $subscriber = $this->subscriberService->create(
                $data['email'],
                $data['firstName'] ?? null,
                $data['lastName'] ?? null,
                $data['listIds'] ?? [],
                $data['tagIds'] ?? [],
                $data['metadata'] ?? null,
            );

            return $this->jsonResponse($this->serializeSubscriberDetail($subscriber), 201);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 409);
        }
    }

    #[Route('/{id}', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $subscriber = $this->subscriberRepository->find($id);
        if (!$subscriber) {
            return $this->errorResponse('Subscriber not found.', 404);
        }

        $data = json_decode($request->getContent(), true);

        try {
            $subscriber = $this->subscriberService->update($subscriber, $data);
            return $this->jsonResponse($this->serializeSubscriberDetail($subscriber));
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 409);
        }
    }

    #[Route('/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        $user = $this->getCurrentUser();
        if (!$user || $user->getRole() !== UserRole::Admin) {
            return $this->errorResponse('Only administrators can delete subscribers.', 403);
        }

        $subscriber = $this->subscriberRepository->find($id);
        if (!$subscriber) {
            return $this->errorResponse('Subscriber not found.', 404);
        }

        $this->subscriberService->delete($subscriber);
        return $this->jsonResponse(['message' => 'Subscriber deleted.']);
    }

    #[Route('/{id}/lists/{listId}', methods: ['POST'], requirements: ['id' => '\d+', 'listId' => '\d+'])]
    public function addToList(int $id, int $listId): JsonResponse
    {
        $subscriber = $this->subscriberRepository->find($id);
        if (!$subscriber) {
            return $this->errorResponse('Subscriber not found.', 404);
        }

        $list = $this->listRepository->find($listId);
        if (!$list) {
            return $this->errorResponse('List not found.', 404);
        }

        $this->subscriberService->addToList($subscriber, $list);
        return $this->jsonResponse(['message' => 'Subscriber added to list.']);
    }

    #[Route('/{id}/lists/{listId}', methods: ['DELETE'], requirements: ['id' => '\d+', 'listId' => '\d+'])]
    public function removeFromList(int $id, int $listId): JsonResponse
    {
        $subscriber = $this->subscriberRepository->find($id);
        if (!$subscriber) {
            return $this->errorResponse('Subscriber not found.', 404);
        }

        $list = $this->listRepository->find($listId);
        if (!$list) {
            return $this->errorResponse('List not found.', 404);
        }

        $this->subscriberService->removeFromList($subscriber, $list);
        return $this->jsonResponse(['message' => 'Subscriber removed from list.']);
    }

    #[Route('/{id}/tags/{tagId}', methods: ['POST'], requirements: ['id' => '\d+', 'tagId' => '\d+'])]
    public function addTag(int $id, int $tagId): JsonResponse
    {
        $subscriber = $this->subscriberRepository->find($id);
        if (!$subscriber) {
            return $this->errorResponse('Subscriber not found.', 404);
        }

        $tag = $this->tagRepository->find($tagId);
        if (!$tag) {
            return $this->errorResponse('Tag not found.', 404);
        }

        $this->subscriberService->addTag($subscriber, $tag);
        return $this->jsonResponse(['message' => 'Tag added to subscriber.']);
    }

    #[Route('/{id}/tags/{tagId}', methods: ['DELETE'], requirements: ['id' => '\d+', 'tagId' => '\d+'])]
    public function removeTag(int $id, int $tagId): JsonResponse
    {
        $subscriber = $this->subscriberRepository->find($id);
        if (!$subscriber) {
            return $this->errorResponse('Subscriber not found.', 404);
        }

        $tag = $this->tagRepository->find($tagId);
        if (!$tag) {
            return $this->errorResponse('Tag not found.', 404);
        }

        $this->subscriberService->removeTag($subscriber, $tag);
        return $this->jsonResponse(['message' => 'Tag removed from subscriber.']);
    }

    private function serializeSubscriber(mixed $subscriber): array
    {
        return [
            'id' => $subscriber->getId(),
            'email' => $subscriber->getEmail(),
            'firstName' => $subscriber->getFirstName(),
            'lastName' => $subscriber->getLastName(),
            'status' => $subscriber->getStatus()->value,
            'subscribedAt' => $subscriber->getSubscribedAt()->format('c'),
            'createdAt' => $subscriber->getCreatedAt()->format('c'),
        ];
    }

    private function serializeSubscriberDetail(mixed $subscriber): array
    {
        $lists = [];
        foreach ($subscriber->getSubscriberLists() as $list) {
            $lists[] = [
                'id' => $list->getId(),
                'name' => $list->getName(),
            ];
        }

        $tags = [];
        foreach ($subscriber->getTags() as $tag) {
            $tags[] = [
                'id' => $tag->getId(),
                'name' => $tag->getName(),
                'color' => $tag->getColor(),
            ];
        }

        return [
            'id' => $subscriber->getId(),
            'email' => $subscriber->getEmail(),
            'firstName' => $subscriber->getFirstName(),
            'lastName' => $subscriber->getLastName(),
            'status' => $subscriber->getStatus()->value,
            'metadata' => $subscriber->getMetadata(),
            'ipAddress' => $subscriber->getIpAddress(),
            'subscribedAt' => $subscriber->getSubscribedAt()->format('c'),
            'unsubscribedAt' => $subscriber->getUnsubscribedAt()?->format('c'),
            'createdAt' => $subscriber->getCreatedAt()->format('c'),
            'updatedAt' => $subscriber->getUpdatedAt()->format('c'),
            'lists' => $lists,
            'tags' => $tags,
        ];
    }
}
