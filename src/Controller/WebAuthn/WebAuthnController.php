<?php

namespace App\Controller\WebAuthn;

use App\Entity\WebAuthn\WebAuthnCredential;
use App\Repository\Profile\ProfileRepository;
use App\Repository\User\UserRepository;
use App\Repository\WebAuthn\WebAuthnCredentialRepository;
use App\Repository\WebAuthn\WebAuthnUserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\Bundle\Service\PublicKeyCredentialCreationOptionsFactory;
use Webauthn\Bundle\Service\PublicKeyCredentialRequestOptionsFactory;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\AuthenticatorSelectionCriteria;

#[Route('/webauthn', name: 'webauthn_')]
class WebAuthnController extends AbstractController
{
    public function __construct(
        private readonly PublicKeyCredentialCreationOptionsFactory $creationOptionsFactory,
        private readonly PublicKeyCredentialRequestOptionsFactory $requestOptionsFactory,
        private readonly AuthenticatorAttestationResponseValidator $attestationValidator,
        private readonly AuthenticatorAssertionResponseValidator $assertionValidator,
        private readonly WebAuthnUserRepository $userRepository,
        private readonly WebAuthnCredentialRepository $credentialRepository,
        private readonly SerializerInterface $serializer,
        private readonly ProfileRepository $profileRepository
    ) {
    }

