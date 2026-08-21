<?php declare(strict_types = 1);

namespace ShipMonkTests\DoctrineEntityPreloader\Fixtures\MixedIdTypes;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\ManyToMany;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog\TestEntityWithCustomPrimaryKey;

/**
 * Owning side of a many-to-many association where the identifier type of the
 * source entity (custom primary_key type) differs from the identifier type of
 * the target entity (autoincrement integer).
 */
#[Entity]
class Album extends TestEntityWithCustomPrimaryKey
{

    #[Column]
    private string $title;

    /**
     * @var Collection<int, Photo>
     */
    #[ManyToMany(targetEntity: Photo::class, inversedBy: 'albums')]
    private Collection $photos;

    public function __construct(string $title)
    {
        parent::__construct();
        $this->title = $title;
        $this->photos = new ArrayCollection();
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @return ReadableCollection<int, Photo>
     */
    public function getPhotos(): ReadableCollection
    {
        return $this->photos;
    }

    public function addPhoto(Photo $photo): void
    {
        if (!$this->photos->contains($photo)) {
            $this->photos->add($photo);
            $photo->addAlbum($this);
        }
    }

}
