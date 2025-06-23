<?php

namespace Frakt24\LaravelFirestore;

use Google\Client;
use Google\Exception;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Cache;
use Frakt24\LaravelFirestore\Exceptions\ApiException;
use Frakt24\LaravelFirestore\Exceptions\AuthenticationException;
use Frakt24\LaravelFirestore\Exceptions\TransactionException;
use Throwable;

class Firestore
{
    /**
     * The Google API client instance.
     */
    protected Client $client;

    /**
     * The HTTP client instance.
     */
    protected HttpClient $httpClient;

    /**
     * The Firestore project ID.
     */
    protected string $projectId;

    /**
     * The Firestore database ID (defaults to '(default)').
     */
    protected string $databaseId;

    /**
     * The base API URL for Firestore.
     */
    protected string $baseUrl = 'https://firestore.googleapis.com/v1';

    /**
     * Whether to use token caching.
     */
    protected bool $useTokenCache;

    /**
     * How long to cache the token in seconds.
     */
    protected int $tokenCacheTime;

    /**
     * Create a new Firestore instance.
     * @throws AuthenticationException|Exception
     */
    public function __construct(array $config)
    {
        $this->projectId  = $config['project_id']   ?? '';
        $this->databaseId = $config['database_id']  ?? '(default)';
        if (! str_ends_with($this->baseUrl, '/')) {
            $this->baseUrl .= '/';
        }

        $this->client = new Client();
        $this->client->setApplicationName('Laravel Firestore');
        $this->client->setScopes([
            'https://www.googleapis.com/auth/datastore',
            'https://www.googleapis.com/auth/cloud-platform',
        ]);

        $keyFile = $this->resolveKeyFilePath(
            $config['key_file_path'] ?? null
        );
        if ($keyFile && file_exists($keyFile)) {
            $this->client->setAuthConfig($keyFile);
        } elseif (getenv('GOOGLE_APPLICATION_CREDENTIALS')) {
            $this->client->useApplicationDefaultCredentials();
        } else {
            throw AuthenticationException::credentialsNotFound();
        }

        $token = $this->getAccessToken();

        $this->httpClient = new HttpClient([
            'base_uri'      => $this->baseUrl,
            'http_version'  => 2.0,
            'headers'       => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
                'Connection'    => 'keep-alive',
            ],
            'curl' => [
                CURLOPT_TCP_KEEPALIVE => 1,
                CURLOPT_TCP_KEEPIDLE  => 120,
            ],
            'timeout'         => 5.0,
            'connect_timeout' => 1.0,
        ]);
    }

    protected function getAccessToken(): string
    {
        $cacheKey = 'firestore_token_' . $this->projectId;
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $this->client->fetchAccessTokenWithAssertion();
        $token = $this->client->getAccessToken()['access_token'] ?? '';
        if ($token) {
            Cache::put($cacheKey, $token, 3500);
        }
        return $token;
    }

    protected function resolveKeyFilePath(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (file_exists($path) && \Illuminate\Support\Str::startsWith($path, '/')) {
            return $path;
        }

        $storage = storage_path('app/' . ltrim($path, '/'));
        if (file_exists($storage)) {
            return $storage;
        }

        $base = base_path($path);
        if (file_exists($base)) {
            return $base;
        }

        $real = realpath($path);
        if ($real && file_exists($real)) {
            return $real;
        }

        return $path;
    }


    public function getClient(): Client
    {
        return $this->client;
    }

    public function getBasePath(): string
    {
        return "projects/{$this->projectId}/databases/{$this->databaseId}/documents";
    }

    public function getDatabasePath(): string
    {
        return "projects/{$this->projectId}/databases/{$this->databaseId}";
    }

    public function collection(string $name): Collection
    {
        return new Collection($this, $name);
    }

    public function document(string $collection, string $document): Document
    {
        return new Document($this, $collection, $document);
    }

    /**
     * @throws GuzzleException
     * @throws ApiException
     */
    public function get(string $path)
    {
        try {
            $response = $this->httpClient->get($path);
            
            if ($response->getStatusCode() >= 400) {
                throw ApiException::fromResponse($response, $path);
            }
            
            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            throw ApiException::fromRequestException($e);
        }
    }

    /**
     * @throws ApiException
     * @throws GuzzleException
     */
    public function post(string $path, array $data)
    {
        try {
            $response = $this->httpClient->post($path, [
                'json' => $data,
            ]);
            
            if ($response->getStatusCode() >= 400) {
                throw ApiException::fromResponse($response, $path);
            }
            
            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            throw ApiException::fromRequestException($e);
        }
    }

    /**
     * @throws ApiException
     * @throws GuzzleException
     */
    public function patch(string $path, array $data)
    {
        try {
            $response = $this->httpClient->patch($path, [
                'json' => $data,
            ]);
            
            if ($response->getStatusCode() >= 400) {
                throw ApiException::fromResponse($response, $path);
            }
            
            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            throw ApiException::fromRequestException($e);
        }
    }

    /**
     * @throws ApiException
     * @throws GuzzleException
     */
    public function delete(string $path)
    {
        try {
            $response = $this->httpClient->delete($path);
            
            if ($response->getStatusCode() >= 400) {
                throw ApiException::fromResponse($response, $path);
            }
            
            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            throw ApiException::fromRequestException($e);
        }
    }

    public function beginTransaction(): Transaction
    {
        return new Transaction($this);
    }

    /**
     * @throws Throwable
     * @throws TransactionException
     */
    public function runTransaction(callable $callback): mixed
    {
        $transaction = $this->beginTransaction();
        
        try {
            $result = $callback($transaction);
            
            if ($transaction->isActive()) {
                $transaction->commit();
            }
            
            return $result;
        } catch (Throwable $e) {
            if ($transaction->isActive()) {
                $transaction->rollback();
            }
            throw $e;
        }
    }

    public function getProjectId()
    {
        return $this->projectId;
    }

    public function getDatabaseId()
    {
        return $this->databaseId;
    }
}
