<?php declare(strict_types = 1);

namespace ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog\Type;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use LogicException;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog\PrimaryKey;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Compat\CompatibilityType;
use function get_debug_type;
use function is_string;
use function pack;
use function unpack;

final class PrimaryKeyBinaryType extends Type
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
            return new PrimaryKey(unpack('N', $value)[1]); // @phpstan-ignore offsetAccess.nonOffsetAccessible
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
            return pack('N', $value->getData());

        } else {
            throw new LogicException('Unexpected value: ' . $value);
        }
    }

    public function getSQLDeclaration(
        array $column,
        AbstractPlatform $platform,
    ): string
    {
        return $platform->getBinaryTypeDeclarationSQL([
            'length' => PrimaryKey::LENGTH_BYTES,
            'fixed' => true,
        ]);
    }

    public function getName(): string
    {
        return PrimaryKey::DOCTRINE_TYPE_NAME;
    }

    public function doGetBindingType(): ParameterType|int // @phpstan-ignore return.unusedType (old dbal compat)
    {
        return ParameterType::BINARY;
    }

}
