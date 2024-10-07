<?php declare(strict_types = 1);

namespace ShipMonk\DoctrineEntityPreloader;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\PersistentCollection;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\Proxy;
use LogicException;
use function array_chunk;
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

        if ($associationMapping->isOrdered()) {
            throw new LogicException('Preloading of ordered associations is not supported');
        }

        $maxFetchJoinSameFieldCount ??= 1;
        $sourceEntities = $this->loadProxies($sourceClassMetadata, $sourceEntities, $batchSize ?? self::BATCH_SIZE, $maxFetchJoinSameFieldCount);

        return match ($associationMapping->type()) {
            ClassMetadata::ONE_TO_MANY => $this->preloadOneToMany($sourceEntities, $sourceClassMetadata, $sourcePropertyName, $targetClassMetadata, $batchSize, $maxFetchJoinSameFieldCount),
            ClassMetadata::MANY_TO_MANY => $this->preloadManyToMany($sourceEntities, $sourceClassMetadata, $sourcePropertyName, $targetClassMetadata, $batchSize, $maxFetchJoinSameFieldCount),
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
     * @param ClassMetadata<T> $classMetadata
     * @param list<T> $entities
     * @param positive-int $batchSize
     * @param non-negative-int $maxFetchJoinSameFieldCount
     * @return list<T>
     * @template T of E
     */
    private function loadProxies(
        ClassMetadata $classMetadata,
        array $entities,
        int $batchSize,
        int $maxFetchJoinSameFieldCount,
    ): array
    {
        $identifierReflection = $classMetadata->getSingleIdReflectionProperty(); // e.g. Order::$id reflection
        $identifierName = $classMetadata->getSingleIdentifierFieldName(); // e.g. 'id'

        if ($identifierReflection === null) {
            throw new LogicException('Doctrine should use RuntimeReflectionService which never returns null.');
        }

        $uniqueEntities = [];
        $uninitializedIds = [];

        foreach ($entities as $entity) {
            $entityId = $identifierReflection->getValue($entity);
            $entityKey = (string) $entityId;
            $uniqueEntities[$entityKey] = $entity;

            if ($entity instanceof Proxy && !$entity->__isInitialized()) {
                $uninitializedIds[$entityKey] = $entityId;
            }
        }

        foreach (array_chunk($uninitializedIds, $batchSize) as $idsChunk) {
            $this->loadEntitiesBy($classMetadata, $identifierName, $idsChunk, $maxFetchJoinSameFieldCount);
        }

        return array_values($uniqueEntities);
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

        $targetIdentifierReflection = $targetClassMetadata->getSingleIdReflectionProperty();
        $targetPropertyName = $sourceClassMetadata->getAssociationMappedByTargetField($sourcePropertyName); // e.g. 'order'
        $targetPropertyReflection = $targetClassMetadata->getReflectionProperty($targetPropertyName); // e.g. Item::$order reflection

        if ($sourceIdentifierReflection === null || $sourcePropertyReflection === null || $targetIdentifierReflection === null || $targetPropertyReflection === null) {
            throw new LogicException('Doctrine should use RuntimeReflectionService which never returns null.');
        }

        $batchSize ??= self::PRELOAD_COLLECTION_DEFAULT_BATCH_SIZE;
        $targetEntities = [];
        $uninitializedSourceEntityIds = [];
        $uninitializedCollections = [];

        foreach ($sourceEntities as $sourceEntity) {
            $sourceEntityId = $sourceIdentifierReflection->getValue($sourceEntity);
            $sourceEntityKey = (string) $sourceEntityId;
            $sourceEntityCollection = $sourcePropertyReflection->getValue($sourceEntity);

            if (
                $sourceEntityCollection instanceof PersistentCollection
                && !$sourceEntityCollection->isInitialized()
                && !$sourceEntityCollection->isDirty() // preloading dirty collection is too hard to handle
            ) {
                $uninitializedSourceEntityIds[$sourceEntityKey] = $sourceEntityId;
                $uninitializedCollections[$sourceEntityKey] = $sourceEntityCollection;
                continue;
            }

            foreach ($sourceEntityCollection as $targetEntity) {
                $targetEntityId = $targetIdentifierReflection->getValue($targetEntity);
                $targetEntityKey = (string) $targetEntityId;
                $targetEntities[$targetEntityKey] = $targetEntity;
            }
        }

        foreach (array_chunk($uninitializedSourceEntityIds, $batchSize, preserve_keys: true) as $uninitializedSourceEntityIdsChunk) {
            $targetEntitiesChunk = $this->loadEntitiesBy($targetClassMetadata, $targetPropertyName, array_values($uninitializedSourceEntityIdsChunk), $maxFetchJoinSameFieldCount);

            foreach ($targetEntitiesChunk as $targetEntity) {
                $sourceEntity = $targetPropertyReflection->getValue($targetEntity);
                $sourceEntityKey = (string) $sourceIdentifierReflection->getValue($sourceEntity);
                $uninitializedCollections[$sourceEntityKey]->add($targetEntity);

                $targetEntityKey = (string) $targetIdentifierReflection->getValue($targetEntity);
                $targetEntities[$targetEntityKey] = $targetEntity;
            }
        }

        foreach ($uninitializedCollections as $sourceEntityCollection) {
            $sourceEntityCollection->setInitialized(true);
            $sourceEntityCollection->takeSnapshot();
        }

        return array_values($targetEntities);
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
    private function preloadManyToMany(
        array $sourceEntities,
        ClassMetadata $sourceClassMetadata,
        string $sourcePropertyName,
        ClassMetadata $targetClassMetadata,
        ?int $batchSize,
        int $maxFetchJoinSameFieldCount,
    ): array
    {
        $sourceIdentifierReflection = $sourceClassMetadata->getSingleIdReflectionProperty();
        $sourceIdentifierName = $sourceClassMetadata->getSingleIdentifierFieldName();
        $sourcePropertyReflection = $sourceClassMetadata->getReflectionProperty($sourcePropertyName);

        $targetIdentifierReflection = $targetClassMetadata->getSingleIdReflectionProperty();
        $targetIdentifierName = $targetClassMetadata->getSingleIdentifierFieldName();

        if ($sourceIdentifierReflection === null || $sourcePropertyReflection === null || $targetIdentifierReflection === null) {
            throw new LogicException('Doctrine should use RuntimeReflectionService which never returns null.');
        }

        $batchSize ??= self::PRELOAD_COLLECTION_DEFAULT_BATCH_SIZE;
        $targetEntities = [];
        $uninitializedSourceEntityIds = [];
        $uninitializedCollections = [];

        foreach ($sourceEntities as $sourceEntity) {
            $sourceEntityId = $sourceIdentifierReflection->getValue($sourceEntity);
            $sourceEntityKey = (string) $sourceEntityId;
            $sourceEntityCollection = $sourcePropertyReflection->getValue($sourceEntity);

            if (
                $sourceEntityCollection instanceof PersistentCollection
                && !$sourceEntityCollection->isInitialized()
                && !$sourceEntityCollection->isDirty() // preloading dirty collection is too hard to handle
            ) {
                $uninitializedSourceEntityIds[$sourceEntityKey] = $sourceEntityId;
                $uninitializedCollections[$sourceEntityKey] = $sourceEntityCollection;
                continue;
            }

            foreach ($sourceEntityCollection as $targetEntity) {
                $targetEntityId = $targetIdentifierReflection->getValue($targetEntity);
                $targetEntityKey = (string) $targetEntityId;
                $targetEntities[$targetEntityKey] = $targetEntity;
            }
        }

        foreach (array_chunk($uninitializedSourceEntityIds, $batchSize, preserve_keys: true) as $uninitializedSourceEntityIdsChunk) {
            $manyToManyRows = $this->entityManager->createQueryBuilder()
                ->select("source.{$sourceIdentifierName} AS sourceId", "target.{$targetIdentifierName} AS targetId")
                ->from($sourceClassMetadata->getName(), 'source')
                ->join("source.{$sourcePropertyName}", 'target')
                ->andWhere('source IN (:sourceEntityIds)')
                ->setParameter('sourceEntityIds', array_values($uninitializedSourceEntityIdsChunk))
                ->getQuery()
                ->getResult();

            $uninitializedTargetEntityIds = [];

            foreach ($manyToManyRows as $manyToManyRow) {
                $targetEntityId = $manyToManyRow['targetId'];
                $targetEntityKey = (string) $targetEntityId;
                $targetEntity = $this->entityManager->getUnitOfWork()->tryGetById($targetEntityId, $targetClassMetadata->getName());

                if ($targetEntity !== false && (!$targetEntity instanceof Proxy || $targetEntity->__isInitialized())) {
                    $targetEntities[$targetEntityKey] = $targetEntity;
                    continue;
                }

                $uninitializedTargetEntityIds[$targetEntityKey] = $targetEntityId;
            }

            foreach ($this->loadEntitiesBy($targetClassMetadata, $targetIdentifierName, array_values($uninitializedTargetEntityIds), $maxFetchJoinSameFieldCount) as $targetEntity) {
                $targetEntityKey = (string) $targetIdentifierReflection->getValue($targetEntity);
                $targetEntities[$targetEntityKey] = $targetEntity;
            }

            foreach ($manyToManyRows as $manyToManyRow) {
                $sourceEntityKey = (string) $manyToManyRow['sourceId'];
                $targetEntityKey = (string) $manyToManyRow['targetId'];
                $uninitializedCollections[$sourceEntityKey]->add($targetEntities[$targetEntityKey]);
            }
        }

        foreach ($uninitializedCollections as $sourceEntityCollection) {
            $sourceEntityCollection->setInitialized(true);
            $sourceEntityCollection->takeSnapshot();
        }

        return array_values($targetEntities);
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
        $targetEntities = [];

        if ($sourcePropertyReflection === null) {
            throw new LogicException('Doctrine should use RuntimeReflectionService which never returns null.');
        }

        foreach ($sourceEntities as $sourceEntity) {
            $targetEntity = $sourcePropertyReflection->getValue($sourceEntity);

            if ($targetEntity === null) {
                continue;
            }

            $targetEntities[] = $targetEntity;
        }

        return $this->loadProxies($targetClassMetadata, $targetEntities, $batchSize ?? self::BATCH_SIZE, $maxFetchJoinSameFieldCount);
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
        if (count($fieldValues) === 0) {
            return [];
        }

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
