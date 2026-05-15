<?php

namespace App\Controller\FaceCred;

use App\Entity\FaceCred\FaceCredential;
use App\Repository\FaceCred\FaceCredentialRepository;
use App\Repository\Profile\ProfileRepository;
use App\Repository\User\UserRepository;
use App\Service\Face\FaceEncryptionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/face', name: 'face_')]
class FaceController extends AbstractController
{
    private const DISTANCE_THRESHOLD = 0.5; // Configurable threshold for face-api.js embeddings

    public function __construct(
        private readonly FaceCredentialRepository $faceRepository,
        private readonly FaceEncryptionService $encryptionService,
        private readonly UserRepository $userRepository,
        private readonly ProfileRepository $profileRepository
    ) {
    }

    #[Route('/enroll', name: 'enroll', methods: ['POST'])]
    public function enroll(Request $request): JsonResponse
    {
        $session = $request->getSession();
        if (!$session->get('is_logged_in') || !$session->get('user')) {
            return $this->json(['error' => 'User not logged in'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        $embedding = $data['embedding'] ?? null;
        $pin = $data['pin'] ?? null;
        $deviceId = $data['deviceId'] ?? null;

        if (!$embedding || !$pin || !$deviceId) {
            return $this->json(['error' => 'Missing required data'], Response::HTTP_BAD_REQUEST);
        }

        $userId = (int) $session->get('user')['id'];
        $user = $this->userRepository->find($userId);

        if (!$user) {
            return $this->json(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $key = $this->encryptionService->deriveKey($pin, $user->getEmailUser());
            $serializedEmbedding = json_encode($embedding);
            $encryptedData = $this->encryptionService->encrypt($serializedEmbedding, $key);

            // Hash the PIN using bcrypt
            $pinHash = password_hash($pin, PASSWORD_BCRYPT);

            // Check if device already has a credential
            $credential = $this->faceRepository->findActiveForUserAndDevice($user, $deviceId) ?: new FaceCredential();

            $credential->setUser($user);
            $credential->setDeviceId($deviceId);
            $credential->setEncryptedFaceid($encryptedData);
            $credential->setPinHash($pinHash);
            $credential->setUpdatedAt(new \DateTime());
            $credential->setFlag('active');

            $this->faceRepository->save($credential, true);

            return $this->json(['status' => 'ok', 'message' => 'Face enrolled successfully']);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Enrollment failed: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/auth', name: 'auth', methods: ['POST'])]
    public function auth(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;
        $embedding = $data['embedding'] ?? null;
        $pin = $data['pin'] ?? null;
        $deviceId = $data['deviceId'] ?? null;

        if (!$email || !$embedding || !$pin || !$deviceId) {
            return $this->json(['error' => 'Missing required data'], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->userRepository->findOneBy(['email_user' => $email]);
        if (!$user) {
            return $this->json(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        $credential = $this->faceRepository->findActiveForUserAndDevice($user, $deviceId);
        if (!$credential) {
            return $this->json(['error' => 'No Face ID enrolled for this device'], Response::HTTP_NOT_FOUND);
        }

        $packedData = $credential->getEncryptedFaceid();
        if ($packedData === null || $packedData === '') {
            return $this->json(['error' => 'No Face ID enrolled for this device'], Response::HTTP_NOT_FOUND);
        }

        try {
            // Verify PIN hash first
            $pinHash = $credential->getPinHash();
            if (!$pinHash || !password_verify($pin, $pinHash)) {
                return $this->json(['error' => 'Invalid PIN'], Response::HTTP_UNAUTHORIZED);
            }

            $key = $this->encryptionService->deriveKey($pin, $user->getEmailUser());
            $decryptedEmbeddingJson = $this->encryptionService->decrypt($packedData, $key);

            if (!$decryptedEmbeddingJson) {
                return $this->json(['error' => 'Invalid PIN or corrupted data'], Response::HTTP_UNAUTHORIZED);
            }

            $storedEmbedding = json_decode($decryptedEmbeddingJson, true);
            $distance = $this->encryptionService->calculateDistance($embedding, $storedEmbedding);

            if ($distance < self::DISTANCE_THRESHOLD) {
                $credential->setLastUsedAt(new \DateTime());
                $this->faceRepository->save($credential, true);

                $session = $request->getSession();
                $session->set('face_verified', true);
                // Log the user in with same session shape as password login (name, avatar, settings for navbar + theme)
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
                    'message' => 'Face verified successfully',
                    'distance' => $distance,
                    'redirect' => $redirectUrl,
                ]);
            }

            return $this->json(['error' => 'Face mismatch', 'distance' => $distance], Response::HTTP_UNAUTHORIZED);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Authentication failed: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/check', name: 'check', methods: ['POST'])]
    public function check(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $userId = $data['userId'] ?? null;
        $deviceId = $data['deviceId'] ?? null;

        if (!$userId || !$deviceId) {
            return $this->json(['error' => 'Missing required data'], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->userRepository->find((int) $userId);
        if (!$user) {
            return $this->json(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        $credential = $this->faceRepository->findActiveForUserAndDevice($user, $deviceId);

        return $this->json([
            'enrolled' => $credential !== null,
            'deviceId' => $deviceId
        ]);
    }

    #[Route('/remove', name: 'remove', methods: ['POST'])]
    public function remove(Request $request): JsonResponse
    {
        $session = $request->getSession();
        if (!$session->get('is_logged_in') || !$session->get('user')) {
            return $this->json(['error' => 'User not logged in'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        $userId = $data['userId'] ?? null;
        $deviceId = $data['deviceId'] ?? null;

        if (!$userId || !$deviceId) {
            return $this->json(['error' => 'Missing required data'], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->userRepository->find((int) $userId);
        if (!$user) {
            return $this->json(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        $credential = $this->faceRepository->findActiveForUserAndDevice($user, $deviceId);
        if (!$credential) {
            return $this->json(['error' => 'No Face ID enrollment found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->faceRepository->remove($credential, true);
            return $this->json(['status' => 'ok', 'message' => 'Face ID enrollment removed']);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Removal failed: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
