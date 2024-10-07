<?php declare(strict_types = 1);

namespace ShipMonk\DoctrineEntityPreloader;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\PersistentCollection;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\Proxy;
use LogicException;
use function array_chunk;
use function array_keys;
use function array_values;
use function count;
use function get_parent_class;
use function is_a;

/**
 * @template E of object
 */
class EntityPreloader
{

    private const BATCH_SIZE = 1_000;
    private const PRELOAD_COLLECTION_DEFAULT_BATCH_SIZE = 100;

    public function __construct(
        private EntityManagerInterface $entityManager,
    )
    {
    }

    /**
     * @param list<S> $sourceEntities
     * @param positive-int|null $batchSize
     * @param non-negative-int|null $maxFetchJoinSameFieldCount
     * @return list<E>
     * @template S of E
     */
    public function preload(
        array $sourceEntities,
        string $sourcePropertyName,
        ?int $batchSize = null,
        ?int $maxFetchJoinSameFieldCount = null,
    ): array
    {
        $sourceEntitiesCommonAncestor = $this->getCommonAncestor($sourceEntities);

        if ($sourceEntitiesCommonAncestor === null) {
            return [];
        }

        $sourceClassMetadata = $this->entityManager->getClassMetadata($sourceEntitiesCommonAncestor);
        $associationMapping = $sourceClassMetadata->getAssociationMapping($sourcePropertyName);
        /** @var ClassMetadata<E> $targetClassMetadata */
        $targetClassMetadata = $this->entityManager->getClassMetadata($associationMapping->targetEntity);

        if ($associationMapping->isIndexed()) {
            throw new LogicException('Preloading of indexed associations is not supported');
        }

        $maxFetchJoinSameFieldCount ??= 1;

        $this->loadProxies($sourceClassMetadata, $sourceEntities, $batchSize, $maxFetchJoinSameFieldCount);

        return match ($associationMapping->type()) {
            ClassMetadata::ONE_TO_MANY => $this->preloadOneToMany($sourceEntities, $sourceClassMetadata, $sourcePropertyName, $targetClassMetadata, $batchSize, $maxFetchJoinSameFieldCount),
            ClassMetadata::ONE_TO_ONE,
            ClassMetadata::MANY_TO_ONE => $this->preloadToOne($sourceEntities, $sourceClassMetadata, $sourcePropertyName, $targetClassMetadata, $batchSize, $maxFetchJoinSameFieldCount),
            default => throw new LogicException("Unsupported association mapping type {$associationMapping->type()}"),
        };
    }

    /**
     * @param list<S> $entities
     * @return class-string<S>|null
     * @template S of E
     */
    private function getCommonAncestor(array $entities): ?string
    {
        $commonAncestor = null;

        foreach ($entities as $entity) {
            $entityClassName = $entity::class;

            if ($commonAncestor === null) {
                $commonAncestor = $entityClassName;
                continue;
            }

            while (!is_a($entityClassName, $commonAncestor, true)) {
                /** @var class-string<S>|false $commonAncestor */
                $commonAncestor = get_parent_class($commonAncestor);

                if ($commonAncestor === false) {
                    throw new LogicException('Given entities must have a common ancestor');
                }
            }
        }

        return $commonAncestor;
    }

    /**
     * @param ClassMetadata<T> $sourceClassMetadata
     * @param list<T> $sourceEntities
     * @param positive-int|null $batchSize
     * @param non-negative-int $maxFetchJoinSameFieldCount
     * @template T of E
     */
    private function loadProxies(
        ClassMetadata $sourceClassMetadata,
        array $sourceEntities,
        ?int $batchSize,
        int $maxFetchJoinSameFieldCount,
    ): void
    {
        $sourceIdentifierReflection = $sourceClassMetadata->getSingleIdReflectionProperty();

        if ($sourceIdentifierReflection === null) {
            throw new LogicException('Doctrine should use RuntimeReflectionService which never returns null.');
        }

        $proxyIds = [];

        foreach ($sourceEntities as $sourceEntity) {
            if ($sourceEntity instanceof Proxy && !$sourceEntity->__isInitialized()) {
                $proxyIds[] = $sourceIdentifierReflection->getValue($sourceEntity);
            }
        }

        $batchSize ??= self::PRELOAD_COLLECTION_DEFAULT_BATCH_SIZE;

        foreach (array_chunk($proxyIds, $batchSize) as $idsChunk) {
            $this->loadEntitiesBy($sourceClassMetadata, $sourceIdentifierReflection->getName(), $idsChunk, $maxFetchJoinSameFieldCount);
        }
    }

