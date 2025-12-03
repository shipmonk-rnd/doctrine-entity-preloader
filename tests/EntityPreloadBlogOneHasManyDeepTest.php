<?php declare(strict_types = 1);

namespace ShipMonkTests\DoctrineEntityPreloader;

use Doctrine\DBAL\Types\Type as DbalType;
use Doctrine\ORM\Mapping\ClassMetadata;
use PHPUnit\Framework\Attributes\DataProvider;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog\Category;
use ShipMonkTests\DoctrineEntityPreloader\Lib\TestCase;
use function array_map;
use function array_merge;

class EntityPreloadBlogOneHasManyDeepTest extends TestCase
{

    #[DataProvider('providePrimaryKeyTypes')]
    public function testOneHasManyDeepUnoptimized(DbalType $primaryKey): void
    {
        $this->createCategoryTree($primaryKey, depth: 5, branchingFactor: 5);

        $rootCategories = $this->getEntityManager()->createQueryBuilder()
            ->select('category')
            ->from(Category::class, 'category')
            ->where('category.parent IS NULL')
            ->getQuery()
            ->getResult();

        $this->readSubSubCategoriesNames($rootCategories);

        self::assertAggregatedQueries([
            ['count' => 1, 'query' => 'SELECT * FROM category c0_ WHERE c0_.parent_id IS NULL'],
            ['count' => 5 + 25, 'query' => 'SELECT * FROM category t0 WHERE t0.parent_id = ?'],
        ]);
    }

    #[DataProvider('providePrimaryKeyTypes')]
    public function testOneHasManyDeepWithWithManualPreloadUsingPartial(DbalType $primaryKey): void
    {
        $this->skipIfPartialEntitiesAreNotSupported();
        $this->createCategoryTree($primaryKey, depth: 5, branchingFactor: 5);

        $rootCategories = $this->getEntityManager()->createQueryBuilder()
            ->select('category')
            ->from(Category::class, 'category')
            ->where('category.parent IS NULL')
            ->getQuery()
            ->getResult();

        $platform = $this->getEntityManager()->getConnection()->getDatabasePlatform();
        $rawRootCategoryIds = array_map(static fn (Category $category) => $primaryKey->convertToDatabaseValue($category->getId(), $platform), $rootCategories);

        $this->getEntityManager()->createQueryBuilder()
            ->select('PARTIAL category.{id}', 'subCategory')
            ->from(Category::class, 'category')
            ->leftJoin('category.children', 'subCategory')
            ->where('category IN (:categories)')
            ->setParameter('categories', $rawRootCategoryIds, $this->deduceArrayParameterType($primaryKey))
            ->getQuery()
            ->getResult();

        $subCategories = array_merge(...array_map(static fn (Category $category) => $category->getChildren()->toArray(), $rootCategories));
        $rawSubCategoryIds = array_map(static fn (Category $category) => $primaryKey->convertToDatabaseValue($category->getId(), $platform), $subCategories);

        $this->getEntityManager()->createQueryBuilder()
            ->select('PARTIAL subCategory.{id}', 'subSubCategory')
            ->from(Category::class, 'subCategory')
            ->leftJoin('subCategory.children', 'subSubCategory')
            ->where('subCategory IN (:subCategories)')
            ->setParameter('subCategories', $rawSubCategoryIds, $this->deduceArrayParameterType($primaryKey))
            ->getQuery()
            ->getResult();

        $this->readSubSubCategoriesNames($rootCategories);

        self::assertAggregatedQueries([
            ['count' => 1, 'query' => 'SELECT * FROM category c0_ WHERE c0_.parent_id IS NULL'],
            ['count' => 1, 'query' => 'SELECT * FROM category c0_ LEFT JOIN category c1_ ON c0_.id = c1_.parent_id WHERE c0_.id IN (?, ?, ?, ?, ?)'],
            ['count' => 1, 'query' => 'SELECT * FROM category c0_ LEFT JOIN category c1_ ON c0_.id = c1_.parent_id WHERE c0_.id IN (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'],
        ]);
    }

