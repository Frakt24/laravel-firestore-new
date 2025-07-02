<?php

namespace Frakt24\LaravelFirestore;

use DateMalformedStringException;
use DateTime;
use Random\RandomException;

class Collection
{
    /**
     * The Firestore instance.
     */
    protected Firestore $firestore;

    /**
     * The collection ID.
     */
    protected string $id;

    /**
     * The collection path.
     */
    protected string $path;

    public function __construct(Firestore $firestore, string $path)
    {
        $this->firestore = $firestore;
        
        $basePath = $firestore->getBasePath();
        if (strpos($path, $basePath) === 0) {
            $this->path = $path;
        } else {
            $this->path = $basePath . '/' . $path;
        }
        
        $pathParts = explode('/', $this->path);
        $this->id = end($pathParts);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function document(string $id): Document
    {
        $basePath = $this->firestore->getBasePath();
        $collectionPath = $this->path;
        
        if (strpos($this->path, $basePath) === 0) {
            $collectionPath = substr($this->path, strlen($basePath) + 1);
        }

        return new Document($this->firestore, $collectionPath, $id);
    }

    /**
     * Add a new document to the collection with an auto-generated ID.
     * @throws RandomException
     */
    public function add(array $data, ?string $id = null): Document
    {
        if (!$id) {
            $id = $this->generateRandomId();
        }

        $document = $this->document($id);
        $document->createOrUpdateDocument($data);
        
        return $document;
    }

    /**
     * List all documents in the collection.
     * @throws DateMalformedStringException
     */
    public function listDocuments(int $pageSize = 20, ?string $pageToken = null): array
    {
        $query = "?pageSize={$pageSize}";
        
        if ($pageToken) {
            $query .= "&pageToken=" . urlencode($pageToken);
        }
        
        $response = $this->firestore->get($this->path . $query);
        
        return $this->parseListDocumentsResponse($response);
    }

    public function query(): Query
    {
        return new Query($this->firestore, $this->path);
    }

    /**
     * Parse the list documents response.
     * @throws DateMalformedStringException
     */
    protected function parseListDocumentsResponse(array $response): array
    {
        $result = [
            'documents' => [],
            'nextPageToken' => $response['nextPageToken'] ?? null,
        ];
        
        if (!isset($response['documents']) || !is_array($response['documents'])) {
            return $result;
        }
        
        foreach ($response['documents'] as $doc) {
            if (!isset($doc['name'])) {
                continue;
            }
            
            $parts = explode('/', $doc['name']);
            $documentId = end($parts);
            
            $document = $this->document($documentId);
            
            $data = [];
            if (isset($doc['fields'])) {
                foreach ($doc['fields'] as $key => $value) {
                    $data[$key] = $this->decodeValue($value);
                }
            }
            
            $result['documents'][] = [
                'id' => $documentId,
                'ref' => $document,
                'data' => $data,
            ];
        }
        
        return $result;
    }

    /**
     * Generate a random document ID.
     * @throws RandomException
     */
    protected function generateRandomId(int $length = 20): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $id = '';
        
        for ($i = 0; $i < $length; $i++) {
            $id .= $chars[random_int(0, strlen($chars) - 1)];
        }
        
        return $id;
    }

    /**
     * Decode a Firestore value to PHP value.
     * @throws DateMalformedStringException
     */
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
            'timestampValue' => new DateTime($val),
            'arrayValue' => $this->decodeArray($val),
            'mapValue' => $this->decodeMap($val),
            default => $val,
        };
    }

    /**
     * Decode a Firestore array value.
     * @throws DateMalformedStringException
     */
    protected function decodeArray(array $array): array
    {
        if (!isset($array['values'])) {
            return [];
        }

        return array_map(function ($value) {
            return $this->decodeValue($value);
        }, $array['values']);
    }

    /**
     * Decode a Firestore map value.
     * @throws DateMalformedStringException
     */
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
