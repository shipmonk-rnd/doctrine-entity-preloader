<?php declare(strict_types = 1);

namespace ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\InheritanceType;
use Doctrine\ORM\Mapping\OneToMany;
use LogicException;

#[Entity]
#[InheritanceType('SINGLE_TABLE')]
abstract class Contributor
{

    #[Id]
    #[Column]
    #[GeneratedValue]
    private int $id;

    #[Column]
    private string $name;

    /**
     * @var Collection<int, Comment>
     */
    #[OneToMany(targetEntity: Comment::class, mappedBy: 'author')]
    private Collection $comments;

    public function __construct(string $name)
    {
        $this->name = $name;
        $this->comments = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return ReadableCollection<int, Comment>
     */
    public function getComments(): ReadableCollection
    {
        return $this->comments;
    }

    /**
     * @internal
     */
    public function addComment(Comment $comment): void
    {
        if ($comment->getAuthor() !== $this) {
            throw new LogicException('Comment already added to another user');
        }

        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
        }
    }

}
