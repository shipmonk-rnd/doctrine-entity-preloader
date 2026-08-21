<?php declare(strict_types = 1);

namespace ShipMonkTests\DoctrineEntityPreloader;

use Doctrine\DBAL\Types\Type as DbalType;
use PHPUnit\Framework\Attributes\DataProvider;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\MixedIdTypes\Album;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\MixedIdTypes\Photo;
use ShipMonkTests\DoctrineEntityPreloader\Lib\TestCase;

/**
 * Album::$photos is a many-to-many where the two sides have differently typed
 * identifiers: Album uses the custom primary_key type, Photo an autoincrement integer.
 */
class EntityPreloadMixedIdTypesManyHasManyTest extends TestCase
{

    #[DataProvider('providePrimaryKeyTypes')]
    public function testManyHasManyWithPreload(DbalType $primaryKey): void
    {
        $this->createDummyAlbumData($primaryKey, albumCount: 2, photoForEachAlbumCount: 3);

        $albums = $this->getEntityManager()->getRepository(Album::class)->findAll();
        $this->getEntityPreloader()->preload($albums, 'photos');

        self::assertSame(
            [['Photo#0', 'Photo#1', 'Photo#2'], ['Photo#0', 'Photo#1', 'Photo#2']],
            $this->readPhotoPaths($albums),
        );

        self::assertAggregatedQueries([
            ['count' => 1, 'query' => 'SELECT * FROM album t0'],
            ['count' => 1, 'query' => 'SELECT * FROM album a0_ INNER JOIN album_photo a2_ ON a0_.id = a2_.album_id INNER JOIN photo p1_ ON p1_.id = a2_.photo_id WHERE a0_.id IN (?, ?)'],
            ['count' => 1, 'query' => 'SELECT * FROM photo p0_ WHERE p0_.id IN (?, ?, ?, ?, ?, ?)'],
        ]);
    }

    #[DataProvider('providePrimaryKeyTypes')]
    public function testManyHasManyInversedWithPreload(DbalType $primaryKey): void
    {
        $this->createDummyAlbumData($primaryKey, albumCount: 2, photoForEachAlbumCount: 3);

        $photos = $this->getEntityManager()->getRepository(Photo::class)->findAll();
        $this->getEntityPreloader()->preload($photos, 'albums');

        self::assertSame(
            [['Album#0'], ['Album#0'], ['Album#0'], ['Album#1'], ['Album#1'], ['Album#1']],
            $this->readAlbumTitles($photos),
        );

        self::assertAggregatedQueries([
            ['count' => 1, 'query' => 'SELECT * FROM photo t0'],
            ['count' => 1, 'query' => 'SELECT * FROM photo p0_ INNER JOIN album_photo a2_ ON p0_.id = a2_.photo_id INNER JOIN album a1_ ON a1_.id = a2_.album_id WHERE p0_.id IN (?, ?, ?, ?, ?, ?)'],
            ['count' => 1, 'query' => 'SELECT * FROM album a0_ WHERE a0_.id IN (?, ?)'],
        ]);
    }

    private function createDummyAlbumData(
        DbalType $primaryKey,
        int $albumCount,
        int $photoForEachAlbumCount,
    ): void
    {
        $this->initializeEntityManager($primaryKey, $this->getQueryLogger());
        $entityManager = $this->getEntityManager();

        for ($i = 0; $i < $albumCount; $i++) {
            $album = new Album("Album#{$i}");
            $entityManager->persist($album);

            for ($j = 0; $j < $photoForEachAlbumCount; $j++) {
                $photo = new Photo("Photo#{$j}");
                $entityManager->persist($photo);
                $album->addPhoto($photo);
            }
        }

        $entityManager->flush();
        $entityManager->clear();
        $this->getQueryLogger()->clear();
    }

    /**
     * @param array<Album> $albums
     * @return list<list<string>>
     */
    private function readPhotoPaths(array $albums): array
    {
        $paths = [];

        foreach ($albums as $album) {
            $albumPaths = [];

            foreach ($album->getPhotos() as $photo) {
                $albumPaths[] = $photo->getPath();
            }

            $paths[] = $albumPaths;
        }

        return $paths;
    }

    /**
     * @param array<Photo> $photos
     * @return list<list<string>>
     */
    private function readAlbumTitles(array $photos): array
    {
        $titles = [];

        foreach ($photos as $photo) {
            $photoTitles = [];

            foreach ($photo->getAlbums() as $album) {
                $photoTitles[] = $album->getTitle();
            }

            $titles[] = $photoTitles;
        }

        return $titles;
    }

}
