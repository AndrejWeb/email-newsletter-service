<?php

namespace App\Repository;

use App\Entity\TrackingEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TrackingEvent>
 */
class TrackingEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TrackingEvent::class);
    }

    public function findByCampaignRecipient(int $recipientId): array
    {
        return $this->createQueryBuilder('te')
            ->where('te.campaignRecipient = :recipientId')
            ->setParameter('recipientId', $recipientId)
            ->orderBy('te.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getEventCountsByType(int $campaignId): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = "SELECT te.type, COUNT(*) as count
                FROM tracking_event te
                INNER JOIN campaign_recipient cr ON te.campaign_recipient_id = cr.id
                WHERE cr.campaign_id = :campaignId
                GROUP BY te.type";

        $result = $conn->executeQuery($sql, ['campaignId' => $campaignId]);

        return $result->fetchAllAssociative();
    }

    public function getClicksByUrl(int $campaignId): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = "SELECT te.url, COUNT(*) as count
                FROM tracking_event te
                INNER JOIN campaign_recipient cr ON te.campaign_recipient_id = cr.id
                WHERE cr.campaign_id = :campaignId AND te.type = 'click' AND te.url IS NOT NULL
                GROUP BY te.url
                ORDER BY count DESC";

        $result = $conn->executeQuery($sql, ['campaignId' => $campaignId]);

        return $result->fetchAllAssociative();
    }

    public function getOpenTimeline(int $campaignId, int $days = 30): array
    {
        $since = new \DateTimeImmutable("-{$days} days");

        $conn = $this->getEntityManager()->getConnection();
        $sql = "SELECT DATE(te.created_at) as date, COUNT(*) as count
                FROM tracking_event te
                INNER JOIN campaign_recipient cr ON te.campaign_recipient_id = cr.id
                WHERE cr.campaign_id = :campaignId
                  AND te.type = 'open'
                  AND te.created_at >= :since
                GROUP BY DATE(te.created_at)
                ORDER BY date ASC";

        $result = $conn->executeQuery($sql, [
            'campaignId' => $campaignId,
            'since' => $since->format('Y-m-d'),
        ]);

        return $result->fetchAllAssociative();
    }

    public function getRecentActivity(int $campaignId, int $limit = 20): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = "SELECT te.type, te.url, te.created_at, te.ip_address,
                       s.email as subscriber_email, s.first_name, s.last_name
                FROM tracking_event te
                INNER JOIN campaign_recipient cr ON te.campaign_recipient_id = cr.id
                INNER JOIN subscriber s ON cr.subscriber_id = s.id
                WHERE cr.campaign_id = :campaignId
                ORDER BY te.created_at DESC
                LIMIT :limit";

        $result = $conn->executeQuery($sql, [
            'campaignId' => $campaignId,
            'limit' => $limit,
        ], [
            'limit' => \Doctrine\DBAL\ParameterType::INTEGER,
        ]);

        return $result->fetchAllAssociative();
    }
}
