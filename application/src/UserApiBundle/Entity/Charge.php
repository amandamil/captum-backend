<?php

namespace UserApiBundle\Entity;

use ExperienceBundle\Entity\Experience;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Doctrine\ORM\Mapping as ORM;
use Money\Currency;
use Money\Money;

/**
 * Class Charge
 * @package UserApiBundle\Entity
 *
 * @ORM\Table(name="charges")
 * @ORM\Entity(repositoryClass="UserApiBundle\Repository\ChargeRepository")
 */
class Charge
{
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
     * @ORM\ManyToOne(targetEntity="UserApiBundle\Entity\Balance", inversedBy="charges")
     * @ORM\JoinColumn(name="balance_id", referencedColumnName="id")
     */
    private $balance;

    /**
     * @ORM\ManyToOne(targetEntity="ExperienceBundle\Entity\Experience", inversedBy="charges")
     * @ORM\JoinColumn(name="experience_id", referencedColumnName="id")
     */
    private $experience;

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
    public function getBalance(): Balance
    {
        return $this->balance;
    }

    /**
     * @param mixed $balance
     */
    public function setBalance($balance): void
    {
        $this->balance = $balance;
    }

    /**
     * @return mixed
     */
    public function getExperience(): Experience
    {
        return $this->experience;
    }

    /**
     * @param mixed $experience
     */
    public function setExperience($experience): void
    {
        $this->experience = $experience;
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
    public function setAmount(Money $amount): Charge
    {
        $this->amount = $amount->getAmount();
        $this->currencyCode = $amount->getCurrency()->getName();

        return $this;
    }
}