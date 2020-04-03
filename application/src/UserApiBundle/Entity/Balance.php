<?php

namespace UserApiBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Money\{
    Currency,
    Money
};
use JMS\Serializer\{
    Annotation as Serializer
};
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Tbbc\MoneyBundle\Formatter\MoneyFormatter;
use Doctrine\ORM\Mapping as ORM;
use UserApiBundle\Repository\ChargeRepository;

/**
 * Class Balance
 * @package UserApiBundle\Entity
 *
 * @ORM\Table(name="balances")
 * @ORM\Entity(repositoryClass="UserApiBundle\Repository\BalanceRepository")
 */
class Balance
{
    const PAY_PER_RECOGNITION = 2;

    const VIEW_BUCK_20 = 'com.umbrellait.CaptumApp.twenty.bucks';
    const VIEW_BUCK_50 = 'com.umbrellait.CaptumApp.fifty.bucks';
    const VIEW_BUCK_100 = 'com.umbrellait.CaptumApp.onehundred.bucks';
    const VIEW_BUCK_300 = 'com.umbrellait.CaptumApp.threehundred.bucks';
    const VIEW_BUCK_500 = 'com.umbrellait.CaptumApp.fivehundred.bucks';
    const VIEW_BUCK_1000 = 'com.umbrellait.CaptumApp.onethousand.bucks';

    const VIEW_BUCKS = [
        self::VIEW_BUCK_20,
        self::VIEW_BUCK_50,
        self::VIEW_BUCK_100,
        self::VIEW_BUCK_300,
        self::VIEW_BUCK_500,
        self::VIEW_BUCK_1000
    ];

    public static $amounts = [
        Balance::VIEW_BUCK_20 => 20,
        Balance::VIEW_BUCK_50 => 50,
        Balance::VIEW_BUCK_100 => 100,
        Balance::VIEW_BUCK_300 => 300,
        Balance::VIEW_BUCK_500 => 500,
        Balance::VIEW_BUCK_1000 => 1000,
    ];

    /**
     * Hook timestampable behavior
     * updates createdAt, updatedAt fields
     */
    use TimestampableEntity;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", options={"unsigned"=true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var int $amount
     * @ORM\Column(name="amount", type="bigint")
     */
    private $amount;

    /**
     * @var string $currencyCode
     * @ORM\Column(name="currency_code", type="string", length=5, nullable=true)
     */
    private $currencyCode;

    /**
     * @ORM\OneToOne(targetEntity="UserApiBundle\Entity\User", inversedBy="balance")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id")
     */
    private $user;

    /**
     * @var int $monthlyLimit
     * @ORM\Column(name="monthly_limit", type="bigint", nullable=true)
     */
    private $monthlyLimit;

    /**
     * @var bool $isChargeLimitEnabled
     * @ORM\Column(name="is_charge_limit_enabled", type="boolean", options={"default":false})
     * @Serializer\Groups({
     *     "subscription_current",
     *     "balance"
     * })
     */
    private $isChargeLimitEnabled = false;

    /**
     * @var  $isLimitWarningEnabled
     * @ORM\Column(name="is_limit_warning_enabled", type="boolean", options={"default":false})
     * @Serializer\Groups({
     *     "subscription_current",
     *     "balance"
     * })
     */
    private $isLimitWarningEnabled = false;

    /**
     * @var Charge[]|Collection
     * @ORM\OneToMany(targetEntity="UserApiBundle\Entity\Charge", mappedBy="balance")
     */
    private $charges;

    /**
     * @var \DateTime|null
     * @ORM\Column(name="refill_balance_at", type="datetime", nullable=true)
     */
    protected $refillBalanceAt = null;

