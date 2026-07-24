<?php

declare(strict_types=1);

namespace BeckonBilling\ApiClient\Exception;

use Psr\Http\Message\ResponseInterface;

/**
 * Base type for every error raised by the client.
 *
 * A failed API call carries the HTTP status, the stable `error.key`
 * (e.g. `send_not_permitted`, `organisation_is_required`) and the numeric
 * `error.code` from the `{ "error": { code, message, key } }` envelope, so a
 * consumer can branch on a stable key instead of parsing messages.
 */
class ApiException extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $statusCode = 0,
        private readonly ?string $errorKey = null,
        private readonly ?int $apiErrorCode = null,
        private readonly ?ResponseInterface $response = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $apiErrorCode ?? 0, $previous);
    }

    /** HTTP status code of the response (0 for transport-level failures). */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /** Stable `error.key` slug from the envelope, or null when absent. */
    public function getErrorKey(): ?string
    {
        return $this->errorKey;
    }

    /** Numeric `error.code` from the envelope, or null when absent. */
    public function getApiErrorCode(): ?int
    {
        return $this->apiErrorCode;
    }

    /** The raw PSR-7 response, when one was received. */
    public function getResponse(): ?ResponseInterface
    {
        return $this->response;
    }

    /**
     * Build the most specific exception subclass for a failed response.
     *
     * @param array<string,mixed>|null $decoded The JSON-decoded body, if any.
     */
    public static function fromResponse(ResponseInterface $response, ?array $decoded): self
    {
        $status = $response->getStatusCode();
        $error = is_array($decoded['error'] ?? null) ? $decoded['error'] : [];

        $message = is_string($error['message'] ?? null) && $error['message'] !== ''
            ? $error['message']
            : ($response->getReasonPhrase() ?: 'HTTP ' . $status);
        $key = is_string($error['key'] ?? null) ? $error['key'] : null;
        $code = is_int($error['code'] ?? null) ? $error['code'] : null;

        $class = match (true) {
            $status === 401 => AuthenticationException::class,
            $status === 403 => PermissionException::class,
            $status === 404 => NotFoundException::class,
            $status === 409 => ConflictException::class,
            $status === 422, $status === 400 => ValidationException::class,
            $status === 429 => RateLimitException::class,
            $status >= 500 => ServerException::class,
            default => self::class,
        };

        return new $class($message, $status, $key, $code, $response);
    }
}
