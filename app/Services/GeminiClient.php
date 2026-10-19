<?php

namespace App\Services;

use App\Exceptions\AiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The single place that speaks to the Gemini REST API.
 *
 * Both consumers - structured text generation and embeddings - differ only in
 * which endpoint they call and how they read the reply. Everything else (the API
 * key header, timeouts, the retry policy, and the translation of provider errors
 * into a safe AiException) lives here, so the two services cannot drift apart in
 * how they authenticate or how they fail.
 *
 * The API key is only ever sent as the x-goog-api-key header. It never appears in
 * a URL, in a log line, or in an exception message.
 */
class GeminiClient
{
    /**
     * Statuses worth repeating: the provider was busy and produced nothing, so a
     * retry costs no output. A 400 or 404 is a configuration fault that would
     * fail identically on the next attempt and is not retried.
     */
    protected const RETRYABLE_STATUSES = [429, 500, 502, 503, 504];

    public function isConfigured(): bool
    {
        return filled(config('services.gemini.key'));
    }

    /**
     * Call a model endpoint and return the decoded JSON body.
     *
     * @param  string  $model  e.g. "gemini-2.5-flash"
     * @param  string  $method  endpoint suffix, e.g. "generateContent"
     * @param  array<string, mixed>  $payload
     * @param  string  $serviceName  how to refer to this service in a user-facing error
     * @return array<string, mixed>
     *
     * @throws AiException
     */
    public function post(string $model, string $method, array $payload, string $serviceName = 'AI'): array
    {
        if (! $this->isConfigured()) {
            throw AiException::notConfigured();
        }

        $url = rtrim((string) config('services.gemini.base_url'), '/')
            .'/models/'.$model.':'.$method;

        try {
            $response = Http::withHeaders(['x-goog-api-key' => (string) config('services.gemini.key')])
                ->acceptJson()
                ->asJson()
                ->connectTimeout((int) config('services.gemini.connect_timeout'))
                ->timeout((int) config('services.gemini.timeout'))
                ->retry(
                    max(1, (int) config('services.gemini.retry_attempts')),
                    max(0, (int) config('services.gemini.retry_delay_ms')),
                    fn (Throwable $exception) => $this->shouldRetry($exception),
                    throw: false
                )
                ->post($url, $payload);
        } catch (ConnectionException $exception) {
            Log::warning('AI request could not reach the provider.', [
                'model' => $model,
                'detail' => $exception->getMessage(),
            ]);

            throw AiException::requestFailed(
                'The '.$serviceName.' service could not be reached. Check your connection, or try again shortly.',
                $exception->getMessage()
            );
        } catch (RequestException $exception) {
            // The retry helper surfaces a final failed attempt this way in some
            // paths, so it is handled rather than escaping as a 500.
            if ($exception->response instanceof Response) {
                throw $this->failedResponse($exception->response, $serviceName);
            }

            throw AiException::requestFailed(
                'The '.$serviceName.' request failed.',
                $exception->getMessage()
            );
        }

        if ($response->failed()) {
            throw $this->failedResponse($response, $serviceName);
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw AiException::invalidResponse(
                'The '.$serviceName.' service returned a response that could not be read.',
                'Non-JSON body: '.mb_substr($response->body(), 0, 500)
            );
        }

        return $data;
    }

    /**
     * Whether a failed attempt is worth repeating.
     */
    protected function shouldRetry(Throwable $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        $status = $exception instanceof RequestException
            ? $exception->response?->status()
            : null;

        return in_array($status, self::RETRYABLE_STATUSES, true);
    }

    /**
     * Translate a provider error into a message safe to show a user, keeping the
     * provider's own wording for the log only.
     */
    protected function failedResponse(Response $response, string $serviceName): AiException
    {
        $status = $response->status();
        $providerMessage = $response->json('error.message');
        $providerMessage = is_string($providerMessage) ? $providerMessage : '';

        $message = match (true) {
            $status === 400 => 'The '.$serviceName.' service rejected the request. The API key may be invalid.',
            $status === 401, $status === 403 => 'The '.$serviceName.' service refused the API key.',
            $status === 404 => 'The configured '.$serviceName.' model was not found. Check the model setting in your environment.',
            $status === 429 => 'The '.$serviceName.' service rate limit was reached. Please wait a moment and try again.',
            $status >= 500 => 'The '.$serviceName.' service is temporarily unavailable. Please try again shortly.',
            default => 'The '.$serviceName.' request failed.',
        };

        Log::warning('AI request failed.', [
            'model' => (string) config('services.gemini.model'),
            'status' => $status,
            'provider_message' => $providerMessage,
        ]);

        return AiException::requestFailed(
            $message,
            'HTTP '.$status.': '.$providerMessage
        );
    }
}
