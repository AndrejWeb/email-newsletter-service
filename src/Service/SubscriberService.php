<?php

namespace App\Service;

use App\Entity\Subscriber;
use App\Entity\SubscriberList;
use App\Entity\Tag;
use App\Enum\SubscriberStatus;
use App\Repository\SubscriberRepository;
use App\Repository\SubscriberListRepository;
use App\Repository\TagRepository;
use Doctrine\ORM\EntityManagerInterface;

class SubscriberService
{
    public function __construct(
        private EntityManagerInterface $em,
        private SubscriberRepository $subscriberRepository,
        private SubscriberListRepository $listRepository,
        private TagRepository $tagRepository,
    ) {}

    public function create(
        string $email,
        ?string $firstName = null,
        ?string $lastName = null,
        array $listIds = [],
        array $tagIds = [],
        ?array $metadata = null
    ): Subscriber {
        $existing = $this->subscriberRepository->findByEmail($email);
        if ($existing) {
            throw new \InvalidArgumentException('A subscriber with this email already exists.');
        }

        $subscriber = new Subscriber();
        $subscriber->setEmail($email);
        $subscriber->setFirstName($firstName);
        $subscriber->setLastName($lastName);
        $subscriber->setMetadata($metadata);

        foreach ($listIds as $listId) {
            $list = $this->listRepository->find($listId);
            if ($list) {
                $subscriber->addSubscriberList($list);
            }
        }

        foreach ($tagIds as $tagId) {
            $tag = $this->tagRepository->find($tagId);
            if ($tag) {
                $subscriber->addTag($tag);
            }
        }

        $this->em->persist($subscriber);
        $this->em->flush();

        // Recalculate list counts
        foreach ($subscriber->getSubscriberLists() as $list) {
            $this->recalculateListCount($list);
        }

        return $subscriber;
    }

    public function update(Subscriber $subscriber, array $data): Subscriber
    {
        if (isset($data['email'])) {
            $existing = $this->subscriberRepository->findByEmail($data['email']);
            if ($existing && $existing->getId() !== $subscriber->getId()) {
                throw new \InvalidArgumentException('A subscriber with this email already exists.');
            }
            $subscriber->setEmail($data['email']);
        }
        if (array_key_exists('firstName', $data)) {
            $subscriber->setFirstName($data['firstName']);
        }
        if (array_key_exists('lastName', $data)) {
            $subscriber->setLastName($data['lastName']);
        }
        if (isset($data['status'])) {
            $subscriber->setStatus(SubscriberStatus::from($data['status']));
        }
        if (array_key_exists('metadata', $data)) {
            $subscriber->setMetadata($data['metadata']);
        }

        $subscriber->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();

        return $subscriber;
    }

    public function delete(Subscriber $subscriber): void
    {
        $lists = $subscriber->getSubscriberLists()->toArray();
        $this->em->remove($subscriber);
        $this->em->flush();

        foreach ($lists as $list) {
            $this->recalculateListCount($list);
        }
    }

    public function importFromCsv(string $csvContent): array
    {
        $maxSize = 5 * 1024 * 1024; // 5MB
        if (strlen($csvContent) > $maxSize) {
            return ['imported' => 0, 'skipped' => 0, 'errors' => ['CSV file too large (max 5MB).']];
        }

        $lines = array_filter(explode("\n", trim($csvContent)));
        if (count($lines) < 2) {
            return ['imported' => 0, 'skipped' => 0, 'errors' => ['CSV must have a header row and at least one data row.']];
        }

        $maxRows = 10000;
        if (count($lines) - 1 > $maxRows) {
            return ['imported' => 0, 'skipped' => 0, 'errors' => ["Too many rows (max {$maxRows})."]];
        }

        $header = str_getcsv(array_shift($lines));
        $header = array_map('trim', array_map('strtolower', $header));

        $emailIdx = array_search('email', $header);
        $firstNameIdx = array_search('first_name', $header);
        $lastNameIdx = array_search('last_name', $header);

        if ($emailIdx === false) {
            return ['imported' => 0, 'skipped' => 0, 'errors' => ['CSV must contain an "email" column.']];
        }

        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ($lines as $lineNum => $line) {
            $row = str_getcsv($line);
            if (empty($row) || !isset($row[$emailIdx])) {
                continue;
            }

            $email = trim($row[$emailIdx]);
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
                $errors[] = "Line " . ($lineNum + 2) . ": Invalid email '{$email}'";
                continue;
            }

            $existing = $this->subscriberRepository->findByEmail($email);
            if ($existing) {
                $skipped++;
                continue;
            }

            $subscriber = new Subscriber();
            $subscriber->setEmail($email);
            if ($firstNameIdx !== false && isset($row[$firstNameIdx])) {
                $subscriber->setFirstName(trim($row[$firstNameIdx]) ?: null);
            }
            if ($lastNameIdx !== false && isset($row[$lastNameIdx])) {
                $subscriber->setLastName(trim($row[$lastNameIdx]) ?: null);
            }

            $this->em->persist($subscriber);
            $imported++;
        }

        $this->em->flush();

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
    }

    public function exportToCsv(?int $listId = null): string
    {
        if ($listId !== null) {
            $subscribers = $this->subscriberRepository->findByFilters(null, $listId, null, null, 1, 999999);
        } else {
            $subscribers = $this->subscriberRepository->findAll();
        }

        $csv = "email,first_name,last_name,status,subscribed_at\n";
        foreach ($subscribers as $subscriber) {
            $csv .= sprintf(
                "%s,%s,%s,%s,%s\n",
                $subscriber->getEmail(),
                $subscriber->getFirstName() ?? '',
                $subscriber->getLastName() ?? '',
                $subscriber->getStatus()->value,
                $subscriber->getSubscribedAt()->format('Y-m-d H:i:s')
            );
        }

        return $csv;
    }

    public function unsubscribe(Subscriber $subscriber): Subscriber
    {
        $subscriber->setStatus(SubscriberStatus::Unsubscribed);
        $subscriber->setUnsubscribedAt(new \DateTimeImmutable());
        $subscriber->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();

        return $subscriber;
    }

    public function addToList(Subscriber $subscriber, SubscriberList $list): void
    {
        $subscriber->addSubscriberList($list);
        $subscriber->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();
        $this->recalculateListCount($list);
    }

    public function removeFromList(Subscriber $subscriber, SubscriberList $list): void
    {
        $subscriber->removeSubscriberList($list);
        $subscriber->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();
        $this->recalculateListCount($list);
    }

    public function addTag(Subscriber $subscriber, Tag $tag): void
    {
        $subscriber->addTag($tag);
        $subscriber->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();
    }

    public function removeTag(Subscriber $subscriber, Tag $tag): void
    {
        $subscriber->removeTag($tag);
        $subscriber->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();
    }

    private function recalculateListCount(SubscriberList $list): void
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