    /**
     * @param list<S> $sourceEntities
     * @param ClassMetadata<S> $sourceClassMetadata
     * @param ClassMetadata<T> $targetClassMetadata
     * @param positive-int|null $batchSize
     * @param non-negative-int $maxFetchJoinSameFieldCount
     * @return list<T>
     * @template S of E
     * @template T of E
     */
    private function preloadOneToMany(
        array $sourceEntities,
        ClassMetadata $sourceClassMetadata,
        string $sourcePropertyName,
        ClassMetadata $targetClassMetadata,
        ?int $batchSize,
        int $maxFetchJoinSameFieldCount,
    ): array
    {
        $sourceIdentifierReflection = $sourceClassMetadata->getSingleIdReflectionProperty(); // e.g. Order::$id reflection
        $sourcePropertyReflection = $sourceClassMetadata->getReflectionProperty($sourcePropertyName); // e.g. Order::$items reflection
        $targetPropertyName = $sourceClassMetadata->getAssociationMappedByTargetField($sourcePropertyName); // e.g. 'order'
        $targetPropertyReflection = $targetClassMetadata->getReflectionProperty($targetPropertyName); // e.g. Item::$order reflection

        if ($sourceIdentifierReflection === null || $sourcePropertyReflection === null || $targetPropertyReflection === null) {
            throw new LogicException('Doctrine should use RuntimeReflectionService which never returns null.');
        }

        $batchSize ??= self::PRELOAD_COLLECTION_DEFAULT_BATCH_SIZE;

        $targetEntities = [];
        $uninitializedCollections = [];

        foreach ($sourceEntities as $sourceEntity) {
            $sourceEntityId = (string) $sourceIdentifierReflection->getValue($sourceEntity);
            $sourceEntityCollection = $sourcePropertyReflection->getValue($sourceEntity);

            if (
                $sourceEntityCollection instanceof PersistentCollection
                && !$sourceEntityCollection->isInitialized()
                && !$sourceEntityCollection->isDirty() // preloading dirty collection is too hard to handle
            ) {
                $uninitializedCollections[$sourceEntityId] = $sourceEntityCollection;
                continue;
            }

            foreach ($sourceEntityCollection as $targetEntity) {
                $targetEntities[] = $targetEntity;
            }
        }

        foreach (array_chunk($uninitializedCollections, $batchSize, true) as $chunk) {
            $targetEntitiesChunk = $this->loadEntitiesBy($targetClassMetadata, $targetPropertyName, array_keys($chunk), $maxFetchJoinSameFieldCount);

            foreach ($targetEntitiesChunk as $targetEntity) {
                $sourceEntity = $targetPropertyReflection->getValue($targetEntity);
                $sourceEntityId = (string) $sourceIdentifierReflection->getValue($sourceEntity);
                $uninitializedCollections[$sourceEntityId]->add($targetEntity);
                $targetEntities[] = $targetEntity;
            }

            foreach ($chunk as $sourceEntityCollection) {
                $sourceEntityCollection->setInitialized(true);
                $sourceEntityCollection->takeSnapshot();
            }
        }

        return $targetEntities;
    }

