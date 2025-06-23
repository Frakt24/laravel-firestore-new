<?php

namespace Frakt24\LaravelFirestore;

use DateTimeInterface;

class Query
{
    /**
     * The Firestore instance.
     */
    protected Firestore $firestore;

    /**
     * The parent path.
     */
    protected string $parent;

    /**
     * The structured query.
     */
    protected array $query = [];

    /**
     * The next page token.
     */
    protected ?string $nextPageToken = null;

    public function __construct(Firestore $firestore, string $parent)
    {
        $this->firestore = $firestore;
        $this->parent = $parent;
        $this->query = [
            'from' => [['collectionId' => basename($parent)]],
        ];
    }

    public function where(string $field, string $operator, mixed $value): self
    {
        $operatorMap = [
            '==' => 'EQUAL',
            '<' => 'LESS_THAN',
            '<=' => 'LESS_THAN_OR_EQUAL',
            '>' => 'GREATER_THAN',
            '>=' => 'GREATER_THAN_OR_EQUAL',
            '!=' => 'NOT_EQUAL',
            'array-contains' => 'ARRAY_CONTAINS',
            'array-contains-any' => 'ARRAY_CONTAINS_ANY',
            'in' => 'IN',
            'not-in' => 'NOT_IN',
        ];

        if (!isset($operatorMap[$operator])) {
            throw new \InvalidArgumentException("Operator {$operator} is not supported");
        }

        if (!isset($this->query['where'])) {
            $this->query['where'] = ['compositeFilter' => [
                'op' => 'AND',
                'filters' => [],
            ]];
        }

        $this->query['where']['compositeFilter']['filters'][] = [
            'fieldFilter' => [
                'field' => ['fieldPath' => $field],
                'op' => $operatorMap[$operator],
                'value' => $this->encodeValue($value),
            ],
        ];

        return $this;
    }

    public function orderBy(string $field, string $direction = 'asc'): self
    {
        if (!isset($this->query['orderBy'])) {
            $this->query['orderBy'] = [];
        }

        $this->query['orderBy'][] = [
            'field' => ['fieldPath' => $field],
            'direction' => strtoupper($direction) === 'DESC' ? 'DESCENDING' : 'ASCENDING',
        ];

        return $this;
    }

    public function limit(int $limit): self
    {
        $this->query['limit'] = $limit;

        return $this;
    }

    public function startAt(array $values, bool $before = true): self
    {
        $encodedValues = array_map([$this, 'encodeValue'], $values);

        $this->query[$before ? 'startAt' : 'startAfter'] = [
            'values' => $encodedValues,
        ];

        return $this;
    }

    public function endAt(array $values, bool $before = false): self
    {
        $encodedValues = array_map([$this, 'encodeValue'], $values);

        $this->query[$before ? 'endBefore' : 'endAt'] = [
            'values' => $encodedValues,
        ];

        return $this;
    }

    public function get(): array
    {
        $response = $this->firestore->post(
            "v1/projects/{$this->firestore->getProjectId()}/databases/{$this->firestore->getDatabaseId()}/documents:runQuery",
            ['structuredQuery' => $this->query]
        );
        
        // Store the next page token if available
        $this->nextPageToken = $response['nextPageToken'] ?? null;
        
        return $this->parseQueryResponse($response);
    }

    public function hasNextPage(): bool
    {
        return !empty($this->nextPageToken);
    }

    public function nextPage(): array
    {
        if (!$this->hasNextPage()) {
            return [];
        }
        
        $response = $this->firestore->post(
            "v1/projects/{$this->firestore->getProjectId()}/databases/{$this->firestore->getDatabaseId()}/documents:runQuery",
            [
                'structuredQuery' => $this->query,
                'pageToken' => $this->nextPageToken
            ]
        );
        
        $this->nextPageToken = $response['nextPageToken'] ?? null;
        
        return $this->parseQueryResponse($response);
    }

    protected function parseQueryResponse(array $response): array
    {
        $documents = [];

        foreach ($response as $item) {
            if (!isset($item['document'])) {
                continue;
            }

            $doc = $item['document'];

            $parts = explode('/', $doc['name']);
            $documentId = end($parts);

            $pathParts = explode('/', $this->parent);
            $collectionId = end($pathParts);

            $document = new Document($this->firestore, $collectionId, $documentId);

            $data = [];
            if (isset($doc['fields'])) {
                foreach ($doc['fields'] as $key => $value) {
                    $data[$key] = $this->decodeValue($value);
                }
            }

            $documents[] = [
                'id' => $documentId,
                'ref' => $document,
                'data' => $data,
            ];
        }

        return $documents;
    }

    protected function encodeValue(mixed $value): array
    {
        return match (true) {
            is_null($value) => ['nullValue' => null],
            is_bool($value) => ['booleanValue' => $value],
            is_int($value) => ['integerValue' => (string) $value],
            is_float($value) => ['doubleValue' => $value],
            is_string($value) => ['stringValue' => $value],
            $value instanceof \DateTime => ['timestampValue' => $value->format(DateTimeInterface::RFC3339)],
            is_array($value) => $this->encodeArrayOrMap($value),
            default => ['stringValue' => (string) $value],
        };
    }

    protected function encodeArrayOrMap(array $array): array
    {
        if (array_keys($array) !== range(0, count($array) - 1)) {
            $fields = [];
            foreach ($array as $key => $value) {
                $fields[$key] = $this->encodeValue($value);
            }

            return ['mapValue' => ['fields' => $fields]];
        }

        $values = array_map([$this, 'encodeValue'], $array);

        return ['arrayValue' => ['values' => $values]];
    }

    protected function decodeValue(array $value): mixed
    {
        $type = key($value);
        $val = current($value);

        return match ($type) {
            'nullValue' => null,
            'booleanValue' => (bool) $val,
            'integerValue' => (int) $val,
            'doubleValue' => (float) $val,
            'stringValue' => (string) $val,
            'timestampValue' => new \DateTime($val),
            'arrayValue' => $this->decodeArray($val),
            'mapValue' => $this->decodeMap($val),
            default => $val,
        };
    }

    protected function decodeArray(array $array): array
    {
        if (!isset($array['values'])) {
            return [];
        }

        return array_map(function ($value) {
            return $this->decodeValue($value);
        }, $array['values']);
    }

    protected function decodeMap(array $map): array
    {
        if (!isset($map['fields'])) {
            return [];
        }

        return array_map(function ($value) {
            return $this->decodeValue($value);
        }, $map['fields']);
    }
}