    #[DataProvider('providePrimaryKeyTypes')]
    public function testOneHasManyDeepWithFetchJoin(DbalType $primaryKey): void
    {
        $this->createCategoryTree($primaryKey, depth: 5, branchingFactor: 5);

        $rootCategories = $this->getEntityManager()->createQueryBuilder()
            ->select('category', 'subCategories', 'subSubCategories')
            ->from(Category::class, 'category')
            ->leftJoin('category.children', 'subCategories')
            ->leftJoin('subCategories.children', 'subSubCategories')
            ->where('category.parent IS NULL')
            ->getQuery()
            ->getResult();

        $this->readSubSubCategoriesNames($rootCategories);

        self::assertAggregatedQueries([
            ['count' => 1, 'query' => 'SELECT * FROM category c0_ LEFT JOIN category c1_ ON c0_.id = c1_.parent_id LEFT JOIN category c2_ ON c1_.id = c2_.parent_id WHERE c0_.parent_id IS NULL'],
        ]);
    }

    #[DataProvider('providePrimaryKeyTypes')]
    public function testOneHasManyDeepWithEagerFetchMode(DbalType $primaryKey): void
    {
        $this->skipIfDoctrineOrmHasBrokenUnhandledMatchCase();
        $this->skipIfDoctrineOrmHasBrokenEagerFetch($primaryKey);
        $this->createCategoryTree($primaryKey, depth: 5, branchingFactor: 5);

        $rootCategories = $this->getEntityManager()->createQueryBuilder()
            ->select('category')
            ->from(Category::class, 'category')
            ->where('category.parent IS NULL')
            ->getQuery()
            ->setFetchMode(Category::class, 'children', ClassMetadata::FETCH_EAGER)
            ->getResult();

        $this->readSubSubCategoriesNames($rootCategories);

        self::assertAggregatedQueries([
            ['count' => 1, 'query' => 'SELECT * FROM category c0_ WHERE c0_.parent_id IS NULL'],
            ['count' => 1, 'query' => 'SELECT * FROM category t0 WHERE t0.parent_id IN (?, ?, ?, ?, ?)'],
            ['count' => 25, 'query' => 'SELECT * FROM category t0 WHERE t0.parent_id = ?'],
        ]);
    }

    #[DataProvider('providePrimaryKeyTypes')]
    public function testOneHasManyDeepWithPreload(DbalType $primaryKey): void
    {
        $this->createCategoryTree($primaryKey, depth: 5, branchingFactor: 5);

        /** @var list<Category> $rootCategories */
        $rootCategories = $this->getEntityManager()->createQueryBuilder()
            ->select('category')
            ->from(Category::class, 'category')
            ->where('category.parent IS NULL')
            ->getQuery()
            ->getResult();

        $subCategories = $this->getEntityPreloader()->preload($rootCategories, 'children');
        $this->getEntityPreloader()->preload($subCategories, 'children');

        $this->readSubSubCategoriesNames($rootCategories);

        self::assertAggregatedQueries([
            ['count' => 1, 'query' => 'SELECT * FROM category c0_ WHERE c0_.parent_id IS NULL'],
            ['count' => 1, 'query' => 'SELECT * FROM category c0_ WHERE c0_.parent_id IN (?, ?, ?, ?, ?)'],
            ['count' => 1, 'query' => 'SELECT * FROM category c0_ WHERE c0_.parent_id IN (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'],
        ]);
    }

    private function createCategoryTree(
        DbalType $primaryKey,
        int $depth,
        int $branchingFactor,
        ?Category $parent = null,
    ): void
    {
        $this->initializeEntityManager($primaryKey, $this->getQueryLogger());

        for ($i = 0; $i < $branchingFactor; $i++) {
            $category = new Category("Category $depth-$i", $parent);
            $this->getEntityManager()->persist($category);

            if ($depth > 1) {
                $this->createCategoryTree($primaryKey, $depth - 1, $branchingFactor, $category);
            }
        }

        if ($parent === null) {
            $this->getEntityManager()->flush();
            $this->getEntityManager()->clear();
            $this->getQueryLogger()->clear();
        }
    }

    /**
     * @param array<Category> $categories
     */
    private function readSubSubCategoriesNames(array $categories): void
    {
        foreach ($categories as $category) {
            foreach ($category->getChildren() as $child) {
                foreach ($child->getChildren() as $child2) {
                    $child2->getName();
                }
            }
        }
    }

}
