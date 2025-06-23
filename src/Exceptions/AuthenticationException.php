<?php

namespace Frakt24\LaravelFirestore\Exceptions;

class AuthenticationException extends FirestoreException
{
    public static function credentialsNotFound(): self
    {
        return new self(
            'No valid Google credentials found. Set FIRESTORE_KEY_FILE or GOOGLE_APPLICATION_CREDENTIALS.'
        );
    }

    public static function invalidCredentials(string $details = ''): self
    {
        $message = 'Invalid Google credentials.';
        if ($details) {
            $message .= ' ' . $details;
        }

        return new self($message);
    }
}
