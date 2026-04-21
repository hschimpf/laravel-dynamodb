<?php

declare(strict_types=1);

namespace Kitar\Dynamodb\Events;

use Illuminate\Database\Connection;
use Illuminate\Database\Events\QueryExecuted;
use Kitar\Dynamodb\Query\Builder;

final class DynamodbQueryExecuted extends QueryExecuted {

    public function __construct(
        private readonly Builder $builder,
        public readonly string $method,
        public readonly string $table_name,
        public readonly array $params,
        ?float $time,
        ?string $readWriteType = null,
    ) {
        parent::__construct($this->query(), $this->bindings(), $time, $this->builder->connection, $readWriteType);
    }

    private function query(): string {
        return trim(sprintf('%s "%s" %s',
            ucfirst($this->method === 'clientQuery' ? 'query' : $this->method),
            $this->table_name.($this->builder->index ? ' ['.$this->builder->index.']' : ''),
            implode(' ', array_filter([
                ! empty($this->params['ProjectionExpression']) ? 'SELECT '.$this->params['ProjectionExpression'] : null,
                ! empty($this->params['UpdateExpression']) ? $this->params['UpdateExpression'] : null,
                ! empty($this->params['KeyConditionExpression']) ? 'WHERE '.$this->params['KeyConditionExpression'] : null,
                ! empty($this->params['FilterExpression']) ? 'FILTER '.$this->params['FilterExpression'] : null,
            ])),
        ));
    }

    private function bindings() {
        return array_merge(
            $this->values($this->params['Key'] ?? []),
            $this->values($this->params['Item'] ?? []),
            $this->params['ExpressionAttributeNames'] ?? [],
            $this->values($this->params['ExpressionAttributeValues'] ?? []),
        );
    }

    private function values($values) {
        return array_map(static fn ($value) => match (array_keys($value)[0]) {
            'N'    => (float) $value['N'],
            'BOOL' => (bool) $value['BOOL'] ? 'true' : 'false',
            'NULL' => 'null',

            default => array_first($value),
        }, $values);
    }

}
