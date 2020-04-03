<?php


namespace UserApiBundle\Model;

class VerificationCodeType
{
    const REGISTRATION = 'registration';
    const RESET_PASSWORD = 'reset_password';

    /**
     * {@inheritdoc}
     */
    public static function getChoices()
    {
        return [
            self::REGISTRATION => self::REGISTRATION,
            self::RESET_PASSWORD => self::RESET_PASSWORD,
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