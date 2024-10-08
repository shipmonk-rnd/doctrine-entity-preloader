# Doctrine Entity Preloader

`shipmonk/doctrine-entity-preloader` is a PHP library designed to tackle the n+1 query problem in Doctrine ORM by efficiently preloading related entities. This library offers a flexible and powerful way to optimize database access patterns, especially in cases with complex entity relationships.

- 🚀 **Performance Boost:** Minimizes n+1 issues by preloading related entities in batches.
- 🔄 **Flexible:** Supports `OneToOne`, `OneToMany`, `ManyToOne`, and `ManyToMany` associations.
- 🛠️ **Configurable:** Customizable batch sizes and fetch join limits.
- 💡 **Easy Integration:** Simple to integrate with your existing Doctrine setup.

## Comparison

Below is an example showcasing different ways of handling the n+1 query issue and how `EntityPreloader` stacks up:

| Approach               | Query Count                          | Explanation                                                                 |
|------------------------|--------------------------------------|-----------------------------------------------------------------------------|
| Unoptimized            | 1 + n                                | One query to fetch articles, plus one for each category.                    |
| With Fetch Join        | 1                                    | Fetches articles and categories in a single join.                           |
| Eager Fetch Mode       | 1 + 1                                | Fetches articles, then fetches all categories with an `IN` clause.          |
| Using EntityPreloader  | 1 + 1                                | Fetches articles, then loads categories in a single query with `IN` clause. |

## Installation

To install the library, use Composer:

```sh
composer require shipmonk/doctrine-entity-preloader
```

## Usage

Below is a basic example demonstrating how to use `EntityPreloader` to preload related entities and avoid the n+1 problem:

```php
use ShipMonk\DoctrineEntityPreloader\EntityPreloader;
use Doctrine\ORM\EntityManagerInterface;

$entityManager = // Get your EntityManager instance

$articles = $entityManager->getRepository(Article::class)->findAll();

$preloader = new EntityPreloader($entityManager);
$preloader->preload($articles, 'category'); // Preload categories for all articles

foreach ($articles as $article) {
    echo $article->getCategory()->getName();
}
```

## Configuration

`EntityPreloader` allows you to adjust batch sizes and fetch join limits to fit your application's performance needs:

- **Batch Size:** Set a custom batch size for preloading to optimize memory usage.
- **Max Fetch Join Same Field Count:** Define the maximum number of join fetches allowed per field.

```php
$preloader->preload(
    $articles,
    'category',
    batchSize: 20,
    maxFetchJoinSameFieldCount: 5
);
```

## Example Tests

The following test cases illustrate various approaches and how `EntityPreloader` performs compared to other common solutions:

```php
public function testManyHasOneWithPreload(): void
{
    $this->createDummyBlogData(categoryCount: 5, articleInEachCategoryCount: 5);

    $articles = $this->getEntityManager()->getRepository(Article::class)->findAll();
    $this->getEntityPreloader()->preload($articles, 'category');

    // Category names are accessed without triggering additional queries
    $this->readArticleCategoryNames($articles);

    self::assertAggregatedQueries([
        ['count' => 1, 'query' => 'SELECT * FROM article t0'],
        ['count' => 1, 'query' => 'SELECT * FROM category c0_ WHERE c0_.id IN (?, ?, ?, ?, ?)'],
    ]);
}
```

## Supported Association Types

- `OneToOne`
- `OneToMany`
- `ManyToOne`
- `ManyToMany`

## Contributing

Contributions are welcome! To contribute:

1. Fork the repository.
2. Create a feature branch (`git checkout -b feature/my-new-feature`).
3. Commit your changes (`git commit -am 'Add new feature'`).
4. Push to the branch (`git push origin feature/my-new-feature`).
5. Open a pull request.

## License

`shipmonk/doctrine-entity-preloader` is open-source software licensed under the MIT license.

Enjoy optimal performance with efficient entity preloading!
