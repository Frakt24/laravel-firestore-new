<?php

namespace Frakt24\LaravelFirestore\Exceptions;

class DocumentNotFoundException extends FirestoreException
{
    protected string $collection;
    protected string $documentId;

    public static function create(string $collection, string $documentId): self
    {
        $exception = new self(
            sprintf('Document "%s" not found in collection "%s"', $documentId, $collection)
        );

        $exception->collection = $collection;
        $exception->documentId = $documentId;

        return $exception->withContext([
            'collection' => $collection,
            'documentId' => $documentId,
        ]);
    }

    public function getCollection(): string
    {
        return $this->collection;
    }

    public function getDocumentId(): string
    {
        return $this->documentId;
    }
}
