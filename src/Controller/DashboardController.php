<?php

namespace App\Controller;

use App\Service\AnalyticsService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends ApiController
{
    public function __construct(
        private AnalyticsService $analyticsService,
    ) {}

    #[Route('/api/dashboard', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $stats = $this->analyticsService->getDashboardStats();
        return $this->jsonResponse($stats);
    }
}
