<?php

namespace App\Repository;

use App\Entity\Subscriber;
use App\Enum\SubscriberStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Subscriber>
 */
class SubscriberRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Subscriber::class);
    }

    public function findByFilters(
        ?string $status = null,
        ?int $listId = null,
        ?int $tagId = null,
        ?string $search = null,
        int $page = 1,
        int $limit = 20
    ): array {
        $qb = $this->createQueryBuilder('s');

        if ($status !== null) {
            $qb->andWhere('s.status = :status')
                ->setParameter('status', $status);
        }

        if ($listId !== null) {
            $qb->innerJoin('s.subscriberLists', 'sl')
                ->andWhere('sl.id = :listId')
                ->setParameter('listId', $listId);
        }

        if ($tagId !== null) {
            $qb->innerJoin('s.tags', 't')
                ->andWhere('t.id = :tagId')
                ->setParameter('tagId', $tagId);
        }

        if ($search !== null && $search !== '') {
            $qb->andWhere('s.email LIKE :search OR s.firstName LIKE :search OR s.lastName LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $qb->orderBy('s.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    public function countByFilters(
        ?string $status = null,
        ?int $listId = null,
        ?int $tagId = null,
        ?string $search = null
    ): int {
        $qb = $this->createQueryBuilder('s')
            ->select('COUNT(s.id)');

        if ($status !== null) {
            $qb->andWhere('s.status = :status')
                ->setParameter('status', $status);
        }

        if ($listId !== null) {
            $qb->innerJoin('s.subscriberLists', 'sl')
                ->andWhere('sl.id = :listId')
                ->setParameter('listId', $listId);
        }

        if ($tagId !== null) {
            $qb->innerJoin('s.tags', 't')
                ->andWhere('t.id = :tagId')
                ->setParameter('tagId', $tagId);
        }

        if ($search !== null && $search !== '') {
            $qb->andWhere('s.email LIKE :search OR s.firstName LIKE :search OR s.lastName LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function findByEmail(string $email): ?Subscriber
    {
        return $this->findOneBy(['email' => $email]);
    }

    public function getGrowthData(int $days = 30): array
    {
        $since = new \DateTimeImmutable("-{$days} days");

        $conn = $this->getEntityManager()->getConnection();
        $sql = "SELECT DATE(subscribed_at) as date, COUNT(*) as count
                FROM subscriber
                WHERE subscribed_at >= :since
                GROUP BY DATE(subscribed_at)
                ORDER BY date ASC";

        $result = $conn->executeQuery($sql, ['since' => $since->format('Y-m-d')]);

        return $result->fetchAllAssociative();
    }

    public function getStatusCounts(): array
    {
        $qb = $this->createQueryBuilder('s')
            ->select('s.status, COUNT(s.id) as count')
            ->groupBy('s.status');

        $results = $qb->getQuery()->getResult();

        $counts = [];
        foreach ($results as $row) {
            $status = $row['status'] instanceof SubscriberStatus ? $row['status']->value : $row['status'];
            $counts[$status] = (int) $row['count'];
        }

        return $counts;
    }
}