    #[Route('/register/options', name: 'register_options', methods: ['POST'])]
    public function registerOptions(Request $request, UserRepository $userRepository): JsonResponse
    {
        // Use session-based authentication (matching the app's auth system)
        $session = $request->getSession();
        if (!$session->get('is_logged_in') || !$session->get('user') || !isset($session->get('user')['id'])) {
            return $this->json(['error' => 'User not logged in'], Response::HTTP_UNAUTHORIZED);
        }

        $userId = (int) $session->get('user')['id'];
        $user = $userRepository->find($userId);

        if (!$user) {
            return $this->json(['error' => 'User not found'], Response::HTTP_UNAUTHORIZED);
        }

        try {

            $webAuthnUser = $this->userRepository->findOneByUsername($user->getUserIdentifier());

            if (!$webAuthnUser) {
                return $this->json(['error' => 'WebAuthn user not found'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            $publicKeyCredentialCreationOptions = $this->creationOptionsFactory->create(
                'default',
                $webAuthnUser,
                [], // excludeCredentials
                AuthenticatorSelectionCriteria::create(
                    null,
                    AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED,
                    AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_REQUIRED
                )
            );

            // Use request host as rpId so WebAuthn works on both localhost and 127.0.0.1
            $host = $request->getHost();
            if (in_array($host, ['localhost', '127.0.0.1'], true)) {
                $rp = PublicKeyCredentialRpEntity::create(
                    $publicKeyCredentialCreationOptions->rp->name,
                    $host
                );
                $publicKeyCredentialCreationOptions = PublicKeyCredentialCreationOptions::create(
                    $rp,
                    $publicKeyCredentialCreationOptions->user,
                    $publicKeyCredentialCreationOptions->challenge,
                    $publicKeyCredentialCreationOptions->pubKeyCredParams,
                    $publicKeyCredentialCreationOptions->authenticatorSelection,
                    $publicKeyCredentialCreationOptions->attestation,
                    $publicKeyCredentialCreationOptions->excludeCredentials,
                    $publicKeyCredentialCreationOptions->timeout,
                    $publicKeyCredentialCreationOptions->extensions
                );
            }

            $request->getSession()->set('webauthn_creation_options', $publicKeyCredentialCreationOptions);

            // Serialize to JSON properly
            $jsonOptions = $this->serializer->serialize($publicKeyCredentialCreationOptions, 'json');

            return new JsonResponse($jsonOptions, Response::HTTP_OK, [], true);
        } catch (\Throwable $e) {
            return $this->json([
                'error' => 'Registration setup failed',
                'message' => $e->getMessage(),
                'class' => get_class($e)
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/login/options', name: 'login_options', methods: ['POST'])]
    public function loginOptions(Request $request, UserRepository $userRepository): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;

        if (!$email) {
            return $this->json(['error' => 'Email is required'], Response::HTTP_BAD_REQUEST);
        }

        $user = $userRepository->findOneBy(['email_user' => $email]);
        if (!$user) {
            return $this->json(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $webAuthnUser = $this->userRepository->findOneByUsername($user->getUserIdentifier());
            if (!$webAuthnUser) {
                return $this->json(['error' => 'WebAuthn user not found'], Response::HTTP_NOT_FOUND);
            }
            $credentials = $this->credentialRepository->findAllEntitiesForUser($user);
            $allowCredentials = array_map(function (WebAuthnCredential $cred) {
                $transports = $cred->getTransports();
                if (empty($transports)) {
                    $transports = ['internal', 'hybrid', 'usb', 'nfc', 'ble'];
                }

                // CRITICAL: The credentialId in DB is base64url encoded.
                // PublicKeyCredentialDescriptor expects the RAW binary ID.
                return new \Webauthn\PublicKeyCredentialDescriptor(
                    \Webauthn\PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
                    $this->base64url_decode($cred->getCredentialId()),
                    $transports
                );
            }, $credentials);

            $publicKeyCredentialRequestOptions = $this->requestOptionsFactory->create(
                'default',
                $allowCredentials,
                PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_REQUIRED
            );

            // Use request host as rpId so WebAuthn works on both localhost and 127.0.0.1
            $host = $request->getHost();
            if (in_array($host, ['localhost', '127.0.0.1'], true)) {
                $publicKeyCredentialRequestOptions->rpId = $host;
            }

            // The user IDs in allowCredentials ID fields are already base64url encoded strings
            // from the database, and prepareOptions will convert them to buffers.

            $request->getSession()->set('webauthn_login_options', $publicKeyCredentialRequestOptions);
            $request->getSession()->set('webauthn_login_user_id', $user->getIdUser());

            $jsonOptions = $this->serializer->serialize($publicKeyCredentialRequestOptions, 'json');
            return new JsonResponse($jsonOptions, Response::HTTP_OK, [], true);

        } catch (\Throwable $e) {
            return $this->json(['error' => 'Login setup failed: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function base64url_encode(string $data): string
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }

    private function base64url_decode(string $data): string
    {
        return base64_decode(str_replace(['-', '_'], ['+', '/'], $data) . str_repeat('=', (4 - strlen($data) % 4) % 4));
    }

    #[Route('/login/verify', name: 'login_verify', methods: ['POST'])]
    public function loginVerify(Request $request, UserRepository $userRepository): JsonResponse
    {
        $session = $request->getSession();
        $publicKeyCredentialRequestOptions = $session->get('webauthn_login_options');
        $userId = $session->get('webauthn_login_user_id');

        if (!$publicKeyCredentialRequestOptions || !$userId) {
            return $this->json(['error' => 'No login session found'], Response::HTTP_BAD_REQUEST);
        }

        $session->remove('webauthn_login_options');
        $session->remove('webauthn_login_user_id');

        try {
            $publicKeyCredential = $this->serializer->deserialize(
                $request->getContent(),
                PublicKeyCredential::class,
                'json'
            );
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Invalid assertion data'], Response::HTTP_BAD_REQUEST);
        }

        $response = $publicKeyCredential->response;
        if (!$response instanceof AuthenticatorAssertionResponse) {
            return $this->json(['error' => 'Invalid response type'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $user = $userRepository->find($userId);
            if (!$user) {
                throw new \Exception('User no longer exists');
            }

            $source = $this->credentialRepository->findOneByCredentialId($this->base64url_encode($publicKeyCredential->rawId));
            if (!$source) {
                return $this->json(['error' => 'Credential not found'], Response::HTTP_NOT_FOUND);
            }

            $this->assertionValidator->check(
                $source,
                $response,
                $publicKeyCredentialRequestOptions,
                $request->getHost(),
                $source->userHandle
            );

            // Update credential sign count and last used date
            $credentialEntity = $this->credentialRepository->findOneEntityByCredentialId($publicKeyCredential->rawId);
            if ($credentialEntity) {
                $credentialEntity->setSignCount($source->counter);
                $credentialEntity->setLastUsedAt(new \DateTime());
                $this->credentialRepository->save($credentialEntity, true);
            }

            // Authentication successful -> Create session (same shape as password login: name, avatar, settings for navbar + theme)
            $profile = $this->profileRepository->findOneByUser($user);
            $avatar = $profile?->getAvatar();
            $defaults = [
                'theme' => 'dark',
                'accent-gradient' => 'linear-gradient(135deg, #6c5ce7, #8b5cf6, #06b6d4)',
                'accent-color' => '#6c5ce7',
                'lang' => 'fr',
            ];
            $userSettings = $profile ? array_merge($defaults, $profile->getSettings()) : $defaults;
            $session->set('is_logged_in', true);
            $session->set('user', [
                'id' => $user->getIdUser(),
                'name' => trim($user->getFirstName() . ' ' . $user->getLastName()),
                'email' => $user->getEmailUser(),
                'role' => $user->getRoleUser(),
                'avatar' => $avatar,
                'settings' => $userSettings,
            ]);

            $redirectUrl = in_array($user->getRoleUser(), ['OWNER', 'ADMIN', 'SYNDIC', 'SUPERADMIN'], true)
                ? $this->generateUrl('auth_sign_in', ['destination' => 'choice'])
                : $this->generateUrl('main_home');

            return $this->json([
                'status' => 'ok',
                'message' => 'Authenticated successfully',
                'redirect' => $redirectUrl,
            ]);

        } catch (\Throwable $e) {
            return $this->json(['error' => 'Authentication failed: ' . $e->getMessage()], Response::HTTP_UNAUTHORIZED);
        }
    }

    #[Route('/register/verify', name: 'register_verify', methods: ['POST'])]
    public function registerVerify(Request $request, UserRepository $userRepository): JsonResponse
    {
        // Use session-based authentication
        $session = $request->getSession();
        if (!$session->get('is_logged_in') || !$session->get('user') || !isset($session->get('user')['id'])) {
            return $this->json(['error' => 'User not logged in'], Response::HTTP_UNAUTHORIZED);
        }

        $userId = (int) $session->get('user')['id'];
        $user = $userRepository->find($userId);

        if (!$user) {
            return $this->json(['error' => 'User not found'], Response::HTTP_UNAUTHORIZED);
        }

        $publicKeyCredentialCreationOptions = $request->getSession()->get('webauthn_creation_options');
        if (!$publicKeyCredentialCreationOptions) {
            return $this->json(['error' => 'No registration session found'], Response::HTTP_BAD_REQUEST);
        }
        $request->getSession()->remove('webauthn_creation_options');

        try {
            $publicKeyCredential = $this->serializer->deserialize(
                $request->getContent(),
                PublicKeyCredential::class,
                'json'
            );
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Invalid credential data: ' . $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $response = $publicKeyCredential->response;
        if (!$response instanceof AuthenticatorAttestationResponse) {
            return $this->json(['error' => 'Invalid response type'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $credentialSource = $this->attestationValidator->check(
                $response,
                $publicKeyCredentialCreationOptions,
                $request
            );

            $encodedId = $this->base64url_encode($publicKeyCredential->rawId);

            // Save the credential
            $webAuthnCredential = new WebAuthnCredential();
            $webAuthnCredential->setUser($user);
            // Use the base64url encoded version from the request data
            $webAuthnCredential->setCredentialId($encodedId);
            $webAuthnCredential->setPublicKey($this->base64url_encode($credentialSource->credentialPublicKey));
            $webAuthnCredential->setSignCount($credentialSource->counter);
            $webAuthnCredential->setTransports($credentialSource->transports);

            $this->credentialRepository->save($webAuthnCredential, true);

            return $this->json(['status' => 'ok', 'message' => 'Authenticator registered successfully']);

        } catch (\Throwable $e) {
            return $this->json(['error' => 'Registration failed: ' . $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/credentials', name: 'list_credentials', methods: ['GET'])]
    public function listCredentials(Request $request, UserRepository $userRepository): JsonResponse
    {
        // Use session-based authentication
        $session = $request->getSession();
        if (!$session->get('is_logged_in') || !$session->get('user') || !isset($session->get('user')['id'])) {
            return $this->json(['error' => 'User not logged in'], Response::HTTP_UNAUTHORIZED);
        }

        $userId = (int) $session->get('user')['id'];
        $user = $userRepository->find($userId);

        if (!$user) {
            return $this->json(['error' => 'User not found'], Response::HTTP_UNAUTHORIZED);
        }

        /** @var WebAuthnCredential[] $credentials */
        $credentials = $this->credentialRepository->findAllEntitiesForUser($user);

        $data = [];
        foreach ($credentials as $cred) {
            $data[] = [
                'id' => $cred->getCredentialId(), // Raw ID
                'id_encoded' => base64_encode($cred->getCredentialId()),
                'type' => 'public-key',
                'transports' => $cred->getTransports(),
                'created_at' => $cred->getCreatedAt() ? $cred->getCreatedAt()->format('Y-m-d H:i:s') : null,
                'aaguid' => '00000000-0000-0000-0000-000000000000', // Default or stored
            ];
        }

        return $this->json($data);
    }

    #[Route('/credentials/{id}', name: 'remove_credential', methods: ['DELETE'])]
    public function removeCredential(string $id, Request $request, UserRepository $userRepository): JsonResponse
    {
        // Use session-based authentication
        $session = $request->getSession();
        if (!$session->get('is_logged_in') || !$session->get('user') || !isset($session->get('user')['id'])) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $userId = (int) $session->get('user')['id'];
        $user = $userRepository->find($userId);

        if (!$user) {
            return $this->json(['error' => 'User not found'], Response::HTTP_UNAUTHORIZED);
        }

        // The ID from frontend is likely base64 encoded
        $credentialId = base64_decode($id, true);
        if ($credentialId === false) {
            $credentialId = $id;
        }

        $credential = $this->credentialRepository->findOneEntityByCredentialId($credentialId);

        if (!$credential) {
            return $this->json(['error' => 'Credential not found'], Response::HTTP_NOT_FOUND);
        }

        if ($credential->getUser() !== $user) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_FORBIDDEN);
        }

        $this->credentialRepository->remove($credential, true);

        return $this->json(['status' => 'ok']);
    }
}
