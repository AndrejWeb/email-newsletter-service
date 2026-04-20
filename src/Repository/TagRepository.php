<?php

namespace App\Repository;

use App\Entity\Tag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tag>
 */
class TagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tag::class);
    }

    public function findAllWithSubscriberCounts(): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = "SELECT t.id, t.name, t.color, COUNT(st.subscriber_id) as subscriber_count
                FROM tag t
                LEFT JOIN subscriber_tag st ON t.id = st.tag_id
                GROUP BY t.id, t.name, t.color
                ORDER BY t.name ASC";

        $result = $conn->executeQuery($sql);

        return $result->fetchAllAssociative();
    }
}
