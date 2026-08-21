<?php declare(strict_types = 1);

namespace ShipMonkTests\DoctrineEntityPreloader\Fixtures\MixedIdTypes;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\ManyToMany;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Synthetic\TestEntityWithId;

/**
 * Inverse side of a many-to-many association where the identifier type of the
 * source entity (autoincrement integer) differs from the identifier type of
 * the target entity (custom primary_key type).
 */
#[Entity]
class Photo extends TestEntityWithId
{

    #[Column]
    private string $path;

    /**
     * @var Collection<int, Album>
     */
    #[ManyToMany(targetEntity: Album::class, mappedBy: 'photos')]
    private Collection $albums;

    public function __construct(string $path)
    {
        $this->path = $path;
        $this->albums = new ArrayCollection();
    }

    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @return ReadableCollection<int, Album>
     */
    public function getAlbums(): ReadableCollection
    {
        return $this->albums;
    }

    public function addAlbum(Album $album): void
    {
        if (!$this->albums->contains($album)) {
            $this->albums->add($album);
            $album->addPhoto($this);
        }
    }

}
