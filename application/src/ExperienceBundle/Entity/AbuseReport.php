<?php

namespace ExperienceBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Class AbuseReport
 * @package ExperienceBundle\Entity
 *
 * @ORM\Table(name="abuse_report")
 * @ORM\Entity(repositoryClass="ExperienceBundle\Repository\AbuseReportRepository")
 */
class AbuseReport
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
     * @var Experience
     * @ORM\ManyToOne(targetEntity="ExperienceBundle\Entity\Experience", inversedBy="reports")
     * @ORM\JoinColumn(name="experience_id", referencedColumnName="id")
     */
    private $experience;

    /**
     * @var string|null
     * @ORM\Column(name="message", type="text", nullable=true)
     */
    private $message;

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return Experience
     */
    public function getExperience(): Experience
    {
        return $this->experience;
    }

    /**
     * @param Experience $experience
     */
    public function setExperience(Experience $experience): void
    {
        $this->experience = $experience;
    }

    /**
     * @return string|null
     */
    public function getMessage(): ?string
    {
        return $this->message;
    }

    /**
     * @param string|null $message
     */
    public function setMessage(?string $message): void
    {
        $this->message = $message;
    }
}