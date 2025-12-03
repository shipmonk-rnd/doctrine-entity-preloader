<?php declare(strict_types = 1);

namespace ShipMonk\DoctrineEntityPreloader\PHPStan;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Type;
use ShipMonk\DoctrineEntityPreloader\EntityPreloader;

final class EntityPreloaderReturnTypeExtension extends EntityPreloaderCore implements DynamicMethodReturnTypeExtension
{

    public function getClass(): string
    {
        return EntityPreloader::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'preload';
    }

    public function getTypeFromMethodCall(
        MethodReflection $methodReflection,
        MethodCall $methodCall,
        Scope $scope,
    ): ?Type
    {
        $propertyName = $this->getPreloadedPropertyName($methodCall, $scope);

        if ($propertyName === null) {
            return null;
        }

        try {
            $preloadedEntityType = $this->getPreloadedEntityType($methodCall, $scope, $propertyName);
            return $this->createListType($preloadedEntityType);

        } catch (EntityPreloaderRuleException $e) {
            return null;
        }
    }

}
