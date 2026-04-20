<?php

namespace App\Repository;

use App\Entity\SubscriberList;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SubscriberList>
 */
class SubscriberListRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SubscriberList::class);
    }

    public function findAllWithCounts(): array
    {
        return $this->createQueryBuilder('l')
            ->orderBy('l.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByIdWithSubscribers(int $id, int $page = 1, int $limit = 20): ?array
    {
        $list = $this->find($id);
        if (!$list) {
            return null;
        }

        $subscriberQb = $this->getEntityManager()->createQueryBuilder()
            ->select('s')
            ->from('App\Entity\Subscriber', 's')
            ->innerJoin('s.subscriberLists', 'sl')
            ->where('sl.id = :listId')
            ->setParameter('listId', $id)
            ->orderBy('s.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $countQb = $this->getEntityManager()->createQueryBuilder()
            ->select('COUNT(s2.id)')
            ->from('App\Entity\Subscriber', 's2')
            ->innerJoin('s2.subscriberLists', 'sl2')
            ->where('sl2.id = :listId')
            ->setParameter('listId', $id);

        return [
            'list' => $list,
            'subscribers' => $subscriberQb->getQuery()->getResult(),
            'total' => (int) $countQb->getQuery()->getSingleScalarResult(),
        ];
    }
}
