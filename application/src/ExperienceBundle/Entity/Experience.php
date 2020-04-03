<?php

namespace ExperienceBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use UserApiBundle\Entity\Charge;
use UserApiBundle\Entity\User;
use JMS\Serializer\Annotation as Serializer;
use Doctrine\ORM\Mapping\Index;

/**
 * Experience
 *
 * @ORM\Table(name="experience", indexes={@Index(name="status_idx", columns={"status"})})
 * @ORM\Entity(repositoryClass="ExperienceBundle\Repository\ExperienceRepository")
 * @ORM\HasLifecycleCallbacks()
 */
class Experience
{
    const EXPERIENCE_REJECTED = -1;
    const EXPERIENCE_PENDING = 0;
    const EXPERIENCE_ACTIVE = 1;
    const EXPERIENCE_DISABLED = 2;
    const EXPERIENCE_DELETED = 3;

    const TRANSCODER_JOB_STATUS_PROGRESSING = 'PROGRESSING';
    const TRANSCODER_JOB_STATUS_COMPLETE = 'COMPLETED';
    const TRANSCODER_JOB_STATUS_WARNING = 'WARNING';
    const TRANSCODER_JOB_STATUS_ERROR = 'ERROR';

    const VUFORIA_REJECTED = -1;
    const VUFORIA_PROCESSING = 0;
    const VUFORIA_ACTIVE = 1;
    const VUFORIA_DISABLED = 2;

    public static $statuses = [
        Experience::EXPERIENCE_DELETED => "Experience is deleted",
        Experience::EXPERIENCE_ACTIVE => "Experience is active",
        Experience::EXPERIENCE_DISABLED => "Experience is disabled",
        Experience::EXPERIENCE_PENDING => "Experience is processing",
        Experience::EXPERIENCE_REJECTED => "Experience target is rejected",
    ];

    const IMAGE_BIT_DEPTH = [ 8, 24 ];
    const IMAGE_MAX_FILE_SIZE = '2M';
    const IMAGE_MAX_WIDTH = '4096';
    const IMAGE_MAX_HEIGHT = '4096';
    const IMAGE_MIN_WIDTH = 320;
    const IMAGE_FILE_MIMETYPE = [
        'image/jpg',
        'image/png',
        'image/jpeg'
    ];

    const VIDEO_MAX_SIZE = '300M';
    const VIDEO_MAX_SIZE_BYTES = 314572800;
    const VIDEO_MAX_DURATION = 90;
    const VIDEO_FILE_MIMETYPE = [
        'video/mp4',
        'video/quicktime',
        'video/x-msvideo',
        'video/mpeg',
        'video/3gpp',
        'video/3gpp2',
        'video/JPEG',
    ];

    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @Serializer\Groups({
     *     "experience_list",
     *     "experience"
     * })
     */
    private $id;

    /**
     * @var int
     * @ORM\Column(name="status", type="integer", length=2)
     * @Serializer\Groups({
     *     "experience",
     *     "experience_list",
     * })
     */
    private $status;

    /**
     * @var string
     * @ORM\Column(name="title", type="string", nullable=true)
     * @Assert\Length(
     *     min = 1,
     *     max = 255,
     *     minMessage = "Title should be minimum 1 symbol length",
     *     maxMessage = "Title should have maximum 255 symbols"
     * )
     * @Serializer\Groups({
     *     "experience",
     *     "experience_list",
     * })
     */
    private $title;

    /**
     * @var string
     * @ORM\Column(name="contact_name", type="string", nullable=true)
     * @Assert\Length(
     *     min = 0,
     *     max = 201,
     *     minMessage = "Contact name should be minimum 0 symbol length",
     *     maxMessage = "Contact name should have maximum 201 symbols"
     * )
     * @Serializer\Groups({
     *     "experience",
     *     "experience_list",
     * })
     */
    private $contactName;

    /**
     * @var string
     * @ORM\Column(name="phone", type="string", nullable=true)
     * @Assert\Regex(pattern="/^\d{0,20}$/", message="Phone number should contain from 0 to 20 digits")
     * @Serializer\Groups({
     *     "experience",
     *     "experience_list",
     * })
     */
    private $phone;

    /**
     * @var string
     * @ORM\Column(name="email", type="string", nullable=true)
     * @Assert\Email(
     *     checkMX=true,
     *     message = "The email {{ value }} is not a valid email.",
     * )
     * @Assert\Length(
     *     min = 0,
     *     max = 255,
     *     minMessage = "Email should be minimum 0 symbol length",
     *     maxMessage = "Email should have maximum 255 symbols"
     * )
     * @Serializer\Groups({
     *     "experience",
     *     "experience_list",
     * })
     */
    private $email;

