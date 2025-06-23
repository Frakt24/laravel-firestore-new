<?php

namespace Frakt24\LaravelFirestore\Exceptions;

class EncodingException extends FirestoreException
{
    protected mixed $value;
    protected ?string $fieldPath;

    public static function invalidValue(mixed $value, ?string $fieldPath = null): self
    {
        $valueType = is_object($value) ? get_class($value) : gettype($value);

        $message = sprintf('Cannot encode value of type "%s" for Firestore', $valueType);
        if ($fieldPath) {
            $message .= sprintf(' at path "%s"', $fieldPath);
        }

        $exception = new self($message);
        $exception->value = $value;
        $exception->fieldPath = $fieldPath;

        return $exception->withContext([
            'valueType' => $valueType,
            'fieldPath' => $fieldPath,
        ]);
    }

    public static function invalidArrayIndex(string $fieldPath): self
    {
        $message = sprintf('Invalid array index in field path "%s"', $fieldPath);

        $exception = new self($message);
        $exception->fieldPath = $fieldPath;

        return $exception->withContext([
            'fieldPath' => $fieldPath,
        ]);
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function getFieldPath(): ?string
    {
        return $this->fieldPath;
    }
}
