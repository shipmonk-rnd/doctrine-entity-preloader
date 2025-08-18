<?php declare(strict_types = 1);

namespace ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog\Type;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use LogicException;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog\PrimaryKey;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Compat\CompatibilityType;
use function base64_decode;
use function base64_encode;
use function get_debug_type;
use function is_string;

/**
 * This type exists only to have SOME working version of tests that are failing due to ORM bug https://github.com/doctrine/orm/pull/12130
 * - the key feature here is that convertToDatabaseValue return value equals to PrimaryKey::__toString()
 */
final class PrimaryKeyBase64StringType extends Type
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

        if (is_string($value)) {
            return new PrimaryKey((int) base64_decode($value, true));
        }

        throw new LogicException('Unexpected value: ' . get_debug_type($value));
    }

    public function convertToDatabaseValue(
        mixed $value,
        AbstractPlatform $platform,
    ): ?string
    {
        if ($value === null) {
            return null;

        } elseif ($value instanceof PrimaryKey) {
            return base64_encode((string) $value->getData());

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
