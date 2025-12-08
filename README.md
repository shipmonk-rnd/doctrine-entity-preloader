# Doctrine Entity Preloader

`shipmonk/doctrine-entity-preloader` is a PHP library designed to tackle the n+1 query problem in Doctrine ORM by efficiently preloading related entities. This library offers a flexible and powerful way to optimize database access patterns, especially in cases with complex entity relationships.

- :rocket: **Performance Boost:** Minimizes n+1 issues by preloading related entities with **constant number of queries**.
- :arrows_counterclockwise: **Flexible:** Supports all associations: `#[OneToOne]`, `#[OneToMany]`, `#[ManyToOne]`, and `#[ManyToMany]`.
- :bulb: **Easy Integration:** Simple to integrate with your existing Doctrine setup (supports both v2 and v3).


## Comparison

|                                                                        | [Default](https://docs.google.com/presentation/d/1sSlZOxmEUVKt0l8zhimex-6lR0ilC001GXh8colaXxg/edit#slide=id.g30998e74a82_0_0) | [Manual](https://docs.google.com/presentation/d/1sSlZOxmEUVKt0l8zhimex-6lR0ilC001GXh8colaXxg/edit#slide=id.g309b68062f4_0_0) | [Fetch Join](https://docs.google.com/presentation/d/1sSlZOxmEUVKt0l8zhimex-6lR0ilC001GXh8colaXxg/edit#slide=id.g309b68062f4_0_15) | [setFetchMode](https://docs.google.com/presentation/d/1sSlZOxmEUVKt0l8zhimex-6lR0ilC001GXh8colaXxg/edit#slide=id.g309b68062f4_0_35) | [**EntityPreloader**](https://docs.google.com/presentation/d/1sSlZOxmEUVKt0l8zhimex-6lR0ilC001GXh8colaXxg/edit#slide=id.g309b68062f4_0_265) |
|------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------|------------------------------------------------------------------------------------------------------------------------------|-----------------------------------------------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------|---------------------------------------------------------------------------------------------------------------------------------------------|
| [OneToMany](tests/EntityPreloadBlogOneHasManyTest.php)                 | :red_circle: 1 + n                                                                                                            | :green_circle: 2                                                                                                             | :orange_circle: 1, but<br>duplicate rows                                                                                          | :green_circle: 2                                                                                                                    | :green_circle: 2                                                                                                                            |
| [OneToManyDeep](tests/EntityPreloadBlogOneHasManyDeepTest.php)         | :red_circle: 1 + n + n²                                                                                                       | :green_circle: 3                                                                                                             | :orange_circle: 1, but<br>duplicate rows                                                                                          | :red_circle: 2 + n²                                                                                                                 | :green_circle: 3                                                                                                                            |
| [OneToManyAbstract](tests/EntityPreloadBlogOneHasManyAbstractTest.php) | :red_circle: 1 + n + n²                                                                                                       | :orange_circle: 3, but<br>duplicate rows                                                                                     | :orange_circle: 1, but<br>duplicate rows                                                                                          | :red_circle: 2 + n²                                                                                                                 | :orange_circle: 3, but<br>duplicate rows                                                                                                    |
| [ManyToOne](tests/EntityPreloadBlogManyHasOneTest.php)                 | :red_circle: 1 + n                                                                                                            | :green_circle: 2                                                                                                             | :orange_circle: 1, but<br>duplicate rows                                                                                          | :green_circle: 2                                                                                                                    | :green_circle: 2                                                                                                                            |
| [ManyToOneDeep](tests/EntityPreloadBlogManyHasOneDeepTest.php)         | :red_circle: 1 + n + n                                                                                                        | :green_circle: 3                                                                                                             | :orange_circle: 1, but<br>duplicate rows                                                                                          | :red_circle: 2 + n                                                                                                                  | :green_circle: 3                                                                                                                            |
| [ManyToMany](tests/EntityPreloadBlogManyHasManyTest.php)               | :red_circle: 1 + n                                                                                                            | :green_circle: 2                                                                                                             | :orange_circle: 1, but<br>duplicate rows                                                                                          | :red_circle: 1 + n                                                                                                                  | :green_circle: 2                                                                                                                            |


### Comparison vs. Manual Preload

Unlike manual preload, the EntityPreloader does not require writing custom queries for each association.

### Comparison vs. Fetch Join

Unlike fetch joins, the EntityPreloader does not fetches duplicate data, which slows down both the query and the hydration process, except when necessary to prevent additional queries fired by Doctrine during hydration process.

The fetch join scales poorly with the number of associations. With every preloaded association, the number of duplicate rows grows. The EntityPreloader does not have this problem.

### Comparison vs. setFetchMode

Unlike `Doctrine\ORM\AbstractQuery::setFetchMode` it can

* preload nested associations
* preload `#[ManyToMany]` association
* avoid additional queries fired by Doctrine during hydration process


## Installation

To install the library, use Composer:

```sh
composer require shipmonk/doctrine-entity-preloader
```

### PHPStan

This library provides PHPStan integration:

- **extension.neon** - Infers return types for `EntityPreloader::preload()` based on the preloaded association
- **rules.neon** - Validates that the property name passed to `preload()` exists on the entity

If you use [PHPStan](https://phpstan.org/) and have [phpstan/extension-installer](https://github.com/phpstan/extension-installer) installed, the extension and rules are enabled automatically.

Otherwise, add the following to your `phpstan.neon`:

```neon
includes:
    - vendor/shipmonk/doctrine-entity-preloader/extension.neon
    - vendor/shipmonk/doctrine-entity-preloader/rules.neon
```

If [phpstan/phpstan-doctrine](https://github.com/phpstan/phpstan-doctrine) is installed, real Doctrine metadata will be used for more accurate type inference.

## Usage

Below is a basic example demonstrating how to use `EntityPreloader` to preload related entities and avoid the n+1 problem:

```php
use ShipMonk\DoctrineEntityPreloader\EntityPreloader;

$categories = $entityManager->getRepository(Category::class)->findAll();

$preloader = new EntityPreloader($entityManager);
$articles = $preloader->preload($categories, 'articles'); // 1 query to preload articles
$preloader->preload($articles, 'tags'); // 2 queries to preload tags
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
