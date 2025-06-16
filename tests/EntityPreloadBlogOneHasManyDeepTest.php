<?php declare(strict_types = 1);

namespace ShipMonkTests\DoctrineEntityPreloader;

use Doctrine\ORM\Mapping\ClassMetadata;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog\Category;
use ShipMonkTests\DoctrineEntityPreloader\Lib\TestCase;
use function array_map;
use function array_merge;

class EntityPreloadBlogOneHasManyDeepTest extends TestCase
{

    public function testOneHasManyDeepUnoptimized(): void
    {
        $this->createCategoryTree(depth: 5, branchingFactor: 5);

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

    public function testOneHasManyDeepWithWithManualPreloadUsingPartial(): void
    {
        $this->skipIfPartialEntitiesAreNotSupported();
        $this->createCategoryTree(depth: 5, branchingFactor: 5);

        $rootCategories = $this->getEntityManager()->createQueryBuilder()
            ->select('category')
            ->from(Category::class, 'category')
            ->where('category.parent IS NULL')
            ->getQuery()
            ->getResult();

        $this->getEntityManager()->createQueryBuilder()
            ->select('PARTIAL category.{id}', 'subCategory')
            ->from(Category::class, 'category')
            ->leftJoin('category.children', 'subCategory')
            ->where('category IN (:categories)')
            ->setParameter('categories', $rootCategories)
            ->getQuery()
            ->getResult();

        $subCategories = array_merge(...array_map(static fn (Category $category) => $category->getChildren()->toArray(), $rootCategories));
        $this->getEntityManager()->createQueryBuilder()
            ->select('PARTIAL subCategory.{id}', 'subSubCategory')
            ->from(Category::class, 'subCategory')
            ->leftJoin('subCategory.children', 'subSubCategory')
            ->where('subCategory IN (:subCategories)')
            ->setParameter('subCategories', $subCategories)
            ->getQuery()
            ->getResult();

        $this->readSubSubCategoriesNames($rootCategories);

        self::assertAggregatedQueries([
            ['count' => 1, 'query' => 'SELECT * FROM category c0_ WHERE c0_.parent_id IS NULL'],
            ['count' => 1, 'query' => 'SELECT * FROM category c0_ LEFT JOIN category c1_ ON c0_.id = c1_.parent_id WHERE c0_.id IN (?, ?, ?, ?, ?)'],
            ['count' => 1, 'query' => 'SELECT * FROM category c0_ LEFT JOIN category c1_ ON c0_.id = c1_.parent_id WHERE c0_.id IN (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'],
        ]);
    }

    public function testOneHasManyDeepWithFetchJoin(): void
    {
        $this->createCategoryTree(depth: 5, branchingFactor: 5);

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

    public function testOneHasManyDeepWithEagerFetchMode(): void
    {
        $this->createCategoryTree(depth: 5, branchingFactor: 5);

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

    public function testOneHasManyDeepWithPreload(): void
    {
        $this->createCategoryTree(depth: 5, branchingFactor: 5);

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
        int $depth,
        int $branchingFactor,
        ?Category $parent = null,
    ): void
    {
        for ($i = 0; $i < $branchingFactor; $i++) {
            $category = new Category("Category $depth-$i", $parent);
            $this->getEntityManager()->persist($category);

            if ($depth > 1) {
                $this->createCategoryTree($depth - 1, $branchingFactor, $category);
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
