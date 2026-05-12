<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Daily performance insight LLM
    |--------------------------------------------------------------------------
    |
    | When true, each new daily insight row uses Laravel AI (Anthropic) with
    | rolling metrics. On failure or when false, a short emergency template is used.
    |
    */

    'use_llm' => (bool) env('DAILY_PERFORMANCE_INSIGHT_USE_LLM', true),

];
