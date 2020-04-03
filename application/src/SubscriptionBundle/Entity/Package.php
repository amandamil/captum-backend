<?php

namespace SubscriptionBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use JMS\Serializer\Annotation as Serializer;
use Money\{
    Currency,
    Money
};
use Tbbc\MoneyBundle\Formatter\MoneyFormatter;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class Package
 *
 * @ORM\Table(name="packages")
 * @ORM\Entity(repositoryClass="SubscriptionBundle\Repository\PackageRepository")
 */
class Package
{
    /**
     * Hook timestampable behavior
     * updates createdAt, updatedAt fields
     */
    use TimestampableEntity;

    /**
     * @var int
     *
     * @Serializer\Groups({
     *     "packages_list",
     *     "subscription_current"
     * })
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="title", type="string")
     * @Serializer\Groups({
     *     "packages_list",
     *     "subscription_current"
     * })
     */
    private $title;

    /**
     * @var string
     *
     * @ORM\Column(name="description", type="string")
     * @Serializer\Groups({
     *     "packages_list",
     *     "subscription_current"
     * })
     */
    private $description;

    /**
     * @var int $price
     * @ORM\Column(name="price", type="integer")
     */
    private $price;

    /**
     * @var string $currencyCode
     *
     * @ORM\Column(name="currency_code", type="string", length=5, nullable=true)
     */
    private $currencyCode;

    /**
     * @var int $expiresInMonths
     *
     * @ORM\Column(name="expires_in_months", type="smallint", length=2)
     * @Serializer\Groups({
     *     "packages_list",
     *     "subscription_current"
     * })
     */
    private $expiresInMonths;

    /**
     * @var int $experiencesNumber
     *
     * @ORM\Column(name="experiences_number", type="smallint", length=3)
     * @Serializer\Groups({
     *     "packages_list",
     *     "subscription_current"
     * })
     */
    private $experiencesNumber;

    /**
     * @var bool $isTrial
     *
     * @ORM\Column(name="is_trial", type="boolean", options={"default":false})
     * @Serializer\Groups({
     *     "packages_list",
     *     "subscription_current"
     * })
     */
    private $isTrial = false;

    /**
     * @var string $braintreePlanId
     *
     * @ORM\Column(name="braintree_plan_id", type="string", nullable=true)
     */
    private $braintreePlanId;

    /**
     * @var string $icon
     * @ORM\Column(name="icon", type="string", nullable=true)
     *
     * @Serializer\Groups({
     *     "packages_list",
     *     "subscription_current"
     * })
     *
     * @Assert\Image(
     *     mimeTypes={ "image/jpg", "image/png", "image/jpeg" },
     *     mimeTypesMessage = "Please, upload a valid image (.jpg, .png)",
     *     maxSize="1M"
     * )
     */
    private $icon;

    /**
     * @var int $recognitionsNumber
     * @ORM\Column(name="recognitions_number", type="integer")
     * @Serializer\Groups({
     *     "packages_list",
     *     "subscription_current"
     * })
     */
    private $recognitionsNumber;

    /**
     * @var bool $isPublic
     * @ORM\Column(name="is_public", type="boolean", options={})
     */
    private $isPublic = false;

    /**
     * @var string $appleProductId
     * @ORM\Column(name="apple_product_id", type="string", nullable=true)
     * @Serializer\Groups({
     *     "packages_list",
     *     "subscription_current"
     * })
     */
    private $appleProductId;

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @param string $title
     */
    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @param string $description
     */
    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    /**
     * @return Money|null
     * @throws \Money\UnknownCurrencyException
     */
    public function getPrice(): ?Money
    {
        if (!$this->currencyCode) {
            return null;
        }

        if (!$this->price) {
            return new Money(0, new Currency($this->currencyCode));
        }

        return new Money((int)$this->price, new Currency($this->currencyCode));
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("price")
     * @Serializer\Groups({
     *     "packages_list",
     *     "subscription_current"
     * })
     *
     * @return string
     * @throws \Money\UnknownCurrencyException
     */
    public function getFormattedMoney(): string
    {
        return (new MoneyFormatter())->localizedFormatMoney($this->getPrice());
    }

    /**
     * @param Money $price
     * @return $this
     */
    public function setPrice(Money $price) : Package
    {
        $this->price = $price->getAmount();
        $this->currencyCode = $price->getCurrency()->getName();

        return $this;
    }

    /**
     * @return int
     */
    public function getExpiresInMonths(): int
    {
        return $this->expiresInMonths;
    }

    /**
     * @param int $expiresInMonths
     */
    public function setExpiresInMonths(int $expiresInMonths): void
    {
        $this->expiresInMonths = $expiresInMonths;
    }

    /**
     * @return int
     */
    public function getExperiencesNumber(): int
    {
        return $this->experiencesNumber;
    }

    /**
     * @param int $experiencesNumber
     */
    public function setExperiencesNumber(int $experiencesNumber): void
    {
        $this->experiencesNumber = $experiencesNumber;
    }

    /**
     * @return bool
     */
    public function isTrial(): bool
    {
        return $this->isTrial;
    }

    /**
     * @param bool $isTrial
     */
    public function setIsTrial(bool $isTrial): void
    {
        $this->isTrial = $isTrial;
    }

    /**
     * @return string
     */
    public function getBraintreePlanId(): string
    {
        return $this->braintreePlanId;
    }

    /**
     * @param string $braintreePlanId
     */
    public function setBraintreePlanId(string $braintreePlanId): void
    {
        $this->braintreePlanId = $braintreePlanId;
    }

    /**
     * @return string|null
     */
    public function getIcon(): ?string
    {
        return $this->icon;
    }

    /**
     * @param string|null $icon
     */
    public function setIcon(string $icon = null): void
    {
        $this->icon = $icon;
    }

    /**
     * @return int
     */
    public function getRecognitionsNumber(): int
    {
        return $this->recognitionsNumber;
    }

    /**
     * @param int $recognitionsNumber
     */
    public function setRecognitionsNumber(int $recognitionsNumber): void
    {
        $this->recognitionsNumber = $recognitionsNumber;
    }

    /**
     * @return bool
     */
    public function isPublic(): bool
    {
        return $this->isPublic;
    }

    /**
     * @param bool $isPublic
     */
    public function setIsPublic(bool $isPublic): void
    {
        $this->isPublic = $isPublic;
    }

    /**
     * @return string|null
     */
    public function getAppleProductId(): ?string
    {
        return $this->appleProductId;
    }

    /**
     * @param string|null $appleProductId
     */
    public function setAppleProductId(?string $appleProductId = null): void
    {
        $this->appleProductId = $appleProductId;
    }
}