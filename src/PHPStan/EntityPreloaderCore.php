<?php declare(strict_types = 1);

namespace ShipMonk\DoctrineEntityPreloader\PHPStan;

use Doctrine\ORM\Mapping\ManyToMany;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Mapping\OneToOne;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Type\Accessory\AccessoryArrayListType;
use PHPStan\Type\ArrayType;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;
use PHPStan\Type\IntegerType;
use PHPStan\Type\IntersectionType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\TypeWithClassName;
use PHPStan\Type\UnionType;
use ReflectionNamedType;
use ReflectionProperty;
use function count;
use function is_string;

abstract class EntityPreloaderCore
{

    public function __construct(
        private ?ObjectMetadataResolver $objectMetadataResolver = null,
    )
    {
    }

    protected function getPreloadedPropertyName(
        MethodCall $methodCall,
        Scope $scope,
    ): ?string
    {
        $args = $methodCall->getArgs();

        if (!isset($args[1])) {
            return null;
        }

        $type = $scope->getType($args[1]->value);
        $constantValues = $type->getConstantScalarValues();

        if (count($constantValues) !== 1) {
            return null;
        }

        if (!is_string($constantValues[0])) {
            return null;
        }

        return $constantValues[0];
    }

    /**
     * @throws EntityPreloaderRuleException
     */
    protected function getPreloadedEntityType(
        MethodCall $methodCall,
        Scope $scope,
        string $propertyName,
    ): Type
    {
        $args = $methodCall->getArgs();

        $sourceEntityListType = $scope->getType($args[0]->value);
        $sourceEntityType = $sourceEntityListType->getIterableValueType();

        return $this->getAssociationTargetType($sourceEntityType, $propertyName);
    }

    /**
     * @throws EntityPreloaderRuleException
     */
    private function getAssociationTargetType(
        Type $type,
        string $propertyName,
    ): Type
    {
        if ($type instanceof UnionType) {
            return $this->getAssociationTargetTypeFromUnion($type, $propertyName);

        } elseif ($type instanceof IntersectionType) { // @phpstan-ignore phpstanApi.instanceofType
            return $this->getAssociationTargetTypeFromIntersection($type, $propertyName);

        } elseif ($type instanceof TypeWithClassName) { // @phpstan-ignore phpstanApi.instanceofType
            return $this->getAssociationTargetTypeFromObjectType($type, $propertyName);

        } else {
            throw EntityPreloaderRuleException::propertyNotFound('object', $propertyName);
        }
    }

    /**
     * @throws EntityPreloaderRuleException
     */
    private function getAssociationTargetTypeFromUnion(
        UnionType $type,
        string $propertyName,
    ): Type
    {
        $propertyTypes = [];

        foreach ($type->getTypes() as $innerType) {
            $propertyTypes[] = $this->getAssociationTargetType($innerType, $propertyName);
        }

        return TypeCombinator::union(...$propertyTypes);
    }

    /**
     * @throws EntityPreloaderRuleException
     */
    private function getAssociationTargetTypeFromIntersection(
        IntersectionType $type,
        string $propertyName,
    ): Type
    {
        $propertyTypes = [];
        $exceptions = [];

        foreach ($type->getTypes() as $innerType) {
            try {
                $propertyTypes[] = $this->getAssociationTargetType($innerType, $propertyName);

            } catch (EntityPreloaderRuleException $e) {
                $exceptions[] = $e;
            }
        }

        if (count($propertyTypes) === 0 && count($exceptions) > 0) {
            throw $exceptions[0];
        }

        return TypeCombinator::intersect(...$propertyTypes);
    }

    /**
     * @throws EntityPreloaderRuleException
     */
    private function getAssociationTargetTypeFromObjectType(
        TypeWithClassName $type,
        string $propertyName,
    ): Type
    {
        $classReflection = $type->getClassReflection();

        if ($classReflection === null) {
            throw EntityPreloaderRuleException::classNotFound($type->getClassName());
        }

        if ($this->objectMetadataResolver !== null) {
            return $this->getAssociationTargetTypeFromMetadata(
                $this->objectMetadataResolver,
                $classReflection->getName(),
                $propertyName,
            );
        }

        for ($currentClassReflection = $classReflection; $currentClassReflection !== null; $currentClassReflection = $currentClassReflection->getParentClass()) {
            if ($currentClassReflection->hasInstanceProperty($propertyName)) {
                $propertyReflection = $currentClassReflection->getNativeProperty($propertyName)->getNativeReflection();
                return $this->getAssociationTargetTypeFromPropertyReflection($classReflection->getName(), $propertyReflection);
            }
        }

        throw EntityPreloaderRuleException::propertyNotFound($classReflection->getName(), $propertyName);
    }

    /**
     * @param class-string $className
     *
     * @throws EntityPreloaderRuleException
     */
    private function getAssociationTargetTypeFromMetadata(
        ObjectMetadataResolver $metadataResolver,
        string $className,
        string $propertyName,
    ): Type
    {
        $classMetadata = $metadataResolver->getClassMetadata($className);

        if ($classMetadata === null) {
            throw EntityPreloaderRuleException::classNotFound($className);
        }

        if (!$classMetadata->hasAssociation($propertyName)) {
            throw $classMetadata->hasField($propertyName)
                ? EntityPreloaderRuleException::invalidAssociations($className, $propertyName)
                : EntityPreloaderRuleException::propertyNotFound($className, $propertyName);
        }

        $associationMapping = $classMetadata->getAssociationMapping($propertyName);
        return new ObjectType($associationMapping['targetEntity']);
    }

    /**
     * @throws EntityPreloaderRuleException
     */
    private function getAssociationTargetTypeFromPropertyReflection(
        string $className,
        ReflectionProperty $propertyReflection,
    ): Type
    {
        $associationAttributes = [
            OneToOne::class,
            OneToMany::class,
            ManyToOne::class,
            ManyToMany::class,
        ];

        foreach ($associationAttributes as $attributeClass) {
            foreach ($propertyReflection->getAttributes($attributeClass) as $attributeReflection) {
                $attribute = $attributeReflection->newInstance();

                if ($attribute->targetEntity !== null) {
                    return new ObjectType($attribute->targetEntity);

                } elseif ($attributeClass === OneToOne::class && $propertyReflection->getType() instanceof ReflectionNamedType) {
                    return new ObjectType($propertyReflection->getType()->getName());
                }
            }
        }

        throw EntityPreloaderRuleException::invalidAssociations($className, $propertyReflection->getName());
    }

    protected function createListType(Type $type): Type
    {
        return TypeCombinator::intersect(
            new ArrayType(new IntegerType(), $type),
            new AccessoryArrayListType(),
        );
    }

}
