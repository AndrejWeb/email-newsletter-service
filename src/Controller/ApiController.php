<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

abstract class ApiController extends AbstractController
{
    protected function jsonResponse(mixed $data, int $status = 200, array $meta = []): JsonResponse
    {
        $response = ['data' => $data];
        if (!empty($meta)) {
            $response['meta'] = $meta;
        }
        return new JsonResponse($response, $status);
    }

    protected function paginatedResponse(mixed $data, int $page, int $limit, int $total): JsonResponse
    {
        return new JsonResponse([
            'data' => $data,
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'totalPages' => (int) ceil($total / $limit),
            ],
        ]);
    }

    protected function errorResponse(string $message, int $statusCode = 400): JsonResponse
    {
        return new JsonResponse([
            'error' => $message,
            'code' => $statusCode,
        ], $statusCode);
    }

    protected function getCurrentUser(): ?User
    {
        $user = $this->getUser();
        return $user instanceof User ? $user : null;
    }
}
