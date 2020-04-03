<?php

namespace SubscriptionBundle\Services;

use Braintree\WebhookNotification;
use Braintree_CustomerSearch;
use Braintree_Gateway;
use SubscriptionBundle\Entity\Subscription;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use UserApiBundle\Entity\User;

/**
 * Class BraintreeService
 * @package ExperienceBundle\Services
 */
class BraintreeService extends Braintree_Gateway
{
    /** @var ContainerInterface $container */
    private $container;

    /**
     * BraintreeService constructor.
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;

        parent::__construct([
           'environment' => $this->container->getParameter('braintree_enviroment'),
           'merchantId' => $this->container->getParameter('braintree_merchant_id'),
           'publicKey' => $this->container->getParameter('braintree_public_key'),
           'privateKey' => $this->container->getParameter('braintree_private_key'),
        ]);
    }

    /**
     * @param string $aCustomerId
     * @return string
     */
    public function generateClientToken(string $aCustomerId): string
    {
        return $this->clientToken()->generate([ 'customerId' => $aCustomerId ]);
    }

    /**
     * @param User $user
     * @return string|null
     */
    public function createCustomer(User $user): ?string
    {
        $result = $this->customer()->create([
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'email' => $user->getEmail(),
            'phone' => $user->getPhoneNumber(),
        ]);

        if ($result->success) {
            return $result->customer->id;
        }

        return null;
    }

    /**
     * @param string $nonceFromTheClient
     * @param string $planId
     * @return \Braintree\Result\Error|\Braintree\Result\Successful
     */
    public function createSubscription(string $nonceFromTheClient, string $planId)
    {
        $result = $this->subscription()->create([
            'paymentMethodNonce' => $nonceFromTheClient,
            'planId' => $planId,
        ]);

        if ($result instanceof \Braintree\Result\Error) {
            foreach (($result->errors->deepAll()) as $error) {
                throw new BadRequestHttpException($error->message);
            }
        }

        return $result;
    }

    /**
     * @param string $subscriptionId
     * @param string $nonceFromTheClient
     * @param string $planId
     * @param string $price
     * @return \Braintree\Result\Error|\Braintree\Result\Successful
     */
    public function updateSubscription(
        string $subscriptionId,
        string $nonceFromTheClient,
        string $planId,
        string $price)
    {
        $result = $this->subscription()->update($subscriptionId, [
            'paymentMethodNonce' => $nonceFromTheClient,
            'price' => $price,
            'planId' => $planId,
            'neverExpires' => true,
        ]);

        if ($result instanceof \Braintree\Result\Error) {
            foreach (($result->errors->deepAll()) as $error) {
                throw new BadRequestHttpException($error->message);
            }
        }

        return $result;
    }

    /**
     * @param string $subscriptionId
     * @return \Braintree\Result\Error|\Braintree\Result\Successful
     */
    public function cancelSubscription(string $subscriptionId)
    {
        $result = $this->subscription()->update($subscriptionId,[
            'numberOfBillingCycles' => 1,
        ]);

        if ($result instanceof \Braintree\Result\Error) {
            foreach (($result->errors->deepAll()) as $error) {
                throw new BadRequestHttpException($error->message);
            }
        }

        return $result;
    }

    /**
     * @param string $subscriptionId
     * @return \Braintree\Result\Error|\Braintree\Result\Successful
     */
    public function restoreSubscription(string $subscriptionId)
    {
        $result = $this->subscription()->update($subscriptionId,[
            'neverExpires' => true,
        ]);

        if ($result instanceof \Braintree\Result\Error) {
            foreach (($result->errors->deepAll()) as $error) {
                throw new BadRequestHttpException($error->message);
            }
        }

        return $result;
    }

    /**
     * @param string $subscriptionId
     * @return \Braintree\Result\Error|\Braintree\Result\Successful
     */
    public function deleteSubscription(string $subscriptionId)
    {
        $result = $this->subscription()->cancel($subscriptionId);

        if ($result instanceof \Braintree\Result\Error) {
            foreach (($result->errors->deepAll()) as $error) {
                throw new BadRequestHttpException($error->message);
            }
        }

        return $result;
    }

    /**
     * @param string $subscriptionId
     * @param string $nonceFromTheClient
     * @return \Braintree\Result\Error|\Braintree\Result\Successful
     */
    public function updatePaymentMethod(string $subscriptionId, string $nonceFromTheClient)
    {
        $result = $this->subscription()->update($subscriptionId, [
            'paymentMethodNonce' => $nonceFromTheClient,
        ]);

        if ($result instanceof \Braintree\Result\Error) {
            foreach (($result->errors->deepAll()) as $error) {
                throw new BadRequestHttpException($error->message);
            }
        }

        return $result;
    }

    /**
     * @param Subscription $subscription
     * @return \Braintree\Result\Error|\Braintree\Result\Successful
     * @throws \Money\UnknownCurrencyException
     */
    public function retryCharge(Subscription $subscription)
    {
        $retryResult = $this->subscription()->retryCharge(
            $subscription->getBraintreeId(),
            number_format(($subscription->getPackage()->getPrice()->getAmount()/100)),
            true
        );

        if ($retryResult instanceof \Braintree\Result\Error) {
            foreach (($retryResult->errors->deepAll()) as $error) {
                throw new BadRequestHttpException($error->message);
            }
        }

        return $retryResult;
    }

    /**
     * @param array $params
     * @return WebhookNotification
     * @throws \Braintree\Exception\InvalidSignature
     */
    public function parseWebhookNotification(array $params): WebhookNotification
    {
       return $this->webhookNotification()->parse($params["bt_signature"], $params["bt_payload"]);
    }

    /**
     * @param string $nonceFromTheClient
     * @param float  $price
     *
     * @return \Braintree\Result\Error|\Braintree\Result\Successful
     */
    public function saleTransaction(string $nonceFromTheClient, float $price)
    {
        $result = $this->transaction()->sale([
            'amount' => $price,
            'paymentMethodNonce' => $nonceFromTheClient,
            'options' => [
                'submitForSettlement' => true
            ],
        ]);

        if ($result instanceof \Braintree\Result\Error) {
            foreach (($result->errors->deepAll()) as $error) {
                throw new BadRequestHttpException($error->message);
            }
        }

        return $result;
    }

    /**
     * @param string $email
     * @return \Braintree\ResourceCollection
     */
    public function checkCustomer(string $email)
    {
        $result = $this->customer()->search([
            Braintree_CustomerSearch::email()->is($email)
        ]);

        if ($result instanceof \Braintree\Result\Error) {
            foreach (($result->errors->deepAll()) as $error) {
                throw new BadRequestHttpException($error->message);
            }
        }

        return $result;
    }

    /**
     * @param string $customerId
     * @return \Braintree\Result\Successful|void
     */
    public function deleteCustomer(string $customerId)
    {
        $result = $this->customer()->delete($customerId);

        if ($result instanceof \Braintree\Result\Error) {
            foreach (($result->errors->deepAll()) as $error) {
                throw new BadRequestHttpException($error->message);
            }
        }

        return $result;
    }
}