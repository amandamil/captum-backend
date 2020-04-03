<?php

namespace CoreBundle\DataFixtures\ORM;

use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Bundle\FixturesBundle\ORMFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Money\Currency;
use Money\Money;
use SubscriptionBundle\Entity\Package;

/**
 * Class PackageFixtures
 * @package CoreBundle\DataFixtures
 */
class PackageFixtures implements ORMFixtureInterface, FixtureGroupInterface
{
    /**
     * @return array
     */
    public static function getGroups(): array
    {
        return ['packages'];
    }

    /**
     * @param ObjectManager $manager
     * @throws \Money\UnknownCurrencyException
     */
    public function load(ObjectManager $manager)
    {
        $packages = [
            [
                'title' => 'Free trial',
                'description' => 'Free 1 day trial',
                'price' => new Money(0, new Currency('USD')),
                'expiresInMonths' => 0,
                'experiencesNumber' => 1,
                'isTrial' => true,
                'recognitionsNumber' => 100,
                'isPublic' => true,
            ],
            [
                'title' => '1 active experience',
                'description' => '1 exp for $20',
                'price' => new Money(2000, new Currency('USD')),
                'expiresInMonths' => 1,
                'experiencesNumber' => 1,
                'braintreePlanId' => 'ckpm',
                'appleProductId' => 'T30iMCs4jG',
                'recognitionsNumber' => 1000,
                'isPublic' => true,
            ],
            [
                'title' => '3 active experiences',
                'description' => '3 exp for $45',
                'price' => new Money(4500, new Currency('USD')),
                'expiresInMonths' => 1,
                'experiencesNumber' => 3,
                'braintreePlanId' => 'sj2w',
                'appleProductId' => 'Fp9aAoHgHL',
                'recognitionsNumber' => 1000,
                'isPublic' => true,
            ],
            [
                'title' => '5 active experiences',
                'description' => '5 exp for $60',
                'price' => new Money(6000, new Currency('USD')),
                'expiresInMonths' => 1,
                'experiencesNumber' => 5,
                'braintreePlanId' => '44q6',
                'appleProductId' => 'lfmYhFh1J2',
                'recognitionsNumber' => 1000,
                'isPublic' => true,
            ],
            [
                'title' => '10 active experiences',
                'description' => '10 exp for $100',
                'price' => new Money(10000, new Currency('USD')),
                'expiresInMonths' => 1,
                'experiencesNumber' => 10,
                'braintreePlanId' => 'j33g',
                'appleProductId' => 'Y36llAPBb3',
                'recognitionsNumber' => 1000,
                'isPublic' => true,
            ],
            [
                'title' => '10 experiences for $20',
                'description' => '10 experiences for $20',
                'price' => new Money(2000, new Currency('USD')),
                'expiresInMonths' => 1,
                'experiencesNumber' => 10,
                'braintreePlanId' => 'kfym',
                'appleProductId' => 'A9hAVpYoKL',
                'recognitionsNumber' => 1000000,
            ],
            [
                'title' => '10 experiences for free',
                'description' => '10 experiences for free',
                'price' => new Money(0, new Currency('USD')),
                'expiresInMonths' => 1,
                'experiencesNumber' => 10,
                'braintreePlanId' => 'cr6b',
                'appleProductId' => 'TATg5hYDrI',
                'recognitionsNumber' => 1000000,
            ],
        ];

        foreach ($packages as $index => $package) {
            $newPackage = new Package();
            $newPackage->setTitle($package['title']);
            $newPackage->setDescription($package['description']);
            $newPackage->setPrice($package['price']);
            $newPackage->setExpiresInMonths($package['expiresInMonths']);
            $newPackage->setExperiencesNumber($package['experiencesNumber']);
            $newPackage->setRecognitionsNumber($package['recognitionsNumber']);

            if (isset($package['isPublic'])) {
                $newPackage->setIsPublic($package['isPublic']);
            }

            if ($index === 0) {
                $newPackage->setIsTrial($package['isTrial']);
            } else {
                $newPackage->setBraintreePlanId($package['braintreePlanId']);
                $newPackage->setAppleProductId($package['appleProductId']);
            }

            $manager->persist($newPackage);
        }

        $manager->flush();
    }
}