<?php

namespace App\Controller;

use App\Enum\UserRole;
use App\Repository\CampaignRepository;
use App\Repository\CampaignRecipientRepository;
use App\Service\AnalyticsService;
use App\Service\CampaignService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/campaigns')]
class CampaignController extends ApiController
{
    public function __construct(
        private CampaignService $campaignService,
        private CampaignRepository $campaignRepository,
        private CampaignRecipientRepository $recipientRepository,
        private AnalyticsService $analyticsService,
        private RateLimiterFactory $campaignSendLimiter,
    ) {}

    #[Route('', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $status = $request->query->get('status');
        $search = $request->query->get('search');
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = max(1, min(100, (int) $request->query->get('limit', 20)));

        $campaigns = $this->campaignRepository->findByFilters($status, $search, $page, $limit);
        $total = $this->campaignRepository->countByFilters($status, $search);

        $data = array_map(fn($c) => $this->serializeCampaign($c), $campaigns);

        return $this->paginatedResponse($data, $page, $limit, $total);
    }

    #[Route('/{id}', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): JsonResponse
    {
        $campaign = $this->campaignRepository->find($id);
        if (!$campaign) {
            return $this->errorResponse('Campaign not found.', 404);
        }

        $data = $this->serializeCampaignDetail($campaign);
        return $this->jsonResponse($data);
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $required = ['name', 'subject', 'fromName', 'fromEmail', 'listId'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                return $this->errorResponse("{$field} is required.", 422);
            }
        }

        try {
            $campaign = $this->campaignService->create(
                $data['name'],
                $data['subject'],
                $data['fromName'],
                $data['fromEmail'],
                $data['replyTo'] ?? null,
                $data['templateId'] ?? null,
                $data['listId'],
            );

            return $this->jsonResponse($this->serializeCampaignDetail($campaign), 201);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    #[Route('/{id}', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $campaign = $this->campaignRepository->find($id);
        if (!$campaign) {
            return $this->errorResponse('Campaign not found.', 404);
        }

        $data = json_decode($request->getContent(), true);

        try {
            $campaign = $this->campaignService->update($campaign, $data);
            return $this->jsonResponse($this->serializeCampaignDetail($campaign));
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    #[Route('/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        $user = $this->getCurrentUser();
        if (!$user || $user->getRole() !== UserRole::Admin) {
            return $this->errorResponse('Only administrators can delete campaigns.', 403);
        }

        $campaign = $this->campaignRepository->find($id);
        if (!$campaign) {
            return $this->errorResponse('Campaign not found.', 404);
        }

        try {
            $this->campaignService->delete($campaign);
            return $this->jsonResponse(['message' => 'Campaign deleted.']);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    #[Route('/{id}/schedule', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function schedule(int $id, Request $request): JsonResponse
    {
        $campaign = $this->campaignRepository->find($id);
        if (!$campaign) {
            return $this->errorResponse('Campaign not found.', 404);
        }

        $data = json_decode($request->getContent(), true);
        if (!isset($data['scheduledAt'])) {
            return $this->errorResponse('scheduledAt is required.', 422);
        }

        try {
            $scheduledAt = new \DateTimeImmutable($data['scheduledAt']);
            $campaign = $this->campaignService->schedule($campaign, $scheduledAt);
            return $this->jsonResponse($this->serializeCampaignDetail($campaign));
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    #[Route('/{id}/send', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function send(int $id, Request $request): JsonResponse
    {
        $campaign = $this->campaignRepository->find($id);
        if (!$campaign) {
            return $this->errorResponse('Campaign not found.', 404);
        }

        $ip = $request->getClientIp() ?? 'unknown';
        $limiter = $this->campaignSendLimiter->create($ip);
        $limit = $limiter->consume();
        if (!$limit->isAccepted()) {
            return $this->errorResponse('Campaign send rate limit exceeded. Try again later.', 429);
        }

        try {
            $campaign = $this->campaignService->send($campaign);
            return $this->jsonResponse($this->serializeCampaignDetail($campaign));
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    #[Route('/{id}/cancel', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function cancel(int $id): JsonResponse
    {
        $campaign = $this->campaignRepository->find($id);
        if (!$campaign) {
            return $this->errorResponse('Campaign not found.', 404);
        }

        try {
            $campaign = $this->campaignService->cancel($campaign);
            return $this->jsonResponse($this->serializeCampaignDetail($campaign));
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    #[Route('/{id}/analytics', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function analytics(int $id): JsonResponse
    {
        $campaign = $this->campaignRepository->find($id);
        if (!$campaign) {
            return $this->errorResponse('Campaign not found.', 404);
        }

        $analytics = $this->analyticsService->getCampaignAnalytics($campaign);
        return $this->jsonResponse($analytics);
    }

    #[Route('/{id}/recipients', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function recipients(int $id, Request $request): JsonResponse
    {
        $campaign = $this->campaignRepository->find($id);
        if (!$campaign) {
            return $this->errorResponse('Campaign not found.', 404);
        }

        $status = $request->query->get('status');
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = max(1, min(100, (int) $request->query->get('limit', 20)));

        $recipients = $this->recipientRepository->findByCampaign($id, $status, $page, $limit);
        $total = $this->recipientRepository->countByCampaign($id, $status);

        $data = array_map(fn($r) => [
            'id' => $r->getId(),
            'subscriber' => [
                'id' => $r->getSubscriber()->getId(),
                'email' => $r->getSubscriber()->getEmail(),
                'firstName' => $r->getSubscriber()->getFirstName(),
                'lastName' => $r->getSubscriber()->getLastName(),
            ],
            'status' => $r->getStatus()->value,
            'sentAt' => $r->getSentAt()?->format('c'),
            'openedAt' => $r->getOpenedAt()?->format('c'),
            'clickedAt' => $r->getClickedAt()?->format('c'),
            'trackingId' => $r->getTrackingId(),
        ], $recipients);

        return $this->paginatedResponse($data, $page, $limit, $total);
    }

    private function serializeCampaign(mixed $campaign): array
    {
        return [
            'id' => $campaign->getId(),
            'name' => $campaign->getName(),
            'subject' => $campaign->getSubject(),
            'status' => $campaign->getStatus()->value,
            'fromName' => $campaign->getFromName(),
            'fromEmail' => $campaign->getFromEmail(),
            'totalRecipients' => $campaign->getTotalRecipients(),
            'sentCount' => $campaign->getSentCount(),
            'openCount' => $campaign->getOpenCount(),
            'clickCount' => $campaign->getClickCount(),
            'scheduledAt' => $campaign->getScheduledAt()?->format('c'),
            'sentAt' => $campaign->getSentAt()?->format('c'),
            'createdAt' => $campaign->getCreatedAt()->format('c'),
        ];
    }

    private function serializeCampaignDetail(mixed $campaign): array
    {
        $stats = $this->campaignService->getStats($campaign);

        return [
            'id' => $campaign->getId(),
            'name' => $campaign->getName(),
            'subject' => $campaign->getSubject(),
            'fromName' => $campaign->getFromName(),
            'fromEmail' => $campaign->getFromEmail(),
            'replyTo' => $campaign->getReplyTo(),
            'status' => $campaign->getStatus()->value,
            'htmlContent' => $campaign->getHtmlContent(),
            'template' => $campaign->getTemplate() ? [
                'id' => $campaign->getTemplate()->getId(),
                'name' => $campaign->getTemplate()->getName(),
            ] : null,
            'subscriberList' => $campaign->getSubscriberList() ? [
                'id' => $campaign->getSubscriberList()->getId(),
                'name' => $campaign->getSubscriberList()->getName(),
            ] : null,
            'stats' => $stats,
            'scheduledAt' => $campaign->getScheduledAt()?->format('c'),
            'sentAt' => $campaign->getSentAt()?->format('c'),
            'createdAt' => $campaign->getCreatedAt()->format('c'),
            'updatedAt' => $campaign->getUpdatedAt()->format('c'),
        ];
    }
}
