<?php

namespace App\Service;

use App\Entity\SubscriberList;
use App\Repository\SubscriberListRepository;
use Doctrine\ORM\EntityManagerInterface;

class SubscriberListService
{
    public function __construct(
        private EntityManagerInterface $em,
        private SubscriberListRepository $listRepository,
    ) {}

    public function create(string $name, ?string $description = null, bool $isDefault = false): SubscriberList
    {
        $list = new SubscriberList();
        $list->setName($name);
        $list->setDescription($description);
        $list->setIsDefault($isDefault);

        $this->em->persist($list);
        $this->em->flush();

        return $list;
    }

    public function update(SubscriberList $list, array $data): SubscriberList
    {
        if (isset($data['name'])) {
            $list->setName($data['name']);
        }
        if (array_key_exists('description', $data)) {
            $list->setDescription($data['description']);
        }
        if (isset($data['isDefault'])) {
            $list->setIsDefault($data['isDefault']);
        }

        $list->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();

        return $list;
    }

    public function delete(SubscriberList $list): void
    {
        $this->em->remove($list);
        $this->em->flush();
    }

    public function recalculateCount(SubscriberList $list): void
    {
        $count = $this->em->createQueryBuilder()
            ->select('COUNT(s.id)')
            ->from('App\Entity\Subscriber', 's')
            ->innerJoin('s.subscriberLists', 'sl')
            ->where('sl.id = :listId')
            ->setParameter('listId', $list->getId())
            ->getQuery()
            ->getSingleScalarResult();

        $list->setSubscriberCount((int) $count);
        $list->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();
    }
}
