<?php

namespace App\Repository\WebAuthn;

use App\Entity\WebAuthn\WebAuthnCredential;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Webauthn\Bundle\Repository\PublicKeyCredentialSourceRepositoryInterface;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialUserEntity;

/**
 * @extends ServiceEntityRepository<WebAuthnCredential>
 *
 * @method WebAuthnCredential|null find($id, $lockMode = null, $lockVersion = null)
 * @method WebAuthnCredential|null findOneBy(array $criteria, array $orderBy = null)
 * @method WebAuthnCredential[]    findAll()
 * @method WebAuthnCredential[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class WebAuthnCredentialRepository extends ServiceEntityRepository implements PublicKeyCredentialSourceRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WebAuthnCredential::class);
    }

    public function save(WebAuthnCredential $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function saveCredentialSource($publicKeyCredentialSource): void
    {
        if (!$publicKeyCredentialSource instanceof WebAuthnCredential && !$publicKeyCredentialSource instanceof PublicKeyCredentialSource) {
            throw new \InvalidArgumentException('This repository only supports WebAuthnCredential entities or PublicKeyCredentialSource objects.');
        }

        if ($publicKeyCredentialSource instanceof PublicKeyCredentialSource) {
            // Logic to convert and save if needed, but for now we expect our entity
            return;
        }

        $this->save($publicKeyCredentialSource, true);
    }

    public function remove(WebAuthnCredential $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOneByCredentialId(string $publicKeyCredentialId): ?PublicKeyCredentialSource
    {
        $credential = $this->findOneBy(['credentialId' => $publicKeyCredentialId]);

        return $credential?->toPublicKeyCredentialSource();
    }

    public function findAllForUserEntity(PublicKeyCredentialUserEntity $publicKeyCredentialUserEntity): array
    {
        $userHandle = $publicKeyCredentialUserEntity->id;
        $credentials = $this->findBy(['userHandle' => $userHandle]);

        return array_map(
            fn(WebAuthnCredential $credential) => $credential->toPublicKeyCredentialSource(),
            $credentials
        );
    }

    /**
     * @return WebAuthnCredential[]
     */
    public function findAllEntitiesForUser(\App\Entity\User\User $user): array
    {
        return $this->findBy(['user' => $user]);
    }

    public function findOneEntityByCredentialId(string $credentialId): ?WebAuthnCredential
    {
        return $this->findOneBy(['credentialId' => $credentialId]);
    }
}
