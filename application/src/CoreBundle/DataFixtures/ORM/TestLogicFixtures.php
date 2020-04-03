<?php

namespace CoreBundle\DataFixtures\ORM;

use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Bundle\FixturesBundle\ORMFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use ExperienceBundle\Entity\Experience;
use Money\Currency;
use Money\Money;
use SubscriptionBundle\Entity\Package;
use SubscriptionBundle\Entity\Subscription;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use UserApiBundle\Entity\ApiToken;
use UserApiBundle\Entity\Balance;
use UserApiBundle\Entity\User;

/**
 * Class TestLogicFixtures
 * @package CoreBundle\DataFixtures\ORM
 */
class TestLogicFixtures implements ORMFixtureInterface, FixtureGroupInterface
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
        return ['test_experiences'];
    }

    /**
     * @param ObjectManager $manager
     * @throws \Exception
     */
    public function load(ObjectManager $manager)
    {
        $users = [
            [
                'email' => 'user+1@example.com',
                'password' => 'z_3eSpnNQ&<(qA]e',
                'plan_braintree_id' => '3mww',
                'plan_experiences_number' => 1,
            ],
            [
                'email' => 'user+2@example.com',
                'password' => '!K2Lj;q;B3]U)#:,',
                'plan_braintree_id' => 'hk96',
                'plan_experiences_number' => 3,
            ],
            [
                'email' => 'user+3@example.com',
                'password' => 'vVVa}`9$"k3[G^7!',
                'plan_braintree_id' => '9m42',
                'plan_experiences_number' => 5,
            ],
            [
                'email' => 'user+4@example.com',
                'password' => 'b8t,.be5/*L*<A5b',
                'plan_braintree_id' => 'cr6b',
                'plan_experiences_number' => 10,
            ],
        ];

        foreach ($users as $index => $user) {
            /** @var User $newUser */
            $newUser = new User();
            $newUser->setEmail($user['email']);
            $newUser->setPassword($this->encoder->encodePassword($newUser, $user['password']));
            $newUser->setIsVerified(true);
            $newUser->setIsTrialUsed(true);
            $newUser->setIsExample(true);

            $manager->persist($newUser);

            /** @var Balance $balance */
            $balance = new Balance();
            $balance->setUser($newUser);
            $balance->setAmount(new Money(2000, new Currency('USD')));

            $manager->persist($balance);

            /** @var ApiToken $token */
            $token = new ApiToken();
            $token->setUser($newUser);
            $token->setToken(md5(uniqid().time()));
            $token->setExpireAt((new \DateTime())->modify('+1 day'));

            $manager->persist($token);

            /** @var Package|null $package */
            $package = $manager->getRepository(Package::class)->findOneBy(['braintreePlanId' => $user['plan_braintree_id']]);

            if (is_null($package)) {
                $package = new Package();
                $package->setIsPublic(false);
                $package->setPrice(new Money(0, new Currency('USD')));
                $package->setRecognitionsNumber(1000);
                $package->setExperiencesNumber($user['plan_experiences_number']);
                $package->setBraintreePlanId($user['plan_braintree_id']);
                $package->setExpiresInMonths(1);
                $package->setTitle('test title');
                $package->setDescription('test description');

                $manager->persist($package);
            }

            /** @var Subscription $subscription */
            $subscription = new Subscription();
            $subscription->setPackage($package);
            $subscription->setUser($newUser);
            $subscription->setStatus(Subscription::STATUS_ACTIVE);
            $subscription->setExpiresAt((new \DateTime())->add(new \DateInterval('P1M')));
            $subscription->setInitialBalanceAmount(0);

            $manager->persist($subscription);

            for ($i = 0; $i < $package->getExperiencesNumber(); $i++) {
                /** @var Experience $experience */
                $experience = new Experience();
                $experience->setStatus(Experience::EXPERIENCE_ACTIVE);
                $experience->setUser($newUser);
                $experience->setIsExample(false);
                $experience->setJobStatus(Experience::TRANSCODER_JOB_STATUS_COMPLETE);
                $experience->setVuforiaStatus(Experience::VUFORIA_ACTIVE);
                $experience->setImageKey('test');
                $experience->setImageUrl('test');
                $experience->setVideoKey('test');

                $manager->persist($experience);
            }

            $manager->flush();
        }
    }
}