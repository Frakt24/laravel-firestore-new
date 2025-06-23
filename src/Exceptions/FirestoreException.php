<?php

namespace Frakt24\LaravelFirestore\Exceptions;

use Exception;

class FirestoreException extends Exception
{
    protected array $context = [];

    public function withContext(array $context): self
    {
        $this->context = array_merge($this->context, $context);
        return $this;
    }

    public function getContext(): array
    {
        return $this->context;
    }
}
