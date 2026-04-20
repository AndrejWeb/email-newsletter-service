<?php

namespace App\Controller;

use App\Enum\UserRole;
use App\Repository\UserRepository;
use App\Service\AuthService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/auth')]
class AuthController extends ApiController
{
    public function __construct(
        private AuthService $authService,
        private UserRepository $userRepository,
    ) {}

    #[Route('/register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['email'], $data['password'], $data['name'])) {
            return $this->errorResponse('Email, password, and name are required.', 422);
        }

        // Only admins can register new users, unless no users exist (first user)
        $userCount = $this->userRepository->count([]);
        $currentUser = $this->getCurrentUser();

        if ($userCount > 0 && (!$currentUser || $currentUser->getRole() !== UserRole::Admin)) {
            return $this->errorResponse('Only administrators can register new users.', 403);
        }

        $role = UserRole::Editor;
        if ($userCount === 0) {
            $role = UserRole::Admin;
        } elseif (isset($data['role'])) {
            $role = UserRole::tryFrom($data['role']) ?? UserRole::Editor;
        }

        try {
            $user = $this->authService->register($data['email'], $data['password'], $data['name'], $role);

            return $this->jsonResponse([
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'name' => $user->getName(),
                'role' => $user->getRole()->value,
                'createdAt' => $user->getCreatedAt()->format('c'),
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 409);
        }
    }

    #[Route('/login', name: 'api_auth_login', methods: ['POST'])]
    public function login(): JsonResponse
    {
        // This method is handled by the json_login security firewall
        // It should never be reached directly
        return $this->errorResponse('Missing credentials.', 400);
    }

    #[Route('/me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            return $this->errorResponse('Not authenticated.', 401);
        }

        return $this->jsonResponse([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'name' => $user->getName(),
            'role' => $user->getRole()->value,
            'createdAt' => $user->getCreatedAt()->format('c'),
        ]);
    }
}
