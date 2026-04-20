<?php

namespace App\Service;

use App\Enum\CampaignStatus;
use App\Enum\SubscriberStatus;
use App\Repository\CampaignRecipientRepository;
use App\Repository\CampaignRepository;
use App\Repository\SubscriberRepository;
use App\Repository\TrackingEventRepository;
use App\Entity\Campaign;

class AnalyticsService
{
    public function __construct(
        private SubscriberRepository $subscriberRepository,
        private CampaignRepository $campaignRepository,
        private CampaignRecipientRepository $recipientRepository,
        private TrackingEventRepository $trackingEventRepository,
    ) {}

    public function getDashboardStats(): array
    {
        $totalSubscribers = $this->subscriberRepository->count([]);
        $activeSubscribers = $this->subscriberRepository->count(['status' => SubscriberStatus::Active]);
        $totalCampaigns = $this->campaignRepository->count([]);
        $sentCampaigns = $this->campaignRepository->count(['status' => CampaignStatus::Sent]);

        // Calculate average open/click rates from sent campaigns
        $recentCampaigns = $this->campaignRepository->findRecentCampaigns(10);
        $totalOpenRate = 0;
        $totalClickRate = 0;
        $campaignCount = count($recentCampaigns);

        foreach ($recentCampaigns as $campaign) {
            $sent = $campaign->getSentCount();
            if ($sent > 0) {
                $totalOpenRate += ($campaign->getOpenCount() / $sent) * 100;
                $totalClickRate += ($campaign->getClickCount() / $sent) * 100;
            }
        }

        $avgOpenRate = $campaignCount > 0 ? round($totalOpenRate / $campaignCount, 2) : 0;
        $avgClickRate = $campaignCount > 0 ? round($totalClickRate / $campaignCount, 2) : 0;

        $subscriberGrowth = $this->subscriberRepository->getGrowthData(30);

        $recentCampaignsData = [];
        foreach ($this->campaignRepository->findRecentCampaigns(5) as $campaign) {
            $recentCampaignsData[] = [
                'id' => $campaign->getId(),
                'name' => $campaign->getName(),
                'subject' => $campaign->getSubject(),
                'sentAt' => $campaign->getSentAt()?->format('c'),
                'totalRecipients' => $campaign->getTotalRecipients(),
                'openCount' => $campaign->getOpenCount(),
                'clickCount' => $campaign->getClickCount(),
                'openRate' => $campaign->getSentCount() > 0
                    ? round(($campaign->getOpenCount() / $campaign->getSentCount()) * 100, 2) : 0,
                'clickRate' => $campaign->getSentCount() > 0
                    ? round(($campaign->getClickCount() / $campaign->getSentCount()) * 100, 2) : 0,
            ];
        }

        return [
            'totalSubscribers' => $totalSubscribers,
            'activeSubscribers' => $activeSubscribers,
            'totalCampaigns' => $totalCampaigns,
            'sentCampaigns' => $sentCampaigns,
            'avgOpenRate' => $avgOpenRate,
            'avgClickRate' => $avgClickRate,
            'subscriberGrowth' => $subscriberGrowth,
            'recentCampaigns' => $recentCampaignsData,
        ];
    }

    public function getCampaignAnalytics(Campaign $campaign): array
    {
        $campaignId = $campaign->getId();
        $sent = $campaign->getSentCount();

        $stats = [
            'totalRecipients' => $campaign->getTotalRecipients(),
            'sent' => $sent,
            'delivered' => $sent - $campaign->getBounceCount(),
            'opened' => $campaign->getOpenCount(),
            'clicked' => $campaign->getClickCount(),
            'bounced' => $campaign->getBounceCount(),
            'unsubscribed' => $campaign->getUnsubscribeCount(),
            'openRate' => $sent > 0 ? round(($campaign->getOpenCount() / $sent) * 100, 2) : 0,
            'clickRate' => $sent > 0 ? round(($campaign->getClickCount() / $sent) * 100, 2) : 0,
        ];

        return [
            'stats' => $stats,
            'statusDistribution' => $this->recipientRepository->getStatusDistribution($campaignId),
            'clicksByUrl' => $this->trackingEventRepository->getClicksByUrl($campaignId),
            'openTimeline' => $this->trackingEventRepository->getOpenTimeline($campaignId),
            'recentActivity' => $this->trackingEventRepository->getRecentActivity($campaignId),
        ];
    }

    public function getSubscriberGrowth(int $days = 30): array
    {
        return $this->subscriberRepository->getGrowthData($days);
    }
}
