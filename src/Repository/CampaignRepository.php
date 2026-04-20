<?php

namespace App\Repository;

use App\Entity\Campaign;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Campaign>
 */
class CampaignRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Campaign::class);
    }

    public function findByFilters(
        ?string $status = null,
        ?string $search = null,
        int $page = 1,
        int $limit = 20
    ): array {
        $qb = $this->createQueryBuilder('c');

        if ($status !== null) {
            $qb->andWhere('c.status = :status')
                ->setParameter('status', $status);
        }

        if ($search !== null && $search !== '') {
            $qb->andWhere('c.name LIKE :search OR c.subject LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $qb->orderBy('c.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    public function countByFilters(?string $status = null, ?string $search = null): int
    {
        $qb = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)');

        if ($status !== null) {
            $qb->andWhere('c.status = :status')
                ->setParameter('status', $status);
        }

        if ($search !== null && $search !== '') {
            $qb->andWhere('c.name LIKE :search OR c.subject LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function findRecentCampaigns(int $limit = 5): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.status = :status')
            ->setParameter('status', 'sent')
            ->orderBy('c.sentAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function getCampaignStats(int $campaignId): array
    {
        $campaign = $this->find($campaignId);
        if (!$campaign) {
            return [];
        }

        return [
            'totalRecipients' => $campaign->getTotalRecipients(),
            'sentCount' => $campaign->getSentCount(),
            'openCount' => $campaign->getOpenCount(),
            'clickCount' => $campaign->getClickCount(),
            'bounceCount' => $campaign->getBounceCount(),
            'unsubscribeCount' => $campaign->getUnsubscribeCount(),
        ];
    }
}
