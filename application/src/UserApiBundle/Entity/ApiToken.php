<?php

namespace UserApiBundle\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Index;
use JMS\Serializer\{
    Annotation as Serializer
};

/**
 * ApiToken
 *
 * @ORM\Table(name="api_token", indexes={
 *     @Index(name="token_idx", columns={"token"}),
 *     @Index(name="user_scope_client_idx", columns={"user_id", "scope", "client_id"})
 * })
 * @ORM\Entity(repositoryClass="UserApiBundle\Repository\ApiTokenRepository")
 */
class ApiToken
{
    const SUPPORTED_SCOPES = [self::SCOPE_ANDROID, self::SCOPE_IOS];

    const SCOPE_ANDROID = 'android';
    const SCOPE_IOS = 'ios';

    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var User $user
     * @ORM\ManyToOne(targetEntity="User", inversedBy="apiToken")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id")
     */
    private $user;

    /**
     * @var string
     *
     * @ORM\Column(name="token", type="string")
     * @Serializer\Groups({
     *     "auth.android",
     *     "auth.ios",
     *     "auth"
     * })
     */
    private $token;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="expire_at", type="datetime")
     */
    private $expireAt;

    /**
     * @var string
     * @ORM\Column(name="scope", type="string", nullable=true)
     */
    private $scope;

    /**
     * @ORM\ManyToOne(targetEntity="UserApiBundle\Entity\Client", inversedBy="apiToken", cascade={"remove"})
     * @ORM\JoinColumn(name="client_id", referencedColumnName="id", nullable=true)
     */
    private $client = null;

    /**
     * Get id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param $token
     * @return ApiToken
     */
    public function setToken($token) {
        $this->token = $token;
        return $this;
    }

    /**
     * @return string
     */
    public function getToken() {
        return $this->token;
    }

    /**
     * @param User $user
     * @return ApiToken
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
     * @param \DateTime $expireAt
     * @return ApiToken
     */
    public function setExpireAt($expireAt)
    {
        $this->expireAt = $expireAt;
        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getExpireAt()
    {
        return $this->expireAt;
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function isExpired() : bool
    {
        return $this->getExpireAt() <= new \DateTime();
    }

    /**
     * @return string|null
     */
    public function getScope(): ?string
    {
        return $this->scope;
    }

    /**
     * @param string|null $scope
     */
    public function setScope(?string $scope = null): void
    {
        $this->scope = $scope;
    }

    /**
     * @return Client|null
     */
    public function getClient(): ?Client
    {
        return $this->client;
    }

    /**
     * @param Client|null $client
     */
    public function setClient(?Client $client = null): void
    {
        $this->client = $client;
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("client_id")
     * @Serializer\Groups({
     *     "auth.android",
     *     "auth.ios",
     *     "auth"
     * })
     * @return string|null
     */
    public function getClientId(): ?string
    {
        return is_null($this->getClient()) ? null : $this->getClient()->getPublicId();
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("secret")
     * @Serializer\Groups({
     *     "verify.android",
     *     "verify.ios",
     *     "auth"
     * })
     * @return string|null
     */
    public function getSecret(): ?string
    {
        return is_null($this->getClient()) ? null : $this->getClient()->getSecret();
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\Groups({
     *     "auth.android",
     *     "auth.ios",
     *     "auth"
     * })
     * @return DateTime|null
     * @throws \Exception
     */
    public function getSubscriptionExpiredAt(): ?DateTime
    {
        return $this->user->getSubscriptionExpiredAt();
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\Groups({
     *     "auth.android",
     *     "auth.ios",
     *     "auth"
     * })
     * @return int|null
     * @throws \Exception
     */
    public function getSubscriptionStatus(): ?int
    {
        return $this->user->getSubscriptionStatus();
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\Groups({
     *     "auth.android",
     *     "auth.ios",
     *     "auth"
     * })
     * @return bool
     */
    public function getIsAutorenew(): bool
    {
        return $this->user->getIsAutorenew();
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\Groups({
     *     "auth.android",
     *     "auth.ios",
     *     "auth"
     * })
     * @return bool|null
     */
    public function isSubscriptionTrial(): ?bool
    {
        return $this->user->isSubscriptionTrial();
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("balance")
     * @Serializer\Groups({
     *     "auth.android",
     *     "auth.ios",
     *     "auth"
     * })
     * @return string|null
     *
     * @throws \Money\UnknownCurrencyException
     */
    public function getFormattedBalance(): ?string
    {
        return $this->user->getFormattedBalance();
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\Groups({
     *     "auth.android",
     *     "auth.ios",
     *     "auth"
     * })
     * @return int|null
     *
     * @throws \Exception
     */
    public function getPricePerRecognition()
    {
        return Balance::PAY_PER_RECOGNITION;
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("id")
     * @Serializer\Groups({
     *     "auth.android",
     *     "auth.ios",
     *     "auth"
     * })
     *
     * @return int
     */
    public function getUserId(): int
    {
        return $this->user->getId();
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("email")
     * @Serializer\Groups({
     *     "auth.android",
     *     "auth.ios",
     *     "auth"
     * })
     * @return string
     */
    public function getUserEmail(): string
    {
        return $this->user->getEmail();
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("last_name")
     * @Serializer\Groups({
     *     "auth.android",
     *     "auth.ios",
     *     "auth"
     * })
     * @return string|null
     */
    public function getUserLastName(): ?string
    {
        return $this->user->getLastName();
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("first_name")
     * @Serializer\Groups({
     *     "auth.android",
     *     "auth.ios",
     *     "auth"
     * })
     * @return string|null
     */
    public function getUserFirstName(): ?string
    {
        return $this->user->getFirstName();
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("phone_number")
     * @Serializer\Groups({
     *     "auth.android",
     *     "auth.ios",
     *     "auth"
     * })
     * @return string|null
     */
    public function getUserPhoneNumber(): ?string
    {
        return $this->user->getPhoneNumber();
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("website")
     * @Serializer\Groups({
     *     "auth.android",
     *     "auth.ios",
     *     "auth"
     * })
     * @return string|null
     */
    public function getUserWebsite(): ?string
    {
        return $this->user->getWebsite();
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\Groups({
     *     "auth.android",
     *     "auth.ios",
     *     "auth"
     * })
     * @return bool
     */
    public function isTrialUsed(): bool
    {
        return $this->user->isTrialUsed();
    }
}

