<?php

namespace ExperienceBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;

/**
 * Class TargetView
 * @package ExperienceBundle\Entity
 *
 * @ORM\Table(name="target_views")
 * @ORM\Entity(repositoryClass="ExperienceBundle\Repository\TargetViewRepository")
 */
class TargetView
{
    /**
     * Hook timestampable behavior
     * updates createdAt, updatedAt fields
     */
    use TimestampableEntity;

    /**
     * @var int $id
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="UserApiBundle\Entity\User", inversedBy="targetView")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id")
     */
    private $user;

    /**
     * @ORM\ManyToOne(targetEntity="ExperienceBundle\Entity\Experience", inversedBy="targetView")
     * @ORM\JoinColumn(name="experience_id", referencedColumnName="id")
     */
    private $experience;

    /**
     * @var int $views
     * @ORM\Column(name="views", type="integer")
     */
    private $views;

    /**
     * @var bool $isTrial
     *
     * @ORM\Column(name="is_trial", type="boolean", options={"default":false})
     */
    private $isTrial = false;

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param mixed $user
     */
    public function setUser($user): void
    {
        $this->user = $user;
    }

    /**
     * @return mixed
     */
    public function getExperience()
    {
        return $this->experience;
    }

    /**
     * @param mixed $experience
     */
    public function setExperience($experience): void
    {
        $this->experience = $experience;
    }

    /**
     * @return int
     */
    public function getViews(): int
    {
        return $this->views;
    }

    /**
     * @param int $views
     */
    public function setViews(int $views): void
    {
        $this->views = $views;
    }

    /**
     * @return bool
     */
    public function getIsTrial(): bool
    {
        return $this->isTrial;
    }

    /**
     * @param bool $isTrial
     */
    public function setIsTrial(bool $isTrial): void
    {
        $this->isTrial = $isTrial;
    }
}