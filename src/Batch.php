<?php

namespace Frakt24\LaravelFirestore;

use Frakt24\LaravelFirestore\Exceptions\ApiException;
use Frakt24\LaravelFirestore\Exceptions\BatchException;
use GuzzleHttp\Exception\GuzzleException;

class Batch
{
    private Firestore $firestore;
    private array $operations = [];
    private bool $committed = false;

    public function __construct(Firestore $firestore)
    {
        $this->firestore = $firestore;
    }

    /**
     * @throws BatchException
     */
    public function create(Document $document, array $data): self
    {
        if ($this->committed) {
            throw BatchException::alreadyCommitted();
        }

        $this->operations[] = [
            'type' => 'create',
            'document' => $document,
            'data' => $data
        ];

        return $this;
    }

    /**
     * @throws BatchException
     */
    public function update(Document $document, array $data, bool $merge = true): self
    {
        if ($this->committed) {
            throw BatchException::alreadyCommitted();
        }

        $this->operations[] = [
            'type' => 'update',
            'document' => $document,
            'data' => $data,
            'merge' => $merge
        ];

        return $this;
    }

    /**
     * @throws BatchException
     */
    public function delete(Document $document): self
    {
        if ($this->committed) {
            throw BatchException::alreadyCommitted();
        }

        $this->operations[] = [
            'type' => 'delete',
            'document' => $document
        ];

        return $this;
    }

    /**
     * @throws ApiException
     * @throws GuzzleException
     * @throws BatchException
     */
    public function commit(): array
    {
        if ($this->committed) {
            throw BatchException::alreadyCommitted();
        }

        $writes = [];

        foreach ($this->operations as $operation) {
            switch ($operation['type']) {
                case 'create':
                    $writes[] = [
                        'update' => [
                            'name' => $operation['document']->getDocumentPath(),
                            'fields' => $operation['document']->encodeDocumentData($operation['data'])
                        ]
                    ];
                    break;

                case 'update':
                    $write = [
                        'update' => [
                            'name' => $operation['document']->getDocumentPath(),
                            'fields' => $operation['document']->encodeDocumentData($operation['data'])
                        ]
                    ];

                    if ($operation['merge']) {
                        $updateMask = [];
                        foreach (array_keys($operation['data']) as $key) {
                            $formattedKey = preg_replace('/\.(\d+)\./', '.`$1`.', $key);
                            $updateMask[] = $formattedKey;
                        }
                        $write['updateMask'] = ['fieldPaths' => $updateMask];
                    }

                    $writes[] = $write;
                    break;

                case 'delete':
                    $writes[] = [
                        'delete' => $operation['document']->getDocumentPath()
                    ];
                    break;
            }
        }

        $response = $this->firestore->post('v1:commit', [
            'database' => $this->firestore->getDatabasePath(),
            'writes' => $writes
        ]);

        $this->committed = true;
        return $response;
    }

    public function isCommitted(): bool
    {
        return $this->committed;
    }

    public function getOperationCount(): int
    {
        return count($this->operations);
    }
}
