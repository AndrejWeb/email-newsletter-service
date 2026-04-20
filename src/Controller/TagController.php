<?php

namespace App\Controller;

use App\Entity\Tag;
use App\Repository\TagRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/tags')]
class TagController extends ApiController
{
    public function __construct(
        private TagRepository $tagRepository,
        private EntityManagerInterface $em,
    ) {}

    #[Route('', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $rows = $this->tagRepository->findAllWithSubscriberCounts();

        $tags = array_map(static fn(array $row) => [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'color' => $row['color'],
            'subscriberCount' => (int) ($row['subscriber_count'] ?? 0),
        ], $rows);

        return $this->jsonResponse($tags);
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['name'])) {
            return $this->errorResponse('Name is required.', 422);
        }

        $tag = new Tag();
        $tag->setName($data['name']);
        if (isset($data['color'])) {
            $tag->setColor($data['color']);
        }

        $this->em->persist($tag);
        $this->em->flush();

        return $this->jsonResponse([
            'id' => $tag->getId(),
            'name' => $tag->getName(),
            'color' => $tag->getColor(),
        ], 201);
    }

    #[Route('/{id}', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $tag = $this->tagRepository->find($id);
        if (!$tag) {
            return $this->errorResponse('Tag not found.', 404);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['name'])) {
            $tag->setName($data['name']);
        }
        if (isset($data['color'])) {
            $tag->setColor($data['color']);
        }

        $this->em->flush();

        return $this->jsonResponse([
            'id' => $tag->getId(),
            'name' => $tag->getName(),
            'color' => $tag->getColor(),
        ]);
    }

    #[Route('/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        $tag = $this->tagRepository->find($id);
        if (!$tag) {
            return $this->errorResponse('Tag not found.', 404);
        }

        $this->em->remove($tag);
        $this->em->flush();

        return $this->jsonResponse(['message' => 'Tag deleted.']);
    }
}
