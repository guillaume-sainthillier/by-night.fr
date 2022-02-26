<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Entity;

use App\Repository\CommentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Stringable;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CommentRepository::class)]
class Comment implements Stringable
{
    use EntityIdentityTrait;
    use EntityTimestampableTrait;
    #[Assert\Length(min: 3, minMessage: 'Le commentaire doit faire au moins {{ limit }} caractères')]
    #[Assert\NotBlank(message: 'Le commentaire ne peut pas être vide')]
    #[ORM\Column(type: 'text')]
    private ?string $commentaire = null;

    #[ORM\Column(type: 'boolean')]
    protected bool $approuve = true;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Event::class, inversedBy: 'commentaires')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Event $event = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'reponses')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Comment $parent = null;

    /**
     * @var Collection<int, Comment>
     */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent', cascade: ['persist', 'remove'], fetch: 'EXTRA_LAZY')]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    protected Collection $reponses;

    public function __construct()
    {
        $this->reponses = new ArrayCollection();
    }

    public function __toString(): string
    {
        return sprintf('#%s', $this->id ?: '?');
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(string $commentaire): self
    {
        $this->commentaire = $commentaire;

        return $this;
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

    public function getEvent(): ?Event
    {
        return $this->event;
    }

    public function setEvent(?Event $event): self
    {
        $this->event = $event;

        return $this;
    }

    /**
     * @psalm-return Collection<int, self>
     */
    public function getReponses(): Collection
    {
        return $this->reponses;
    }

    public function addReponse(self $reponse): self
    {
        if (!$this->reponses->contains($reponse)) {
            $this->reponses[] = $reponse;
            $reponse->setParent($this);
        }

        return $this;
    }

    public function removeReponse(self $reponse): self
    {
        if ($this->reponses->contains($reponse)) {
            $this->reponses->removeElement($reponse);
            // set the owning side to null (unless already changed)
            if ($reponse->getParent() === $this) {
                $reponse->setParent(null);
            }
        }

        return $this;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): self
    {
        $this->parent = $parent;

        return $this;
    }

    public function getApprouve(): ?bool
    {
        return $this->approuve;
    }

    public function setApprouve(bool $approuve): self
    {
        $this->approuve = $approuve;

        return $this;
    }
}
