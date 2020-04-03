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
 * Class Product
 * @package SubscriptionBundle\Entity
 *
 * @ORM\Table(name="products")
 * @ORM\Entity(repositoryClass="SubscriptionBundle\Repository\ProductRepository")
 */
class Product
{
    /**
     * Hook timestampable behavior
     * updates createdAt, updatedAt fields
     */
    use TimestampableEntity;

    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     *
     * @Serializer\Groups({
     *      "products_list"
     * })
     */
    private $id;

    /**
     * @var int $price
     * @ORM\Column(name="price", type="integer")
     */
    private $price;

    /**
     * @var string $currencyCode
     * @ORM\Column(name="currency_code", type="string", length=5, nullable=true)
     */
    private $currencyCode;

    /**
     * @var string $productId
     * @ORM\Column(name="product_id", type="string")
     * @Serializer\Groups({
     *      "products_list"
     * })
     */
    private $productId;

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
    public function getProductId(): string
    {
        return $this->productId;
    }

    /**
     * @param string $productId
     */
    public function setProductId(string $productId): void
    {
        $this->productId = $productId;
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
     * @Serializer\SerializedName("title")
     * @Serializer\Groups({
     *     "products_list"
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
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("price")
     * @Serializer\Groups({
     *     "products_list"
     * })
     *
     * @return int
     * @throws \Money\UnknownCurrencyException
     */
    public function getRawAmount(): int
    {
        return (new MoneyFormatter())->asFloat($this->getPrice());
    }

    /**
     * @param Money $price
     * @return $this
     */
    public function setPrice(Money $price) : Product
    {
        $this->price = $price->getAmount();
        $this->currencyCode = $price->getCurrency()->getName();

        return $this;
    }
}