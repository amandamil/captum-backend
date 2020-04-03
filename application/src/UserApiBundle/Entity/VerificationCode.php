<?php

namespace UserApiBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Index;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class VerificationCode
 * @package UserApiBundle\Entity
 *
 * @ORM\Table(name="verification_codes", indexes={
 *     @Index(name="actual_code_email_idx", columns={ "code", "email", "used", "status", "type", "sent_at" })
 * })
 * @ORM\Entity(repositoryClass="UserApiBundle\Repository\VerificationCodeRepository")
 */
class VerificationCode
{
    /**
     * @var int
     *
     * @ORM\Column(type="integer", options={"unsigned"=true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var string
     *
     * @ORM\Column(name="email", type="string", length=255, nullable=false)
     *
     * @Assert\NotBlank()
     * @Assert\Email(
     *     message = "The email {{ value }} is not a valid email.",
     *     checkMX = true
     * )
     * @Assert\Length(
     *     min = 1,
     *     max = 255,
     *     minMessage = "Your email should be minimum 1 symbols length",
     *     maxMessage = "Your email should have maximum 255 symbols"
     * )
     */
    protected $email;

    /**
     * @var string
     *
     * @ORM\Column(name="code", type="string", length=6, nullable=false)
     *
     * @Assert\NotBlank()
     * @Assert\Length(min=6, max=6)
     */
    protected $code;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable=false)
     *
     * @Assert\Type(type="bool")
     */
    protected $used = false;

    /**
     * @var \DateTime
     *
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $usedAt;

    /**
     * @var string
     *
     * @ORM\Column(type="string", nullable=false, length=100)
     *
     * @Assert\Choice(callback={"UserApiBundle\Model\VerificationCodeStatus", "getChoices"})
     */
    protected $status;

    /**
     * @var \DateTime
     *
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $sentAt;

    /**
     * @var \DateTime
     *
     * @ORM\Column(type="datetime", nullable=false)
     */
    protected $expiredAt;

    /**
     * @var string
     *
     * @ORM\Column(type="string", nullable=true, length=64)
     *
     * @Assert\Choice(callback={"UserApiBundle\Model\VerificationCodeType", "getChoices"})
     *
     */
    protected $type;

    /**
     * VerificationCode constructor.
     */
    public function __construct()
    {
        $this->expiredAt = (new \DateTime())->modify('+1 day');
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * @param string $email
     */
    public function setEmail($email)
    {
        $this->email = $email;
    }

    /**
     * @return string
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * @param string $code
     */
    public function setCode($code)
    {
        $this->code = $code;
    }

    /**
     * @return bool
     */
    public function isUsed()
    {
        return $this->used;
    }

    /**
     * @param bool $used
     */
    public function setUsed($used)
    {
        $this->used = $used;
    }

    /**
     * @return \DateTime|null
     */
    public function getUsedAt() : ?string
    {
        return $this->usedAt;
    }

    /**
     * @param \DateTime|null $usedAt
     */
    public function setUsedAt(\DateTime $usedAt = null)
    {
        $this->usedAt = $usedAt;
    }

    /**
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @param string $status
     */
    public function setStatus($status)
    {
        $this->status = $status;
    }

    /**
     * @return \DateTime|null
     */
    public function getSentAt() : ?string
    {
        return $this->sentAt;
    }

    /**
     * @param \DateTime|null $sentAt
     */
    public function setSentAt(\DateTime $sentAt = null)
    {
        $this->sentAt = $sentAt;
    }

    /**
     * @return \DateTime
     */
    public function getExpiredAt()
    {
        return $this->expiredAt;
    }

    /**
     * @param \DateTime $expiredAt
     */
    public function setExpiredAt($expiredAt)
    {
        $this->expiredAt = $expiredAt;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param string $type
     */
    public function setType($type)
    {
        $this->type = $type;
    }
}