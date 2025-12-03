<?php declare(strict_types = 1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

return (new Configuration())
    ->ignoreUnknownFunctions(['PHPStan\Testing\assertType'])
    ->ignoreErrorsOnPackage('nikic/php-parser', [ErrorType::DEV_DEPENDENCY_IN_PROD])
    ->ignoreErrorsOnPackage('phpstan/phpstan', [ErrorType::DEV_DEPENDENCY_IN_PROD]);
