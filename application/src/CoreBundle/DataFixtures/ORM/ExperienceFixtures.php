<?php

namespace CoreBundle\DataFixtures\ORM;

use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Bundle\FixturesBundle\ORMFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use SubscriptionBundle\Entity\Package;
use SubscriptionBundle\Entity\Subscription;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use UserApiBundle\Entity\ApiToken;
use UserApiBundle\Entity\User;

/**
 * Class ExperienceFixtures
 * @package CoreBundle\DataFixtures\ORM
 */
class ExperienceFixtures implements ORMFixtureInterface, FixtureGroupInterface
{
    /** @var UserPasswordEncoderInterface $encoder */
    private $encoder;

    /**
     * ExperienceFixtures constructor.
     * @param UserPasswordEncoderInterface $encoder
     */
    public function __construct(UserPasswordEncoderInterface $encoder)
    {
        $this->encoder = $encoder;
    }

    /**
     * @return array
     */
    public static function getGroups(): array
    {
        return ['examples'];
    }

    /**
     * @param ObjectManager $manager
     * @throws \Exception
     */
    public function load(ObjectManager $manager)
    {
        /** @var User $user */
        $user = new User();
        $user->setEmail('user@example.com');
        $user->setPassword($this->encoder->encodePassword($user, '88RL7M%dJ$u]{pAP'));
        $user->setIsVerified(true);
        $user->setIsTrialUsed(true);
        $user->setIsExample(true);

        $manager->persist($user);

        /** @var ApiToken $token */
        $token = new ApiToken();
        $token->setUser($user);
        $token->setToken(md5(uniqid().time()));
        $token->setExpireAt((new \DateTime())->modify('+1 day'));

        $manager->persist($token);

        /** @var Package $package */
        $package = $manager->getRepository(Package::class)->findOneBy(['braintreePlanId' => 'cr6b']);

        /** @var Subscription $subscription */
        $subscription = new Subscription();
        $subscription->setUser($user);
        $subscription->setStatus(Subscription::STATUS_ACTIVE);
        $subscription->setPackage($package);
        $subscription->setExpiresAt((new \DateTime())->add(new \DateInterval('P1M')));
        $subscription->setInitialBalanceAmount(0);

        $manager->persist($subscription);
        $manager->flush();
    }
}