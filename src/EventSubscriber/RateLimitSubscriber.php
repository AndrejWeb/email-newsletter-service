<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;

class RateLimitSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private RateLimiterFactory $loginLimiter,
        private RateLimiterFactory $apiGeneralLimiter,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 10],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();
        $ip = $request->getClientIp() ?? 'unknown';

        if ($path === '/api/auth/login' && $request->getMethod() === 'POST') {
            $limiter = $this->loginLimiter->create($ip);
            $limit = $limiter->consume();
            if (!$limit->isAccepted()) {
                $event->setResponse(new JsonResponse([
                    'error' => 'Too many login attempts. Please try again later.',
                    'code' => 429,
                ], 429, [
                    'Retry-After' => $limit->getRetryAfter()->getTimestamp() - time(),
                    'X-RateLimit-Limit' => $limit->getLimit(),
                ]));
                return;
            }
        }

        if (str_starts_with($path, '/api')) {
            $limiter = $this->apiGeneralLimiter->create($ip);
            $limit = $limiter->consume();
            if (!$limit->isAccepted()) {
                $event->setResponse(new JsonResponse([
                    'error' => 'Too many requests. Please slow down.',
                    'code' => 429,
                ], 429, [
                    'Retry-After' => $limit->getRetryAfter()->getTimestamp() - time(),
                    'X-RateLimit-Limit' => $limit->getLimit(),
                ]));
            }
        }
    }
}
