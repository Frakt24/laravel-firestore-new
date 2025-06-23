<?php

namespace Frakt24\LaravelFirestore\Exceptions;

use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;

class ApiException extends FirestoreException
{
    protected int $statusCode;
    protected ?array $responseData;
    protected string $requestPath;

    public static function fromRequestException(RequestException $exception): self
    {
        $response = $exception->getResponse();
        $statusCode = $response ? $response->getStatusCode() : 0;
        $responseData = $response ? self::parseResponseData($response) : null;
        $requestPath = $exception->getRequest()->getUri()->getPath();

        $message = sprintf(
            'Firestore API error: %s (%d) for path %s',
            $exception->getMessage(),
            $statusCode,
            $requestPath
        );

        $apiException = new self($message, $exception->getCode(), $exception);
        $apiException->statusCode = $statusCode;
        $apiException->responseData = $responseData;
        $apiException->requestPath = $requestPath;

        return $apiException->withContext([
            'statusCode' => $statusCode,
            'responseData' => $responseData,
            'requestPath' => $requestPath,
        ]);
    }

    public static function fromResponse(ResponseInterface $response, string $requestPath): self
    {
        $statusCode = $response->getStatusCode();
        $responseData = self::parseResponseData($response);

        $message = sprintf(
            'Firestore API error: HTTP %d for path %s',
            $statusCode,
            $requestPath
        );

        $apiException = new self($message);
        $apiException->statusCode = $statusCode;
        $apiException->responseData = $responseData;
        $apiException->requestPath = $requestPath;

        return $apiException->withContext([
            'statusCode' => $statusCode,
            'responseData' => $responseData,
            'requestPath' => $requestPath,
        ]);
    }

    private static function parseResponseData(ResponseInterface $response): ?array
    {
        $contents = (string) $response->getBody();
        $data = json_decode($contents, true);

        return $data ?: ['raw' => $contents];
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getResponseData(): ?array
    {
        return $this->responseData;
    }

    public function getRequestPath(): string
    {
        return $this->requestPath;
    }
}
