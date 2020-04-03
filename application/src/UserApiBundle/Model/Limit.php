<?php

namespace UserApiBundle\Model;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class Limit
 * @package UserApiBundle\Model
 */
class Limit
{
    /**
     * @var boolean $charge_limit_enabled
     * @Assert\NotNull(message="Charge limit should not be null")
     */
    private $charge_limit_enabled;

    /**
     * @var float $monthly_limit
     * @Assert\GreaterThanOrEqual(20)
     * @Assert\LessThanOrEqual(1000000)
     */
    private $monthly_limit;

    /**
     * @var boolean $warn_limit_reached
     */
    private $warn_limit_reached;

    /**
     * @return bool|null
     */
    public function isChargeLimitEnabled(): ?bool
    {
        return $this->charge_limit_enabled;
    }

    /**
     * @param bool|null $charge_limit_enabled
     */
    public function setChargeLimitEnabled(bool $charge_limit_enabled = null): void
    {
        $this->charge_limit_enabled = $charge_limit_enabled;
    }

    /**
     * @return float|null
     */
    public function getMonthlyLimit(): ?float
    {
        return $this->monthly_limit;
    }

    /**
     * @param float|null $monthly_limit
     */
    public function setMonthlyLimit(float $monthly_limit = null): void
    {
        $this->monthly_limit = $monthly_limit;
    }

    /**
     * @return bool|null
     */
    public function getWarnLimitReached(): ?bool
    {
        return $this->warn_limit_reached;
    }

    /**
     * @param bool|null $warn_limit_reached
     */
    public function setWarnLimitReached(bool $warn_limit_reached = null): void
    {
        $this->warn_limit_reached = $warn_limit_reached;
    }
}