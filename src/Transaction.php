<?php

namespace Frakt24\LaravelFirestore;

use Frakt24\LaravelFirestore\Exceptions\ApiException;
use Frakt24\LaravelFirestore\Exceptions\TransactionException;
use GuzzleHttp\Exception\GuzzleException;

class Transaction
{
    private Firestore $firestore;
    private string $transactionId;
    private array $operations = [];
    private bool $committed = false;
    private bool $rolledBack = false;

    /**
     * @throws TransactionException|ApiException|GuzzleException
     */
    public function __construct(Firestore $firestore, ?string $transactionId = null)
    {
        $this->firestore = $firestore;

        if ($transactionId) {
            $this->transactionId = $transactionId;
        } else {
            $this->begin();
        }
    }

    /**
     * @throws GuzzleException
     * @throws ApiException
     * @throws TransactionException
     */
    public function begin(): self
    {
        if ($this->committed || $this->rolledBack) {
            throw TransactionException::alreadyFinalized();
        }

        $response = $this->firestore->post('v1:beginTransaction', [
            'database' => $this->firestore->getDatabasePath()
        ]);

        $this->transactionId = $response['transaction'] ?? '';

        if (empty($this->transactionId)) {
            throw TransactionException::failedToStart();
        }

        return $this;
    }

    /**
     * @throws GuzzleException
     * @throws ApiException
     * @throws TransactionException
     */
    public function get(Document $document): array
    {
        if ($this->committed || $this->rolledBack) {
            throw TransactionException::alreadyFinalized();
        }

        $response = $this->firestore->post('v1:runQuery', [
            'transaction' => $this->transactionId,
            'structuredQuery' => [
                'from' => [
                    [
                        'collectionId' => $document->getCollectionName()
                    ]
                ],
                'where' => [
                    'fieldFilter' => [
                        'field' => [
                            'fieldPath' => '__name__'
                        ],
                        'op' => 'EQUAL',
                        'value' => [
                            'referenceValue' => $document->getDocumentPath()
                        ]
                    ]
                ],
                'limit' => 1
            ]
        ]);

        if (empty($response[0]['document'])) {
            throw TransactionException::documentNotFound($document->getDocumentId());
        }

        return $document->parseDocumentData($response[0]['document']);
    }

    /**
     * @throws TransactionException
     */
    public function create(Document $document, array $data): self
    {
        if ($this->committed || $this->rolledBack) {
            throw TransactionException::alreadyFinalized();
        }

        $this->operations[] = [
            'type' => 'create',
            'document' => $document,
            'data' => $data
        ];

        return $this;
    }

    /**
     * @throws TransactionException
     */
    public function update(Document $document, array $data, bool $merge = true): self
    {
        if ($this->committed || $this->rolledBack) {
            throw TransactionException::alreadyFinalized();
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
     * @throws TransactionException
     */
    public function delete(Document $document): self
    {
        if ($this->committed || $this->rolledBack) {
            throw TransactionException::alreadyFinalized();
        }

        $this->operations[] = [
            'type' => 'delete',
            'document' => $document
        ];

        return $this;
    }

    /**
     * @throws GuzzleException
     * @throws ApiException
     * @throws TransactionException
     */
    public function commit(): array
    {
        if ($this->committed) {
            throw TransactionException::alreadyCommitted();
        }

        if ($this->rolledBack) {
            throw TransactionException::alreadyRolledBack();
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
            'transaction' => $this->transactionId,
            'writes' => $writes
        ]);

        $this->committed = true;
        return $response;
    }

    /**
     * @throws GuzzleException
     * @throws ApiException
     * @throws TransactionException
     */
    public function rollback(): array
    {
        if ($this->committed) {
            throw TransactionException::alreadyCommitted();
        }

        if ($this->rolledBack) {
            throw TransactionException::alreadyRolledBack();
        }

        $response = $this->firestore->post('v1:rollback', [
            'database' => $this->firestore->getDatabasePath(),
            'transaction' => $this->transactionId
        ]);

        $this->rolledBack = true;
        $this->operations = [];

        return $response;
    }

    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    public function isActive(): bool
    {
        return !$this->committed && !$this->rolledBack;
    }
}
