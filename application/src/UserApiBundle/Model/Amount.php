<?php


namespace UserApiBundle\Model;

use Symfony\Component\Validator\Constraints as Assert;

class Amount
{
    /**
     * @var float $balance_amount
     *
     * @Assert\GreaterThanOrEqual(20)
     * @Assert\NotBlank(message="Balance amount should not be blank")
     * @Assert\NotNull(message="Balance amount should not be null")
     *
     */
    private $balance_amount;

    /**
     * @var string $payment_method_nonce
     *
     * @Assert\NotBlank(message="Payment method nonce should not be blank")
     * @Assert\NotNull(message="Payment method nonce should not be null")
     */
    private $payment_method_nonce;

    /**
     * @return float|null
     */
    public function getBalanceAmount(): ?float
    {
        return $this->balance_amount;
    }

    /**
     * @param float|null $balance_amount
     */
    public function setBalanceAmount(float $balance_amount = null): void
    {
        $this->balance_amount = $balance_amount;
    }

    /**
     * @return string|null
     */
    public function getPaymentMethodNonce(): ?string
    {
        return $this->payment_method_nonce;
    }

    /**
     * @param string|null $payment_method_nonce
     */
    public function setPaymentMethodNonce(string $payment_method_nonce = null): void
    {
        $this->payment_method_nonce = $payment_method_nonce;
    }
}