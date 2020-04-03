<?php

namespace UserApiBundle\Entity;

use Doctrine\Common\Collections\{
    ArrayCollection,
    Collection,
    Criteria
};
use ExperienceBundle\Entity\{
    Experience,
    TargetView
};
use ExperienceBundle\Repository\{
    ExperienceRepository,
    TargetViewRepository
};
use SubscriptionBundle\Repository\SubscriptionRepository;
use SubscriptionBundle\Entity\{
    Transaction,
    Subscription
};
use Symfony\Component\{
    Validator\Constraints as Assert,
    Security\Core\User\UserInterface
};
use JMS\Serializer\{
    Annotation as Serializer,
    Annotation\VirtualProperty
};
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Doctrine\ORM\Mapping as ORM;
use DateTime;

/**
 * User
 *
 * @ORM\Table(name="users")
 * @ORM\Entity(repositoryClass="UserApiBundle\Repository\UserRepository")
 * @ORM\HasLifecycleCallbacks()
 * @UniqueEntity(fields="email", message="This email is already used.")
 */
class User implements UserInterface
{
    const ROLE_DEFAULT = 'ROLE_USER';
    const ROLE_SUPER_ADMIN = 'ROLE_SUPER_ADMIN';

    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @Serializer\Groups({"auth"})
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="email", type="string", length=255, unique=true)
     *
     * @Assert\NotBlank()
     * @Assert\Email(
     *     message = "The email {{ value }} is not a valid email.",
     *     checkMX = true
     * )
     * @Assert\Length(
     *     min = 1,
     *     max = 255,
     *     minMessage = "Your email should be minimum 1 symbols length",
     *     maxMessage = "Your email should have maximum 255 symbols"
     * )
     * @Serializer\Groups({"auth"})
     */
    private $email;

    /**
     * @var string
     *
     * @Assert\NotBlank()
     * @Assert\Regex(
     *     pattern="/^(\w|[!@#$%^*()_+\-=\[\]{}\\,.\/?~`]){6,50}$/",
     *     message="Password should have only letters, numbers and symbols(e.g. @, ., #, -, _) and it's length should be from 6 to 50"
     * )
     *
     * @ORM\Column(name="password", type="string")
     */
    private $password;

    /**
     * @var string
     *
     * @Assert\Length(
     *     min = 1,
     *     max = 100,
     *     minMessage = "Your last name should have minimum 1 symbol",
     *     maxMessage = "Your last name should have maximum 255 symbol",
     *     groups={"update"}
     * )
     * @ORM\Column(name="last_name", type="string", nullable=true)
     * @Serializer\Groups({"auth"})
     */
    private $lastName;

    /**
     * @var string
     *
     * @Assert\Length(
     *     min = 1,
     *     max = 100,
     *     minMessage = "Your first name should have minimum 1 symbol",
     *     maxMessage = "Your first name should have maximum 255 symbol",
     *     groups={"update"}
     * )
     *
     * @ORM\Column(name="first_name", type="string", nullable=true)
     * @Serializer\Groups({"auth"})
     */
    private $firstName;

    /**
     * @ORM\Column(name="roles", type="json_array")
     */
    private $roles = ['ROLE_USER'];

    /**
     * @ORM\Column(name="is_verified", type="boolean")
     */
    private $isVerified = false;

    /**
     * @var Collection
     * @ORM\OneToMany(targetEntity="ApiToken", mappedBy="user")
     */
    private $apiToken;

    /**
     * @var Collection
     * @ORM\OneToMany(targetEntity="Device", mappedBy="user")
     */
    private $devices;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="created_at", type="datetime")
     */
    private $createdAt;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="updated_at", type="datetime")
     */
    private $updatedAt;

    /**
     * @var string
     *
     * @ORM\Column(name="phone_number", type="string", length=30, nullable=true)
     * @Assert\Regex(
     *     pattern="/^\d{0,20}$/", message="Phone number should contain from 0 to 20 digits",
     *     groups={"update"}
     * )
     * @Serializer\Groups({"auth"})
     */
    private $phoneNumber;

    /**
     * @var Experience[]|Collection
     * @ORM\OneToMany(targetEntity="ExperienceBundle\Entity\Experience", mappedBy="user")
     */
    private $experience;

    /**
     * @var string
     * @ORM\Column(name="website", type="string", length=255, nullable=true)
     * @Assert\Length(
     *     min = 1,
     *     max = 255,
     *     minMessage = "Your website should have minimum 1 symbol",
     *     maxMessage = "Your website should have maximum 255 symbol",
     *     groups={"update"}
     * )
     * @Serializer\Groups({"auth"})
     */
    private $website;

    /**
     * @var Subscription[]|Collection
     * @ORM\OneToMany(targetEntity="SubscriptionBundle\Entity\Subscription", mappedBy="user")
     * @ORM\OrderBy({"createdAt" = "ASC"})
     */
    private $subscription;

    /**
     * @var string $customerId
     * @ORM\Column(name="customer_id", type="string", nullable=true)
     */
    private $customerId;

    /**
     * @var bool $isTrialUsed
     * @ORM\Column(name="is_trial_used", type="boolean")
     * @Serializer\Groups({"auth"})
     */
    private $isTrialUsed = false;

    /**
     * @var Balance $balance
     * @ORM\OneToOne(targetEntity="UserApiBundle\Entity\Balance", mappedBy="user")
     * @Serializer\Groups({"subscription_current"})
     */
    private $balance;

    /**
     * @var TargetView[]|Collection
     * @ORM\OneToMany(targetEntity="ExperienceBundle\Entity\TargetView", mappedBy="user")
     */
    private $targetView;

    /**
     * @var Transaction[]|Collection
     * @ORM\OneToMany(targetEntity="SubscriptionBundle\Entity\Transaction", mappedBy="user")
     */
    private $transactions;

    /**
     * @var bool
     * @ORM\Column(name="is_example", type="boolean", options={"default": false})
     */
    private $isExample = false;

    /**
     * User constructor.
     */
    public function __construct() {
        $this->experience = new ArrayCollection();
        $this->apiToken = new ArrayCollection();
        $this->devices = new ArrayCollection();
        $this->subscription = new ArrayCollection();
        $this->targetView = new ArrayCollection();
        $this->transactions = new ArrayCollection();
    }

    /**
     * @return array
     */
    public function getRoles()
    {
        $roles = $this->roles;

        // guarantees that a user always has at least one role for security
        if (empty($roles)) {
            $roles[] = 'ROLE_USER';
        }

        return array_unique($roles);
    }

    /**
     * @param $roles
     */
    public function setRoles($roles)
    {
        $this->roles = $roles;
    }

    /**
     * @param string $role
     *
     * @return bool
     */
    public function hasRole($role)
    {
        return in_array(strtoupper($role), $this->getRoles(), true);
    }

    public function getSalt()
    {
        return null;
    }

    public function eraseCredentials()
    {
        // TODO: Implement eraseCredentials() method.
    }

    /**
     * @ORM\PrePersist
     */
    public function setCreatedAtValue()
    {
        $this->createdAt = new \DateTime();
    }

    /**
     * @ORM\PrePersist
     * @ORM\PreUpdate
     */
    public function setUpdatedAtValue()
    {
        $this->updatedAt = new \DateTime();
    }

    /**
     * Get id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get email
     *
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * @param $email
     * @return User
     */
    public function setEmail($email)
    {
        $this->email = $email;
        return $this;
    }

    /**
     * Get last name
     *
     * @return string|null
     */
    public function getLastName()
    {
        return $this->lastName;
    }

    /**
     * @param string|null $lastName
     * @return User
     */
    public function setLastName($lastName = null)
    {
        $this->lastName = $lastName;
        return $this;
    }

    /**
     * Get first name
     *
     * @return string|null
     */
    public function getFirstName()
    {
        return $this->firstName;
    }

    /**
     * @param string|null $firstName
     * @return User
     */
    public function setFirstName($firstName = null)
    {
        $this->firstName = $firstName;
        return $this;
    }

    /**
     * Get email
     *
     * @return string
     */
    public function getUsername()
    {
        return $this->email;
    }

    /**
     * Get password
     *
     * @return string
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @param $password
     * @return User
     */
    public function setPassword($password)
    {
        $this->password = $password;
        return $this;
    }

    /**
     * Get created date
     *
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * Set updated date
     *
     * @param \DateTime $updatedAt
     *
     * @return User
     */
    public function setUpdatedAt($updatedAt)
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * Get updated date
     *
     * @return \DateTime
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    /**
     * @param $role
     * @return $this
     */
    public function addRole($role)
    {
        $role = strtoupper($role);
        if ($role === static::ROLE_DEFAULT) {
            return $this;
        }

        if (!in_array($role, $this->roles, true)) {
            $this->roles[] = $role;
        }

        return $this;
    }

    /**
     * @return Collection|ApiToken[]
     */
    public function getApiToken()
    {
        return $this->apiToken;
    }

    /**
     * @param mixed $apiToken
     */
    public function setApiToken($apiToken)
    {
        $this->apiToken = $apiToken;
    }

    /**
     * @return Collection|Device[]
     */
    public function getDevices()
    {
        return $this->devices;
    }

    /**
     * @param Device $device
     * @return User
     */
    public function addDevice(Device $device): self
    {
        if (!$this->devices->contains($device)) {
            $this->devices[] = $device;
            $device->setUser($this);
        }

        return $this;
    }

    /**
     * @param Device $device
     * @return User
     */
    public function removeDevice(Device $device): self
    {
        if ($this->devices->contains($device)) {
            $this->devices->removeElement($device);
        }

        return $this;
    }

    /**
     * @VirtualProperty
     * @Serializer\Groups({"auth"})
     */
    public function getToken()
    {
        return $this->apiToken->last()->getToken();
    }

    /**
     * @return bool
     */
    public function getIsVerified()
    {
        return $this->isVerified;
    }

    /**
     * @param bool $isVerified
     *
     * @return User
     */
    public function setIsVerified($isVerified)
    {
        $this->isVerified = $isVerified;
        return $this;
    }

    /**
     * Add Token.
     *
     * @param ApiToken $token
     *
     * @return User
     */
    public function addApiToken(ApiToken $token)
    {
        if (!$this->apiToken->contains($token)) {
            $this->apiToken->add($token);
            $token->setUser($this);
        }

        return $this;
    }

    /**
     * Remove Token.
     *
     * @param ApiToken $token
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeApiToken(ApiToken $token)
    {
        return $this->apiToken->removeElement($token);
    }

    /**
     * Get ucpUsers.
     *
     * @return Collection
     */
    public function getApiTokens()
    {
        return $this->apiToken;
    }

    /**
     * Set phone number
     *
     * @param string|null $phoneNumber
     *
     * @return User
     */
    public function setPhoneNumber($phoneNumber = null)
    {
        $this->phoneNumber = $phoneNumber;

        return $this;
    }

    /**
     * Get phone number
     *
     * @return string|null
     */
    public function getPhoneNumber()
    {
        return $this->phoneNumber;
    }

    /**
     * @return Experience[]|Collection
     */
    public function getExperience()
    {
        return $this->experience;
    }

    /**
     * @param Experience $experience
     *
     * @return $this
     */
    public function addExperience(Experience $experience): self
    {
        if (!$this->experience->contains($experience)) {
            $this->experience->add($experience);
        }

        return $this;
    }

    /**
     * @param Experience $experience
     *
     * @return $this
     */
    public function removeExperience(Experience $experience): self
    {
        $this->experience->removeElement($experience);

        return $this;
    }

    /**
     * @return string|null
     */
    public function getWebsite(): ?string
    {
        return $this->website;
    }

    /**
     * @param string|null $website
     */
    public function setWebsite(?string $website): void
    {
        $this->website = $website;
    }

    /**
     * @return Collection|Subscription[]
     */
    public function getSubscription()
    {
        return $this->subscription;
    }

    /**
     * @param Collection|Subscription[] $subscription
     */
    public function setSubscription($subscription): void
    {
        $this->subscription = $subscription;
    }

    /**
     * @return string|null
     */
    public function getCustomerId(): ?string
    {
        return $this->customerId;
    }

    /**
     * @param string|null $customerId
     */
    public function setCustomerId(string $customerId = null): void
    {
        $this->customerId = $customerId;
    }

    /**
     * @return bool
     */
    public function isTrialUsed(): bool
    {
        return $this->isTrialUsed;
    }

    /**
     * @param bool $isTrialUsed
     */
    public function setIsTrialUsed(bool $isTrialUsed): void
    {
        $this->isTrialUsed = $isTrialUsed;
    }

    /**
     * @return Subscription|bool
     */
    public function getLastSubscription()
    {
        return $this->getSubscription()
            ->filter(function (Subscription $item){
                return $item->getStatus() !== Subscription::STATUS_PENDING;
            })
            ->last();
    }

    /**
     * @return Subscription|null
     * @throws \Exception
     */
    public function getActiveSubscription(): ?Subscription
    {
        foreach ($this->getSubscription() as $subscription) {
            if($subscription->getStatus() === Subscription::STATUS_ACTIVE && $subscription->getExpiresAt() > (new DateTime())) {
                return $subscription;
            }
        }

        return null;
    }

    /**
     * @return Subscription|null
     * @throws \Exception
     */
    public function getActiveSubscriptionUpdatePaymentMethod(): ?Subscription
    {
        $subscription = $this->subscription
            ->matching(SubscriptionRepository::createActiveSubscriptionUpdatePaymentMethodCriteria())
            ->last();

        return $subscription instanceof Subscription ? $subscription : null;
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\Groups({"auth"})
     *
     * @return DateTime|null
     * @throws \Exception
     */
    public function getSubscriptionExpiredAt(): ?DateTime
    {
        $current = $this->getLastSubscription();
        return $current ? $current->getExpiresAt() : null;
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\Groups({"auth"})
     *
     * @throws \Exception
     * @return int|null
     */
    public function getSubscriptionStatus(): ?int
    {
        $current = $this->getLastSubscription();
        return $current ? $current->getStatus() : null;
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\Groups({"auth"})
     *
     * @return bool
     */
    public function getIsAutorenew()
    {
        $last = $this->getLastSubscription();
        return $last ? $last->isAutorenew() : false;
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\Groups({"auth"})
     *
     * @return bool|null
     */
    public function isSubscriptionTrial(): ?bool
    {
        /** @var Subscription $current */
        $current = $this->getLastSubscription();
        return $current ? $current->getPackage()->isTrial() : null;
    }

    /**
     * @return ArrayCollection|Collection
     */
    public function getExperiencesOrderDesc()
    {
        $criteria = Criteria::create()->orderBy(['createdAt' => Criteria::DESC]);
        return $this->experience->matching($criteria);
    }

    /**
     * @return Balance|null
     */
    public function getBalance(): ?Balance
    {
        return $this->balance;
    }

    /**
     * @param Balance $balance
     */
    public function setBalance(Balance $balance): void
    {
        $this->balance = $balance;
    }

    /**
     * @return Collection|TargetView[]
     */
    public function getTargetView()
    {
        return $this->targetView;
    }

    /**
     * @param TargetView $targetView
     * @return $this
     */
    public function addTargetView(TargetView $targetView)
    {
        if (!$this->targetView->contains($targetView)) {
            $this->targetView->add($targetView);
        }

        return $this;
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("balance")
     * @Serializer\Groups({"auth"})
     *
     * @return string|null
     * @throws \Money\UnknownCurrencyException
     */
    public function getFormattedBalance(): ?string
    {
        $balance = $this->getBalance();
        if (!is_null($balance)) {
            return $balance->getFormattedAmount();
        }

        return null;
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\Groups({"auth"})
     * @return int|null
     *
     * @throws \Exception
     */
    public function getPricePerRecognition()
    {
        return Balance::PAY_PER_RECOGNITION;
    }

    /**
     * @param Subscription $subscription
     * @return int
     * @throws \Exception
     */
    public function getViewsNumber(Subscription $subscription): int
    {
        $sum = 0;

        $expiresAt = $subscription->getExpiresAt();

        $periodStarts = clone $expiresAt;
        $periodStarts = $subscription->getPackage()->isTrial()
            ? $periodStarts->modify("-".$subscription->getPackage()->getExpiresInMonths()." month")
            : $periodStarts->modify("-".$subscription->getPackage()->getExpiresInMonths()." month");

        /** @var TargetView[]|Collection $targetViews */
        $targetViews = $this->targetView
            ->matching(TargetViewRepository::createViewsNumberCriteria(
                $periodStarts,
                $expiresAt,
                $subscription->getPackage()->isTrial())
            );

        foreach ($targetViews as $targetView) {
            $sum += $targetView->getViews();
        }

        return $sum;
    }

    /**
     * @param Subscription|null $subscription
     * @return int
     * @throws \Exception
     */
    public function getViewsLeftFreeNumber(?Subscription $subscription = null): int
    {
        if (is_null($subscription)) {
            return 0;
        }

        /** @var int $subscriptionRecognitionNumber */
        $subscriptionRecognitionNumber = $subscription->getPackage()->getRecognitionsNumber();

        /** @var int $viewsNumber */
        $viewsNumber = $this->getViewsNumber($subscription);

        $diff = $subscriptionRecognitionNumber - $viewsNumber;

        return $diff;
    }

    /**
     * @param Subscription|null $subscription
     * @return int
     * @throws \Exception
     */
    public function getTotalFreeAvailableViews(?Subscription $subscription = null): int
    {
        if (is_null($subscription)) {
            return 0;
        }

        return $subscription->getPackage()->getRecognitionsNumber();
    }

    /**
     * @param Subscription|null $subscription
     * @return int
     * @throws \Money\UnknownCurrencyException
     */
    public function getViewsLeftPaidNumber(?Subscription $subscription): int
    {
        if (is_null($subscription)) {
            return 0;
        }

        /** @var Balance $balance */
        $balance = $this->getBalance();

        if (is_null($balance)) {
            return 0;
        }

        $chargesAmount = $this->getSubscriptionPeriodCharges($subscription);

        $limit = (int)$balance->getMonthlyLimit()->getAmount();
        $balanceAmount = $balance->getAmount()->getAmount();

        if ($balance->isChargeLimitEnabled()) {
            $limitDiff = $limit - $chargesAmount;
            if ($limitDiff <= 0) {
                $amount = $limitDiff;
            } else {
                $amount = $limitDiff > $balanceAmount ? $balanceAmount : $limitDiff;
            }

        } else {
            $amount = $balanceAmount;
        }

        return $amount/Balance::PAY_PER_RECOGNITION;
    }

    /**
     * @param Subscription $subscription
     * @return int
     * @throws \Money\UnknownCurrencyException
     */
    public function getTotalAvailableViews(Subscription $subscription): int
    {
        /** @var Balance $balance */
        $balance = $this->getBalance();

        if (is_null($balance)) {
            return 0;
        }

        $limit = (int)$balance->getMonthlyLimit()->getAmount();
        $balanceAmount = (int)$subscription->getInitialBalanceAmount();

        if ($balance->isChargeLimitEnabled()) {
            $amount = $limit > $balanceAmount ? $balanceAmount : $limit;
        } else {
            $amount = $balanceAmount;
        }

        return $amount/Balance::PAY_PER_RECOGNITION;
    }

    /**
     * @param Subscription|null $subscription
     * @return int
     * @throws \Money\UnknownCurrencyException
     */
    public function getSubscriptionPeriodCharges(?Subscription $subscription = null): int
    {
        if (is_null($subscription)) {
            return 0;
        }
        /** @var Balance $balance */
        $balance = $this->getBalance();

        if (is_null($balance)) {
            return 0;
        }

        $chargesAmount = 0;

        $expiresAt = $subscription->getExpiresAt();
        $periodStarts = clone $expiresAt;
        $periodStarts = $subscription->getPackage()->isTrial()
            ? $periodStarts->modify("-".$subscription->getPackage()->getExpiresInMonths()." month")
            : $periodStarts->modify("-".$subscription->getPackage()->getExpiresInMonths()." month");

        /** @var Charge[]|Collection $charges */
        $charges = $balance->getChargesByPeriod($periodStarts, $expiresAt);

        foreach ($charges as $charge) {
            $chargesAmount += $charge->getAmount()->getAmount();
        }

        return $chargesAmount;
    }

    /**
     * @return Collection|Transaction[]
     */
    public function getTransactions()
    {
        return $this->transactions;
    }

    /**
     * @param Collection|Transaction[] $transactions
     */
    public function setTransactions($transactions): void
    {
        $this->transactions = $transactions;
    }

    /**
     * Add Transaction to Order
     *
     * @param Transaction $transaction
     */
    public function addTransaction(Transaction $transaction): void
    {
        if (!$this->transactions->contains($transaction)) {
            $this->transactions->add($transaction);
        }
    }

    /**
     * @return bool
     */
    public function isExample(): bool
    {
        return $this->isExample;
    }

    /**
     * @param bool $isExample
     */
    public function setIsExample(bool $isExample): void
    {
        $this->isExample = $isExample;
    }

    /**
     * @param Experience $currentExperience
     * @return int
     */
    public function getActiveWithoutCurrentCount(Experience $currentExperience): int
    {
        return $this->experience
            ->matching(ExperienceRepository::createActiveWithoutCurrentCountCriteria($currentExperience->getId()))
            ->count();
    }

    /**
     * @return Subscription|bool
     */
    public function getLastPendingSubscription()
    {
        return $this->getSubscription()
        ->filter(function (Subscription $item){
            return $item->getStatus() === Subscription::STATUS_PENDING;
        })
        ->last();
    }
}