    /**
     * @var string
     * @ORM\Column(name="target_id", type="string", nullable=true)
     */
    private $targetId;

    /**
     * @var \DateTime
     * @ORM\Column(name="created_at", type="datetime")
     * @Serializer\Groups({
     *     "experience",
     *     "experience_list",
     * })
     */
    private $createdAt;

    /**
     * @var \DateTime
     * @ORM\Column(name="updated_at", type="datetime")
     * @Serializer\Groups({"experience", "experience_list"})
     */
    private $updatedAt;

    /**
     * @ORM\ManyToOne(targetEntity="UserApiBundle\Entity\User", inversedBy="experience")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id")
     */
    private $user;

    /**
     * @var string
     * @ORM\Column(name="website", type="string", length=255, nullable=true)
     * @Assert\Length(
     *     min = 0,
     *     max = 255,
     *     minMessage = "Website should be minimum 0 symbol length",
     *     maxMessage = "Website should have maximum 255 symbols"
     * )
     * @Serializer\Groups({
     *     "experience",
     *     "experience_list",
     * })
     */
    private $website;

    /**
     * @var string $jobId
     * @ORM\Column(name="job_id", type="string", nullable=true)
     */
    private $jobId;

    /**
     * @var string $jobStatus
     * @ORM\Column(name="job_status", type="string")
     */
    private $jobStatus;

    /**
     * @var bool $isLastUsed
     * @ORM\Column(name="is_last_used", type="boolean")
     */
    private $isLastUsed = false;

    /**
     * @var string $vuforiaRejectMessage
     * @ORM\Column(name="vuforia_reject_message", type="string", nullable=true)
     *  @Serializer\Groups({
     *     "experience",
     *     "experience_list",
     * })
     */
    private $vuforiaRejectMessage;

    /**
     * @var int $vuforiaStatus
     * @ORM\Column(name="vuforia_status", type="integer")
     */
    private $vuforiaStatus;

    /**
     * @var TargetView[]|Collection
     * @ORM\OneToMany(targetEntity="ExperienceBundle\Entity\TargetView", mappedBy="experience")
     */
    private $targetView;

    /**
     * @var Charge[]|Collection
     * @ORM\OneToMany(targetEntity="UserApiBundle\Entity\Charge", mappedBy="experience")
     */
    private $charges;

    /**
     * @ORM\Column(name="aws_image_key", type="string", nullable=true)
     */
    private $imageKey;

    /**
     * @ORM\Column(name="image_url", type="string", nullable=true)
     * @Serializer\SerializedName("image_url")
     * @Serializer\Groups({
     *     "experience",
     *     "experience_list",
     * })
     */
    private $imageUrl;

    /**
     * @ORM\Column(name="aws_video_key", type="string", nullable=true)
     */
    private $videoKey;

    /**
     * @var string|null $transcodedUrl
     * @ORM\Column(name="transcoded_url_hd", type="string", nullable=true)
     * @Serializer\SerializedName("video_url")
     * @Serializer\Groups({
     *     "experience",
     *     "experience_list",
     * })
     */
    private $transcodedUrlHD = null;

    /**
     * @var string|null $transcodedUrlFullHD
     * @ORM\Column(name="transcoded_url_full_hd", type="string", nullable=true)
     * @Serializer\SerializedName("full_hd_video_url")
     * @Serializer\Groups({
     *     "experience",
     *     "experience_list",
     * })
     */
    private $transcodedUrlFullHD = null;

    /**
     * @var string|null $transcodedUrlHDKey
     * @ORM\Column(name="transcoded_url_hdkey", type="string", nullable=true)
     */
    private $transcodedUrlHDKey = null;

    /**
     * @var string|null $transcodedUrlFullHDKey
     * @ORM\Column(name="transcoded_url_full_hdkey", type="string", nullable=true)
     */
    private $transcodedUrlFullHDKey = null;

    /**
     * @var AbuseReport[]|Collection
     * @ORM\OneToMany(targetEntity="ExperienceBundle\Entity\AbuseReport", mappedBy="experience")
     */
    private $reports;

    /**
     * @var int|null $previousStatus
     * @ORM\Column(name="previous_status", type="integer", nullable=true)
     */
    private $previousStatus = null;

