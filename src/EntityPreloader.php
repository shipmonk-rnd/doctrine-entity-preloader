<?php declare(strict_types = 1);

namespace ShipMonk\DoctrineEntityPreloader;

use ArrayAccess;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\PropertyAccessors\PropertyAccessor;
use Doctrine\ORM\PersistentCollection;
use Doctrine\ORM\QueryBuilder;
use LogicException;
use ReflectionProperty;
use function array_chunk;
use function array_values;
use function count;
use function get_parent_class;
use function is_a;
use function method_exists;

class EntityPreloader
{

    private const PRELOAD_ENTITY_DEFAULT_BATCH_SIZE = 1_000;
    private const PRELOAD_COLLECTION_DEFAULT_BATCH_SIZE = 100;

    public function __construct(
        private EntityManagerInterface $entityManager,
    )
    {
    }

    /**
     * @param list<object> $sourceEntities
     * @param positive-int|null $batchSize
     * @param non-negative-int|null $maxFetchJoinSameFieldCount
     * @return list<object>
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

        /** @var ClassMetadata<object> $targetClassMetadata */
        $targetClassMetadata = $this->entityManager->getClassMetadata($associationMapping['targetEntity']);

        if (isset($associationMapping['indexBy'])) {
            throw new LogicException('Preloading of indexed associations is not supported');
        }

        $maxFetchJoinSameFieldCount ??= 1;
        $sourceEntities = $this->loadProxies($sourceClassMetadata, $sourceEntities, $batchSize ?? self::PRELOAD_ENTITY_DEFAULT_BATCH_SIZE, $maxFetchJoinSameFieldCount);

        $preloader = match ($associationMapping['type']) {
            ClassMetadata::ONE_TO_ONE, ClassMetadata::MANY_TO_ONE => $this->preloadToOne(...),
            ClassMetadata::ONE_TO_MANY, ClassMetadata::MANY_TO_MANY => $this->preloadToMany(...),
            default => throw new LogicException("Unsupported association mapping type {$associationMapping['type']}"),
        };

