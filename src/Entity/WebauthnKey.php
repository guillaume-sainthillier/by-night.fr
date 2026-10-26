<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Entity;

use App\Repository\WebauthnKeyRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\Table;
use Doctrine\ORM\Mapping\UniqueConstraint;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;
use Webauthn\CredentialRecord;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\TrustPath\TrustPath;

/**
 * A passkey of a User, their second factor (jbtronics/2fa-webauthn). The credential itself is the mapped superclass
 * Webauthn\CredentialRecord of web-auth/webauthn-symfony-bundle: its columns, and its Doctrine types (base64, trust_path,
 * aaguid). The entity extends it through PublicKeyCredentialSource, unmapped, which the library reads the passkeys as.
 */
#[Entity(repositoryClass: WebauthnKeyRepository::class)]
#[Table(name: 'webauthn_key')]
#[UniqueConstraint(name: 'webauthn_key_user_name', columns: ['user_id', 'name'])]
#[UniqueEntity(fields: ['user', 'name'], message: 'Vous avez déjà une clé d\'accès de ce nom.')]
class WebauthnKey extends PublicKeyCredentialSource
{
    #[Id]
    #[GeneratedValue]
    #[Column(type: Types::INTEGER)]
    private ?int $id = null;

    /** Identifies the passkey in URLs */
    #[Column(type: UuidType::NAME, unique: true)]
    private Uuid $uuid;

    #[ManyToOne(targetEntity: User::class, inversedBy: 'webauthnKeys')]
    #[JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[Column(type: Types::STRING, length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private ?string $name = null;

    #[Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $lastUsedAt = null;

    /**
     * @param list<string>              $transports
     * @param array<string, mixed>|null $otherUI
     */
    public function __construct(
        string $publicKeyCredentialId,
        string $type,
        array $transports,
        string $attestationType,
        TrustPath $trustPath,
        Uuid $aaguid,
        string $credentialPublicKey,
        string $userHandle,
        int $counter,
        ?array $otherUI = null,
        ?bool $backupEligible = null,
        ?bool $backupStatus = null,
        ?bool $uvInitialized = null,
    ) {
        parent::__construct(
            $publicKeyCredentialId,
            $type,
            $transports,
            $attestationType,
            $trustPath,
            $aaguid,
            $credentialPublicKey,
            $userHandle,
            $counter,
            $otherUI,
            $backupEligible,
            $backupStatus,
            $uvInitialized,
        );
        $this->uuid = Uuid::v4();
        $this->createdAt = new DateTimeImmutable();
    }

    public static function register(CredentialRecord $record, User $user, string $name): self
    {
        $key = new self(
            publicKeyCredentialId: $record->publicKeyCredentialId,
            type: $record->type,
            transports: $record->transports,
            attestationType: $record->attestationType,
            trustPath: $record->trustPath,
            aaguid: $record->aaguid,
            credentialPublicKey: $record->credentialPublicKey,
            userHandle: $record->userHandle,
            counter: $record->counter,
            otherUI: $record->otherUI,
            backupEligible: $record->backupEligible,
            backupStatus: $record->backupStatus,
            uvInitialized: $record->uvInitialized,
        );
        $key->name = $name;

        $user->addWebauthnKey($key);

        return $key;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): Uuid
    {
        return $this->uuid;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastUsedAt(): ?DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    /**
     * After a login, which also set the signature counter the authenticator returned (against cloned authenticators)
     */
    public function markAsUsed(): self
    {
        $this->lastUsedAt = new DateTimeImmutable();

        return $this;
    }
}