    /**
     * @var int $rating
     * @ORM\Column(name="rating", type="integer", length=1, options={"default": 0})
     * @Serializer\Groups({
     *     "experience_list",
     *     "experience"
     * })
     */
    private $rating = 0;

    /**
     * @var bool
     * @ORM\Column(name="is_example", type="boolean", options={"default": false})
     */
    private $isExample = false;

    /**
     * Experience constructor.
     */
    public function __construct()
    {
        $this->status = self::EXPERIENCE_PENDING;
        $this->jobStatus = self::TRANSCODER_JOB_STATUS_PROGRESSING;
        $this->vuforiaStatus = self::VUFORIA_PROCESSING;
        $this->targetView = new ArrayCollection();
        $this->charges = new ArrayCollection();
        $this->reports = new ArrayCollection();
    }

    /**
     * @ORM\PrePersist
     */
    public function setCreatedAtValue()
    {
        $this->createdAt = new \DateTime();
    }

    /**
     * @ORM\PrePersist
     * @ORM\PreUpdate
     */
    public function setUpdatedAtValue()
    {
        $this->updatedAt = new \DateTime();
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
     * @param mixed $status
     */
    public function setStatus($status)
    {
        $this->status = $status;
    }

    /**
     * @return mixed
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @return string
     */
    public function getStatusMessage()
    {
        return self::$statuses[$this->getStatus()];
    }

    /**
     * @param string|null $title
     */
    public function setTitle(?string $title)
    {
        $this->title = $title;
    }

    /**
     * @return string|null
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * @param string $contactName
     */
    public function setContactName($contactName)
    {
        $this->contactName = $contactName;
    }

    /**
     * @return string
     */
    public function getContactName()
    {
        return $this->contactName;
    }

    /**
     * @param string $phone
     */
    public function setPhone($phone)
    {
        $this->phone = $phone;
    }

    /**
     * @return string
     */
    public function getPhone()
    {
        return $this->phone;
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
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * @param string $targetId
     */
    public function setTargetId($targetId)
    {
        $this->targetId = $targetId;
    }

    /**
     * @return string
     */
    public function getTargetId()
    {
        return $this->targetId;
    }

    /**
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @return \DateTime
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    /**
     * @param User $user
     */
    public function setUser($user): void
    {
        $this->user = $user;
    }

    /**
     * @return User
     */
    public function getUser(): User
    {
        return $this->user;
    }

    /**
     * @return string
     */
    public function getWebsite(): ?string
    {
        return $this->website;
    }

    /**
     * @param string $website
     */
    public function setWebsite(string $website = null): void
    {
        $this->website = $website;
    }

    /**
     * @return string
     */
    public function getJobId(): ?string
    {
        return $this->jobId;
    }

    /**
     * @param string $jobId
     */
    public function setJobId(string $jobId = null): void
    {
        $this->jobId = $jobId;
    }

    /**
     * @Serializer\VirtualProperty(name="owner_id")
     * @Serializer\Groups({
     *     "experience",
     *     "experience_list"
     *     })
     *
     * @return int
     */
    public function getOwnerId() : int
    {
        return $this->user->getId();
    }

    /**
     * @return string
     */
    public function getJobStatus(): string
    {
        return $this->jobStatus;
    }

    /**
     * @param string $jobStatus
     */
    public function setJobStatus(string $jobStatus): void
    {
        $this->jobStatus = $jobStatus;
    }

    /**
     * @return bool
     */
    public function isLastUsed(): bool
    {
        return $this->isLastUsed;
    }

    /**
     * @param bool $isLastUsed
     */
    public function setIsLastUsed(bool $isLastUsed): void
    {
        $this->isLastUsed = $isLastUsed;
    }

    /**
     * @return string|null
     */
    public function getVuforiaRejectMessage(): ?string
    {
        return $this->vuforiaRejectMessage;
    }

    /**
     * @param string|null $vuforiaRejectMessage
     */
    public function setVuforiaRejectMessage(string $vuforiaRejectMessage = null): void
    {
        $this->vuforiaRejectMessage = $vuforiaRejectMessage;
    }

    /**
     * @return int
     */
    public function getVuforiaStatus(): int
    {
        return $this->vuforiaStatus;
    }

    /**
     * @param int $vuforiaStatus
     */
    public function setVuforiaStatus(int $vuforiaStatus): void
    {
        $this->vuforiaStatus = $vuforiaStatus;
    }

    /**
     * @return Collection|TargetView[]
     */
    public function getTargetView()
    {
        return $this->targetView;
    }

    /**
     * @param Collection|TargetView[] $targetView
     */
    public function setTargetView($targetView): void
    {
        $this->targetView = $targetView;
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\Groups({
     *     "experience",
     *     "experience_list",
     * })
     *
     * @return int
     */
    public function getViewsNumber(): int
    {
        $sum = 0;

        foreach ($this->getTargetView() as $targetView) {
            $sum += $targetView->getViews();
        }

        return $sum;
    }

    /**
     * @return Collection|Charge[]
     */
    public function getCharges()
    {
        return $this->charges;
    }

    /**
     * @param Collection|Charge[] $charges
     */
    public function setCharges($charges): void
    {
        $this->charges = $charges;
    }

    /**
     * @param Charge $charge
     */
    public function addCharge(Charge $charge): void
    {
        if (!$this->charges->contains($charge)) {
            $this->charges->add($charge);
        }
    }

    /**
     * @return string|null
     */
    public function formattedTitle(): ?string
    {
        $title = $this->title;

        if (is_null($title)) {
            return null;
        }

        if (strlen($title) > 7) {
            return '"'.substr($title, 0, 7).'..."';
        }

        return $title;
    }

    /**
     * @return mixed
     */
    public function getImageKey()
    {
        return $this->imageKey;
    }

    /**
     * @param mixed $imageKey
     */
    public function setImageKey($imageKey): void
    {
        $this->imageKey = $imageKey;
    }

    /**
     * @return mixed
     */
    public function getImageUrl()
    {
        return $this->imageUrl;
    }

    /**
     * @param mixed $imageUrl
     */
    public function setImageUrl($imageUrl): void
    {
        $this->imageUrl = $imageUrl;
    }

    /**
     * @return mixed
     */
    public function getVideoKey()
    {
        return $this->videoKey;
    }

    /**
     * @param mixed $videoKey
     */
    public function setVideoKey($videoKey): void
    {
        $this->videoKey = $videoKey;
    }

    /**
     * @return string|null
     */
    public function getTranscodedUrlHD(): ?string
    {
        return $this->transcodedUrlHD;
    }

    /**
     * @param string|null $transcodedUrlHD
     */
    public function setTranscodedUrlHD(?string $transcodedUrlHD): void
    {
        $this->transcodedUrlHD = $transcodedUrlHD;
    }

    /**
     * @return string|null
     */
    public function getTranscodedUrlFullHD(): ?string
    {
        return $this->transcodedUrlFullHD;
    }

    /**
     * @param string|null $transcodedUrlFullHD
     */
    public function setTranscodedUrlFullHD(?string $transcodedUrlFullHD): void
    {
        $this->transcodedUrlFullHD = $transcodedUrlFullHD;
    }

    /**
     * @return string|null
     */
    public function getTranscodedUrlHDKey(): ?string
    {
        return $this->transcodedUrlHDKey;
    }

    /**
     * @param string|null $transcodedUrlHDKey
     */
    public function setTranscodedUrlHDKey(?string $transcodedUrlHDKey): void
    {
        $this->transcodedUrlHDKey = $transcodedUrlHDKey;
    }

    /**
     * @return string|null
     */
    public function getTranscodedUrlFullHDKey(): ?string
    {
        return $this->transcodedUrlFullHDKey;
    }

    /**
     * @param string|null $transcodedUrlFullHDKey
     */
    public function setTranscodedUrlFullHDKey(?string $transcodedUrlFullHDKey): void
    {
        $this->transcodedUrlFullHDKey = $transcodedUrlFullHDKey;
    }

    /**
     * @return int|null
     */
    public function getPreviousStatus(): ?int
    {
        return $this->previousStatus;
    }

    /**
     * @param int|null $previousStatus
     */
    public function setPreviousStatus(?int $previousStatus = null): void
    {
        $this->previousStatus = $previousStatus;
    }

    /**
     * @return Collection|AbuseReport[]
     */
    public function getReports()
    {
        return $this->reports;
    }

    /**
     * @param Collection|AbuseReport[] $reports
     */
    public function setReports($reports): void
    {
        $this->reports = $reports;
    }

    /**
     * @return int
     */
    public function getRating(): int
    {
        return $this->rating;
    }

    /**
     * @param int $rating
     */
    public function setRating(int $rating): void
    {
        $this->rating = $rating;
    }
    /**
     * @return bool
     */
    public function isExample(): bool
    {
        return $this->isExample;
    }

    /**
     * @param bool $isExample
     */
    public function setIsExample(bool $isExample): void
    {
        $this->isExample = $isExample;
    }
}
