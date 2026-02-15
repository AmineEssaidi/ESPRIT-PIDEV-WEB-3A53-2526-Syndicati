<?php

namespace App\Repository\WebAuthn;

use App\Repository\User\UserRepository;
use Webauthn\Bundle\Repository\PublicKeyCredentialUserEntityRepositoryInterface;
use Webauthn\PublicKeyCredentialUserEntity;

class WebAuthnUserRepository implements PublicKeyCredentialUserEntityRepositoryInterface
{
    public function __construct(private UserRepository $userRepository)
    {
    }

    public function findOneByUsername(string $username): ?PublicKeyCredentialUserEntity
    {
        $user = $this->userRepository->findOneBy(['email_user' => $username]);

        if (!$user) {
            return null;
        }

        return new PublicKeyCredentialUserEntity(
            $user->getEmailUser(),
            (string) $user->getIdUser(),
            $user->getFirstName() . ' ' . $user->getLastName(),
            null // Icon
        );
    }

    public function findOneByUserHandle(string $userHandle): ?PublicKeyCredentialUserEntity
    {
        $user = $this->userRepository->find($userHandle);

        if (!$user) {
            return null;
        }

        return new PublicKeyCredentialUserEntity(
            $user->getEmailUser(),
            (string) $user->getIdUser(),
            $user->getFirstName() . ' ' . $user->getLastName(),
            null // Icon
        );
    }
}
