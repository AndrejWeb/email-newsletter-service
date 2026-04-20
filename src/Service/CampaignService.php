<?php

namespace App\Service;

use App\Entity\Campaign;
use App\Entity\CampaignRecipient;
use App\Enum\CampaignStatus;
use App\Enum\RecipientStatus;
use App\Enum\SubscriberStatus;
use App\Repository\CampaignRepository;
use App\Repository\SubscriberListRepository;
use App\Repository\TemplateRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

class CampaignService
{
    public function __construct(
        private EntityManagerInterface $em,
        private CampaignRepository $campaignRepository,
        private SubscriberListRepository $listRepository,
        private TemplateRepository $templateRepository,
    ) {}

    public function create(
        string $name,
        string $subject,
        string $fromName,
        string $fromEmail,
        ?string $replyTo = null,
        ?int $templateId = null,
        ?int $listId = null,
    ): Campaign {
        if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid fromEmail address.');
        }
        if ($replyTo !== null && !filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid replyTo address.');
        }
        foreach ([$fromName, $fromEmail, $subject] as $field) {
            if ($field !== null && (str_contains($field, "\r") || str_contains($field, "\n"))) {
                throw new \InvalidArgumentException('Invalid characters in email fields.');
            }
        }

        $campaign = new Campaign();
        $campaign->setName($name);
        $campaign->setSubject($subject);
        $campaign->setFromName($fromName);
        $campaign->setFromEmail($fromEmail);
        $campaign->setReplyTo($replyTo);

        if ($templateId) {
            $template = $this->templateRepository->find($templateId);
            if ($template) {
                $campaign->setTemplate($template);
                $campaign->setHtmlContent($template->getHtmlContent());
            }
        }

        if ($listId) {
            $list = $this->listRepository->find($listId);
            if (!$list) {
                throw new \InvalidArgumentException('Subscriber list not found.');
            }
            $campaign->setSubscriberList($list);
        }

        $this->em->persist($campaign);
        $this->em->flush();

        return $campaign;
    }

    public function update(Campaign $campaign, array $data): Campaign
    {
        if ($campaign->getStatus() !== CampaignStatus::Draft) {
            throw new \InvalidArgumentException('Only draft campaigns can be updated.');
        }

        if (isset($data['fromEmail']) && !filter_var($data['fromEmail'], FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid fromEmail address.');
        }
        if (isset($data['replyTo']) && $data['replyTo'] !== null && !filter_var($data['replyTo'], FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid replyTo address.');
        }
        foreach (['fromName', 'fromEmail', 'subject'] as $key) {
            if (isset($data[$key]) && (str_contains($data[$key], "\r") || str_contains($data[$key], "\n"))) {
                throw new \InvalidArgumentException('Invalid characters in email fields.');
            }
        }

        if (isset($data['name'])) {
            $campaign->setName($data['name']);
        }
        if (isset($data['subject'])) {
            $campaign->setSubject($data['subject']);
        }
        if (isset($data['fromName'])) {
            $campaign->setFromName($data['fromName']);
        }
        if (isset($data['fromEmail'])) {
            $campaign->setFromEmail($data['fromEmail']);
        }
        if (array_key_exists('replyTo', $data)) {
            $campaign->setReplyTo($data['replyTo']);
        }
        if (isset($data['templateId'])) {
            $template = $this->templateRepository->find($data['templateId']);
            if ($template) {
                $campaign->setTemplate($template);
                $campaign->setHtmlContent($template->getHtmlContent());
            }
        }
        if (isset($data['listId'])) {
            $list = $this->listRepository->find($data['listId']);
            if ($list) {
                $campaign->setSubscriberList($list);
            }
        }
        if (array_key_exists('htmlContent', $data)) {
            $campaign->setHtmlContent($data['htmlContent']);
        }

        $campaign->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();

        return $campaign;
    }

    public function delete(Campaign $campaign): void
    {
        if ($campaign->getStatus() !== CampaignStatus::Draft) {
            throw new \InvalidArgumentException('Only draft campaigns can be deleted.');
        }

        $this->em->remove($campaign);
        $this->em->flush();
    }

    public function schedule(Campaign $campaign, \DateTimeImmutable $scheduledAt): Campaign
    {
        if ($campaign->getStatus() !== CampaignStatus::Draft) {
            throw new \InvalidArgumentException('Only draft campaigns can be scheduled.');
        }

        $campaign->setStatus(CampaignStatus::Scheduled);
        $campaign->setScheduledAt($scheduledAt);
        $campaign->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();

        return $campaign;
    }

    public function cancel(Campaign $campaign): Campaign
    {
        if ($campaign->getStatus() !== CampaignStatus::Scheduled) {
            throw new \InvalidArgumentException('Only scheduled campaigns can be cancelled.');
        }

        $campaign->setStatus(CampaignStatus::Cancelled);
        $campaign->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();

        return $campaign;
    }

    public function send(Campaign $campaign): Campaign
    {
        if ($campaign->getStatus() !== CampaignStatus::Draft && $campaign->getStatus() !== CampaignStatus::Scheduled) {
            throw new \InvalidArgumentException('Only draft or scheduled campaigns can be sent.');
        }

        $campaign->setStatus(CampaignStatus::Sending);
        $this->em->flush();

        $list = $campaign->getSubscriberList();
        if (!$list) {
            throw new \InvalidArgumentException('Campaign has no subscriber list.');
        }

        // Get active subscribers in the list
        $subscribers = $this->em->createQueryBuilder()
            ->select('s')
            ->from('App\Entity\Subscriber', 's')
            ->innerJoin('s.subscriberLists', 'sl')
            ->where('sl.id = :listId')
            ->andWhere('s.status = :status')
            ->setParameter('listId', $list->getId())
            ->setParameter('status', SubscriberStatus::Active->value)
            ->getQuery()
            ->getResult();

        $sentCount = 0;
        $now = new \DateTimeImmutable();

        foreach ($subscribers as $subscriber) {
            $recipient = new CampaignRecipient();
            $recipient->setCampaign($campaign);
            $recipient->setSubscriber($subscriber);
            $recipient->setTrackingId(Uuid::v4()->toRfc4122());
            $recipient->setStatus(RecipientStatus::Sent);
            $recipient->setSentAt($now);

            $this->em->persist($recipient);
            $sentCount++;
        }

        $campaign->setTotalRecipients($sentCount);
        $campaign->setSentCount($sentCount);
        $campaign->setSentAt($now);
        $campaign->setStatus(CampaignStatus::Sent);
        $campaign->setUpdatedAt($now);

        $this->em->flush();

        return $campaign;
    }

    public function getStats(Campaign $campaign): array
    {
        $total = $campaign->getTotalRecipients();
        $sent = $campaign->getSentCount();
        $opened = $campaign->getOpenCount();
        $clicked = $campaign->getClickCount();
        $bounced = $campaign->getBounceCount();
        $unsubscribed = $campaign->getUnsubscribeCount();

        return [
            'totalRecipients' => $total,
            'sent' => $sent,
            'delivered' => $sent - $bounced,
            'opened' => $opened,
            'clicked' => $clicked,
            'bounced' => $bounced,
            'unsubscribed' => $unsubscribed,
            'openRate' => $sent > 0 ? round(($opened / $sent) * 100, 2) : 0,
            'clickRate' => $sent > 0 ? round(($clicked / $sent) * 100, 2) : 0,
        ];
    }
}
