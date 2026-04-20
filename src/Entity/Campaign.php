<?php

namespace App\Entity;

use App\Enum\CampaignStatus;
use App\Repository\CampaignRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CampaignRepository::class)]
#[ORM\Table(name: 'campaign')]
class Campaign
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $subject = null;

    #[ORM\Column(length: 255)]
    private ?string $fromName = null;

    #[ORM\Column(length: 255)]
    private ?string $fromEmail = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $replyTo = null;

    #[ORM\ManyToOne(targetEntity: Template::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Template $template = null;

    #[ORM\ManyToOne(targetEntity: SubscriberList::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?SubscriberList $subscriberList = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $htmlContent = null;

    #[ORM\Column(type: 'string', enumType: CampaignStatus::class)]
    private CampaignStatus $status = CampaignStatus::Draft;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $scheduledAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    #[ORM\Column]
    private int $totalRecipients = 0;

    #[ORM\Column]
    private int $sentCount = 0;

    #[ORM\Column]
    private int $openCount = 0;

    #[ORM\Column]
    private int $clickCount = 0;

    #[ORM\Column]
    private int $bounceCount = 0;

    #[ORM\Column]
    private int $unsubscribeCount = 0;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, CampaignRecipient> */
    #[ORM\OneToMany(targetEntity: CampaignRecipient::class, mappedBy: 'campaign', cascade: ['persist', 'remove'])]
    private Collection $recipients;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->recipients = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): static
    {
        $this->subject = $subject;
        return $this;
    }

    public function getFromName(): ?string
    {
        return $this->fromName;
    }

    public function setFromName(string $fromName): static
    {
        $this->fromName = $fromName;
        return $this;
    }

    public function getFromEmail(): ?string
    {
        return $this->fromEmail;
    }

    public function setFromEmail(string $fromEmail): static
    {
        $this->fromEmail = $fromEmail;
        return $this;
    }

    public function getReplyTo(): ?string
    {
        return $this->replyTo;
    }

    public function setReplyTo(?string $replyTo): static
    {
        $this->replyTo = $replyTo;
        return $this;
    }

    public function getTemplate(): ?Template
    {
        return $this->template;
    }

    public function setTemplate(?Template $template): static
    {
        $this->template = $template;
        return $this;
    }

    public function getSubscriberList(): ?SubscriberList
    {
        return $this->subscriberList;
    }

    public function setSubscriberList(?SubscriberList $subscriberList): static
    {
        $this->subscriberList = $subscriberList;
        return $this;
    }

    public function getHtmlContent(): ?string
    {
        return $this->htmlContent;
    }

    public function setHtmlContent(?string $htmlContent): static
    {
        $this->htmlContent = $htmlContent;
        return $this;
    }

    public function getStatus(): CampaignStatus
    {
        return $this->status;
    }

    public function setStatus(CampaignStatus $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getScheduledAt(): ?\DateTimeImmutable
    {
        return $this->scheduledAt;
    }

    public function setScheduledAt(?\DateTimeImmutable $scheduledAt): static
    {
        $this->scheduledAt = $scheduledAt;
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

    public function getTotalRecipients(): int
    {
        return $this->totalRecipients;
    }

    public function setTotalRecipients(int $totalRecipients): static
    {
        $this->totalRecipients = $totalRecipients;
        return $this;
    }

    public function getSentCount(): int
    {
        return $this->sentCount;
    }

    public function setSentCount(int $sentCount): static
    {
        $this->sentCount = $sentCount;
        return $this;
    }

    public function getOpenCount(): int
    {
        return $this->openCount;
    }

    public function setOpenCount(int $openCount): static
    {
        $this->openCount = $openCount;
        return $this;
    }

    public function getClickCount(): int
    {
        return $this->clickCount;
    }

    public function setClickCount(int $clickCount): static
    {
        $this->clickCount = $clickCount;
        return $this;
    }

    public function getBounceCount(): int
    {
        return $this->bounceCount;
    }

    public function setBounceCount(int $bounceCount): static
    {
        $this->bounceCount = $bounceCount;
        return $this;
    }

    public function getUnsubscribeCount(): int
    {
        return $this->unsubscribeCount;
    }

    public function setUnsubscribeCount(int $unsubscribeCount): static
    {
        $this->unsubscribeCount = $unsubscribeCount;
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

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /** @return Collection<int, CampaignRecipient> */
    public function getRecipients(): Collection
    {
        return $this->recipients;
    }

    public function addRecipient(CampaignRecipient $recipient): static
    {
        if (!$this->recipients->contains($recipient)) {
            $this->recipients->add($recipient);
            $recipient->setCampaign($this);
        }
        return $this;
    }
}
