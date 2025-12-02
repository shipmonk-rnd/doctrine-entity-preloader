<?php declare(strict_types = 1);

namespace ShipMonkTests\DoctrineEntityPreloader\PHPStan;

use PHPStan\Testing\TypeInferenceTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use ShipMonk\DoctrineEntityPreloader\PHPStan\EntityPreloaderRule;

final class EntityPreloaderReturnTypeExtensionTest extends TypeInferenceTestCase
{

    protected function getRule(): EntityPreloaderRule
    {
        return new EntityPreloaderRule();
    }

    #[DataProvider('provideTypeAssertsData')]
    public function testTypeAsserts(
        string $assertType,
        string $file,
        mixed ...$args,
    ): void
    {
        $this->assertFileAsserts($assertType, $file, ...$args);
    }

    public static function provideTypeAssertsData(): iterable
    {
        yield from self::gatherAssertTypes(__DIR__ . '/Data/EntityPreloaderRuleTestData.php');
    }

    /**
     * @return list<string>
     */
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/../../rules.neon'];
    }

}