        return $preloader($sourceEntities, $sourceClassMetadata, $sourcePropertyName, $targetClassMetadata, $batchSize, $maxFetchJoinSameFieldCount);
    }

    /**
     * @param list<object> $entities
     * @return class-string<object>|null
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
                $commonAncestor = get_parent_class($commonAncestor);

                if ($commonAncestor === false) {
                    throw new LogicException('Given entities must have a common ancestor');
                }
            }
        }

        return $commonAncestor;
    }

    /**
     * @param ClassMetadata<E> $classMetadata
     * @param list<E> $entities
     * @param positive-int $batchSize
     * @param non-negative-int $maxFetchJoinSameFieldCount
     * @return list<E>
     *
     * @template E of object
     */
    private function loadProxies(
        ClassMetadata $classMetadata,
        array $entities,
        int $batchSize,
        int $maxFetchJoinSameFieldCount,
    ): array
    {
        $identifierAccessor = $this->getSingleIdPropertyAccessor($classMetadata); // e.g. Order::$id reflection
        $identifierName = $classMetadata->getSingleIdentifierFieldName(); // e.g. 'id'

        if ($identifierAccessor === null) {
            throw new LogicException('Doctrine should use RuntimeReflectionService which never returns null.');
        }

        $uniqueEntities = [];
        $uninitializedIds = [];

        foreach ($entities as $entity) {
            $entityId = $identifierAccessor->getValue($entity);
            $entityKey = (string) $entityId;
            $uniqueEntities[$entityKey] = $entity;

            if ($this->entityManager->isUninitializedObject($entity)) {
                $uninitializedIds[$entityKey] = $entityId;
            }
        }

        foreach (array_chunk($uninitializedIds, $batchSize) as $idsChunk) {
            $this->loadEntitiesBy($classMetadata, $identifierName, $classMetadata, $idsChunk, $maxFetchJoinSameFieldCount);
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
     *
     * @template S of object
     * @template T of object
     */
    private function preloadToMany(
        array $sourceEntities,
        ClassMetadata $sourceClassMetadata,
        string $sourcePropertyName,
        ClassMetadata $targetClassMetadata,
        ?int $batchSize,
        int $maxFetchJoinSameFieldCount,
    ): array
    {
        $sourceIdentifierAccessor = $this->getSingleIdPropertyAccessor($sourceClassMetadata); // e.g. Order::$id reflection
        $sourcePropertyAccessor = $this->getPropertyAccessor($sourceClassMetadata, $sourcePropertyName); // e.g. Order::$items reflection
        $targetIdentifierAccessor = $this->getSingleIdPropertyAccessor($targetClassMetadata);

        if ($sourceIdentifierAccessor === null || $sourcePropertyAccessor === null || $targetIdentifierAccessor === null) {
            throw new LogicException('Doctrine should use RuntimeReflectionService which never returns null.');
        }

        $batchSize ??= self::PRELOAD_COLLECTION_DEFAULT_BATCH_SIZE;
        $targetEntities = [];
        $uninitializedSourceEntityIds = [];
        $uninitializedCollections = [];

        foreach ($sourceEntities as $sourceEntity) {
            $sourceEntityId = $sourceIdentifierAccessor->getValue($sourceEntity);
            $sourceEntityKey = (string) $sourceEntityId;
            $sourceEntityCollection = $sourcePropertyAccessor->getValue($sourceEntity);

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
                $targetEntityKey = (string) $targetIdentifierAccessor->getValue($targetEntity);
                $targetEntities[$targetEntityKey] = $targetEntity;
            }
        }

        $associationMapping = $sourceClassMetadata->getAssociationMapping($sourcePropertyName);

        $innerLoader = match ($associationMapping['type']) {
            ClassMetadata::ONE_TO_MANY => $this->preloadOneToManyInner(...),
            ClassMetadata::MANY_TO_MANY => $this->preloadManyToManyInner(...),
            default => throw new LogicException('Unsupported association mapping type'),
        };

        foreach (array_chunk($uninitializedSourceEntityIds, $batchSize, preserve_keys: true) as $uninitializedSourceEntityIdsChunk) {
            $targetEntitiesChunk = $innerLoader(
                associationMapping: $associationMapping,
                sourceClassMetadata: $sourceClassMetadata,
                sourceIdentifierAccessor: $sourceIdentifierAccessor,
                sourcePropertyName: $sourcePropertyName,
                targetClassMetadata: $targetClassMetadata,
                targetIdentifierAccessor: $targetIdentifierAccessor,
                uninitializedSourceEntityIdsChunk: array_values($uninitializedSourceEntityIdsChunk),
                uninitializedCollections: $uninitializedCollections,
                maxFetchJoinSameFieldCount: $maxFetchJoinSameFieldCount,
            );

            foreach ($targetEntitiesChunk as $targetEntityKey => $targetEntity) {
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
     * @param array<string, mixed>|ArrayAccess<string, mixed> $associationMapping
     * @param ClassMetadata<object> $sourceClassMetadata
     * @param ClassMetadata<T> $targetClassMetadata
     * @param list<mixed> $uninitializedSourceEntityIdsChunk
     * @param array<string, PersistentCollection<int, T>> $uninitializedCollections
     * @param non-negative-int $maxFetchJoinSameFieldCount
     * @return array<string, T>
     *
     * @template T of object
     */
    private function preloadOneToManyInner(
        array|ArrayAccess $associationMapping,
        ClassMetadata $sourceClassMetadata,
        PropertyAccessor|ReflectionProperty $sourceIdentifierAccessor,
        string $sourcePropertyName,
        ClassMetadata $targetClassMetadata,
        PropertyAccessor|ReflectionProperty $targetIdentifierAccessor,
        array $uninitializedSourceEntityIdsChunk,
        array $uninitializedCollections,
        int $maxFetchJoinSameFieldCount,
    ): array
    {
        $targetPropertyName = $sourceClassMetadata->getAssociationMappedByTargetField($sourcePropertyName); // e.g. 'order'
        $targetPropertyAccessor = $this->getPropertyAccessor($targetClassMetadata, $targetPropertyName); // e.g. Item::$order reflection
        $targetEntities = [];

        if ($targetPropertyAccessor === null) {
            throw new LogicException('Doctrine should use RuntimeReflectionService which never returns null.');
        }

        $targetEntitiesList = $this->loadEntitiesBy(
            $targetClassMetadata,
            $targetPropertyName,
            $sourceClassMetadata,
            $uninitializedSourceEntityIdsChunk,
            $maxFetchJoinSameFieldCount,
            $associationMapping['orderBy'] ?? [],
        );

        foreach ($targetEntitiesList as $targetEntity) {
            $sourceEntity = $targetPropertyAccessor->getValue($targetEntity);
            $sourceEntityKey = (string) $sourceIdentifierAccessor->getValue($sourceEntity);
            $uninitializedCollections[$sourceEntityKey]->add($targetEntity);

            $targetEntityKey = (string) $targetIdentifierAccessor->getValue($targetEntity);
            $targetEntities[$targetEntityKey] = $targetEntity;
        }

        return $targetEntities;
    }

    /**
     * @param array<string, mixed>|ArrayAccess<string, mixed> $associationMapping
     * @param ClassMetadata<object> $sourceClassMetadata
     * @param ClassMetadata<T> $targetClassMetadata
     * @param list<mixed> $uninitializedSourceEntityIdsChunk
     * @param array<string, PersistentCollection<int, T>> $uninitializedCollections
     * @param non-negative-int $maxFetchJoinSameFieldCount
     * @return array<string, T>
     *
     * @template T of object
     */
    private function preloadManyToManyInner(
        array|ArrayAccess $associationMapping,
        ClassMetadata $sourceClassMetadata,
        PropertyAccessor|ReflectionProperty $sourceIdentifierAccessor,
        string $sourcePropertyName,
        ClassMetadata $targetClassMetadata,
        PropertyAccessor|ReflectionProperty $targetIdentifierAccessor,
        array $uninitializedSourceEntityIdsChunk,
        array $uninitializedCollections,
        int $maxFetchJoinSameFieldCount,
    ): array
    {
        if (count($associationMapping['orderBy'] ?? []) > 0) {
            throw new LogicException('Many-to-many associations with order by are not supported');
        }

        $sourceIdentifierName = $sourceClassMetadata->getSingleIdentifierFieldName();
        $targetIdentifierName = $targetClassMetadata->getSingleIdentifierFieldName();

        $sourceIdentifierType = $this->getIdentifierFieldType($sourceClassMetadata);

        $manyToManyRows = $this->entityManager->createQueryBuilder()
            ->select("source.{$sourceIdentifierName} AS sourceId", "target.{$targetIdentifierName} AS targetId")
            ->from($sourceClassMetadata->getName(), 'source')
            ->join("source.{$sourcePropertyName}", 'target')
            ->andWhere('source IN (:sourceEntityIds)')
            ->setParameter(
                'sourceEntityIds',
                $this->convertFieldValuesToDatabaseValues($sourceIdentifierType, $uninitializedSourceEntityIdsChunk),
                $this->deduceArrayParameterType($sourceIdentifierType),
            )
            ->getQuery()
            ->getResult();

        $targetEntities = [];
        $uninitializedTargetEntityIds = [];

        foreach ($manyToManyRows as $manyToManyRow) {
            $targetEntityId = $manyToManyRow['targetId'];
            $targetEntityKey = (string) $targetEntityId;

            /** @var T|false $targetEntity */
            $targetEntity = $this->entityManager->getUnitOfWork()->tryGetById($targetEntityId, $targetClassMetadata->getName());

            if ($targetEntity !== false && !$this->entityManager->isUninitializedObject($targetEntity)) {
                $targetEntities[$targetEntityKey] = $targetEntity;
                continue;
            }

            $uninitializedTargetEntityIds[$targetEntityKey] = $targetEntityId;
        }

        foreach ($this->loadEntitiesBy($targetClassMetadata, $targetIdentifierName, $sourceClassMetadata, array_values($uninitializedTargetEntityIds), $maxFetchJoinSameFieldCount) as $targetEntity) {
            $targetEntityKey = (string) $targetIdentifierAccessor->getValue($targetEntity);
            $targetEntities[$targetEntityKey] = $targetEntity;
        }

        foreach ($manyToManyRows as $manyToManyRow) {
            $sourceEntityKey = (string) $manyToManyRow['sourceId'];
            $targetEntityKey = (string) $manyToManyRow['targetId'];
            $uninitializedCollections[$sourceEntityKey]->add($targetEntities[$targetEntityKey]);
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
     *
     * @template S of object
     * @template T of object
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
        $sourcePropertyAccessor = $this->getPropertyAccessor($sourceClassMetadata, $sourcePropertyName); // e.g. Item::$order reflection

        if ($sourcePropertyAccessor === null) {
            throw new LogicException('Doctrine should use RuntimeReflectionService which never returns null.');
        }

        $batchSize ??= self::PRELOAD_ENTITY_DEFAULT_BATCH_SIZE;
        $targetEntities = [];

        foreach ($sourceEntities as $sourceEntity) {
            $targetEntity = $sourcePropertyAccessor->getValue($sourceEntity);

            if ($targetEntity === null) {
                continue;
            }

            $targetEntities[] = $targetEntity;
        }

        return $this->loadProxies($targetClassMetadata, $targetEntities, $batchSize, $maxFetchJoinSameFieldCount);
    }

    /**
     * @param ClassMetadata<E> $targetClassMetadata
     * @param list<mixed> $fieldValues
     * @param ClassMetadata<object> $referencedClassMetadata
     * @param non-negative-int $maxFetchJoinSameFieldCount
     * @param array<string, 'asc'|'desc'> $orderBy
     * @return list<E>
     *
     * @template E of object
     */
    private function loadEntitiesBy(
        ClassMetadata $targetClassMetadata,
        string $fieldName,
        ClassMetadata $referencedClassMetadata,
        array $fieldValues,
        int $maxFetchJoinSameFieldCount,
        array $orderBy = [],
    ): array
    {
        if (count($fieldValues) === 0) {
            return [];
        }

        $referencedType = $this->getIdentifierFieldType($referencedClassMetadata);
        $rootLevelAlias = 'e';

        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select($rootLevelAlias)
            ->from($targetClassMetadata->getName(), $rootLevelAlias)
            ->andWhere("{$rootLevelAlias}.{$fieldName} IN (:fieldValues)")
            ->setParameter(
                'fieldValues',
                $this->convertFieldValuesToDatabaseValues($referencedType, $fieldValues),
                $this->deduceArrayParameterType($referencedType),
            );

        $this->addFetchJoinsToPreventFetchDuringHydration($rootLevelAlias, $queryBuilder, $targetClassMetadata, $maxFetchJoinSameFieldCount);

        foreach ($orderBy as $field => $direction) {
            $queryBuilder->addOrderBy("{$rootLevelAlias}.{$field}", $direction);
        }

        return $queryBuilder->getQuery()->getResult();
    }

    private function deduceArrayParameterType(Type $dbalType): ArrayParameterType|int|null // @phpstan-ignore return.unusedType (old dbal compat)
    {
        return match ($dbalType->getBindingType()) {
            ParameterType::INTEGER => ArrayParameterType::INTEGER,
            ParameterType::STRING => ArrayParameterType::STRING,
            ParameterType::ASCII => ArrayParameterType::ASCII,
            ParameterType::BINARY => ArrayParameterType::BINARY,
            default => null,
        };
    }

    /**
     * @param array<mixed> $fieldValues
     * @return list<mixed>
     */
    private function convertFieldValuesToDatabaseValues(
        Type $dbalType,
        array $fieldValues,
    ): array
    {
        $connection = $this->entityManager->getConnection();
        $platform = $connection->getDatabasePlatform();

        $convertedValues = [];
        foreach ($fieldValues as $value) {
            $convertedValues[] = $dbalType->convertToDatabaseValue($value, $platform);
        }

        return $convertedValues;
    }

    /**
     * @param ClassMetadata<object> $classMetadata
     */
    private function getIdentifierFieldType(ClassMetadata $classMetadata): Type
    {
        $identifierName = $classMetadata->getSingleIdentifierFieldName();
        $sourceIdTypeName = $classMetadata->getTypeOfField($identifierName);

        if ($sourceIdTypeName === null) {
            throw new LogicException("Identifier field '{$identifierName}' for class '{$classMetadata->getName()}' has unknown field type.");
        }

        return Type::getType($sourceIdTypeName);
    }

    /**
     * @param ClassMetadata<object> $sourceClassMetadata
     * @param array<string, array<string, int>> $alreadyPreloadedJoins
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

            /** @var ClassMetadata<object> $targetClassMetadata */
            $targetClassMetadata = $this->entityManager->getClassMetadata($associationMapping['targetEntity']);

            $isToOne = ($associationMapping['type'] & ClassMetadata::TO_ONE) !== 0;
            $isToOneInversed = $isToOne && $associationMapping['isOwningSide'] === false;
            $isToOneAbstract = $isToOne && $associationMapping['isOwningSide'] === true && count($targetClassMetadata->subClasses) > 0;

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

    /**
     * @param ClassMetadata<object> $classMetadata
     */
    private function getSingleIdPropertyAccessor(ClassMetadata $classMetadata): PropertyAccessor|ReflectionProperty|null
    {
        if (method_exists($classMetadata, 'getSingleIdPropertyAccessor')) {
            return $classMetadata->getSingleIdPropertyAccessor();
        }

        return $classMetadata->getSingleIdReflectionProperty();
    }

    /**
     * @param ClassMetadata<object> $classMetadata
     */
    private function getPropertyAccessor(
        ClassMetadata $classMetadata,
        string $property,
    ): PropertyAccessor|ReflectionProperty|null
    {
        if (method_exists($classMetadata, 'getPropertyAccessor')) {
            return $classMetadata->getPropertyAccessor($property);
        }

        return $classMetadata->getReflectionProperty($property);
    }

}
