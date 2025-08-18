<?php declare(strict_types = 1);

namespace ShipMonkTests\DoctrineEntityPreloader\Fixtures\Blog;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\MappedSuperclass;

#[MappedSuperclass]
abstract class TestEntityWithCustomPrimaryKey
{

    #[Id]
    #[Column(type: PrimaryKey::DOCTRINE_TYPE_NAME, nullable: false)]
    private PrimaryKey $id;

    protected function __construct()
    {
        $this->id = PrimaryKey::new();
    }

    public function getId(): PrimaryKey
    {
        return $this->id;
    }

}
