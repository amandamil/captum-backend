<?php

namespace SubscriptionBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use ExperienceBundle\Entity\Experience;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use ReceiptValidator\iTunes\PendingRenewalInfo;
use UserApiBundle\Entity\User;
use JMS\Serializer\Annotation as Serializer;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class Subscription
 *
 * @ORM\Table(name="subscriptions")
 * @ORM\Entity(repositoryClass="SubscriptionBundle\Repository\SubscriptionRepository")
 */
class Subscription
{
    const STATUS_PENDING = 0;
    const STATUS_ACTIVE = 1;
    const STATUS_EXPIRED = 2;
    const STATUS_CANCELED = 3;
    const STATUS_CHARGED_UNSUCCESSFULLY = 4;
    const STATUS_PAST_DUE = 5;

    const PROVIDER_BRAINTREE = 'braintree';
    const PROVIDER_APPLE_IN_APP = 'apple_in_app';

    const INTENT_ERRORS = [
        PendingRenewalInfo::EXPIRATION_INTENT_CANCELLED,
        PendingRenewalInfo::EXPIRATION_INTENT_BILLING_ERROR,
        PendingRenewalInfo::EXPIRATION_INTENT_INCREASE_DECLINED,
        PendingRenewalInfo::EXPIRATION_INTENT_PRODUCT_UNAVAILABLE,
        PendingRenewalInfo::EXPIRATION_INTENT_UNKNOWN
    ];

    /**
     * Hook timestampable behavior
     * updates createdAt, updatedAt fields
     */
    use TimestampableEntity;

    /**
     * @var int $id
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="UserApiBundle\Entity\User", inversedBy="subscription")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id")
     */
    private $user;

    /**
     * @ORM\ManyToOne(targetEntity="SubscriptionBundle\Entity\Package", fetch="EAGER")
     * @ORM\JoinColumn(name="package_id", referencedColumnName="id")
     * @Serializer\Groups({"subscription_current"})
     * @Assert\NotBlank(message="Package should not be blank")
     */
    private $package;

    /**
     * @var \DateTime $expiresAt
     * @ORM\Column(name="expires_at", type="datetime")
     * @Serializer\Groups({
     *     "subscription_current",
     * })
     */
    private $expiresAt;

    /**
     * @var int $status
     *
     * @ORM\Column(name="status", type="integer")
     * @Serializer\Groups({
     *     "subscription_current",
     * })
     */
    private $status;

    /**
     * @var string $braintreeId
     * @ORM\Column(name="braintree_id", type="string", nullable=true)
     */
    private $braintreeId;

    /**
     * @var bool $isAutorenew
     * @ORM\Column(name="is_autorenew", type="boolean")
     * @Serializer\Groups({"subscription_current"})
     */
    private $isAutorenew;

    /**
     * @var int $initialBalanceAmount
     * @ORM\Column(name="initial_balance_amount", type="bigint")
     */
    private $initialBalanceAmount;

    /**
     * @var \DateTime|null
     * @ORM\Column(name="changed_plan_at", type="datetime", nullable=true)
     */
    protected $changedPlanAt;

    /**
     * @var string|null
     * @ORM\Column(name="apple_order_line_item_id", type="string", nullable=true)
     */
    private $appleOrderLineItemId;

    /**
     * @var string|null
     * @ORM\Column(name="provider_type", type="string", nullable=true)
     * @Serializer\Accessor(getter="getProviderType")
     * @Serializer\Groups({"subscription_current"})
     */
    private $providerType;

    /**
     * @var string|null
     * @ORM\Column(name="apple_receipt", type="text", nullable=true)
     */
    private $appleReceipt = null;

   /**
    * @var bool|null $appleDowngradeEnabled
    * @ORM\Column(name="apple_downgrade_enabled", type="boolean", nullable=true)
    */
    private $appleDowngradeEnabled = null;

    /**
     * @var Package|null $nextPlan
     * @ORM\ManyToOne(targetEntity="SubscriptionBundle\Entity\Package")
     * @ORM\JoinColumn(name="next_plan_id", referencedColumnName="id", nullable=true)
     */
    private $nextPlan = null;

    /**
     * @var string|null
     * @ORM\Column(name="apple_original_transaction_id", type="text", nullable=true)
     */
    private $appleOriginalTransactionId = null;

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getUser(): User
    {
        return $this->user;
    }

    /**
     * @param mixed $user
     */
    public function setUser($user): void
    {
        $this->user = $user;
    }

    /**
     * @return mixed
     */
    public function getPackage(): ?Package
    {
        return $this->package;
    }

    /**
     * @param mixed $package
     */
    public function setPackage($package = null): void
    {
        $this->package = $package;
        if (!is_null($package)) {
            $this->setIsAutorenew(!$this->getPackage()->isTrial());
        }
    }

    /**
     * @return \DateTime
     */
    public function getExpiresAt(): \DateTime
    {
        return $this->expiresAt;
    }

    /**
     * @param \DateTime $expiresAt
     */
    public function setExpiresAt(\DateTime $expiresAt): void
    {
        $this->expiresAt = $expiresAt;
    }

    /**
     * @return string
     */
    public function getBraintreeId(): ?string
    {
        return $this->braintreeId;
    }

    /**
     * @param string $braintreeId
     */
    public function setBraintreeId(string $braintreeId = null): void
    {
        $this->braintreeId = $braintreeId;
    }

