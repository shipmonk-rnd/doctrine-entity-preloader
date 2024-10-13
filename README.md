# Doctrine Entity Preloader

`shipmonk/doctrine-entity-preloader` is a PHP library designed to tackle the n+1 query problem in Doctrine ORM by efficiently preloading related entities. This library offers a flexible and powerful way to optimize database access patterns, especially in cases with complex entity relationships.

- 🚀 **Performance Boost:** Minimizes n+1 issues by preloading related entities with **constant number of queries**.
- 🔄 **Flexible:** Supports all associations: `#[OneToOne]`, `#[OneToMany]`, `#[ManyToOne]`, and `#[ManyToMany]`.
- 💡 **Easy Integration:** Simple to integrate with your existing Doctrine setup.


## Comparison

|                                                                        | Default                 | Manual Preload                        | Fetch Join                             | setFetchMode            | **EntityPreloader**                            |
|------------------------------------------------------------------------|-------------------------|---------------------------------------|----------------------------------------|-------------------------|------------------------------------------------|
| [OneToMany](tests/EntityPreloadBlogOneHasManyTest.php)                 | :red_circle: 1 + n      | :red_circle: impossible in Doctrine 3 | :orange_circle: 1, but duplicates rows | :green_circle: 1 + 1    | :green_circle: 1 + 1                           |
| [OneToManyDeep](tests/EntityPreloadBlogOneHasManyDeepTest.php)         | :red_circle: 1 + n + n² | :red_circle: impossible in Doctrine 3 | :orange_circle: 1, but duplicates rows | :red_circle: 1 + 1 + n² | :green_circle: 1 + 1 + 1                       |
| [OneToManyAbstract](tests/EntityPreloadBlogOneHasManyAbstractTest.php) | :red_circle: 1 + n + n² | :red_circle: impossible in Doctrine 3 | :orange_circle: 1, but duplicates rows | :red_circle: 1 + 1 + n² | :orange_circle: 1 + 1 + 1, but duplicates rows |
| [ManyToOne](tests/EntityPreloadBlogManyHasOneTest.php)                 | :red_circle: 1 + n      | :green_circle: 1 + 1                  | :orange_circle: 1, but duplicates rows | :green_circle: 1 + 1    | :green_circle: 1 + 1                           |
| [ManyToOneDeep](tests/EntityPreloadBlogManyHasOneDeepTest.php)         | :red_circle: 1 + n + n  | :green_circle: 1 + 1 + 1              | :orange_circle: 1, but duplicates rows | :red_circle: 1 + 1 + n  | :green_circle: 1 + 1 + 1                       |
| [ManyToMany](tests/EntityPreloadBlogManyHasManyTest.php)               | :red_circle: 1 + n      | :red_circle: impossible in Doctrine 3 | :orange_circle: 1, but duplicates rows | :red_circle: 1 + n      | :green_circle: 1 + 1                           |

Unlike fetch joins, the EntityPreloader does not fetches duplicate data, which slows down both the query and the hydration process, except when necessary to prevent additional queries fired by Doctrine during hydration process.

Unlike `Doctrine\ORM\AbstractQuery::setFetchMode` it can

* preload nested associations
* preload `#[ManyToMany]` association
* avoid additional queries fired by Doctrine during hydration process


## Installation

To install the library, use Composer:

```sh
composer require shipmonk/doctrine-entity-preloader
```

## Usage

Below is a basic example demonstrating how to use `EntityPreloader` to preload related entities and avoid the n+1 problem:

```php
use ShipMonk\DoctrineEntityPreloader\EntityPreloader;

$categories = $entityManager->getRepository(Category::class)->findAll();

$preloader = new EntityPreloader($entityManager);
$articles = $preloader->preload($categories, 'articles'); // 1 query to preload articles
$preloader->preload($articles, 'tags'); // 1 query to preload tags
$preloader->preload($articles, 'comments'); // 1 query to preload comments

// no more queries are needed now
foreach ($categories as $category) {
    foreach ($category->getArticles() as $article) {
        echo $article->getTitle(), "\n";

        foreach ($articles->getTags() as $tag) {
            echo $tag->getLabel(), "\n";
        }

        foreach ($articles->getComments() as $comment) {
            echo $comment->getText(), "\n";
        }
    }
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


## Limitations

- no support for indexed collections
- no support for dirty collections
- no support for composite primary keys
