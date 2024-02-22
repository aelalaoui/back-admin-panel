<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Psr\Http\Message\ResponseInterface;

/**
 * Rest client simplement implementation
 */
abstract class ClientLightService
{
    protected array $headers;

    protected string $baseUrl;

    protected ?ResponseInterface $response;

    /**
     * RestClient constructor.
     * @param string $baseUrl
     * @param array $headers
     */
    public function __construct(string $baseUrl, array $headers)
    {
        $this->setHeaders($headers);
        $this->baseUrl = $baseUrl;
    }

    /**
     * @param string $response
     * @return array
     */
    public function decodeResponse(string $response): array
    {
        return json_decode($response, true) ?? [];
    }

    /**
     * @param array $options
     * @return Client
     */
    public function getHttpClient(array $options = []): Client
    {
        return new Client(array_merge($options, [
            'verify' => true,
        ]));
    }

    /**
     * @param string $url
     * @param string $action
     * @param array $params
     * @param array $additionalHeaders
     * @return array
     * @throws GuzzleException
     * @throws ValidationException
     */
    protected function request(string $url, string $action, array $params, array $additionalHeaders = []): array
    {
        $this->response = null;
        $client = $this->getHttpClient();
        $options = [
            'headers' => array_merge($this->headers, $additionalHeaders),
        ];
        if (count($params) > 0) {
            $options['json'] = $params;
        }
        try {
            $this->response = $client->request($action, $this->baseUrl . $url, $options);
        } catch (ClientException $e) {
            if ($e->getCode() === 422) {
                Log::error('['.__CLASS__.'] code error: ' . $e->getCode(), [$e->getMessage(), $e->getTrace()]);
                throw ValidationException::withMessages(
                    json_decode($e->getResponse()->getBody()->getContents(), true)['errors'] ?? []
                );
            }
            throw $e;
        }
        return $this->decodeResponse($this->response->getBody()->getContents());
    }

    /**
     * @param array $headers
     * @return void
     */
    public function setHeaders(array $headers): void
    {
        $this->headers = $headers;
    }

    /**
     * @param string $url
     * @param array $params
     * @return array
     * @throws GuzzleException
     * @throws ValidationException
     */
    public function post(string $url, array $params): array
    {
        return $this->request($url, 'POST', $params);
    }

    /**
     * @param string $url
     * @param array $params
     * @return array
     * @throws GuzzleException
     * @throws ValidationException
     */
    public function put(string $url, array $params): array
    {
        return $this->request($url, 'PUT', $params);
    }

    /**
     * @param string $url
     * @param array $params
     * @return array
     * @throws GuzzleException
     * @throws ValidationException
     */
    public function delete(string $url, array $params): array
    {
        return $this->request($url, 'DELETE', $params);
    }

    /**
     * @return ResponseInterface|null
     */
    public function getResponse() : ?ResponseInterface
    {
        return $this->response;
    }

    /**
     * @return array
     */
    public function getHeaders() : array
    {
        return $this->headers;
    }
}
