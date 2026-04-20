<?php

namespace App\Service;

use App\Entity\TrackingEvent;
use App\Enum\RecipientStatus;
use App\Enum\SubscriberStatus;
use App\Enum\TrackingEventType;
use App\Repository\CampaignRecipientRepository;
use Doctrine\ORM\EntityManagerInterface;

class TrackingService
{
    public function __construct(
        private EntityManagerInterface $em,
        private CampaignRecipientRepository $recipientRepository,
    ) {}

    public function recordOpen(string $trackingId, ?string $ipAddress = null, ?string $userAgent = null): bool
    {
        $recipient = $this->recipientRepository->findByTrackingId($trackingId);
        if (!$recipient) {
            return false;
        }

        $event = new TrackingEvent();
        $event->setCampaignRecipient($recipient);
        $event->setType(TrackingEventType::Open);
        $event->setIpAddress($ipAddress);
        $event->setUserAgent($userAgent);
        $this->em->persist($event);

        // Update recipient openedAt only on first open
        if ($recipient->getOpenedAt() === null) {
            $recipient->setOpenedAt(new \DateTimeImmutable());
            $recipient->setStatus(RecipientStatus::Opened);

            $campaign = $recipient->getCampaign();
            if ($campaign) {
                $campaign->setOpenCount($campaign->getOpenCount() + 1);
            }
        }

        $this->em->flush();
        return true;
    }

    public function recordClick(string $trackingId, string $url, ?string $ipAddress = null, ?string $userAgent = null): bool
    {
        $recipient = $this->recipientRepository->findByTrackingId($trackingId);
        if (!$recipient) {
            return false;
        }

        $event = new TrackingEvent();
        $event->setCampaignRecipient($recipient);
        $event->setType(TrackingEventType::Click);
        $event->setUrl($url);
        $event->setIpAddress($ipAddress);
        $event->setUserAgent($userAgent);
        $this->em->persist($event);

        // Update recipient clickedAt only on first click
        if ($recipient->getClickedAt() === null) {
            $recipient->setClickedAt(new \DateTimeImmutable());
            $recipient->setStatus(RecipientStatus::Clicked);

            $campaign = $recipient->getCampaign();
            if ($campaign) {
                $campaign->setClickCount($campaign->getClickCount() + 1);
            }
        }

        $this->em->flush();
        return true;
    }

    public function recordUnsubscribe(string $trackingId): bool
    {
        $recipient = $this->recipientRepository->findByTrackingId($trackingId);
        if (!$recipient) {
            return false;
        }

        $event = new TrackingEvent();
        $event->setCampaignRecipient($recipient);
        $event->setType(TrackingEventType::Unsubscribe);
        $this->em->persist($event);

        $recipient->setStatus(RecipientStatus::Unsubscribed);

        $subscriber = $recipient->getSubscriber();
        if ($subscriber) {
            $subscriber->setStatus(SubscriberStatus::Unsubscribed);
            $subscriber->setUnsubscribedAt(new \DateTimeImmutable());
            $subscriber->setUpdatedAt(new \DateTimeImmutable());
        }

        $campaign = $recipient->getCampaign();
        if ($campaign) {
            $campaign->setUnsubscribeCount($campaign->getUnsubscribeCount() + 1);
        }

        $this->em->flush();
        return true;
    }
}
