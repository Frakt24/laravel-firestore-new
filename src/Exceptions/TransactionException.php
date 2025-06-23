<?php

namespace Frakt24\LaravelFirestore\Exceptions;

class TransactionException extends FirestoreException
{
    public static function alreadyFinalized(): self
    {
        return new self('Transaction has already been committed or rolled back');
    }

    public static function alreadyCommitted(): self
    {
        return new self('Transaction has already been committed');
    }

    public static function alreadyRolledBack(): self
    {
        return new self('Transaction has already been rolled back');
    }

    public static function failedToStart(): self
    {
        return new self('Failed to start transaction');
    }

    public static function documentNotFound(string $documentId): self
    {
        $exception = new self(sprintf('Document "%s" not found in transaction', $documentId));

        return $exception->withContext([
            'documentId' => $documentId
        ]);
    }

    public static function commitFailed(string $reason = ''): self
    {
        $message = 'Failed to commit transaction';
        if ($reason) {
            $message .= ': ' . $reason;
        }

        return new self($message);
    }
}
