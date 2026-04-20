<?php

namespace App\Entity;

use App\Enum\TrackingEventType;
use App\Repository\TrackingEventRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TrackingEventRepository::class)]
#[ORM\Table(name: 'tracking_event')]
class TrackingEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CampaignRecipient::class, inversedBy: 'trackingEvents')]
    #[ORM\JoinColumn(nullable: false)]
    private ?CampaignRecipient $campaignRecipient = null;

    #[ORM\Column(type: 'string', enumType: TrackingEventType::class)]
    private TrackingEventType $type;

    #[ORM\Column(length: 2048, nullable: true)]
    private ?string $url = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCampaignRecipient(): ?CampaignRecipient
    {
        return $this->campaignRecipient;
    }

    public function setCampaignRecipient(?CampaignRecipient $campaignRecipient): static
    {
        $this->campaignRecipient = $campaignRecipient;
        return $this;
    }

    public function getType(): TrackingEventType
    {
        return $this->type;
    }

    public function setType(TrackingEventType $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): static
    {
        $this->url = $url;
        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): static
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): static
    {
        $this->userAgent = $userAgent;
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
}
