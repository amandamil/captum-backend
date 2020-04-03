<?php

namespace UserApiBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * Device
 *
 * @ORM\Table(name="devices")
 * @ORM\Entity(repositoryClass="UserApiBundle\Repository\DeviceRepository")
 * @UniqueEntity("deviceToken")
 */
class Device
{
    const PLATFORM_IOS = 'ios';
    const PLATFORM_ANDROID = 'android';

    const PUSH_TYPE_PAYMENT = 0;
    const PUSH_TYPE_EXPERIENCE = 1;

    const PUSH_VALUE_LIMIT_PERCENT_REACHED = 20;
    const PUSH_VALUE_BALANCE_PERCENT_REACHED = 10;

    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @Serializer\Groups({"device"})
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="User", inversedBy="devices")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id")
     */
    private $user;

    /**
     * @var string
     *
     * @ORM\Column(name="device_token", type="string", length=255, unique=true)
     * @Assert\NotNull()
     * @Assert\Type("string")
     * @Assert\Length(min=50, max=255)
     * @Serializer\Groups({"device"})
     */
    private $deviceToken;

    /**
     * @var string
     *
     * @ORM\Column(name="aws_arn", type="string", length=255)
     * @Assert\NotNull(groups="final")
     * @Assert\Type("string")
     * @Assert\Length(min=50, max=255)
     * @Serializer\Groups({"device"})
     */
    private $awsArn;

    /**
     * @var string
     *
     * @ORM\Column(name="platform", type="string", length=10)
     * @Assert\NotNull()
     * @Assert\Type("string")
     * @Assert\Choice({"android", "ios"})
     * @Serializer\Groups({"device"})
     */
    private $platform;

    /**
     * @var string|null
     *
     * @ORM\Column(name="version", type="string", length=10, nullable=true)
     * @Assert\NotBlank()
     * @Assert\Type("string")
     * @Assert\Length(min=1, max=10)
     * @Serializer\Groups({"device"})
     */
    private $version;


    /**
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param User $user
     * @return Device
     */
    public function setUser($user)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Set deviceToken.
     *
     * @param string $deviceToken
     *
     * @return Device
     */
    public function setDeviceToken($deviceToken)
    {
        $this->deviceToken = $deviceToken;

        return $this;
    }

    /**
     * Get deviceToken.
     *
     * @return string
     */
    public function getDeviceToken()
    {
        return $this->deviceToken;
    }

    /**
     * Set AWS Endpoint ARN.
     *
     * @param string $awsArn
     *
     * @return Device
     */
    public function setAwsArn($awsArn)
    {
        $this->awsArn = $awsArn;

        return $this;
    }

    /**
     * Get AWS Endpoint ARN.
     *
     * @return string
     */
    public function getAwsArn()
    {
        return $this->awsArn;
    }

    /**
     * Set platform.
     *
     * @param string $platform
     *
     * @return Device
     */
    public function setPlatform($platform)
    {
        $this->platform = $platform;

        return $this;
    }

    /**
     * Get platform.
     *
     * @return string
     */
    public function getPlatform()
    {
        return $this->platform;
    }

    /**
     * Set version.
     *
     * @param string|null $version
     *
     * @return Device
     */
    public function setVersion($version = null)
    {
        $this->version = $version;

        return $this;
    }

    /**
     * Get version.
     *
     * @return string|null
     */
    public function getVersion()
    {
        return $this->version;
    }
}
