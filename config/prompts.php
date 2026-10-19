<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Prompt vocabularies
    |--------------------------------------------------------------------------
    |
    | The single source of truth for the controlled values stored in
    | prompts.ai_model, prompts.language and prompts.visibility. Both the
    | validation rules and the form dropdowns read from here, so the options
    | offered and the options accepted can never drift apart.
    |
    | ai_model and language are stored as display strings, not codes, and these
    | values match the 200 imported sample rows exactly.
    |
    */

    'ai_models' => ['any', 'chatgpt', 'gemini', 'claude'],

    'languages' => ['English', 'Bangla'],

    'visibilities' => ['private', 'public'],

    /*
    |--------------------------------------------------------------------------
    | Input limits
    |--------------------------------------------------------------------------
    |
    | prompts.description is TEXT, which caps at 65,535 BYTES rather than
    | characters, so the limit here stays well below it for multi-byte scripts.
    |
    */

    'max_title_length' => 200,
    'max_description_length' => 2000,
    'max_prompt_text_length' => 20000,

    // tags.name is VARCHAR(80) and tags.slug VARCHAR(100).
    'max_tag_name_length' => 80,
    'max_tag_slug_length' => 100,

    // Combined length of the comma-separated tag input.
    'max_tag_input_length' => 500,

    /*
    |--------------------------------------------------------------------------
    | Ranking and recommendations
    |--------------------------------------------------------------------------
    */

    // How long the computed trending list is cached. Only the public list on the
    // landing page is cached; the library's trending sort is evaluated live so it
    // composes with the viewer's own visibility and filters.
    'trending_cache_minutes' => (int) env('TRENDING_CACHE_MINUTES', 10),

    // Number of prompts shown in each recommendation strip.
    'related_limit' => 4,
    'also_liked_limit' => 4,

    /*
    |--------------------------------------------------------------------------
    | Wilson lower bound
    |--------------------------------------------------------------------------
    |
    | A rating counts as a positive vote when it is at least this value. z is the
    | 95% confidence multiplier.
    |
    */

    'wilson_z' => 1.96,
    'wilson_positive_min' => 4,

    /*
    |--------------------------------------------------------------------------
    | Duplicate detection
    |--------------------------------------------------------------------------
    |
    | Similarity is the Jaccard overlap of the word sets in two texts, ranging
    | from 0 to 1. A prompt is reported as a possible duplicate when either
    | threshold is met. Both are advisory: the warning never blocks a save.
    |
    | The body threshold was calibrated by measurement against the sample data:
    | a close paraphrase scores 0.667, while the closest non-duplicate scores
    | 0.208. 0.6 sits inside that gap, so paraphrases are caught and unrelated
    | text is not.
    |
    */

    'duplicate_title_threshold' => (float) env('DUPLICATE_TITLE_THRESHOLD', 0.6),
    'duplicate_body_threshold' => (float) env('DUPLICATE_BODY_THRESHOLD', 0.6),

    // Words shorter than this are ignored when comparing texts.
    'duplicate_min_word_length' => 3,

    // At most this many possible duplicates are reported.
    'duplicate_max_matches' => 3,

    /*
    |--------------------------------------------------------------------------
    | Semantic search
    |--------------------------------------------------------------------------
    |
    | Candidates are ordered by cosine similarity, then filtered twice.
    |
    | An absolute floor alone does not work on this dataset. The 200 sample
    | prompts are generated from a single template, so any two of them already
    | score about 0.39, and the strongest match for a real query reaches only
    | about 0.58: the whole range is compressed into 0.19. Measured on one query,
    | a floor of 0.55 kept 1 prompt, 0.50 kept 4, and 0.45 kept 46 - so the number
    | is choosing an arbitrary result count rather than a relevance level.
    |
    | 'min_ratio_of_best' therefore does the real work: keep prompts within this
    | fraction of the best match's score. That adapts to the query instead of
    | assuming a scale the data does not have. 'min_similarity' remains as a floor
    | so that a query resembling nothing returns nothing rather than a ranked list
    | of unrelated prompts.
    |
    */

    'semantic_min_similarity' => (float) env('SEMANTIC_MIN_SIMILARITY', 0.30),
    'semantic_min_ratio_of_best' => (float) env('SEMANTIC_MIN_RATIO', 0.85),
];
