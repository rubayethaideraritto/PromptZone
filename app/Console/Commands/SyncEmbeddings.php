<?php

namespace App\Console\Commands;

use App\Exceptions\AiException;
use App\Models\Prompt;
use App\Services\EmbeddingService;
use Illuminate\Console\Command;

/**
 * Builds or refreshes the vectors that semantic search ranks against.
 *
 * Only prompts whose text has changed since they were last embedded are sent, so
 * re-running this is cheap. Use --force to rebuild everything, for example after
 * changing the embedding model or its width.
 *
 * Nothing here is automatic: embedding a library costs API calls, so it is an
 * explicit action rather than something a page load triggers.
 */
class SyncEmbeddings extends Command
{
    protected $signature = 'embeddings:sync
        {--force : Re-embed every prompt, even when its text has not changed}
        {--limit=0 : Stop after this many prompts (0 means no limit)}';

    protected $description = 'Generate or refresh prompt embeddings for semantic search';

    public function handle(EmbeddingService $embeddings): int
    {
        if (! $embeddings->isConfigured()) {
            $this->error('No AI API key is configured. Set GEMINI_API_KEY in .env first.');

            return self::FAILURE;
        }

        $force = (bool) $this->option('force');
        $limit = max(0, (int) $this->option('limit'));

        $this->info(sprintf(
            'Embedding with %s at %d dimensions%s.',
            $embeddings->model(),
            $embeddings->dimensions(),
            $force ? ' (forced rebuild)' : ''
        ));

        $pending = [];
        $considered = 0;

        // The staleness test compares a stored hash, which SQL cannot compute, so
        // candidates are read in chunks and filtered in PHP.
        Prompt::query()
            ->select(['id', 'title', 'description', 'prompt_text'])
            ->orderBy('id')
            ->chunkById(200, function ($prompts) use ($embeddings, $force, $limit, &$pending, &$considered) {
                foreach ($prompts as $prompt) {
                    $considered++;

                    if ($force || $embeddings->needsSync($prompt)) {
                        $pending[] = $prompt;
                    }
                }

                if ($limit > 0 && count($pending) >= $limit) {
                    $pending = array_slice($pending, 0, $limit);

                    return false;
                }

                return true;
            });

        if ($pending === []) {
            $this->info(sprintf('Nothing to do: all %d prompts are already up to date.', $considered));

            return self::SUCCESS;
        }

        $batchSize = max(1, (int) config('services.embeddings.batch_size'));
        $pauseMs = max(0, (int) config('services.embeddings.batch_pause_ms'));
        $stored = 0;
        $failed = 0;
        $batchIndex = 0;

        if ($pauseMs > 0) {
            $this->line(sprintf(
                'Pacing at %d prompt(s) every %ss to stay inside the provider rate limit.',
                $batchSize,
                number_format($pauseMs / 1000, 1)
            ));
        }

        // Skipped when output is piped or quiet, so logs stay clean. Interaction
        // state lives on the input, not the output, which is why the decoration
        // flag is the right check here.
        $showProgress = ! $this->output->isQuiet() && $this->output->isDecorated();

        if ($showProgress) {
            $this->output->progressStart(count($pending));
        }

        foreach (array_chunk($pending, $batchSize) as $batch) {
            // Wait before every batch but the first, so the per-minute allowance
            // has room. Without this the provider answers 429 and the whole run
            // fails after the first batch.
            if ($batchIndex > 0 && $pauseMs > 0) {
                usleep($pauseMs * 1000);
            }

            $batchIndex++;

            try {
                $vectors = $embeddings->embedBatch(array_map(
                    fn (Prompt $prompt) => $embeddings->contentFor($prompt),
                    $batch
                ));
            } catch (AiException $exception) {
                // Report and stop rather than pressing on: the next batch would
                // almost certainly fail the same way.
                $this->newLine(2);
                $this->error($exception->getMessage());
                $failed += count($batch);

                break;
            }

            foreach ($batch as $index => $prompt) {
                if (! isset($vectors[$index])) {
                    $failed++;

                    continue;
                }

                $embeddings->store($prompt, $vectors[$index]);
                $stored++;
            }

            if ($showProgress) {
                $this->output->progressAdvance(count($batch));
            }
        }

        if ($showProgress) {
            $this->output->progressFinish();
        }

        $this->newLine();

        $this->info(sprintf(
            'Stored %d embedding(s); %d of %d prompt(s) considered.%s',
            $stored,
            $considered,
            Prompt::count(),
            $failed > 0 ? ' '.$failed.' failed.' : ''
        ));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
