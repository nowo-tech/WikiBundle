<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Nowo\WikiBundle\ValueObject\Uuid;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * One user's emoji reaction to a comment (1.16): at most one row per comment, user and emoji.
 */
#[ORM\Entity]
#[ORM\Table(name: 'wiki_comment_reactions')]
#[ORM\UniqueConstraint(name: 'wiki_comment_reaction_unique', columns: ['comment_id', 'user_id', 'emoji'])]
class WikiCommentReaction
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: WikiComment::class)]
        #[ORM\JoinColumn(name: 'comment_id', nullable: false, onDelete: 'CASCADE')]
        private WikiComment $comment,
        #[ORM\ManyToOne(targetEntity: UserInterface::class)]
        #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
        private object $user,
        #[ORM\Column(type: 'string', length: 32)]
        private string $emoji,
    ) {
        $this->id = Uuid::generate()->toString();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getComment(): WikiComment
    {
        return $this->comment;
    }

    public function getUser(): object
    {
        return $this->user;
    }

    public function getEmoji(): string
    {
        return $this->emoji;
    }
}
