<?php declare(strict_types = 1);

namespace ShipMonkTests\DoctrineEntityPreloader\Lib;

use Nette\Utils\Strings;
use Psr\Log\AbstractLogger;
use Stringable;
use function array_map;
use function trim;

class QueryLogger extends AbstractLogger
{

    /**
     * @var list<string>
     */
    private array $queries = [];

    /**
     * @param array<mixed> $context
     */
    public function log(mixed $level, Stringable|string $message, array $context = []): void
    {
        if (isset($context['sql'])) {
            $this->queries[] = $context['sql'];
        }
    }

    /**
     * @return list<string>
     */
    public function getQueries(
        bool $omitSelectedColumns = true,
        bool $omitDiscriminatorConditions = true,
        bool $multiline = false,
    ): array
    {
        return array_map(
            static function (string $query) use (
                $omitSelectedColumns,
                $omitDiscriminatorConditions,
                $multiline,
            ): string {
                if ($omitSelectedColumns) {
                    $query = Strings::replace(
                        $query,
                        '#SELECT (.*?) FROM#',
                        'SELECT * FROM',
                    );
                }

                if ($omitDiscriminatorConditions) {
                    $query = Strings::replace(
                        $query,
                        '#IN \\([^?)]++\\)#',
                        'IN (?)',
                    );
                }

                if ($multiline) {
                    $query = trim(
                        Strings::replace(
                            $query,
                            '#\s*+(FROM|LEFT JOIN|INNER JOIN|WHERE|ORDER BY|GROUP BY|HAVING|LIMIT|OFFSET|SET|VALUES)#',
                            "\n$1",
                        ),
                    );

                    $query = trim(
                        Strings::replace(
                            $query,
                            '#\s*+(AND|OR)#',
                            "\n    $1",
                        ),
                    );
                }

                return $query;
            },
            $this->queries,
        );
    }

    /**
     * @return list<array{count: int, query: string}>
     */
    public function getAggregatedQueries(): array
    {
        $queries = $this->getQueries();

        $aggregatedQueries = [];

        foreach ($queries as $query) {
            $found = false;

            foreach ($aggregatedQueries as &$aggregatedQuery) {
                if ($aggregatedQuery['query'] === $query) {
                    $aggregatedQuery['count']++;
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $aggregatedQueries[] = [
                    'count' => 1,
                    'query' => $query,
                ];
            }
        }

        return $aggregatedQueries;
    }

    public function clear(): void
    {
        $this->queries = [];
    }

}
