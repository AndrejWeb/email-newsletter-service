<?php

namespace App\DataFixtures;

use App\Entity\Campaign;
use App\Entity\CampaignRecipient;
use App\Entity\Subscriber;
use App\Entity\SubscriberList;
use App\Entity\Tag;
use App\Entity\Template;
use App\Entity\TrackingEvent;
use App\Entity\User;
use App\Enum\CampaignStatus;
use App\Enum\RecipientStatus;
use App\Enum\SubscriberStatus;
use App\Enum\TrackingEventType;
use App\Enum\UserRole;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

class AppFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
    ) {}

    public function load(ObjectManager $manager): void
    {
        // --- Users ---
        $admin = new User();
        $admin->setEmail('admin@newsletter.app');
        $admin->setName('Admin User');
        $admin->setRole(UserRole::Admin);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'password123'));
        $manager->persist($admin);

        $editor = new User();
        $editor->setEmail('editor@newsletter.app');
        $editor->setName('Editor User');
        $editor->setRole(UserRole::Editor);
        $editor->setPassword($this->passwordHasher->hashPassword($editor, 'password123'));
        $manager->persist($editor);

        // --- Subscriber Lists ---
        $listData = [
            ['All Subscribers', 'Main list containing all subscribers', true],
            ['Premium Members', 'Paid subscription members', false],
            ['Free Trial Users', 'Users currently on free trial', false],
            ['Inactive Subscribers', 'Subscribers who have not engaged recently', false],
            ['VIP Customers', 'High-value customers with exclusive access', false],
        ];

        $lists = [];
        foreach ($listData as [$name, $desc, $isDefault]) {
            $list = new SubscriberList();
            $list->setName($name);
            $list->setDescription($desc);
            $list->setIsDefault($isDefault);
            $manager->persist($list);
            $lists[$name] = $list;
            $this->addReference('list_' . $name, $list);
        }

        // --- Tags ---
        $tagData = [
            ['Early Adopter', '#10b981'],
            ['Power User', '#6366f1'],
            ['Enterprise', '#f59e0b'],
            ['Startup', '#ef4444'],
            ['Newsletter', '#3b82f6'],
            ['Product Updates', '#8b5cf6'],
            ['Marketing', '#ec4899'],
            ['Developer', '#14b8a6'],
        ];

        $tags = [];
        foreach ($tagData as [$name, $color]) {
            $tag = new Tag();
            $tag->setName($name);
            $tag->setColor($color);
            $manager->persist($tag);
            $tags[] = $tag;
        }

        // --- Subscribers ---
        $firstNames = ['James', 'Mary', 'John', 'Patricia', 'Robert', 'Jennifer', 'Michael', 'Linda',
            'William', 'Elizabeth', 'David', 'Barbara', 'Richard', 'Susan', 'Joseph', 'Jessica',
            'Thomas', 'Sarah', 'Charles', 'Karen', 'Christopher', 'Lisa', 'Daniel', 'Nancy',
            'Matthew', 'Betty', 'Anthony', 'Margaret', 'Mark', 'Sandra', 'Donald', 'Ashley',
            'Steven', 'Dorothy', 'Paul', 'Kimberly', 'Andrew', 'Emily', 'Joshua', 'Donna',
            'Kenneth', 'Michelle', 'Kevin', 'Carol', 'Brian', 'Amanda', 'George', 'Melissa',
            'Timothy', 'Deborah'];

        $lastNames = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis',
            'Rodriguez', 'Martinez', 'Hernandez', 'Lopez', 'Gonzalez', 'Wilson', 'Anderson',
            'Thomas', 'Taylor', 'Moore', 'Jackson', 'Martin', 'Lee', 'Perez', 'Thompson',
            'White', 'Harris', 'Sanchez', 'Clark', 'Ramirez', 'Lewis', 'Robinson', 'Walker',
            'Young', 'Allen', 'King', 'Wright', 'Scott', 'Torres', 'Nguyen', 'Hill', 'Flores',
            'Green', 'Adams', 'Nelson', 'Baker', 'Hall', 'Rivera', 'Campbell', 'Mitchell',
            'Carter', 'Roberts'];

        $domains = ['gmail.com', 'yahoo.com', 'outlook.com', 'hotmail.com', 'protonmail.com',
            'company.com', 'startup.io', 'techfirm.dev'];

        $subscribers = [];
        $statusOptions = [
            SubscriberStatus::Active,
            SubscriberStatus::Active,
            SubscriberStatus::Active,
            SubscriberStatus::Active,
            SubscriberStatus::Active,
            SubscriberStatus::Unsubscribed,
            SubscriberStatus::Bounced,
        ];
        $subscriberOffsets = [5, 10, 15, 20, 25, 30, 35, 40, 45, 50];
        $unsubscribeOffsets = [3, 8, 12, 15, 20];

        for ($i = 0; $i < 48; $i++) {
            $firstName = $firstNames[$i];
            $lastName = $lastNames[$i];
            $domain = $domains[$i % count($domains)];
            $email = strtolower($firstName) . '.' . strtolower($lastName) . '@' . $domain;
            $subscriberOffset = $subscriberOffsets[$i % count($subscriberOffsets)];

            $subscriber = new Subscriber();
            $subscriber->setEmail($email);
            $subscriber->setFirstName($firstName);
            $subscriber->setLastName($lastName);
            $subscriber->setStatus($statusOptions[$i % count($statusOptions)]);
            $subscriber->setIpAddress('192.154.1.' . ($i + 1));
            $subscriber->setSubscribedAt(new \DateTimeImmutable('-' . $subscriberOffset . ' days'));
            $subscriber->setCreatedAt(new \DateTimeImmutable('-' . ($subscriberOffset + 1) . ' days'));

            if ($subscriber->getStatus() === SubscriberStatus::Unsubscribed) {
                $unsubOffset = $unsubscribeOffsets[$i % count($unsubscribeOffsets)];
                $subscriber->setUnsubscribedAt(new \DateTimeImmutable('-' . $unsubOffset . ' days'));
            }

            // Add to "All Subscribers" list always
            $subscriber->addSubscriberList($lists['All Subscribers']);

            // Deterministic list assignment for stable seeded stats
            if ($i % 3 !== 0) {
                $subscriber->addSubscriberList($lists['Premium Members']);
            }
            if ($i % 4 === 1) {
                $subscriber->addSubscriberList($lists['Free Trial Users']);
            }
            if ($i % 5 === 2) {
                $subscriber->addSubscriberList($lists['Inactive Subscribers']);
            }
            if ($i % 7 === 3) {
                $subscriber->addSubscriberList($lists['VIP Customers']);
            }

            // Deterministic but varied tag assignment for more realistic distribution
            if ($i % 2 === 0) {
                $subscriber->addTag($tags[4]); // Newsletter
            }
            if ($i % 3 === 0) {
                $subscriber->addTag($tags[5]); // Product Updates
            }
            if ($i % 4 === 0) {
                $subscriber->addTag($tags[6]); // Marketing
            }
            if ($i % 5 === 0) {
                $subscriber->addTag($tags[7]); // Developer
            }
            if ($i % 6 === 0) {
                $subscriber->addTag($tags[3]); // Startup
            }
            if ($i % 7 === 0) {
                $subscriber->addTag($tags[1]); // Power User
            }
            if ($i % 10 === 0) {
                $subscriber->addTag($tags[0]); // Early Adopter
            }
            if ($i % 11 === 0) {
                $subscriber->addTag($tags[2]); // Enterprise
            }
            if ($subscriber->getTags()->isEmpty()) {
                $subscriber->addTag($tags[4]); // Newsletter fallback
            }

            $manager->persist($subscriber);
            $subscribers[] = $subscriber;
        }

        // Update list subscriber counts
        foreach ($lists as $list) {
            $count = 0;
            foreach ($subscribers as $sub) {
                if ($sub->getSubscriberLists()->contains($list)) {
                    $count++;
                }
            }
            $list->setSubscriberCount($count);
        }

        // --- Templates ---
        $template1 = new Template();
        $template1->setName('Welcome Email');
        $template1->setSubject('Welcome to Our Newsletter!');
        $template1->setCategory('onboarding');
        $template1->setIsDefault(true);
        $template1->setContent([
            ['type' => 'header', 'text' => 'Welcome Aboard!', 'level' => 1, 'align' => 'center'],
            ['type' => 'text', 'content' => 'Thank you for subscribing to our newsletter. We\'re excited to have you join our community! You\'ll receive the latest updates, tips, and exclusive offers directly in your inbox.', 'align' => 'left'],
            ['type' => 'button', 'text' => 'Get Started', 'url' => 'https://example.com/get-started', 'color' => '#ffffff', 'bgColor' => '#6366f1', 'align' => 'center'],
            ['type' => 'social', 'networks' => [
                ['name' => 'Twitter', 'url' => 'https://twitter.com/example'],
                ['name' => 'Facebook', 'url' => 'https://facebook.com/example'],
                ['name' => 'LinkedIn', 'url' => 'https://linkedin.com/company/example'],
            ]],
        ]);
        $manager->persist($template1);

        $template2 = new Template();
        $template2->setName('Monthly Newsletter');
        $template2->setSubject('Your Monthly Update');
        $template2->setCategory('newsletter');
        $template2->setIsDefault(true);
        $template2->setContent([
            ['type' => 'header', 'text' => 'Monthly Newsletter', 'level' => 1, 'align' => 'center'],
            ['type' => 'text', 'content' => 'Here\'s your monthly roundup of the latest news, updates, and articles we\'ve curated just for you.', 'align' => 'left'],
            ['type' => 'image', 'src' => '/template-images/featured-article.svg', 'alt' => 'Featured Article', 'width' => '100%', 'align' => 'center', 'link' => 'https://example.com/article'],
            ['type' => 'text', 'content' => 'This month we launched several exciting features that will transform how you work. Read on to discover what\'s new and how it can benefit you.', 'align' => 'left'],
            ['type' => 'button', 'text' => 'Read More', 'url' => 'https://example.com/blog', 'color' => '#ffffff', 'bgColor' => '#3b82f6', 'align' => 'center'],
            ['type' => 'divider', 'color' => '#e2e8f0', 'thickness' => '1'],
            ['type' => 'social', 'networks' => [
                ['name' => 'Twitter', 'url' => 'https://twitter.com/example'],
                ['name' => 'Facebook', 'url' => 'https://facebook.com/example'],
                ['name' => 'Instagram', 'url' => 'https://instagram.com/example'],
            ]],
        ]);
        $manager->persist($template2);

        $template3 = new Template();
        $template3->setName('Product Update');
        $template3->setSubject('New Features Just Dropped!');
        $template3->setCategory('product');
        $template3->setContent([
            ['type' => 'header', 'text' => 'Product Update', 'level' => 1, 'align' => 'center'],
            ['type' => 'text', 'content' => 'We\'ve been hard at work building features you\'ve requested. Here\'s what\'s new in the latest release.', 'align' => 'left'],
            ['type' => 'image', 'src' => '/template-images/product-screenshot.svg', 'alt' => 'New Feature Screenshot', 'width' => '100%', 'align' => 'center'],
            ['type' => 'text', 'content' => 'Our new dashboard gives you real-time insights into your data. Track performance, monitor trends, and make data-driven decisions faster than ever.', 'align' => 'left'],
            ['type' => 'button', 'text' => 'Try It Now', 'url' => 'https://example.com/new-features', 'color' => '#ffffff', 'bgColor' => '#10b981', 'align' => 'center'],
        ]);
        $manager->persist($template3);

        $template4 = new Template();
        $template4->setName('Promotional Offer');
        $template4->setSubject('Exclusive Offer Just for You!');
        $template4->setCategory('promotion');
        $template4->setContent([
            ['type' => 'header', 'text' => 'Special Offer', 'level' => 1, 'align' => 'center'],
            ['type' => 'image', 'src' => '/template-images/special-offer-banner.svg', 'alt' => 'Special Offer Banner', 'width' => '100%', 'align' => 'center', 'link' => 'https://example.com/offer'],
            ['type' => 'text', 'content' => 'For a limited time, enjoy 30% off your annual subscription. Upgrade today and unlock all premium features at an unbeatable price.', 'align' => 'center'],
            ['type' => 'button', 'text' => 'Claim Your Discount', 'url' => 'https://example.com/offer', 'color' => '#ffffff', 'bgColor' => '#ef4444', 'align' => 'center'],
            ['type' => 'spacer', 'height' => '30'],
            ['type' => 'text', 'content' => 'This offer expires at midnight. Don\'t miss out!', 'align' => 'center'],
        ]);
        $manager->persist($template4);

        $templates = [$template1, $template2, $template3, $template4];

        $manager->flush();

        // --- Campaigns ---
        // Get active subscribers for "All Subscribers" list
        $allSubsActive = array_filter($subscribers, fn($s) =>
            $s->getStatus() === SubscriberStatus::Active && $s->getSubscriberLists()->contains($lists['All Subscribers'])
        );
        $allSubsActive = array_values($allSubsActive);

        // Get active subscribers for "Premium Members" list
        $premiumActive = array_filter($subscribers, fn($s) =>
            $s->getStatus() === SubscriberStatus::Active && $s->getSubscriberLists()->contains($lists['Premium Members'])
        );
        $premiumActive = array_values($premiumActive);

        // Campaign 1: January Newsletter - Sent
        $campaign1 = new Campaign();
        $campaign1->setName('January Newsletter');
        $campaign1->setSubject('Your January Newsletter is Here!');
        $campaign1->setFromName('Newsletter Team');
        $campaign1->setFromEmail('newsletter@example.com');
        $campaign1->setTemplate($template2);
        $campaign1->setSubscriberList($lists['All Subscribers']);
        $campaign1->setStatus(CampaignStatus::Sent);
        $campaign1->setSentAt(new \DateTimeImmutable('-25 days'));
        $campaign1->setCreatedAt(new \DateTimeImmutable('-26 days'));
        $manager->persist($campaign1);

        // Campaign 2: February Newsletter - Sent
        $campaign2 = new Campaign();
        $campaign2->setName('February Newsletter');
        $campaign2->setSubject('February Updates & News');
        $campaign2->setFromName('Newsletter Team');
        $campaign2->setFromEmail('newsletter@example.com');
        $campaign2->setTemplate($template2);
        $campaign2->setSubscriberList($lists['Premium Members']);
        $campaign2->setStatus(CampaignStatus::Sent);
        $campaign2->setSentAt(new \DateTimeImmutable('-15 days'));
        $campaign2->setCreatedAt(new \DateTimeImmutable('-16 days'));
        $manager->persist($campaign2);

        // Campaign 3: Spring Sale Promo - Sent
        $campaign3 = new Campaign();
        $campaign3->setName('Spring Sale Promo');
        $campaign3->setSubject('Spring Sale - Up to 50% Off!');
        $campaign3->setFromName('Sales Team');
        $campaign3->setFromEmail('sales@example.com');
        $campaign3->setTemplate($template4);
        $campaign3->setSubscriberList($lists['All Subscribers']);
        $campaign3->setStatus(CampaignStatus::Sent);
        $campaign3->setSentAt(new \DateTimeImmutable('-10 days'));
        $campaign3->setCreatedAt(new \DateTimeImmutable('-11 days'));
        $manager->persist($campaign3);

        // Campaign 4: Product Launch - Scheduled
        $campaign4 = new Campaign();
        $campaign4->setName('Product Launch Announcement');
        $campaign4->setSubject('Introducing Our Newest Product!');
        $campaign4->setFromName('Product Team');
        $campaign4->setFromEmail('product@example.com');
        $campaign4->setTemplate($template3);
        $campaign4->setSubscriberList($lists['All Subscribers']);
        $campaign4->setStatus(CampaignStatus::Scheduled);
        $campaign4->setScheduledAt(new \DateTimeImmutable('+7 days'));
        $campaign4->setCreatedAt(new \DateTimeImmutable('-2 days'));
        $manager->persist($campaign4);

        // Campaign 5: Weekly Tips - Draft
        $campaign5 = new Campaign();
        $campaign5->setName('Weekly Tips #42');
        $campaign5->setSubject('This Week\'s Best Tips & Tricks');
        $campaign5->setFromName('Content Team');
        $campaign5->setFromEmail('content@example.com');
        $campaign5->setTemplate($template2);
        $campaign5->setSubscriberList($lists['All Subscribers']);
        $campaign5->setStatus(CampaignStatus::Draft);
        $campaign5->setCreatedAt(new \DateTimeImmutable('-1 day'));
        $manager->persist($campaign5);

        $manager->flush();

        // --- Create CampaignRecipients and TrackingEvents for sent campaigns ---
        $clickUrls = [
            'https://example.com/article',
            'https://example.com/blog',
            'https://example.com/offer',
            'https://example.com/new-features',
            'https://example.com/get-started',
        ];

        $this->createRecipientsForCampaign($manager, $campaign1, $allSubsActive, $clickUrls, '-25 days', 34);
        $this->createRecipientsForCampaign($manager, $campaign2, $premiumActive, $clickUrls, '-15 days', 17);
        $this->createRecipientsForCampaign($manager, $campaign3, $allSubsActive, $clickUrls, '-10 days', 34);

        $manager->flush();
    }

    private function createRecipientsForCampaign(
        ObjectManager $manager,
        Campaign $campaign,
        array $subscribers,
        array $clickUrls,
        string $sentTimeOffset,
        int $maxRecipients,
    ): void {
        $recipientSubscribers = array_slice($subscribers, 0, min($maxRecipients, count($subscribers)));
        $sentAt = new \DateTimeImmutable($sentTimeOffset);
        $totalRecipients = count($recipientSubscribers);

        $openCount = 0;
        $clickCount = 0;
        $bounceCount = 0;
        $unsubscribeCount = 0;

        foreach ($recipientSubscribers as $i => $subscriber) {
            $recipient = new CampaignRecipient();
            $recipient->setCampaign($campaign);
            $recipient->setSubscriber($subscriber);
            $recipient->setTrackingId(Uuid::v4()->toRfc4122());
            $recipient->setSentAt($sentAt);
            $recipient->setCreatedAt($sentAt);

            // Determine recipient fate using deterministic percentages
            $fate = ($i * 100) / $totalRecipients;

            if ($fate < 5) {
                // ~5% bounced
                $recipient->setStatus(RecipientStatus::Bounced);
                $bounceCount++;

                $event = new TrackingEvent();
                $event->setCampaignRecipient($recipient);
                $event->setType(TrackingEventType::Bounce);
                $eventMinutes = [1, 4, 8, 12, 15, 21, 24, 28, 31, 35];
                $event->setCreatedAt(new \DateTimeImmutable($sentTimeOffset . ' +' . $this->cycleInt($eventMinutes, $i) . ' minutes'));
                $manager->persist($event);
            } elseif ($fate < 7) {
                // ~2% unsubscribed
                $recipient->setStatus(RecipientStatus::Unsubscribed);
                $unsubscribeCount++;

                $event = new TrackingEvent();
                $event->setCampaignRecipient($recipient);
                $event->setType(TrackingEventType::Unsubscribe);
                $eventHours = [8, 12, 15, 20, 21, 24, 28, 30, 35, 40];
                $event->setCreatedAt(new \DateTimeImmutable($sentTimeOffset . ' +' . $this->cycleInt($eventHours, $i) . ' hours'));
                $manager->persist($event);
            } elseif ($fate < 37) {
                // ~30% clicked (implies opened)
                $openHours = [12, 18, 24, 30, 35, 40, 44, 48, 50, 54];
                $clickHours = [71, 75, 80, 85, 91, 95, 101, 105, 110, 115];
                $openedAt = new \DateTimeImmutable($sentTimeOffset . ' +' . $this->cycleInt($openHours, $i) . ' hours');
                $clickedAt = new \DateTimeImmutable($sentTimeOffset . ' +' . $this->cycleInt($clickHours, $i) . ' hours');

                $recipient->setStatus(RecipientStatus::Clicked);
                $recipient->setOpenedAt($openedAt);
                $recipient->setClickedAt($clickedAt);
                $openCount++;
                $clickCount++;

                $openEvent = new TrackingEvent();
                $openEvent->setCampaignRecipient($recipient);
                $openEvent->setType(TrackingEventType::Open);
                $openEvent->setIpAddress('10.0.' . $this->safeIpOctet($i) . '.' . $this->safeIpOctet($i + 3));
                $openEvent->setUserAgent('Mozilla/5.0 (compatible; EmailClient)');
                $openEvent->setCreatedAt($openedAt);
                $manager->persist($openEvent);

                $clickEvent = new TrackingEvent();
                $clickEvent->setCampaignRecipient($recipient);
                $clickEvent->setType(TrackingEventType::Click);
                $clickEvent->setUrl($clickUrls[$i % count($clickUrls)]);
                $clickEvent->setIpAddress('10.0.' . $this->safeIpOctet($i + 6) . '.' . $this->safeIpOctet($i + 9));
                $clickEvent->setUserAgent('Mozilla/5.0 (compatible; EmailClient)');
                $clickEvent->setCreatedAt($clickedAt);
                $manager->persist($clickEvent);
            } elseif ($fate < 67) {
                // ~30% opened but not clicked (total opened ~60%)
                $openHoursOnly = [11, 14, 18, 22, 25, 30, 35, 40, 44, 50];
                $openedAt = new \DateTimeImmutable($sentTimeOffset . ' +' . $this->cycleInt($openHoursOnly, $i) . ' hours');

                $recipient->setStatus(RecipientStatus::Opened);
                $recipient->setOpenedAt($openedAt);
                $openCount++;

                $openEvent = new TrackingEvent();
                $openEvent->setCampaignRecipient($recipient);
                $openEvent->setType(TrackingEventType::Open);
                $openEvent->setIpAddress('10.0.' . $this->safeIpOctet($i + 11) . '.' . $this->safeIpOctet($i + 13));
                $openEvent->setUserAgent('Mozilla/5.0 (compatible; EmailClient)');
                $openEvent->setCreatedAt($openedAt);
                $manager->persist($openEvent);
            } else {
                // ~33% sent but not opened
                $recipient->setStatus(RecipientStatus::Sent);
            }

            $manager->persist($recipient);
        }

        $campaign->setTotalRecipients($totalRecipients);
        $campaign->setSentCount($totalRecipients);
        $campaign->setOpenCount($openCount);
        $campaign->setClickCount($clickCount);
        $campaign->setBounceCount($bounceCount);
        $campaign->setUnsubscribeCount($unsubscribeCount);
    }

    private function cycleInt(array $values, int $index): int
    {
        return $values[$index % count($values)];
    }

    private function safeIpOctet(int $index): int
    {
        $octets = [11, 12, 14, 15, 18, 19, 21, 22, 24, 25, 31, 32, 34, 35, 41, 42, 44, 45, 51, 52];
        return $octets[$index % count($octets)];
    }
}
