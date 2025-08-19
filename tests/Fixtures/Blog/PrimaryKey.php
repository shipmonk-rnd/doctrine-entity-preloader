<?php declare(strict_types = 1);

namespace ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog;

use function base64_encode;

class PrimaryKey
{

    public const DOCTRINE_TYPE_NAME = 'primary_key';

    private static int $autoIncrement = 0;

    private int $data;

    public function __construct(int $data)
    {
        $this->data = $data;
    }

    public static function new(): self
    {
        return new self(self::$autoIncrement++);
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
