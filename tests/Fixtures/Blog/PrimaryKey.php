<?php declare(strict_types = 1);

namespace ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog;

use function base64_encode;
use function random_int;

class PrimaryKey
{

    public const DOCTRINE_TYPE_NAME = 'primary_key';
    public const LENGTH_BYTES = 4;

    private int $data;

    public function __construct(int $data)
    {
        $this->data = $data;
    }

    public static function new(): self
    {
        $bits = self::LENGTH_BYTES * 8;
        $maxValue = (1 << $bits) - 1;

        return new self(random_int(0, $maxValue));
    }

    public function getData(): int
    {
        return $this->data;
    }

    public function __toString(): string
    {
        return base64_encode((string) $this->data); // intentionally not matching any internal PK representation
    }

}
