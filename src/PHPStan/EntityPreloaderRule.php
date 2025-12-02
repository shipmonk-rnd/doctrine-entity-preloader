<?php declare(strict_types = 1);

namespace ShipMonk\DoctrineEntityPreloader\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use ShipMonk\DoctrineEntityPreloader\EntityPreloader;

/**
 * @implements Rule<MethodCall>
 */
final class EntityPreloaderRule extends EntityPreloaderCore implements Rule
{

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    /**
     * @param MethodCall $node
     */
    public function processNode(
        Node $node,
        Scope $scope,
    ): array
    {
        if (!$this->isMethodCall($node, $scope, EntityPreloader::class, 'preload')) {
            return [];
        }

        $propertyName = $this->getPreloadedPropertyName($node, $scope);

        if ($propertyName === null) {
            return [
                RuleErrorBuilder::message('Second argument to function EntityPreloader::preload() must be constant string')
                    ->identifier('shipmonk.entityPreloader.nonConstantPropertyName')
                    ->build(),
            ];
        }

        try {
            $this->getPreloadedEntityType($node, $scope, $propertyName);

        } catch (EntityPreloaderRuleException $e) {
            return [
                RuleErrorBuilder::message($e->getMessage())
                    ->identifier('shipmonk.entityPreloader')
                    ->build(),
            ];
        }

        return [];
    }

    private function isMethodCall(
        MethodCall $node,
        Scope $scope,
        string $className,
        string $methodName,
    ): bool
    {
        return $node->name instanceof Identifier
            && $node->name->name === $methodName
            && (new ObjectType($className))->isSuperTypeOf($scope->getType($node->var))->yes();
    }

}
