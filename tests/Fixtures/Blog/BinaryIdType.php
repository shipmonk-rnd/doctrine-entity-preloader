<?php declare(strict_types = 1);

namespace ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use LogicException;

final class BinaryIdType extends Type
{

    public const NAME = 'binary_id';

    public function convertToPHPValue(
        mixed $value,
        AbstractPlatform $platform,
    ): ?BinaryId
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof BinaryId) {
            return $value;
        }

        return BinaryId::fromBytes($value);
    }

    public function convertToDatabaseValue(
        mixed $value,
        AbstractPlatform $platform,
    ): ?string
    {
        if ($value === null) {
            return null;

        } elseif ($value instanceof BinaryId) {
            return $value->getBytes();

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
            'length' => BinaryId::LENGTH,
            'fixed' => true,
        ]);
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getBindingType(): ParameterType
    {
        return ParameterType::BINARY;
    }

}
