<?php

namespace Frakt24\LaravelFirestore;

use DateTimeInterface;
use Frakt24\LaravelFirestore\Exceptions\ApiException;
use Frakt24\LaravelFirestore\Exceptions\DocumentNotFoundException;
use Frakt24\LaravelFirestore\Exceptions\EncodingException;
use GuzzleHttp\Exception\GuzzleException;

class Document
{
    /**
     * The Firestore instance.
     */
    protected Firestore $firestore;

    /**
     * The collection this document belongs to.
     */
    protected string $collection;

    /**
     * The document ID.
     */
    protected string $id;

    /**
     * The document path.
     */
    protected string $path;

    public function __construct(Firestore $firestore, string $collection, string $id)
    {
        $this->firestore = $firestore;
        $this->collection = $collection;
        $this->id = $id;
        
        $basePath = $firestore->getBasePath();
        if (strpos($collection, $basePath) === 0) {
            $this->path = "{$collection}/{$id}";
        } else {
            $this->path = "{$basePath}/{$collection}/{$id}";
        }
    }

    public function getDocumentId(): string
    {
        return $this->id;
    }

    public function getDocumentPath(): string
    {
        return $this->path;
    }

    public function getCollectionName(): string
    {
        return $this->collection;
    }

    public function retrieveDocumentData(): array
    {
        $response = $this->firestore->get($this->path);
        
        return $this->parseDocumentData($response);
    }

    public function checkDocumentExistence(): bool
    {
        try {
            $this->retrieveDocumentData();
            return true;
        } catch (DocumentNotFoundException $e) {
            return false;
        }
    }

    /**
     * @throws ApiException
     * @throws EncodingException
     * @throws GuzzleException
     */
    public function createOrUpdateDocument(array $data, array $options = []): array
    {
        $fields = $this->encodeDocumentData($data);
        $payload = ['fields' => $fields];
        
        if (isset($options['merge']) && $options['merge']) {
            return $this->firestore->patch($this->path . '?updateMask.fieldPaths=*', $payload);
        }

        $pathParts = explode('/', $this->path);
        array_pop($pathParts);
        $collectionPath = implode('/', $pathParts);
        
        return $this->firestore->post($collectionPath . '?documentId=' . $this->id, $payload);
    }

    /**
     * @throws EncodingException
     * @throws GuzzleException
     * @throws ApiException
     */
    public function updateDocumentFields(array $data): array
    {
        $fields = $this->encodeDocumentData($data);
        
        $fieldPaths = [];
        foreach (array_keys($data) as $key) {
            try {
                $formattedKey = preg_replace('/\.(\d+)\./', '.`$1`.', $key);
                $fieldPaths[] = 'updateMask.fieldPaths=' . urlencode($formattedKey);
            } catch (\Throwable $e) {
                throw EncodingException::invalidArrayIndex($key);
            }
        }
        
        $updateMask = implode('&', $fieldPaths);
        
        return $this->firestore->patch("{$this->path}?{$updateMask}", [
            'fields' => $fields
        ]);
    }

    /**
     * @throws GuzzleException
     * @throws ApiException
     */
    public function deleteDocument(): array
    {
        return $this->firestore->delete($this->path);
    }

    public function getSubcollectionReference(string $collectionId): Collection
    {
        $subcollectionPath = "{$this->collection}/{$this->id}/{$collectionId}";
        
        return new Collection(
            $this->firestore,
            $subcollectionPath
        );
    }

    public function parseDocumentData(array $response): array
    {
        if (!isset($response['fields'])) {
            return [];
        }

        return array_map(function ($value) {
            return $this->decodeValue($value);
        }, $response['fields']);
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

        return array_map([$this, 'decodeValue'], $array['values']);
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

    /**
     * @throws EncodingException
     */
    protected function encodeDocumentData(array $data): array
    {
        $data = $this->preSanitize($data);

        $fields = [];
        foreach ($data as $key => $val) {
            $fields[$key] = $this->encodeValue($val);
        }

        return $fields;
    }

    private function preSanitize(array $a): array
    {
        $out = [];
        foreach ($a as $k => $v) {
            if (is_resource($v) || $v instanceof \Closure) {
                continue;
            }
            if (is_array($v)) {
                $sub = $this->preSanitize($v);
                $out[$k] = $sub;
            } else {
                $out[$k] = $v;
            }
        }
        return $out;
    }

    /**
     * @throws EncodingException
     */
    protected function encodeValue(mixed $value): array
    {
        if (is_array($value) && isset($value['geometry'], $value['distance'])) {
            return ['stringValue' => json_encode($value)];
        }

        if (is_null($value))      return ['nullValue'    => null];
        if (is_bool($value))      return ['booleanValue' => $value];
        if (is_int($value))       return ['integerValue' => (string)$value];
        if (is_float($value))     return ['doubleValue'  => $value];
        if (is_string($value))    return ['stringValue'  => $value];
        if ($value instanceof \DateTimeInterface) {
            return ['timestampValue' => $value->format(\DateTimeInterface::RFC3339)];
        }
        if (is_array($value))     return $this->encodeArrayOrMap($value);

        if ($value instanceof \JsonSerializable) {
            return $this->encodeArrayOrMap($value->jsonSerialize());
        }
        if (method_exists($value, 'toArray')) {
            return $this->encodeArrayOrMap($value->toArray());
        }

        try {
            return ['stringValue' => (string)$value];
        } catch (\Throwable $e) {
            throw EncodingException::invalidValue($value);
        }
    }

    /**
     * @throws EncodingException
     */
    protected function encodeArrayOrMap(array $array): array
    {
        $allInts = true;
        foreach ($array as $k => $_) {
            if (!is_int($k)) {
                $allInts = false;
                break;
            }
        }

        if ($allInts) {
            ksort($array);
            $values = [];
            foreach ($array as $v) {
                $values[] = $this->encodeValue($v);
            }
            return ['arrayValue' => ['values' => $values]];
        }

        $fields = [];
        foreach ($array as $k => $v) {
            $fields[$k] = $this->encodeValue($v);
        }

        return ['mapValue' => ['fields' => (object) $fields]];
    }
}
