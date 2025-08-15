<?php declare(strict_types = 1);

namespace ShipMonkTests\DoctrineEntityPreloader;

use Doctrine\DBAL\Types\IntegerType;
use PHPUnit\Framework\Attributes\DataProvider;
use ShipMonk\DoctrineEntityPreloader\Exception\LogicException;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Synthetic\AbstractEntityWithNoRelations;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Synthetic\ChildEntityWithNoRelationsA;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Synthetic\EntityWithManyToOneAbstractEntityWithNoRelations;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Synthetic\EntityWithManyToOneEntityWithNoRelations;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Synthetic\EntityWithManyToOneOfManyToOneAbstractEntities;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Synthetic\EntityWithManyToOneOfManyToOneOfManyToOneItselfRelation;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Synthetic\EntityWithNoRelations;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Synthetic\OneToOne\OneToOneBidirectional\EntityWithOneToOneBidirectionalInverseSide;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Synthetic\OneToOne\OneToOneBidirectional\EntityWithOneToOneBidirectionalOwningSide;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Synthetic\OneToOne\OneToOneBidirectionalNullable\EntityWithOneToOneBidirectionalNullableInverseSide;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Synthetic\OneToOne\OneToOneBidirectionalNullable\EntityWithOneToOneBidirectionalNullableOwningSide;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Synthetic\OneToOne\OneToOneSelfReferencing\EntityWithOneToOneSelfReferencing;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Synthetic\OneToOne\OneToOneSelfReferencing\EntityWithOneToOneSelfReferencingBidirectional;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Synthetic\OneToOne\OneToOneSelfReferencing\EntityWithOneToOneSelfReferencingBidirectionalPointer;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Synthetic\OneToOne\OneToOneUnidirectional\EntityWithOneToOneUnidirectional;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Synthetic\OneToOne\OneToOneUnidirectional\EntityWithOneToOneUnidirectionalTarget;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Synthetic\OneToOne\OneToOneUnidirectionalAbstract\EntityWithOneToOneUnidirectionalAbstract;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Synthetic\OneToOne\OneToOneUnidirectionalAbstract\EntityWithOneToOneUnidirectionalAbstractPointer;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Synthetic\OneToOne\OneToOneUnidirectionalAbstract\EntityWithOneToOneUnidirectionalAbstractTargetChildA;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Synthetic\SingleTableInheritance\ConcreteStiEntityWithOptionalManyToOneOfItselfRelation;
use ShipMonkTests\DoctrineEntityPreloader\Fixtures\Synthetic\SingleTableInheritance\EntityWithManyToOneOfManyToOneItselfStiRelation;
use ShipMonkTests\DoctrineEntityPreloader\Lib\TestCase;
use function array_fill;
use function implode;
use function intdiv;

class EntityPreloadSyntheticTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();

        $this->initializeEntityManager(new IntegerType(), $this->getQueryLogger());
    }

    public function testManyToOne(): void
    {
        $entityWithNoRelations = $this->givenEntityWithNoRelations();
        $entityWithManyToOneEntityWithNoRelations = $this->givenEntityWithManyToOneEntityWithNoRelations($entityWithNoRelations);

        $this->getEntityManager()->flush();
        $this->getEntityManager()->clear();
        $this->clearSqlCaptureMiddleware();

        $freshEntityWithManyToOneEntityWithNoRelations = $this->refreshExistingEntity($entityWithManyToOneEntityWithNoRelations);

        $this->thenExpectedQueriesArePerformed([
            implode(' ', [
                'SELECT t0.id AS id_1, t0.entity_with_no_relations_id AS entity_with_no_relations_id_2',
                'FROM entity_with_many_to_one_entity_with_no_relations t0',
                'WHERE t0.id = ?',
            ]),
        ]);

        $this->clearSqlCaptureMiddleware();
        $this->whenPreloadIsCalled([$freshEntityWithManyToOneEntityWithNoRelations], 'entityWithNoRelations');

        $this->thenExpectedQueriesArePerformed([
            implode(' ', [
                'SELECT e0_.id AS id_0',
                'FROM entity_with_no_relations e0_',
                'WHERE e0_.id IN (?)',
            ]),
        ]);
    }

    public function testManyToOneAbstractEntity(): void
    {
        $childEntityWithNoRelationsA = $this->givenChildEntityWithNoRelationsA();
        $entityWithManyToOneAbstractEntityWithNoRelations = $this->givenEntityWithManyToOneAbstractEntityWithNoRelations($childEntityWithNoRelationsA);

        $this->getEntityManager()->flush();
        $this->getEntityManager()->clear();
        $this->clearSqlCaptureMiddleware();

        $freshEntityWithManyToOneAbstractEntityWithNoRelations = $this->refreshExistingEntity($entityWithManyToOneAbstractEntityWithNoRelations);

        $this->thenExpectedQueriesArePerformed([
            implode(' ', [
                'SELECT t0.id AS id_1, t0.abstract_entity_with_no_relations_id AS abstract_entity_with_no_relations_id_2',
                'FROM entity_with_many_to_one_abstract_entity_with_no_relations t0',
                'WHERE t0.id = ?',
            ]),
            implode(' ', [
                'SELECT t0.id AS id_1, t0.dtype',
                'FROM abstract_entity_with_no_relations t0',
                "WHERE t0.id = ? AND t0.dtype IN ('a', 'b')",
            ]),
        ]);

        $this->clearSqlCaptureMiddleware();

        $this->whenPreloadIsCalled([$freshEntityWithManyToOneAbstractEntityWithNoRelations], 'abstractEntityWithNoRelations');

        $this->thenExpectedQueriesArePerformed([]);
    }

    public function testManyToOneOfManyToOneAbstractEntity(): void
    {
        $childEntityWithNoRelationsA = $this->givenChildEntityWithNoRelationsA();
        $entityWithManyToOneAbstractEntityWithNoRelations = $this->givenEntityWithManyToOneAbstractEntityWithNoRelations($childEntityWithNoRelationsA);
        $entityWithManyToOneOfManyToOneAbstractEntities = $this->givenEntityWithManyToOneOfManyToOneAbstractEntities($entityWithManyToOneAbstractEntityWithNoRelations);

        $this->getEntityManager()->flush();
        $this->getEntityManager()->clear();
        $this->clearSqlCaptureMiddleware();

        $freshEntityWithManyToOneOfManyToOneAbstractEntities = $this->refreshExistingEntity($entityWithManyToOneOfManyToOneAbstractEntities);

        $this->thenExpectedQueriesArePerformed([
            implode(' ', [
                'SELECT t0.id AS id_1, t0.entity_with_many_to_one_abstract_entity_with_no_relations_id AS entity_with_many_to_one_abstract_entity_with_no_relations_id_2',
                'FROM entity_with_many_to_one_of_many_to_one_abstract_entities t0',
                'WHERE t0.id = ?',
            ]),
        ]);

        $this->clearSqlCaptureMiddleware();
        $this->whenPreloadIsCalled([$freshEntityWithManyToOneOfManyToOneAbstractEntities], 'entityWithManyToOneAbstractEntityWithNoRelations');

        $this->thenExpectedQueriesArePerformed([
            implode(' ', [
                'SELECT e0_.id AS id_0, a1_.id AS id_1, e0_.abstract_entity_with_no_relations_id AS abstract_entity_with_no_relations_id_2, a1_.dtype AS dtype_3',
                'FROM entity_with_many_to_one_abstract_entity_with_no_relations e0_',
                "LEFT JOIN abstract_entity_with_no_relations a1_ ON e0_.abstract_entity_with_no_relations_id = a1_.id AND a1_.dtype IN ('a', 'b')",
                'WHERE e0_.id IN (?)',
            ]),
        ]);
    }

    #[DataProvider('entityWithManyToOneOfAbstractEntityWithMultipleLevelsOfRelationToItselfDataProvider')]
    public function testManyToOneOfAbstractEntityWithMultipleLevelsOfRelationToItself(
        int $levelsOfRelationToItself,
        int $expectedNumberOfQueriesDuringRefreshForEntitiesWithRelationToItself,
    ): void
    {
        $currentEntityWithRelationToItself = $this->givenConcreteEntityWithOptionalManyToOneItselfRelation(null);

        for ($i = 0; $i < $levelsOfRelationToItself; $i++) {
            $concreteEntityWithManyToOneOfItselfRelation = $this->givenConcreteEntityWithOptionalManyToOneItselfRelation($currentEntityWithRelationToItself);
            $currentEntityWithRelationToItself = $concreteEntityWithManyToOneOfItselfRelation;
        }

        $entityWithManyToOneOfManyToOneItselfRelation = $this->givenWithManyToOneOfManyToOneItselfRelation($currentEntityWithRelationToItself);

        $this->getEntityManager()->flush();
        $this->getEntityManager()->clear();
        $this->clearSqlCaptureMiddleware();

        $freshEntityWithManyToOneOfManyToOneAbstractEntities = $this->refreshExistingEntity($entityWithManyToOneOfManyToOneItselfRelation);

        $expectedQueries = [
            implode(' ', [
                'SELECT t0.id AS id_1, t0.abstract_entity_with_optional_many_to_one_itself_relation_id AS abstract_entity_with_optional_many_to_one_itself_relation_id_2',
                'FROM entity_with_many_to_one_of_many_to_one_itself_sti_relation t0',
                'WHERE t0.id = ?',
            ]),
        ];

        $concreteEntityWithRelationToItselfQuery = implode(' ', [
            'SELECT t0.id AS id_1, t0.parent_id AS parent_id_2, t0.dtype',
            'FROM abstract_sti_entity_with_optional_many_to_one_itself_relation t0',
            "WHERE t0.id = ? AND t0.dtype IN ('a')",
        ]);

        for ($j = 0; $j < $expectedNumberOfQueriesDuringRefreshForEntitiesWithRelationToItself; $j++) {
            $expectedQueries[] = $concreteEntityWithRelationToItselfQuery;
        }

        $this->thenExpectedQueriesArePerformed($expectedQueries);

        $this->clearSqlCaptureMiddleware();
        $this->whenPreloadIsCalled([$freshEntityWithManyToOneOfManyToOneAbstractEntities], 'abstractEntityWithOptionalManyToOneItselfRelation');

        $this->thenExpectedQueriesArePerformed([]);
    }

    /**
     * @return list<mixed>
     */
    public static function entityWithManyToOneOfAbstractEntityWithMultipleLevelsOfRelationToItselfDataProvider(): array
    {
        return [
            [
                'levelsOfRelationToItself' => 0,
                'expectedNumberOfQueriesDuringRefreshForEntitiesWithRelationToItself' => 1,
            ],
            [
                'levelsOfRelationToItself' => 1,
                'expectedNumberOfQueriesDuringRefreshForEntitiesWithRelationToItself' => 2,
            ],
            [
                'levelsOfRelationToItself' => 2,
                'expectedNumberOfQueriesDuringRefreshForEntitiesWithRelationToItself' => 3,
            ],
            [
                'levelsOfRelationToItself' => 3,
                'expectedNumberOfQueriesDuringRefreshForEntitiesWithRelationToItself' => 4,
            ],
        ];
    }

    /**
     * @param non-negative-int $maxFetchJoinSameFieldCount
     */
    #[DataProvider('entityWithManyToOneOfManyToOneOfAbstractEntityWithMultipleLevelsOfRelationToItselfDataProvider')]
    public function testManyToOneOfManyToOneOfAbstractEntityWithMultipleLevelsOfRelationToItself(
        int $levelsOfRelationToItself,
        int $maxFetchJoinSameFieldCount,
        int $expectedNumberOfQueriesForEntitiesWithRelationToItself,
    ): void
    {
        $currentEntityWithRelationToItself = $this->givenConcreteEntityWithOptionalManyToOneItselfRelation(null);

        for ($i = 0; $i < $levelsOfRelationToItself; $i++) {
            $concreteEntityWithManyToOneOfItselfRelation = $this->givenConcreteEntityWithOptionalManyToOneItselfRelation($currentEntityWithRelationToItself);
            $currentEntityWithRelationToItself = $concreteEntityWithManyToOneOfItselfRelation;
        }

        $entityWithManyToOneOfManyToOneItselfRelation = $this->givenWithManyToOneOfManyToOneItselfRelation($currentEntityWithRelationToItself);

        $entityWithManyToOneOfManyToOneOfManyToOneItselfRelation = $this->givenWithManyToOneOfManyToOneOfManyToOneItselfRelation($entityWithManyToOneOfManyToOneItselfRelation);

        $this->getEntityManager()->flush();
        $this->getEntityManager()->clear();
        $this->clearSqlCaptureMiddleware();

        $freshEntityWithManyToOneOfManyToOneOfManyToOneItselfRelation = $this->refreshExistingEntity($entityWithManyToOneOfManyToOneOfManyToOneItselfRelation);

        $this->thenExpectedQueriesArePerformed([
            implode(' ', [
                'SELECT t0.id AS id_1, t0.entity_with_many_to_one_of_many_to_one_itself_relation_id AS entity_with_many_to_one_of_many_to_one_itself_relation_id_2',
                'FROM entity_with_n_to_1_of_n_to_1_itself_relation t0',
                'WHERE t0.id = ?',
            ]),
        ]);

        $this->clearSqlCaptureMiddleware();
        $this->whenPreloadIsCalled([$freshEntityWithManyToOneOfManyToOneOfManyToOneItselfRelation], 'entityWithManyToOneOfManyToOneItselfRelation', $maxFetchJoinSameFieldCount);

        $expectedQueries = [];

        if ($maxFetchJoinSameFieldCount === 0) {
            $expectedQueries[] = implode(' ', [
                'SELECT e0_.id AS id_0, e0_.abstract_entity_with_optional_many_to_one_itself_relation_id AS abstract_entity_with_optional_many_to_one_itself_relation_id_1',
                'FROM entity_with_many_to_one_of_many_to_one_itself_sti_relation e0_',
                'WHERE e0_.id IN (?)',
            ]);

        } elseif ($maxFetchJoinSameFieldCount === 1) {
            $expectedQueries[] = implode(' ', [
                'SELECT e0_.id AS id_0, a1_.id AS id_1, a2_.id AS id_2, e0_.abstract_entity_with_optional_many_to_one_itself_relation_id AS abstract_entity_with_optional_many_to_one_itself_relation_id_3, a1_.dtype AS dtype_4, a1_.parent_id AS parent_id_5, a2_.dtype AS dtype_6, a2_.parent_id AS parent_id_7',
                'FROM entity_with_many_to_one_of_many_to_one_itself_sti_relation e0_',
                "LEFT JOIN abstract_sti_entity_with_optional_many_to_one_itself_relation a1_ ON e0_.abstract_entity_with_optional_many_to_one_itself_relation_id = a1_.id AND a1_.dtype IN ('a')",
                "LEFT JOIN abstract_sti_entity_with_optional_many_to_one_itself_relation a2_ ON a1_.parent_id = a2_.id AND a2_.dtype IN ('a')",
                'WHERE e0_.id IN (?)',
            ]);

        } elseif ($maxFetchJoinSameFieldCount === 2) {
            $expectedQueries[] = implode(' ', [
                'SELECT e0_.id AS id_0, a1_.id AS id_1, a2_.id AS id_2, a3_.id AS id_3, e0_.abstract_entity_with_optional_many_to_one_itself_relation_id AS abstract_entity_with_optional_many_to_one_itself_relation_id_4, a1_.dtype AS dtype_5, a1_.parent_id AS parent_id_6, a2_.dtype AS dtype_7, a2_.parent_id AS parent_id_8, a3_.dtype AS dtype_9, a3_.parent_id AS parent_id_10',
                'FROM entity_with_many_to_one_of_many_to_one_itself_sti_relation e0_',
                "LEFT JOIN abstract_sti_entity_with_optional_many_to_one_itself_relation a1_ ON e0_.abstract_entity_with_optional_many_to_one_itself_relation_id = a1_.id AND a1_.dtype IN ('a')",
                "LEFT JOIN abstract_sti_entity_with_optional_many_to_one_itself_relation a2_ ON a1_.parent_id = a2_.id AND a2_.dtype IN ('a')",
                "LEFT JOIN abstract_sti_entity_with_optional_many_to_one_itself_relation a3_ ON a2_.parent_id = a3_.id AND a3_.dtype IN ('a')",
                'WHERE e0_.id IN (?)',
            ]);

        } elseif ($maxFetchJoinSameFieldCount === 3) {
            $expectedQueries[] = implode(' ', [
                'SELECT e0_.id AS id_0, a1_.id AS id_1, a2_.id AS id_2, a3_.id AS id_3, a4_.id AS id_4, e0_.abstract_entity_with_optional_many_to_one_itself_relation_id AS abstract_entity_with_optional_many_to_one_itself_relation_id_5, a1_.dtype AS dtype_6, a1_.parent_id AS parent_id_7, a2_.dtype AS dtype_8, a2_.parent_id AS parent_id_9, a3_.dtype AS dtype_10, a3_.parent_id AS parent_id_11, a4_.dtype AS dtype_12, a4_.parent_id AS parent_id_13',
                'FROM entity_with_many_to_one_of_many_to_one_itself_sti_relation e0_',
                "LEFT JOIN abstract_sti_entity_with_optional_many_to_one_itself_relation a1_ ON e0_.abstract_entity_with_optional_many_to_one_itself_relation_id = a1_.id AND a1_.dtype IN ('a')",
                "LEFT JOIN abstract_sti_entity_with_optional_many_to_one_itself_relation a2_ ON a1_.parent_id = a2_.id AND a2_.dtype IN ('a')",
                "LEFT JOIN abstract_sti_entity_with_optional_many_to_one_itself_relation a3_ ON a2_.parent_id = a3_.id AND a3_.dtype IN ('a')",
                "LEFT JOIN abstract_sti_entity_with_optional_many_to_one_itself_relation a4_ ON a3_.parent_id = a4_.id AND a4_.dtype IN ('a')",
                'WHERE e0_.id IN (?)',
            ]);

        } else {
            throw new LogicException('Unexpected maxFetchJoinSameFieldCount');
        }

        $concreteEntityWithRelationToItselfQuery = implode(' ', [
            'SELECT t0.id AS id_1, t0.parent_id AS parent_id_2, t0.dtype',
            'FROM abstract_sti_entity_with_optional_many_to_one_itself_relation t0',
            "WHERE t0.id = ? AND t0.dtype IN ('a')",
        ]);

        for ($j = 0; $j < $expectedNumberOfQueriesForEntitiesWithRelationToItself; $j++) {
            $expectedQueries[] = $concreteEntityWithRelationToItselfQuery;
        }

        $this->thenExpectedQueriesArePerformed($expectedQueries);
    }

    /**
     * @return list<mixed>
     */
    public static function entityWithManyToOneOfManyToOneOfAbstractEntityWithMultipleLevelsOfRelationToItselfDataProvider(): array
    {
        return [
            // depth 0
            [
                'levelsOfRelationToItself' => 0,
                'maxFetchJoinSameFieldCount' => 0,
                'expectedNumberOfQueriesForEntitiesWithRelationToItself' => 1,
            ],
            [
                'levelsOfRelationToItself' => 1,
                'maxFetchJoinSameFieldCount' => 0,
                'expectedNumberOfQueriesForEntitiesWithRelationToItself' => 2,
            ],
            [
                'levelsOfRelationToItself' => 2,
                'maxFetchJoinSameFieldCount' => 0,
                'expectedNumberOfQueriesForEntitiesWithRelationToItself' => 3,
            ],
            [
                'levelsOfRelationToItself' => 3,
                'maxFetchJoinSameFieldCount' => 0,
                'expectedNumberOfQueriesForEntitiesWithRelationToItself' => 4,
            ],

            // depth 1
            [
                'levelsOfRelationToItself' => 0,
                'maxFetchJoinSameFieldCount' => 1,
                'expectedNumberOfQueriesForEntitiesWithRelationToItself' => 0,
            ],
            [
                'levelsOfRelationToItself' => 1,
                'maxFetchJoinSameFieldCount' => 1,
                'expectedNumberOfQueriesForEntitiesWithRelationToItself' => 0,
            ],
            [
                'levelsOfRelationToItself' => 2,
                'maxFetchJoinSameFieldCount' => 1,
                'expectedNumberOfQueriesForEntitiesWithRelationToItself' => 1,
            ],
            [
                'levelsOfRelationToItself' => 3,
                'maxFetchJoinSameFieldCount' => 1,
                'expectedNumberOfQueriesForEntitiesWithRelationToItself' => 2,
            ],

            // depth 2
            [
                'levelsOfRelationToItself' => 0,
                'maxFetchJoinSameFieldCount' => 2,
                'expectedNumberOfQueriesForEntitiesWithRelationToItself' => 0,
            ],
            [
                'levelsOfRelationToItself' => 1,
                'maxFetchJoinSameFieldCount' => 2,
                'expectedNumberOfQueriesForEntitiesWithRelationToItself' => 0,
            ],
            [
                'levelsOfRelationToItself' => 2,
                'maxFetchJoinSameFieldCount' => 2,
                'expectedNumberOfQueriesForEntitiesWithRelationToItself' => 0,
            ],
            [
                'levelsOfRelationToItself' => 3,
                'maxFetchJoinSameFieldCount' => 2,
                'expectedNumberOfQueriesForEntitiesWithRelationToItself' => 1,
            ],

            // depth 3
            [
                'levelsOfRelationToItself' => 3,
                'maxFetchJoinSameFieldCount' => 3,
                'expectedNumberOfQueriesForEntitiesWithRelationToItself' => 0,
            ],
        ];
    }

    public function testOneToOneUnidirectional(): void
    {
        $inverseSide = new EntityWithOneToOneUnidirectionalTarget();
        $owningSide = new EntityWithOneToOneUnidirectional($inverseSide);

        $this->getEntityManager()->persist($inverseSide);
        $this->getEntityManager()->persist($owningSide);
        $this->getEntityManager()->flush();
        $this->getEntityManager()->clear();
        $this->clearSqlCaptureMiddleware();

        $owningSide = $this->refreshExistingEntity($owningSide);

        $this->thenExpectedQueriesArePerformed([
            implode(' ', [
                'SELECT t0.id AS id_1, t0.target_id AS target_id_2',
                'FROM entity_with_1_to_1_unidirectional t0',
                'WHERE t0.id = ?',
            ]),
        ]);

        $this->clearSqlCaptureMiddleware();
        $this->whenPreloadIsCalled([$owningSide], 'target');

        $this->thenExpectedQueriesArePerformed([
            implode(' ', [
                'SELECT e0_.id AS id_0',
                'FROM entity_with_1_to_1_unidirectional_target e0_',
                'WHERE e0_.id IN (?)',
            ]),
        ]);
    }

    public function testOneToOneUnidirectionalWithAlreadyLoadedEntity(): void
    {
        $inverseSide = new EntityWithOneToOneUnidirectionalTarget();
        $owningSide = new EntityWithOneToOneUnidirectional($inverseSide);

        $this->getEntityManager()->persist($inverseSide);
        $this->getEntityManager()->persist($owningSide);
        $this->getEntityManager()->flush();
        $this->getEntityManager()->clear();
        $this->clearSqlCaptureMiddleware();

        $this->refreshExistingEntity($inverseSide);
        $owningSide = $this->refreshExistingEntity($owningSide);

        $this->thenExpectedQueriesArePerformed([
            implode(' ', [
                'SELECT t0.id AS id_1',
                'FROM entity_with_1_to_1_unidirectional_target t0',
                'WHERE t0.id = ?',
            ]),
            implode(' ', [
                'SELECT t0.id AS id_1, t0.target_id AS target_id_2',
                'FROM entity_with_1_to_1_unidirectional t0',
                'WHERE t0.id = ?',
            ]),
        ]);

        $this->clearSqlCaptureMiddleware();
        $this->whenPreloadIsCalled([$owningSide], 'target');

        $this->thenExpectedQueriesArePerformed([]);
    }

    public function testOneToOneUnidirectionalAbstract(): void
    {
        $inverseSide = new EntityWithOneToOneUnidirectionalAbstractTargetChildA();
        $owningSide = new EntityWithOneToOneUnidirectionalAbstract($inverseSide);

        $this->getEntityManager()->persist($inverseSide);
        $this->getEntityManager()->persist($owningSide);
        $this->getEntityManager()->flush();
        $this->getEntityManager()->clear();
        $this->clearSqlCaptureMiddleware();

        $owningSide = $this->refreshExistingEntity($owningSide);

        $this->thenExpectedQueriesArePerformed([
            implode(' ', [
                'SELECT t0.id AS id_1, t0.target_id AS target_id_2',
                'FROM entity_with_1_to_1_unidirectional_abstract t0',
                'WHERE t0.id = ?',
            ]),
            implode(' ', [
                'SELECT t0.id AS id_1, t0.dtype',
                'FROM entity_with_1_to_1_unidirectional_abstract_target t0',
                "WHERE t0.id = ? AND t0.dtype IN ('a', 'b')",
            ]),
        ]);

        $this->clearSqlCaptureMiddleware();
        $this->whenPreloadIsCalled([$owningSide], 'target');

        $this->thenExpectedQueriesArePerformed([]);
    }

    public function testOneToOneUnidirectionalAbstractPointer(): void
    {
        $inverseSide = new EntityWithOneToOneUnidirectionalAbstractTargetChildA();
        $owningSide = new EntityWithOneToOneUnidirectionalAbstract($inverseSide);
        $pointer = new EntityWithOneToOneUnidirectionalAbstractPointer($owningSide);

        $this->getEntityManager()->persist($inverseSide);
        $this->getEntityManager()->persist($owningSide);
        $this->getEntityManager()->persist($pointer);
        $this->getEntityManager()->flush();
        $this->getEntityManager()->clear();
        $this->clearSqlCaptureMiddleware();

        $pointer = $this->refreshExistingEntity($pointer);

        $this->thenExpectedQueriesArePerformed([
            implode(' ', [
                'SELECT t0.id AS id_1, t0.target_id AS target_id_2',
                'FROM entity_with_1_to_1_unidirectional_abstract_pointer t0',
                'WHERE t0.id = ?',
            ]),
        ]);

        $this->clearSqlCaptureMiddleware();
        $this->whenPreloadIsCalled([$pointer], 'target');

        $this->thenExpectedQueriesArePerformed([
            implode(' ', [
                'SELECT e0_.id AS id_0, e1_.id AS id_1, e0_.target_id AS target_id_2, e1_.dtype AS dtype_3',
                'FROM entity_with_1_to_1_unidirectional_abstract e0_',
                "LEFT JOIN entity_with_1_to_1_unidirectional_abstract_target e1_ ON e0_.target_id = e1_.id AND e1_.dtype IN ('a', 'b')",
                'WHERE e0_.id IN (?)',
            ]),
        ]);
    }

    public function testOneToOneBidirectionalOwningSide(): void
    {
        $inverseSide = new EntityWithOneToOneBidirectionalInverseSide();
        $owningSide = new EntityWithOneToOneBidirectionalOwningSide($inverseSide);

        $this->getEntityManager()->persist($inverseSide);
        $this->getEntityManager()->persist($owningSide);
        $this->getEntityManager()->flush();
        $this->getEntityManager()->clear();
        $this->clearSqlCaptureMiddleware();

        $owningSide = $this->refreshExistingEntity($owningSide);

        $this->thenExpectedQueriesArePerformed([
            implode(' ', [
                'SELECT t0.id AS id_1, t0.target_id AS target_id_2',
                'FROM entity_with_1_to_1_bidirectional t0',
                'WHERE t0.id = ?',
            ]),
        ]);

        $this->clearSqlCaptureMiddleware();
        $this->whenPreloadIsCalled([$owningSide], 'target');

        $this->thenExpectedQueriesArePerformed([
            implode(' ', [
                'SELECT e0_.id AS id_0, e1_.id AS id_1, e1_.target_id AS target_id_2',
                'FROM entity_with_1_to_1_bidirectional_inverse e0_',
                'LEFT JOIN entity_with_1_to_1_bidirectional e1_ ON e0_.id = e1_.target_id',
                'WHERE e0_.id IN (?)',
            ]),
        ]);
    }

    public function testOneToOneBidirectionalInverseSide(): void
    {
        $inverseSide = new EntityWithOneToOneBidirectionalInverseSide();
        $owningSide = new EntityWithOneToOneBidirectionalOwningSide($inverseSide);

        $this->getEntityManager()->persist($inverseSide);
        $this->getEntityManager()->persist($owningSide);
        $this->getEntityManager()->flush();
        $this->getEntityManager()->clear();
        $this->clearSqlCaptureMiddleware();

        $inverseSide = $this->refreshExistingEntity($inverseSide);

        $this->thenExpectedQueriesArePerformed([
            implode(' ', [
                'SELECT t0.id AS id_1, t2.id AS id_3, t2.target_id AS target_id_4',
                'FROM entity_with_1_to_1_bidirectional_inverse t0',
                'LEFT JOIN entity_with_1_to_1_bidirectional t2 ON t2.target_id = t0.id',
                'WHERE t0.id = ?',
            ]),
        ]);

        $this->clearSqlCaptureMiddleware();
        $this->whenPreloadIsCalled([$inverseSide], 'back');

        $this->thenExpectedQueriesArePerformed([]);
    }

    public function testOneToOneBidirectionalNullableOwningSide(): void
    {
        $inverseSide = new EntityWithOneToOneBidirectionalNullableInverseSide();
        $owningSide = new EntityWithOneToOneBidirectionalNullableOwningSide($inverseSide);

        $this->getEntityManager()->persist($inverseSide);
        $this->getEntityManager()->persist($owningSide);
        $this->getEntityManager()->flush();
        $this->getEntityManager()->clear();
        $this->clearSqlCaptureMiddleware();

        $owningSide = $this->refreshExistingEntity($owningSide);

        $this->thenExpectedQueriesArePerformed([
            implode(' ', [
                'SELECT t0.id AS id_1, t0.target_id AS target_id_2',
                'FROM entity_with_1_to_1_bidirectional_nullable t0',
                'WHERE t0.id = ?',
            ]),
        ]);

        $this->clearSqlCaptureMiddleware();
        $this->whenPreloadIsCalled([$owningSide], 'target');

        $this->thenExpectedQueriesArePerformed([
            implode(' ', [
                'SELECT e0_.id AS id_0, e1_.id AS id_1, e1_.target_id AS target_id_2',
                'FROM entity_with_1_to_1_bidirectional_nullable_inverse e0_',
                'LEFT JOIN entity_with_1_to_1_bidirectional_nullable e1_ ON e0_.id = e1_.target_id',
                'WHERE e0_.id IN (?)',
            ]),
        ]);
    }

    public function testOneToOneBidirectionalNullableOwningSideWithNull(): void
    {
        $owningSide = new EntityWithOneToOneBidirectionalNullableOwningSide(null);

        $this->getEntityManager()->persist($owningSide);
        $this->getEntityManager()->flush();
        $this->getEntityManager()->clear();
        $this->clearSqlCaptureMiddleware();

        $owningSide = $this->refreshExistingEntity($owningSide);

        $this->thenExpectedQueriesArePerformed([
            implode(' ', [
                'SELECT t0.id AS id_1, t0.target_id AS target_id_2',
                'FROM entity_with_1_to_1_bidirectional_nullable t0',
                'WHERE t0.id = ?',
            ]),
        ]);

        $this->clearSqlCaptureMiddleware();
        $this->whenPreloadIsCalled([$owningSide], 'target');

        $this->thenExpectedQueriesArePerformed([]);
    }

    #[DataProvider('provideOneToOneSelfReferencingData')]
    public function testOneToOneSelfReferencing(int $depth): void
    {
        $entity = new EntityWithOneToOneSelfReferencing(null);
        $this->getEntityManager()->persist($entity);

        for ($i = 0; $i < $depth; $i++) {
            $entity = new EntityWithOneToOneSelfReferencing($entity);
            $this->getEntityManager()->persist($entity);
        }

        $this->getEntityManager()->flush();
        $this->getEntityManager()->clear();
        $this->clearSqlCaptureMiddleware();

        $entity = $this->refreshExistingEntity($entity);

        $this->thenExpectedQueriesArePerformed([
            implode(' ', [
                'SELECT t0.id AS id_1, t0.self_id AS self_id_2',
                'FROM entity_with_1_to_1_self_referencing t0',
                'WHERE t0.id = ?',
            ]),
        ]);

        $this->clearSqlCaptureMiddleware();
        $this->whenPreloadIsCalled([$entity], 'self');

        if ($depth === 0) {
            $this->thenExpectedQueriesArePerformed([]);

        } else {
            $this->thenExpectedQueriesArePerformed([
                implode(' ', [
                    'SELECT e0_.id AS id_0, e0_.self_id AS self_id_1',
                    'FROM entity_with_1_to_1_self_referencing e0_',
                    'WHERE e0_.id IN (?)',
                ]),
            ]);
        }
    }

    /**
     * @return iterable<array{int}>
     */
    public static function provideOneToOneSelfReferencingData(): iterable
    {
        yield [0];
        yield [1];
        yield [3];
    }

    #[DataProvider('provideOneToOneSelfReferencingBidirectionalData')]
    public function testOneToOneSelfReferencingBidirectional(int $depth): void
    {
        $entity = new EntityWithOneToOneSelfReferencingBidirectional(null);
        $this->getEntityManager()->persist($entity);

        for ($i = 0; $i < $depth; $i++) {
            $entity = new EntityWithOneToOneSelfReferencingBidirectional($entity);
            $this->getEntityManager()->persist($entity);
        }

        $this->getEntityManager()->flush();
        $this->getEntityManager()->clear();
        $this->clearSqlCaptureMiddleware();

        $entity = $this->refreshExistingEntity($entity);

        $this->thenExpectedQueriesArePerformed([
            implode(' ', [
                'SELECT t0.id AS id_1, t0.self_id AS self_id_2, t3.id AS id_4, t3.self_id AS self_id_5',
                'FROM entity_with_1_to_1_self_referencing_bidirectional t0',
                'LEFT JOIN entity_with_1_to_1_self_referencing_bidirectional t3 ON t3.self_id = t0.id',
                'WHERE t0.id = ?',
            ]),
        ]);

        $this->clearSqlCaptureMiddleware();
        $this->whenPreloadIsCalled([$entity], 'self');

        if ($depth === 0) {
            $this->thenExpectedQueriesArePerformed([]);

        } else {
            $this->thenExpectedQueriesArePerformed([
                implode(' ', [
                    'SELECT e0_.id AS id_0, e1_.id AS id_1, e0_.self_id AS self_id_2, e1_.self_id AS self_id_3',
                    'FROM entity_with_1_to_1_self_referencing_bidirectional e0_',
                    'LEFT JOIN entity_with_1_to_1_self_referencing_bidirectional e1_ ON e0_.id = e1_.self_id',
                    'WHERE e0_.id IN (?)',
                ]),
            ]);
        }
    }

    /**
     * @return iterable<array{int}>
     */
    public static function provideOneToOneSelfReferencingBidirectionalData(): iterable
    {
        yield [0];
        yield [1];
        yield [3];
    }

    #[DataProvider('provideOneToOneSelfReferencingBidirectionalBackTraversalData')]
    public function testOneToOneSelfReferencingBidirectionalBackTraversal(int $depth): void
    {
        $entity = new EntityWithOneToOneSelfReferencingBidirectional(null);
        $root = $entity;
        $this->getEntityManager()->persist($entity);

        for ($i = 0; $i < $depth; $i++) {
            $entity = new EntityWithOneToOneSelfReferencingBidirectional($entity);
            $this->getEntityManager()->persist($entity);
        }

        $this->getEntityManager()->flush();
        $this->getEntityManager()->clear();
        $this->clearSqlCaptureMiddleware();

        $root = $this->refreshExistingEntity($root);

        $this->thenExpectedQueriesArePerformed([
            implode(' ', [
                'SELECT t0.id AS id_1, t0.self_id AS self_id_2, t3.id AS id_4, t3.self_id AS self_id_5',
                'FROM entity_with_1_to_1_self_referencing_bidirectional t0',
                'LEFT JOIN entity_with_1_to_1_self_referencing_bidirectional t3 ON t3.self_id = t0.id',
                'WHERE t0.id = ?',
            ]),
            ...array_fill(0, intdiv($depth + 1, 2), implode(' ', [
                'SELECT t0.id AS id_1, t0.self_id AS self_id_2, t3.id AS id_4, t3.self_id AS self_id_5',
                'FROM entity_with_1_to_1_self_referencing_bidirectional t0',
                'LEFT JOIN entity_with_1_to_1_self_referencing_bidirectional t3 ON t3.self_id = t0.id',
                'WHERE t0.self_id = ?',
            ])),
        ]);

        $this->clearSqlCaptureMiddleware();
        $this->whenPreloadIsCalled([$root], 'back');

        $this->thenExpectedQueriesArePerformed([]);
    }

    /**
     * @return iterable<array{int}>
     */
    public static function provideOneToOneSelfReferencingBidirectionalBackTraversalData(): iterable
    {
        yield [0];
        yield [1];
        yield [2];
        yield [3];
        yield [4];
        yield [5];
    }

    /**
     * @param non-negative-int $depth
     * @param non-negative-int $maxFetchJoinSameFieldCount
     * @param non-negative-int $expectedSingleFetchQueries
     */
    #[DataProvider('provideOneToOneSelfReferencingBidirectionalBackTraversalPointerData')]
    public function testOneToOneSelfReferencingBidirectionalBackTraversalPointer(
        int $depth,
        int $maxFetchJoinSameFieldCount,
        int $expectedSingleFetchQueries,
    ): void
    {
        $entity = new EntityWithOneToOneSelfReferencingBidirectional(null);
        $root = $entity;
        $this->getEntityManager()->persist($entity);

        for ($i = 0; $i < $depth; $i++) {
            $entity = new EntityWithOneToOneSelfReferencingBidirectional($entity);
            $this->getEntityManager()->persist($entity);
        }

        $pointer = new EntityWithOneToOneSelfReferencingBidirectionalPointer($root);
        $this->getEntityManager()->persist($pointer);

        $this->getEntityManager()->flush();
        $this->getEntityManager()->clear();
        $this->clearSqlCaptureMiddleware();

        $pointer = $this->refreshExistingEntity($pointer);

        $this->clearSqlCaptureMiddleware();
        $this->whenPreloadIsCalled([$pointer], 'target', $maxFetchJoinSameFieldCount);

        $expectedQueries = [];

        if ($maxFetchJoinSameFieldCount === 1) {
            $expectedQueries[] = implode(' ', [
                'SELECT e0_.id AS id_0, e1_.id AS id_1, e0_.self_id AS self_id_2, e1_.self_id AS self_id_3',
                'FROM entity_with_1_to_1_self_referencing_bidirectional e0_',
                'LEFT JOIN entity_with_1_to_1_self_referencing_bidirectional e1_ ON e0_.id = e1_.self_id',
                'WHERE e0_.id IN (?)',
            ]);

        } elseif ($maxFetchJoinSameFieldCount === 2) {
            $expectedQueries[] = implode(' ', [
                'SELECT e0_.id AS id_0, e1_.id AS id_1, e2_.id AS id_2, e0_.self_id AS self_id_3, e1_.self_id AS self_id_4, e2_.self_id AS self_id_5',
                'FROM entity_with_1_to_1_self_referencing_bidirectional e0_',
                'LEFT JOIN entity_with_1_to_1_self_referencing_bidirectional e1_ ON e0_.id = e1_.self_id',
                'LEFT JOIN entity_with_1_to_1_self_referencing_bidirectional e2_ ON e1_.id = e2_.self_id',
                'WHERE e0_.id IN (?)',
            ]);

        } elseif ($maxFetchJoinSameFieldCount === 3) {
            $expectedQueries[] = implode(' ', [
                'SELECT e0_.id AS id_0, e1_.id AS id_1, e2_.id AS id_2, e3_.id AS id_3, e0_.self_id AS self_id_4, e1_.self_id AS self_id_5, e2_.self_id AS self_id_6, e3_.self_id AS self_id_7',
                'FROM entity_with_1_to_1_self_referencing_bidirectional e0_',
                'LEFT JOIN entity_with_1_to_1_self_referencing_bidirectional e1_ ON e0_.id = e1_.self_id',
                'LEFT JOIN entity_with_1_to_1_self_referencing_bidirectional e2_ ON e1_.id = e2_.self_id',
                'LEFT JOIN entity_with_1_to_1_self_referencing_bidirectional e3_ ON e2_.id = e3_.self_id',
                'WHERE e0_.id IN (?)',
            ]);

        } else {
            throw new LogicException('Unexpected maxFetchJoinSameFieldCount value');
        }

        for ($i = 0; $i < $expectedSingleFetchQueries; $i++) {
            $expectedQueries[] = implode(' ', [
                'SELECT t0.id AS id_1, t0.self_id AS self_id_2, t3.id AS id_4, t3.self_id AS self_id_5',
                'FROM entity_with_1_to_1_self_referencing_bidirectional t0',
                'LEFT JOIN entity_with_1_to_1_self_referencing_bidirectional t3 ON t3.self_id = t0.id',
                'WHERE t0.self_id = ?',
            ]);
        }

        $this->thenExpectedQueriesArePerformed($expectedQueries);
    }

    /**
     * @return iterable<array{non-negative-int, non-negative-int, non-negative-int}>
     */
    public static function provideOneToOneSelfReferencingBidirectionalBackTraversalPointerData(): iterable
    {
        yield [0, 1, 0];
        yield [1, 1, 1];
        yield [2, 1, 1];
        yield [3, 1, 2];
        yield [4, 1, 2];
        yield [5, 1, 3];

        yield [0, 2, 0];
        yield [1, 2, 0];
        yield [2, 2, 1];
        yield [3, 2, 1];
        yield [4, 2, 2];
        yield [5, 2, 2];

        yield [0, 3, 0];
        yield [1, 3, 0];
        yield [2, 3, 0];
        yield [3, 3, 1];
        yield [4, 3, 1];
        yield [5, 3, 2];
    }

    private function givenEntityWithNoRelations(): EntityWithNoRelations
    {
        $entityWithNoRelations = new EntityWithNoRelations();
        $this->getEntityManager()->persist($entityWithNoRelations);

        return $entityWithNoRelations;
    }

    private function givenEntityWithManyToOneEntityWithNoRelations(
        EntityWithNoRelations $entityWithNoRelations,
    ): EntityWithManyToOneEntityWithNoRelations
    {
        $entityWithManyToOneEntityWithNoRelations = new EntityWithManyToOneEntityWithNoRelations($entityWithNoRelations);
        $this->getEntityManager()->persist($entityWithManyToOneEntityWithNoRelations);

        return $entityWithManyToOneEntityWithNoRelations;
    }

    private function givenChildEntityWithNoRelationsA(): ChildEntityWithNoRelationsA
    {
        $entityWithNoRelations = new ChildEntityWithNoRelationsA();
        $this->getEntityManager()->persist($entityWithNoRelations);

        return $entityWithNoRelations;
    }

    private function givenEntityWithManyToOneAbstractEntityWithNoRelations(
        AbstractEntityWithNoRelations $abstractEntityWithNoRelations,
    ): EntityWithManyToOneAbstractEntityWithNoRelations
    {
        $entityWithManyToOneAbstractEntityWithNoRelations = new EntityWithManyToOneAbstractEntityWithNoRelations($abstractEntityWithNoRelations);
        $this->getEntityManager()->persist($entityWithManyToOneAbstractEntityWithNoRelations);

        return $entityWithManyToOneAbstractEntityWithNoRelations;
    }

    private function givenEntityWithManyToOneOfManyToOneAbstractEntities(
        EntityWithManyToOneAbstractEntityWithNoRelations $entityWithManyToOneAbstractEntityWithNoRelations,
    ): EntityWithManyToOneOfManyToOneAbstractEntities
    {
        $entityWithManyToOneOfManyToOneAbstractEntities = new EntityWithManyToOneOfManyToOneAbstractEntities($entityWithManyToOneAbstractEntityWithNoRelations);
        $this->getEntityManager()->persist($entityWithManyToOneOfManyToOneAbstractEntities);

        return $entityWithManyToOneOfManyToOneAbstractEntities;
    }

    /**
     * @param non-negative-int|null $maxFetchJoinSameFieldCount
     * @param list<object> $sourceEntities
     */
    private function whenPreloadIsCalled(
        array $sourceEntities,
        string $sourcePropertyName,
        ?int $maxFetchJoinSameFieldCount = null,
    ): void
    {
        $this->getEntityPreloader()->preload($sourceEntities, $sourcePropertyName, null, $maxFetchJoinSameFieldCount);
    }

    private function clearSqlCaptureMiddleware(): void
    {
        $this->getQueryLogger()->clear();
    }

    /**
     * @param list<string> $expectedQueries
     */
    private function thenExpectedQueriesArePerformed(array $expectedQueries): void
    {
        self::assertSame(
            $expectedQueries,
            $this->getQueryLogger()->getQueries(omitSelectedColumns: false, omitDiscriminatorConditions: false),
        );
    }

    private function givenConcreteEntityWithOptionalManyToOneItselfRelation(
        ?ConcreteStiEntityWithOptionalManyToOneOfItselfRelation $parent,
    ): ConcreteStiEntityWithOptionalManyToOneOfItselfRelation
    {
        $entity = new ConcreteStiEntityWithOptionalManyToOneOfItselfRelation($parent);
        $this->getEntityManager()->persist($entity);
        $this->getEntityManager()->flush();

        return $entity;
    }

    private function givenWithManyToOneOfManyToOneItselfRelation(
        ConcreteStiEntityWithOptionalManyToOneOfItselfRelation $currentEntityWithRelationToItself,
    ): EntityWithManyToOneOfManyToOneItselfStiRelation
    {
        $entityWithManyToOneOfManyToOneItselfRelation = new EntityWithManyToOneOfManyToOneItselfStiRelation($currentEntityWithRelationToItself);
        $this->getEntityManager()->persist($entityWithManyToOneOfManyToOneItselfRelation);
        $this->getEntityManager()->flush();

        return $entityWithManyToOneOfManyToOneItselfRelation;
    }

    private function givenWithManyToOneOfManyToOneOfManyToOneItselfRelation(
        EntityWithManyToOneOfManyToOneItselfStiRelation $entityWithManyToOneOfManyToOneItselfRelation,
    ): EntityWithManyToOneOfManyToOneOfManyToOneItselfRelation
    {
        $entityWithManyToOneOfManyToOneOfManyToOneItselfRelation = new EntityWithManyToOneOfManyToOneOfManyToOneItselfRelation($entityWithManyToOneOfManyToOneItselfRelation);
        $this->getEntityManager()->persist($entityWithManyToOneOfManyToOneOfManyToOneItselfRelation);
        $this->getEntityManager()->flush();

        return $entityWithManyToOneOfManyToOneOfManyToOneItselfRelation;
    }

}
