<?php

namespace CoreBundle\DataFixtures\ORM;

use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Bundle\FixturesBundle\ORMFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Money\Currency;
use Money\Money;
use SubscriptionBundle\Entity\Product;

/**
 * Class ProductFixture
 * @package CoreBundle\DataFixtures\ORM
 */
class ProductFixture implements ORMFixtureInterface, FixtureGroupInterface
{
    /**
     * @return array
     */
    public static function getGroups(): array
    {
        return ['products'];
    }

    /**
     * @param ObjectManager $manager
     * @throws \Money\UnknownCurrencyException
     */
    public function load(ObjectManager $manager)
    {
        $products = [
            [
                'productId' => 'com.umbrellait.CaptumApp.twenty.bucks',
                'price' => new Money(2000, new Currency('USD')),
            ],
            [
                'productId' => 'com.umbrellait.CaptumApp.fifty.bucks',
                'price' => new Money(5000, new Currency('USD')),
            ],
            [
                'productId' => 'com.umbrellait.CaptumApp.onehundred.bucks',
                'price' => new Money(10000, new Currency('USD')),
            ],
            [
                'productId' => 'com.umbrellait.CaptumApp.threehundred.bucks',
                'price' => new Money(30000, new Currency('USD')),
            ],
            [
                'productId' => 'com.umbrellait.CaptumApp.fivehundred.bucks',
                'price' => new Money(50000, new Currency('USD')),
            ],
            [
                'productId' => 'com.umbrellait.CaptumApp.onethousand.bucks',
                'price' => new Money(100000, new Currency('USD')),
            ]
        ];

        foreach ($products as $index => $product) {
            $newProduct = new Product();
            $newProduct->setProductId($product['productId']);
            $newProduct->setPrice($product['price']);

            $manager->persist($newProduct);
        }

        $manager->flush();
    }
}