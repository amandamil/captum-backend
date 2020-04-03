<?php


namespace UserApiBundle\Model;

/**
 * Class VerificationCodeStatus
 * @package UserApiBundle\Model
 */
class VerificationCodeStatus
{
    const ACCEPTED = 'accepted';
    const PENDING = 'pending';
    const EXPIRED = 'expired';
    const REJECTED = 'rejected';

    /**
     * @return array
     */
    public static function getChoices()
    {
        return [
            self::ACCEPTED => 'accepted',
            self::PENDING => 'pending',
            self::EXPIRED => 'expired',
            self::REJECTED => 'rejected',
        ];
    }

    /**
     * @return array
     */
    public static function getValues()
    {
        return array_keys(self::getChoices());
    }
}