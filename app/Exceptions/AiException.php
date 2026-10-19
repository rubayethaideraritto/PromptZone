<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * An AI feature could not produce a result.
 *
 * The message returned by getMessage() is written for the end user: it never
 * contains the API key, the provider's raw payload, or any other internal
 * detail. Anything more verbose belongs in logDetail(), which is written to the
 * application log and not shown in the browser.
 *
 * The application never substitutes made-up content for a failed AI call. A
 * thrown AiException is always surfaced to the user as an error state.
 */
class AiException extends RuntimeException
{
    public function __construct(
        string $message,
        protected readonly string $logDetail = '',
    ) {
        parent::__construct($message);
    }

    /**
     * Verbose diagnosis for the application log.
     */
    public function logDetail(): string
    {
        return $this->logDetail !== '' ? $this->logDetail : $this->getMessage();
    }

    /**
     * The same failure, described in the caller's terms.
     *
     * Used so that the shared transport in GeminiClient can report what went
     * wrong while each feature adds what it means for the user, without either
     * one duplicating the other's HTTP handling.
     */
    public function withSuffix(string $suffix): self
    {
        return new self(
            rtrim($this->getMessage()).' '.trim($suffix),
            $this->logDetail()
        );
    }

    /**
     * No API key is configured, so no request can be made.
     */
    public static function notConfigured(): self
    {
        return new self(
            'AI features are not configured. Add GEMINI_API_KEY to the environment to enable them.'
        );
    }

    /**
     * The provider was unreachable, refused the request, or returned an error.
     *
     * @param  string  $message  Safe, user-facing summary.
     * @param  string  $detail  Full provider diagnosis for the log.
     */
    public static function requestFailed(string $message, string $detail = ''): self
    {
        return new self($message, $detail);
    }

    /**
     * The provider replied, but not with something that can be trusted.
     */
    public static function invalidResponse(string $message, string $detail = ''): self
    {
        return new self($message, $detail);
    }
}
