<?php

namespace SecurityAccessBundle\Security;

use Symfony\Component\HttpFoundation\{ Request, JsonResponse, Response };
use Symfony\Component\Security\Core\{
    Exception\CustomUserMessageAuthenticationException,
    User\UserInterface,
    Authentication\Token\TokenInterface,
    Exception\AuthenticationException,
    User\UserProviderInterface
};
use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;
use UserApiBundle\Entity\{ApiToken, Client, User};
use UserApiBundle\Repository\ApiTokenRepository;
use UserApiBundle\Repository\ClientRepository;

/**
 * Class TokenAuthenticator
 * @package SecurityAccessBundle\Security
 */
class TokenAuthenticator extends AbstractGuardAuthenticator
{
    /** @var ApiTokenRepository $apiTokenRepository */
    private $apiTokenRepository;

    /** @var ClientRepository $clientRepository */
    private $clientRepository;

    /**
     * TokenAuthenticator constructor.
     * @param ApiTokenRepository $apiTokenRepository
     * @param ClientRepository $clientRepository
     */
    public function __construct(ApiTokenRepository $apiTokenRepository, ClientRepository $clientRepository)
    {
        $this->apiTokenRepository = $apiTokenRepository;
        $this->clientRepository = $clientRepository;
    }

    /**
     * @param Request $request
     * @return bool
     */
    public function supports(Request $request) : bool
    {
        if ($request->headers->has('scope') && !in_array($request->headers->get('scope'),ApiToken::SUPPORTED_SCOPES)) {
            throw new CustomUserMessageAuthenticationException('Invalid scope');
        }

        return ($request->headers->has('X-API-Token') &&
               $request->headers->has('client')) ||
               ($request->headers->has('X-API-Token'));
    }

    /**
     * Called on every request. Return whatever credentials you want to
     * be passed to getUser(). Returning null will cause this authenticator
     * to be skipped.
     *
     * @param Request $request
     * @return array
     */
    public function getCredentials(Request $request): array
    {
        return [
            'token' => $request->headers->get('X-API-Token'),
            'scope' => $request->headers->get('scope'),
            'client' => $request->headers->get('client'),
        ];
    }

    /**
     * @param array                 $credentials
     * @param UserProviderInterface $userProvider
     * @return UserInterface|User|null
     * @throws \Exception
     */
    public function getUser($credentials, UserProviderInterface $userProvider) : User
    {
        if (is_null($credentials['client']) && is_null($credentials['scope'])) {
            /** @var ApiToken $token */
            $token = $this->apiTokenRepository->findOneBy(['token' => $credentials['token']]);

            if ($token && !is_null($token->getClient())) {
                throw new CustomUserMessageAuthenticationException('Invalid API Token');
            }
        } else {
            /** @var Client|null $client */
            $client = $this->clientRepository->findClientByPublicId($credentials['client']);

            if (is_null($client)) {
                throw new CustomUserMessageAuthenticationException('Invalid client');
            }

            /** @var ApiToken $token */
            $token = $this->apiTokenRepository->findOneBy([
                'token' => $credentials['token'],
                'scope' => $credentials['scope'],
                'client' => $client,
            ]);
        }

        if (!$token) {
            throw new CustomUserMessageAuthenticationException('Invalid API Token');
        }

        if ($token->isExpired()) {
            throw new CustomUserMessageAuthenticationException('Token expired');
        }

        return $token->getUser();
    }

    /**
     * @param mixed         $credentials
     * @param UserInterface $user
     * @return bool
     */
    public function checkCredentials($credentials, UserInterface $user)
    {
        return true;
    }

    /**
     * @param Request        $request
     * @param TokenInterface $token
     * @param string         $providerKey
     * @return Response|null
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey)
    {
        // on success, let the request continue
        return null;
    }

    /**
     * @param Request                 $request
     * @param AuthenticationException $exception
     * @return JsonResponse|Response|null
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception) : JsonResponse
    {
        return new JsonResponse(['message' => $exception->getMessageKey()],Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Called when authentication is needed, but it's not sent
     *
     * @param Request                      $request
     * @param AuthenticationException|null $authException
     * @return JsonResponse|Response
     */
    public function start(Request $request, AuthenticationException $authException = null) : JsonResponse
    {
        return new JsonResponse(['message' => 'Authentication Required'],Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @return bool
     */
    public function supportsRememberMe() : bool
    {
        return false;
    }
}