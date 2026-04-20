<?php

namespace App\Repository;

use App\Entity\CampaignRecipient;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CampaignRecipient>
 */
class CampaignRecipientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CampaignRecipient::class);
    }

    public function findByCampaign(int $campaignId, ?string $status = null, int $page = 1, int $limit = 20): array
    {
        $qb = $this->createQueryBuilder('cr')
            ->innerJoin('cr.subscriber', 's')
            ->addSelect('s')
            ->where('cr.campaign = :campaignId')
            ->setParameter('campaignId', $campaignId);

        if ($status !== null) {
            $qb->andWhere('cr.status = :status')
                ->setParameter('status', $status);
        }

        $qb->orderBy('cr.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    public function countByCampaign(int $campaignId, ?string $status = null): int
    {
        $qb = $this->createQueryBuilder('cr')
            ->select('COUNT(cr.id)')
            ->where('cr.campaign = :campaignId')
            ->setParameter('campaignId', $campaignId);

        if ($status !== null) {
            $qb->andWhere('cr.status = :status')
                ->setParameter('status', $status);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function findByTrackingId(string $trackingId): ?CampaignRecipient
    {
        return $this->findOneBy(['trackingId' => $trackingId]);
    }

    public function getStatusDistribution(int $campaignId): array
    {
        $qb = $this->createQueryBuilder('cr')
            ->select('cr.status, COUNT(cr.id) as count')
            ->where('cr.campaign = :campaignId')
            ->setParameter('campaignId', $campaignId)
            ->groupBy('cr.status');

        $results = $qb->getQuery()->getResult();

        $distribution = [];
        foreach ($results as $row) {
            $status = $row['status'] instanceof \App\Enum\RecipientStatus ? $row['status']->value : $row['status'];
            $distribution[$status] = (int) $row['count'];
        }

        return $distribution;
    }
}