    /**
     * @return int
     */
    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * @param int $status
     */
    public function setStatus(int $status): void
    {
        $this->status = $status;
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\Groups({"subscription_current"})
     *
     * @return int
     * @throws \Exception
     */
    public function getActiveExperiencesCount(): int
    {
        $count = 0;
        $user = $this->getUser();
        $experiences = $user->getExperience();
        $subscription = $user->getActiveSubscription();

        if (!is_null($subscription) && $subscription->getPackage()->isTrial()) {
            foreach ($experiences as $experience) {
                if (!in_array($experience->getStatus(),[Experience::EXPERIENCE_REJECTED, Experience::EXPERIENCE_DELETED])) {
                    ++$count;
                }
            }
        } else {
            foreach ($experiences as $experience) {
                if (in_array($experience->getStatus(), [Experience::EXPERIENCE_PENDING, Experience::EXPERIENCE_ACTIVE])) {
                    ++$count;
                }
            }
        }

        return $count;
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\Groups({"subscription_current"})
     *
     * @return int
     * @throws \Exception
     */
    public function getAvailableExperiencesCount(): int
    {
        return $this->getPackage()->getExperiencesNumber() - $this->getActiveExperiencesCount();
    }

    /**
     * @param Experience $currentExperience
     * @return int
     * @throws \Exception
     */
    public function getActiveWithoutCurrentCount(Experience $currentExperience): int
    {
        $count = 0;
        $user = $this->getUser();

        if (!is_null($user->getActiveSubscription())) {
            $count = $user->getActiveWithoutCurrentCount($currentExperience);
        }

        return $count;
    }

    /**
     * @param Experience $currentExperience
     * @return int
     * @throws \Exception
     */
    public function getAvailableExperiencesWithoutCurrentCount(Experience $currentExperience): int
    {
        return $this->getPackage()->getExperiencesNumber() - $this->getActiveWithoutCurrentCount($currentExperience);
    }

    /**
     * @return bool
     */
    public function isAutorenew(): bool
    {
        return $this->isAutorenew;
    }

    /**
     * @param bool $isAutoRenew
     */
    public function setIsAutorenew(bool $isAutoRenew): void
    {
        $this->isAutorenew = $isAutoRenew;
    }

    /**
     * @return int
     */
    public function getInitialBalanceAmount(): int
    {
        return $this->initialBalanceAmount;
    }

    /**
     * @param int $initialBalanceAmount
     */
    public function setInitialBalanceAmount(int $initialBalanceAmount): void
    {
        $this->initialBalanceAmount = $initialBalanceAmount;
    }

    /**
     * @return \DateTime|null
     */
    public function getChangedPlanAt(): ?\DateTime
    {
        return $this->changedPlanAt;
    }

    /**
     * @param \DateTime|null $changedPlanAt
     */
    public function setChangedPlanAt(?\DateTime $changedPlanAt): void
    {
        $this->changedPlanAt = $changedPlanAt;
    }

    /**
     * @return string|null
     */
    public function getAppleOrderLineItemId(): ?string
    {
        return $this->appleOrderLineItemId;
    }

    /**
     * @param string|null $appleOrderLineItemId
     */
    public function setAppleOrderLineItemId(?string $appleOrderLineItemId = null): void
    {
        $this->appleOrderLineItemId = $appleOrderLineItemId;
    }

    /**
     * @return string|null
     */
    public function getProviderType(): ?string
    {
        return is_null($this->providerType) && !is_null($this->getBraintreeId())
            ? self::PROVIDER_BRAINTREE
            : $this->providerType;
    }

    /**
     * @param string|null $providerType
     */
    public function setProviderType(?string $providerType = null): void
    {
        $this->providerType = $providerType;
    }

    /**
     * @param string|null $receipt
     * @return void
     */
    public function setAppleReceipt(?string $receipt = null): void
    {
        $this->appleReceipt = $receipt;
    }

    /**
     * @return string|null
     */
    public function getAppleReceipt(): ?string
    {
        return $this->appleReceipt;
    }

    /**
     * @return bool|null
     */
    public function getAppleDowngradeEnabled(): ?bool
    {
        return $this->appleDowngradeEnabled;
    }

    /**
     * @param bool|null $appleDowngradeEnabled
     */
    public function setAppleDowngradeEnabled(?bool $appleDowngradeEnabled = null): void
    {
        $this->appleDowngradeEnabled = $appleDowngradeEnabled;
    }

    /**
     * @return Package|null
     */
    public function getNextPlan(): ?Package
    {
        return $this->nextPlan;
    }

    /**
     * @param Package|null $nextPlan
     */
    public function setNextPlan(?Package $nextPlan): void
    {
        $this->nextPlan = $nextPlan;
    }

    /**
     * @return string|null
     */
    public function getAppleOriginalTransactionId(): ?string
    {
        return $this->appleOriginalTransactionId;
    }

    /**
     * @param string|null $appleOriginalTransactionId
     */
    public function setAppleOriginalTransactionId(?string $appleOriginalTransactionId = null): void
    {
        $this->appleOriginalTransactionId = $appleOriginalTransactionId;
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function isActive(): bool
    {
        return $this->getStatus() === self::STATUS_ACTIVE && $this->getExpiresAt() > (new \DateTime());
    }
}