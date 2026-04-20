<?php

namespace App\Controller;

use App\Service\TrackingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

class TrackingController extends AbstractController
{
    public function __construct(
        private TrackingService $trackingService,
    ) {}

    #[Route('/track/open/{trackingId}', methods: ['GET'])]
    public function open(string $trackingId, Request $request): Response
    {
        $this->trackingService->recordOpen(
            $trackingId,
            $request->getClientIp(),
            $request->headers->get('User-Agent'),
        );

        // Return 1x1 transparent GIF pixel
        $pixel = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

        return new Response($pixel, 200, [
            'Content-Type' => 'image/gif',
            'Content-Length' => strlen($pixel),
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => 'Mon, 01 Jan 1990 00:00:00 GMT',
        ]);
    }

    #[Route('/track/click/{trackingId}', methods: ['GET'])]
    public function click(string $trackingId, Request $request): Response
    {
        $url = $request->query->get('url', '');

        if (!empty($url)) {
            $parsed = parse_url($url);
            $scheme = $parsed['scheme'] ?? '';
            if (!in_array($scheme, ['http', 'https'], true)) {
                $url = '';
            }

            if (!empty($url)) {
                $this->trackingService->recordClick(
                    $trackingId,
                    $url,
                    $request->getClientIp(),
                    $request->headers->get('User-Agent'),
                );
            }
        }

        $redirectUrl = !empty($url) ? $url : '/';

        return new RedirectResponse($redirectUrl, 302);
    }

    #[Route('/unsubscribe/{trackingId}', methods: ['GET'])]
    public function unsubscribeConfirm(string $trackingId): Response
    {
        $escapedId = htmlspecialchars($trackingId, ENT_QUOTES, 'UTF-8');

        $html = $this->renderUnsubscribePage(
            'Unsubscribe',
            <<<HTML
        <h1>Unsubscribe</h1>
        <p>Are you sure you want to unsubscribe from our mailing list?</p>
        <form method="POST" action="/unsubscribe/{$escapedId}">
            <button type="submit" style="background-color: #ef4444; color: white; border: none; padding: 12px 32px; font-size: 16px; border-radius: 6px; cursor: pointer; margin-top: 16px;">Yes, Unsubscribe Me</button>
        </form>
        <p style="margin-top: 16px; font-size: 14px; color: #6b7280;">If you did not request this, you can safely ignore this page.</p>
HTML
        );

        return new Response($html, 200, ['Content-Type' => 'text/html']);
    }

    #[Route('/unsubscribe/{trackingId}', methods: ['POST'])]
    public function unsubscribe(string $trackingId): Response
    {
        $success = $this->trackingService->recordUnsubscribe($trackingId);

        if ($success) {
            $content = <<<HTML
        <h1>You've been unsubscribed</h1>
        <p>You have been successfully removed from our mailing list. You will no longer receive emails from us.</p>
        <p>If this was a mistake, you can re-subscribe at any time.</p>
HTML;
        } else {
            $content = <<<HTML
        <h1>Unsubscribe Error</h1>
        <p>We couldn't process your unsubscribe request. The link may be invalid or expired.</p>
        <p>Please contact support if you continue to receive unwanted emails.</p>
HTML;
        }

        $html = $this->renderUnsubscribePage('Unsubscribe', $content);

        return new Response($html, 200, ['Content-Type' => 'text/html']);
    }

    private function renderUnsubscribePage(string $title, string $body): string
    {
        $escapedTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$escapedTitle}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background-color: #f4f4f7;
        }
        .container {
            text-align: center;
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            max-width: 500px;
        }
        h1 { color: #1a1a2e; margin-bottom: 16px; }
        p { color: #4a4a68; line-height: 1.6; }
    </style>
</head>
<body>
    <div class="container">
{$body}
    </div>
</body>
</html>
HTML;
    }
}
