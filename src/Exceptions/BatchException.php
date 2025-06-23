<?php

namespace Frakt24\LaravelFirestore\Exceptions;

class BatchException extends FirestoreException
{
    public static function alreadyCommitted(): self
    {
        return new self('Batch has already been committed');
    }

    public static function tooManyOperations(int $count, int $limit = 500): self
    {
        $exception = new self(
            sprintf('Batch contains too many operations (%d). Maximum allowed is %d', $count, $limit)
        );

        return $exception->withContext([
            'operationCount' => $count,
            'operationLimit' => $limit
        ]);
    }

    public static function commitFailed(string $reason = ''): self
    {
        $message = 'Failed to commit batch';
        if ($reason) {
            $message .= ': ' . $reason;
        }

        return new self($message);
    }
}
