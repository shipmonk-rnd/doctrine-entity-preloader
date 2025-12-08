<?php declare(strict_types = 1);

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMSetup;

// Use new non-deprecated API on Doctrine ORM 3.5+ with PHP 8.4+
if (PHP_VERSION_ID >= 8_04_00 && method_exists(ORMSetup::class, 'createAttributeMetadataConfig')) { // @phpstan-ignore function.alreadyNarrowedType (BC for older Doctrine)
    $config = ORMSetup::createAttributeMetadataConfig([__DIR__ . '/../Fixtures'], isDevMode: true);
    $config->enableNativeLazyObjects(true);
} else {
    $config = ORMSetup::createAttributeMetadataConfiguration([__DIR__ . '/../Fixtures'], isDevMode: true, proxyDir: __DIR__ . '/../../cache/proxies');
}

$config->setNamingStrategy(new UnderscoreNamingStrategy());

$connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config);

return new EntityManager($connection, $config);
