<?php declare(strict_types = 1);

namespace ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog\Type;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use LogicException;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog\PrimaryKey;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Compat\CompatibilityType;

final class PrimaryKeyStringType extends Type
{

    use CompatibilityType;

    public function convertToPHPValue(
        mixed $value,
        AbstractPlatform $platform,
    ): ?PrimaryKey
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof PrimaryKey) {
            return $value;
        }

        return new PrimaryKey((int) $value);
    }

    public function convertToDatabaseValue(
        mixed $value,
        AbstractPlatform $platform,
    ): ?string
    {
        if ($value === null) {
            return null;

        } elseif ($value instanceof PrimaryKey) {
            return (string) $value->getData();

        } else {
            throw new LogicException('Unexpected value: ' . $value);
        }
    }

    public function getSQLDeclaration(
        array $column,
        AbstractPlatform $platform,
    ): string
    {
        return $platform->getStringTypeDeclarationSQL([]);
    }

    public function getName(): string
    {
        return PrimaryKey::DOCTRINE_TYPE_NAME;
    }

    public function doGetBindingType(): ParameterType|int // @phpstan-ignore return.unusedType (old dbal compat)
    {
        return ParameterType::STRING;
    }

}
