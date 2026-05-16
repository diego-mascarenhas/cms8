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

    /*
    |--------------------------------------------------------------------------
    | Email daily digest
    |--------------------------------------------------------------------------
    |
    | When true, admin/root users receive an email after the scheduled command
    | generates their daily insight (requires performance_insights module).
    |
    */

    'send_email' => (bool) env('DAILY_PERFORMANCE_INSIGHT_SEND_EMAIL', true),

];
