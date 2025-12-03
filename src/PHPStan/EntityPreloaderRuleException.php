<?php declare(strict_types = 1);

namespace ShipMonk\DoctrineEntityPreloader\PHPStan;

use ShipMonk\DoctrineEntityPreloader\Exception\RuntimeException;

final class EntityPreloaderRuleException extends RuntimeException
{

    public static function classNotFound(
        string $class,
    ): self
    {
        return new self("Class '{$class}' not found.");
    }

    public static function propertyNotFound(
        string $class,
        string $property,
    ): self
    {
        return new self("Property '{$class}::\${$property}' not found.");
    }

    public static function invalidAssociations(
        string $class,
        string $property,
    ): self
    {
        return new self("Property '{$class}::\${$property}' is not a valid Doctrine association.");
    }

}
