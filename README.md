# Doctrine Entity Preloader

`shipmonk/doctrine-entity-preloader` is a PHP library designed to tackle the n+1 query problem in Doctrine ORM by efficiently preloading related entities. This library offers a flexible and powerful way to optimize database access patterns, especially in cases with complex entity relationships.

- 🚀 **Performance Boost:** Minimizes n+1 issues by preloading related entities with **constant number of queries**.
- 🔄 **Flexible:** Supports `OneToOne`, `OneToMany`, `ManyToOne`, and `ManyToMany` associations.
- 💡 **Easy Integration:** Simple to integrate with your existing Doctrine setup.
- 🛠️ **Configurable:** Customizable batch sizes and fetch join limits.

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

## Comparison vs. Fetch Joins

Unlike fetch joins, the EntityPreloader does not fetches duplicate data, which slows down both the query and the hydration process, except when necessary to prevent additional queries fired by Doctrine during hydration process.

## Comparison vs. `Doctrine\ORM\AbstractQuery::setFetchMode`

Unlike `setFetchMode` it can

* preload nested associations
* preload many has many association
* avoid additional queries fired by Doctrine during hydration process

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


## Supported Association Types

- `OneToOne`
- `OneToMany`
- `ManyToOne`
- `ManyToMany`


## Limitations

- no support for ordered collections
- no support for indexed collections
- no support for dirty collections
- no support for composite primary keys


## License

`shipmonk/doctrine-entity-preloader` is open-source software licensed under the MIT license.

Enjoy optimal performance with efficient entity preloading!
