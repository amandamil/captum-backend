<?php

namespace UserApiBundle\Entity;

use Braintree\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Index;

/**
 * Class Client
 * @package UserApiBundle\Entity
 *
 * @ORM\Table(name="clients", indexes={@Index(name="public_id_idx", columns={"id","random_id"})})
 * @ORM\Entity(repositoryClass="UserApiBundle\Repository\ClientRepository")
 */
class Client
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     * @ORM\Column(name="random_id", type="string")
     */
    private $randomId;

    /**
     * @var string
     * @ORM\Column(name="secret", type="string")
     */
    private $secret;

    /**
     * @var ApiToken[]|Collection
     * @ORM\OneToMany(targetEntity="UserApiBundle\Entity\ApiToken", mappedBy="client", cascade={"remove"})
     */
    private $apiToken;

    /**
     * ApiToken constructor.
     */
    public function __construct()
    {
        $this->apiToken = new ArrayCollection();
        $this->randomId = uniqid('', true);
        $this->secret = uniqid('', true);
    }

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
     * @return string
     */
    public function getRandomId(): string
    {
        return $this->randomId;
    }

    /**
     * @param string $randomId
     */
    public function setRandomId(string $randomId): void
    {
        $this->randomId = $randomId;
    }

    /**
     * @return string
     */
    public function getSecret(): string
    {
        return $this->secret;
    }

    /**
     * @param string $secret
     */
    public function setSecret(string $secret): void
    {
        $this->secret = $secret;
    }

    /**
     * @return string
     */
    public function getPublicId(): string
    {
        return sprintf('%s_%s', $this->getId(), $this->getRandomId());
    }

    /**
     * @param string|null $secret
     * @return bool
     */
    public function checkSecret(?string $secret): bool
    {
        return null === $this->secret || $secret === $this->secret;
    }

    /**
     * @return ApiToken[]|Collection
     */
    public function getApiToken()
    {
        return $this->apiToken;
    }

    /**
     * @param ApiToken $apiToken
     * @return $this
     */
    public function addApiToken(ApiToken $apiToken)
    {
        if (!$this->apiToken->contains($apiToken)) {
            $this->apiToken->add($apiToken);
        }

        return $this;
    }

    /**
     * @param Collection|ApiToken[] $apiToken
     */
    public function setApiToken($apiToken): void
    {
        $this->apiToken = $apiToken;
    }
}