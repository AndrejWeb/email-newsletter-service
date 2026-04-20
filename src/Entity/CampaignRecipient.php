<?php

namespace App\Entity;

use App\Enum\RecipientStatus;
use App\Repository\CampaignRecipientRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CampaignRecipientRepository::class)]
#[ORM\Table(name: 'campaign_recipient')]
class CampaignRecipient
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Campaign::class, inversedBy: 'recipients')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Campaign $campaign = null;

    #[ORM\ManyToOne(targetEntity: Subscriber::class, inversedBy: 'campaignRecipients')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Subscriber $subscriber = null;

    #[ORM\Column(type: 'string', enumType: RecipientStatus::class)]
    private RecipientStatus $status = RecipientStatus::Pending;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $openedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $clickedAt = null;

    #[ORM\Column(length: 36, unique: true)]
    private ?string $trackingId = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, TrackingEvent> */
    #[ORM\OneToMany(targetEntity: TrackingEvent::class, mappedBy: 'campaignRecipient', cascade: ['persist', 'remove'])]
    private Collection $trackingEvents;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->trackingEvents = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCampaign(): ?Campaign
    {
        return $this->campaign;
    }

    public function setCampaign(?Campaign $campaign): static
    {
        $this->campaign = $campaign;
        return $this;
    }

    public function getSubscriber(): ?Subscriber
    {
        return $this->subscriber;
    }

    public function setSubscriber(?Subscriber $subscriber): static
    {
        $this->subscriber = $subscriber;
        return $this;
    }

    public function getStatus(): RecipientStatus
    {
        return $this->status;
    }

    public function setStatus(RecipientStatus $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getSentAt(): ?\DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function setSentAt(?\DateTimeImmutable $sentAt): static
    {
        $this->sentAt = $sentAt;
        return $this;
    }

    public function getOpenedAt(): ?\DateTimeImmutable
    {
        return $this->openedAt;
    }

    public function setOpenedAt(?\DateTimeImmutable $openedAt): static
    {
        $this->openedAt = $openedAt;
        return $this;
    }

    public function getClickedAt(): ?\DateTimeImmutable
    {
        return $this->clickedAt;
    }

    public function setClickedAt(?\DateTimeImmutable $clickedAt): static
    {
        $this->clickedAt = $clickedAt;
        return $this;
    }

    public function getTrackingId(): ?string
    {
        return $this->trackingId;
    }

    public function setTrackingId(string $trackingId): static
    {
        $this->trackingId = $trackingId;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /** @return Collection<int, TrackingEvent> */
    public function getTrackingEvents(): Collection
    {
        return $this->trackingEvents;
    }
}
