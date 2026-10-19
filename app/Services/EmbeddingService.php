<?php

namespace App\Services;

use App\Exceptions\AiException;
use App\Models\Prompt;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Embedding storage and similarity for semantic search.
 *
 * Vectors are kept in prompt_embeddings as JSON, one current row per prompt,
 * keyed by prompt_id as the schema defines. Each row records the model, the
 * width and a hash of the text it was built from, so a row whose prompt text has
 * since changed is recognised as stale and regenerated rather than silently
 * ranking on out-of-date content.
 *
 * Similarity is cosine distance computed in PHP. That is linear in the number of
 * candidate prompts and is only acceptable because the library is small: it is a
 * prototype, not a vector index. Beyond a few thousand prompts this needs a real
 * index (or a database with vector support) and the candidate set would have to
 * be narrowed before scoring.
 */
class EmbeddingService
{
    public function __construct(protected readonly GeminiClient $client) {}

    public function isConfigured(): bool
    {
        return $this->client->isConfigured();
    }

    public function model(): string
    {
        return (string) config('services.embeddings.model');
    }

    public function dimensions(): int
    {
        return (int) config('services.embeddings.dimensions');
    }

    /**
     * The text an embedding is built from: the same fields keyword search covers.
     */
    public function contentFor(Prompt $prompt): string
    {
        return trim($prompt->title."\n".(string) $prompt->description."\n".$prompt->prompt_text);
    }

    public function hashFor(Prompt $prompt): string
    {
        return hash('sha256', $this->contentFor($prompt));
    }

    /**
     * Embed a search query.
     *
     * @return list<float>
     *
     * @throws AiException
     */
    public function embedQuery(string $text): array
    {
        $vectors = $this->embedBatch([$text]);

        if ($vectors === []) {
            throw AiException::invalidResponse(
                'The embedding service returned no vector for the query.',
                'embedQuery(): empty embedding list'
            );
        }

        return $vectors[0];
    }

    /**
     * Embed several texts in one request.
     *
     * @param  list<string>  $texts
     * @return list<list<float>>
     *
     * @throws AiException
     */
    public function embedBatch(array $texts): array
    {
        if (! $this->isConfigured()) {
            throw AiException::notConfigured();
        }

        if ($texts === []) {
            return [];
        }

        $model = $this->model();
        $dimensions = $this->dimensions();

        $payload = [
            'requests' => array_map(fn (string $text) => [
                'model' => 'models/'.$model,
                'content' => ['parts' => [['text' => $text]]],
                // Truncated output keeps the stored vector compact. Cosine is
                // scale invariant, so no re-normalisation is required.
                'outputDimensionality' => $dimensions,
            ], array_values($texts)),
        ];

        // Transport and error translation are GeminiClient's job; only what a
        // failure means for semantic search belongs here.
        try {
            $data = $this->client->post($model, 'batchEmbedContents', $payload, 'embedding');
        } catch (AiException $exception) {
            throw $exception->withSuffix('Semantic search is unavailable.');
        }

        $embeddings = $data['embeddings'] ?? null;

        if (! is_array($embeddings) || count($embeddings) !== count($texts)) {
            throw AiException::invalidResponse(
                'The embedding service returned an unexpected number of vectors.',
                'requested '.count($texts).', received '.(is_array($embeddings) ? count($embeddings) : 0)
            );
        }

        $vectors = [];

        foreach ($embeddings as $embedding) {
            $values = $embedding['values'] ?? null;

            if (! is_array($values) || $values === []) {
                throw AiException::invalidResponse(
                    'The embedding service returned an empty vector.',
                    'empty values in batch response'
                );
            }

            $vectors[] = array_map(fn ($value) => (float) $value, array_values($values));
        }

        return $vectors;
    }

    /**
     * Whether a prompt needs (re-)embedding.
     */
    public function needsSync(Prompt $prompt): bool
    {
        $row = DB::table('prompt_embeddings')->where('prompt_id', $prompt->getKey())->first();

        if ($row === null) {
            return true;
        }

        // A row built by a different model or width is not comparable with
        // vectors produced now, so it counts as stale.
        return $row->content_hash !== $this->hashFor($prompt)
            || $row->model_name !== $this->model()
            || (int) $row->dimensions !== $this->dimensions();
    }

    /**
     * Store or replace the embedding for a prompt.
     *
     * @param  list<float>  $vector
     */
    public function store(Prompt $prompt, array $vector): void
    {
        DB::table('prompt_embeddings')->updateOrInsert(
            ['prompt_id' => $prompt->getKey()],
            [
                'provider' => 'gemini',
                'model_name' => $this->model(),
                'dimensions' => count($vector),
                'vector_json' => json_encode($vector),
                'content_hash' => $this->hashFor($prompt),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    /**
     * Every stored vector for the given prompts, keyed by prompt id.
     *
     * @param  list<int>  $promptIds
     * @return array<int, list<float>>
     */
    public function vectorsFor(array $promptIds): array
    {
        if ($promptIds === []) {
            return [];
        }

        $vectors = [];

        // Chunked so a large id list cannot exceed the placeholder limit.
        foreach (array_chunk($promptIds, 500) as $chunk) {
            $rows = DB::table('prompt_embeddings')
                ->whereIn('prompt_id', $chunk)
                ->where('model_name', $this->model())
                ->where('dimensions', $this->dimensions())
                ->get(['prompt_id', 'vector_json']);

            foreach ($rows as $row) {
                $decoded = json_decode((string) $row->vector_json, true);

                if (is_array($decoded) && $decoded !== []) {
                    $vectors[(int) $row->prompt_id] = array_map('floatval', $decoded);
                }
            }
        }

        return $vectors;
    }

    /**
     * Cosine similarity, from -1 to 1 for these vectors but in practice 0 to 1
     * because the provider returns non-negative embeddings.
     *
     * @param  list<float>  $a
     * @param  list<float>  $b
     */
    public function cosineSimilarity(array $a, array $b): float
    {
        $length = min(count($a), count($b));

        // Vectors of different widths are not comparable; reporting 0 is
        // safer than comparing a prefix and inventing a similarity.
        if ($length === 0 || count($a) !== count($b)) {
            return 0.0;
        }

        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0; $i < $length; $i++) {
            $dot += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        if ($normA <= 0.0 || $normB <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }

    /**
     * How much of the eligible set is actually indexed.
     *
     * Surfaced in the interface so a partly populated index is never presented as
     * a complete semantic search.
     *
     * @return array{indexed: int, eligible: int}
     */
    public function coverage(Collection|array $eligibleIds): array
    {
        $ids = is_array($eligibleIds) ? $eligibleIds : $eligibleIds->all();

        if ($ids === []) {
            return ['indexed' => 0, 'eligible' => 0];
        }

        $indexed = 0;

        foreach (array_chunk($ids, 500) as $chunk) {
            $indexed += DB::table('prompt_embeddings')
                ->whereIn('prompt_id', $chunk)
                ->where('model_name', $this->model())
                ->where('dimensions', $this->dimensions())
                ->count();
        }

        return ['indexed' => $indexed, 'eligible' => count($ids)];
    }
}
