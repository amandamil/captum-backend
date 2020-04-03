<?php

namespace CoreBundle\DataFixtures\ORM;

use Doctrine\Bundle\FixturesBundle\{
    FixtureGroupInterface,
    ORMFixtureInterface
};
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use UserApiBundle\Entity\User;

/**
 * Class AdminFixture
 * @package CoreBundle\DataFixtures\ORM
 */
class AdminFixture implements ORMFixtureInterface, FixtureGroupInterface
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
        return ['admins'];
    }

    /**
     * @param ObjectManager $manager
     */
    public function load(ObjectManager $manager): void
    {
        $admin = new User();
        $admin->setEmail('admin@admin.com');
        $admin->setPassword($this->encoder->encodePassword($admin, '^6mf/Y~ZArdAVTyC'));
        $admin->setIsVerified(true);
        $admin->setIsExample(true);
        $admin->setRoles(['ROLE_ADMIN']);

        $manager->persist($admin);

        $superAdmin = new User();
        $superAdmin->setEmail('super_admin@admin.com');
        $superAdmin->setPassword($this->encoder->encodePassword($superAdmin, 'aB~=t2$xe*x&jM,4'));
        $superAdmin->setIsVerified(true);
        $superAdmin->setIsExample(true);
        $superAdmin->setRoles(['ROLE_SUPER_ADMIN']);

        $manager->persist($superAdmin);
        $manager->flush();
    }
}