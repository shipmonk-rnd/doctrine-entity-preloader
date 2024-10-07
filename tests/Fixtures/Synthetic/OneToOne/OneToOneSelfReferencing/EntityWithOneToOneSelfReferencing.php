<?php declare(strict_types = 1);

namespace ShipMonkTests\DoctrineEntityPreloader\Fixtures\Synthetic\OneToOne\OneToOneSelfReferencing;

use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\OneToOne;
use Doctrine\ORM\Mapping\Table;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Synthetic\TestEntityWithId;

#[Entity]
#[Table(name: 'entity_with_1_to_1_self_referencing')]
class EntityWithOneToOneSelfReferencing extends TestEntityWithId
{

    #[OneToOne(targetEntity: self::class)]
    #[JoinColumn(nullable: true)]
    private ?self $self;

    public function __construct(?self $self)
    {
        $this->self = $self;
    }

}