    /**
     * @param list<S> $sourceEntities
     * @param ClassMetadata<S> $sourceClassMetadata
     * @param ClassMetadata<T> $targetClassMetadata
     * @param positive-int|null $batchSize
     * @param non-negative-int $maxFetchJoinSameFieldCount
     * @return list<T>
     * @template S of E
     * @template T of E
     */
    private function preloadToOne(
        array $sourceEntities,
        ClassMetadata $sourceClassMetadata,
        string $sourcePropertyName,
        ClassMetadata $targetClassMetadata,
        ?int $batchSize,
        int $maxFetchJoinSameFieldCount,
    ): array
    {
        $sourcePropertyReflection = $sourceClassMetadata->getReflectionProperty($sourcePropertyName); // e.g. Item::$order reflection
        $targetIdentifierReflection = $targetClassMetadata->getSingleIdReflectionProperty(); // e.g. Order::$id reflection

        if ($sourcePropertyReflection === null || $targetIdentifierReflection === null) {
            throw new LogicException('Doctrine should use RuntimeReflectionService which never returns null.');
        }

        $targetIdentifierName = $targetClassMetadata->getSingleIdentifierFieldName(); // e.g. 'id'

        $batchSize ??= self::BATCH_SIZE;

        $targetEntities = [];
        $uninitializedIds = [];

        foreach ($sourceEntities as $sourceEntity) {
            $targetEntity = $sourcePropertyReflection->getValue($sourceEntity);

            if ($targetEntity === null) {
                continue;
            }

            $targetEntityId = (string) $targetIdentifierReflection->getValue($targetEntity);
            $targetEntities[$targetEntityId] = $targetEntity;

            if ($targetEntity instanceof Proxy && !$targetEntity->__isInitialized()) {
                $uninitializedIds[$targetEntityId] = true;
            }
        }

        foreach (array_chunk(array_keys($uninitializedIds), $batchSize) as $idsChunk) {
            $this->loadEntitiesBy($targetClassMetadata, $targetIdentifierName, $idsChunk, $maxFetchJoinSameFieldCount);
        }

        return array_values($targetEntities);
    }

    /**
     * @param ClassMetadata<T> $targetClassMetadata
     * @param list<mixed> $fieldValues
     * @param non-negative-int $maxFetchJoinSameFieldCount
     * @return list<T>
     * @template T of E
     */
    private function loadEntitiesBy(
        ClassMetadata $targetClassMetadata,
        string $fieldName,
        array $fieldValues,
        int $maxFetchJoinSameFieldCount,
    ): array
    {
        $rootLevelAlias = 'e';

        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select($rootLevelAlias)
            ->from($targetClassMetadata->getName(), $rootLevelAlias)
            ->andWhere("{$rootLevelAlias}.{$fieldName} IN (:fieldValues)")
            ->setParameter('fieldValues', $fieldValues);

        $this->addFetchJoinsToPreventFetchDuringHydration($rootLevelAlias, $queryBuilder, $targetClassMetadata, $maxFetchJoinSameFieldCount);

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * @param ClassMetadata<S> $sourceClassMetadata
     * @param array<string, array<string, int>> $alreadyPreloadedJoins
     * @template S of E
     */
    private function addFetchJoinsToPreventFetchDuringHydration(
        string $alias,
        QueryBuilder $queryBuilder,
        ClassMetadata $sourceClassMetadata,
        int $maxFetchJoinSameFieldCount,
        array $alreadyPreloadedJoins = [],
    ): void
    {
        $sourceClassName = $sourceClassMetadata->getName();

        foreach ($sourceClassMetadata->getAssociationMappings() as $fieldName => $associationMapping) {
            $alreadyPreloadedJoins[$sourceClassName][$fieldName] ??= 0;

            if ($alreadyPreloadedJoins[$sourceClassName][$fieldName] >= $maxFetchJoinSameFieldCount) {
                continue;
            }

            /** @var ClassMetadata<E> $targetClassMetadata */
            $targetClassMetadata = $this->entityManager->getClassMetadata($associationMapping->targetEntity);

            $isToOne = ($associationMapping->type() & ClassMetadata::TO_ONE) !== 0;
            $isToOneInversed = $isToOne && !$associationMapping->isOwningSide();
            $isToOneAbstract = $isToOne && $associationMapping->isOwningSide() && count($targetClassMetadata->subClasses) > 0;

            if (!$isToOneInversed && !$isToOneAbstract) {
                continue;
            }

            $targetRelationAlias = "{$alias}_{$fieldName}";

            $queryBuilder->addSelect($targetRelationAlias);
            $queryBuilder->leftJoin("{$alias}.{$fieldName}", $targetRelationAlias);
            $alreadyPreloadedJoins[$sourceClassName][$fieldName]++;

            $this->addFetchJoinsToPreventFetchDuringHydration($targetRelationAlias, $queryBuilder, $targetClassMetadata, $maxFetchJoinSameFieldCount, $alreadyPreloadedJoins);
        }
    }

}