    /**
     * Subscription constructor.
     */
    public function __construct()
    {
        $this->charges = new ArrayCollection();
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return Money|null
     * @throws \Money\UnknownCurrencyException
     */
    public function getAmount(): ?Money
    {
        if (!$this->currencyCode) {
            return null;
        }

        if (!$this->amount) {
            return new Money(0, new Currency($this->currencyCode));
        }

        return new Money((int)$this->amount, new Currency($this->currencyCode));
    }

    /**
     * @param Money $amount
     * @return $this
     */
    public function setAmount(Money $amount): Balance
    {
        $this->amount = $amount->getAmount();
        $this->currencyCode = $amount->getCurrency()->getName();

        return $this;
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("balance_amount")
     * @Serializer\Groups({
     *     "balance",
     *     "subscription_current",
     * })
     *
     * @return string
     * @throws \Money\UnknownCurrencyException
     */
    public function getFormattedAmount(): string
    {
        $amount = $this->getAmount()->getAmount();
        $money = $amount < 0 ? new Money(0, new Currency($this->currencyCode)) : $this->getAmount();
        return (new MoneyFormatter())->localizedFormatMoney($money);
    }

    /**
     * @return mixed
     */
    public function getUser()
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
     * @return Money|null
     * @throws \Money\UnknownCurrencyException
     */
    public function getMonthlyLimit(): ?Money
    {
        if (!$this->currencyCode) {
            return null;
        }

        if (!$this->amount) {
            return new Money(0, new Currency($this->currencyCode));
        }

        return new Money((int)$this->monthlyLimit, new Currency($this->currencyCode));
    }

    /**
     * @return string
     * @throws \Money\UnknownCurrencyException
     *
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("montly_limit_amount")
     * @Serializer\Groups({
     *     "subscription_current",
     *     "balance"
     * })
     */
    public function getFormattedMonthlyLimit(): string
    {
        return (new MoneyFormatter())->localizedFormatMoney($this->getMonthlyLimit());
    }

    /**
     * @param Money $monthlyLimit
     */
    public function setMonthlyLimit(Money $monthlyLimit): void
    {
        $this->monthlyLimit = $monthlyLimit->getAmount();
    }

    /**
     * @return bool
     */
    public function isChargeLimitEnabled(): bool
    {
        return $this->isChargeLimitEnabled;
    }

    /**
     * @param bool $isChargeLimitEnabled
     */
    public function setIsChargeLimitEnabled(bool $isChargeLimitEnabled): void
    {
        $this->isChargeLimitEnabled = $isChargeLimitEnabled;
    }

    /**
     * @return bool
     */
    public function isLimitWarningEnabled(): bool
    {
        return $this->isLimitWarningEnabled;
    }

    /**
     * @param bool $isLimitWarningEnabled
     */
    public function setIsLimitWarningEnabled(bool $isLimitWarningEnabled): void
    {
        $this->isLimitWarningEnabled = $isLimitWarningEnabled;
    }

    /**
     * @return Collection|Charge[]
     */
    public function getCharges()
    {
        return $this->charges;
    }

    /**
     * @param Collection|Charge[] $charges
     */
    public function setCharges($charges): void
    {
        $this->charges = $charges;
    }

    /**
     * @param Charge $charge
     */
    public function addCharge(Charge $charge): void
    {
        if (!$this->charges->contains($charge)) {
            $this->charges->add($charge);
        }
    }

    /**
     * @return \DateTime|null
     */
    public function getRefillBalanceAt(): ?\DateTime
    {
        return $this->refillBalanceAt;
    }

    /**
     * @param \DateTime|null $refillBalanceAt
     */
    public function setRefillBalanceAt(?\DateTime $refillBalanceAt): void
    {
        $this->refillBalanceAt = $refillBalanceAt;
    }

    /**
     * @param \DateTime $periodStarts
     * @param \DateTime $expiresAt
     * @return Collection
     */
    public function getChargesByPeriod(\DateTime $periodStarts, \DateTime $expiresAt): Collection
    {
        return $this->charges
            ->matching(ChargeRepository::createSubscriptionPeriodChargesCriteria($periodStarts, $expiresAt));
    }
}