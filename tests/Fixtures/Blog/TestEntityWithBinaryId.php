<?php declare(strict_types = 1);

namespace ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\MappedSuperclass;

#[MappedSuperclass]
abstract class TestEntityWithBinaryId
{

    #[Id]
    #[Column(type: BinaryIdType::NAME, nullable: false)]
    private BinaryId $id;

    protected function __construct()
    {
        $this->id = BinaryId::new();
    }

    public function getId(): BinaryId
    {
        return $this->id;
    }

}
