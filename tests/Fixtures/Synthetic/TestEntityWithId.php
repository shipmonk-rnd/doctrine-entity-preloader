<?php declare(strict_types = 1);

namespace ShipMonkTests\DoctrineEntityPreloader\Fixtures\Synthetic;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\MappedSuperclass;

#[MappedSuperclass]
abstract class TestEntityWithId
{

    #[Id]
    #[Column]
    #[GeneratedValue]
    protected int $id;

    /**
     * For performance reasons we can't explicitly throw LogicException when id is null:
     * Doctrine proxies only contain cheap id accessors when the method body is straightforward return;
     * PHP will throw TypeError instead.
     */
    public function getId(): int
    {
        return $this->id;
    }

}
