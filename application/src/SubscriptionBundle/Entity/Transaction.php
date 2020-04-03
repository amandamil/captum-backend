<?php

namespace SubscriptionBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Money\Currency;
use Money\Money;
use phpDocumentor\Reflection\Types\Self_;
use Tbbc\MoneyBundle\Formatter\MoneyFormatter;
use JMS\Serializer\{
    Annotation as Serializer
};

/**
 * @ORM\Table(name="transactions")
 * @ORM\Entity(repositoryClass="SubscriptionBundle\Repository\TransactionRepository")
 */
class Transaction
{
    const TYPE_SUBSCRIPTION = 0;
    const TYPE_BALANCE = 1;

    /** https://developers.braintreepayments.com/reference/general/statuses#transaction */
    const STATUS_AUTHORIZED = 'authorized';
    const STATUS_AUTHORIZATION_EXPIRED = 'authorization_expired';
    const STATUS_PROCESSOR_DECLINED = 'processor_declined';
    const STATUS_GATEWAY_REJECTED = 'gateway_rejected';
    const STATUS_FAILED = 'failed';
    const STATUS_VOIDED = 'voided';
    const STATUS_SUBMITTED_FOR_SETTLEMENT = 'submitted_for_settlement';
    const STATUS_SETTLING = 'settling';
    const STATUS_SETTLED = 'settled';
    const STATUS_SETTLEMENT_DECLINED = 'settlement_declined';
    const STATUS_SETTLEMENT_PENDING = 'settlement_pending';
    const STATUS_SUCCESS = 'success';

    const SUCCESS_STATUSES = [
        self::STATUS_AUTHORIZED,
        self::STATUS_SUBMITTED_FOR_SETTLEMENT,
        self::STATUS_SETTLED,
        self::STATUS_SETTLING,
        self::STATUS_SETTLEMENT_PENDING,
        self::STATUS_SUCCESS
    ];

    const FAILED_STATUSES = [
        self::STATUS_AUTHORIZATION_EXPIRED,
        self::STATUS_PROCESSOR_DECLINED,
        self::STATUS_GATEWAY_REJECTED,
        self::STATUS_FAILED,
        self::STATUS_VOIDED,
        self::STATUS_SETTLEMENT_DECLINED,
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
     * @var string $transactionId
     * @ORM\Column(name="transaction_id", type="string")
     */
    private $transactionId;

    /**
     * @var string|null $status
     * @ORM\Column(name="status", type="string", nullable=true)
     */
    private $status;

    /**
     * @ORM\ManyToOne(targetEntity="UserApiBundle\Entity\User", inversedBy="transactions")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id")
     */
    private $user;

    /**
     * @var int $amount
     * @ORM\Column(name="amount", type="bigint", nullable=true)
     */
    private $amount;

    /**
     * @var string $currencyCode
     * @ORM\Column(name="currency_code", type="string", length=5, nullable=true)
     */
    private $currencyCode;

    /**
     * @var int $type
     * @ORM\Column(name="type", type="integer")
     * @Serializer\Groups({
     *     "purchase_history",
     * })
     */
    private $type;

    /**
     * @var string|null
     * @ORM\Column(name="provider_type", type="string", nullable=true)
     */
    private $providerType;

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return string|null
     */
    public function getStatus(): ?string
    {
        return $this->status;
    }

    /**
     * @param string|null $status
     */
    public function setStatus(?string $status = null): void
    {
        $this->status = $status;
    }

    /**
     * @return string
     */
    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    /**
     * @param string $transactionId
     */
    public function setTransactionId(string $transactionId): void
    {
        $this->transactionId = $transactionId;
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
    public function setAmount(Money $amount): Transaction
    {
        $this->amount = $amount->getAmount();
        $this->currencyCode = $amount->getCurrency()->getName();

        return $this;
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("balance_amount")
     * @Serializer\Groups({
     *     "purchase_history",
     * })
     *
     * @return string
     * @throws \Money\UnknownCurrencyException
     */
    public function getFormattedAmount(): string
    {
        return (new MoneyFormatter())->localizedFormatMoney($this->getAmount());
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
     * @return int
     */
    public function getType(): int
    {
        return $this->type;
    }

    /**
     * @param int $type
     */
    public function setType(int $type): void
    {
        $this->type = $type;
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\Groups({
     *     "purchase_history",
     * })
     */
    public function getDate()
    {
       return $this->getCreatedAt();
    }

    /**
     * @return string|null
     */
    public function getProviderType(): ?string
    {
        return $this->providerType;
    }

    /**
     * @param string|null $providerType
     */
    public function setProviderType(?string $providerType): void
    {
        $this->providerType = $providerType;
    }
}