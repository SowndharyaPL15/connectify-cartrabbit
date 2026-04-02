<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI Provider
    |--------------------------------------------------------------------------
    | Supported: "openai", "groq", "gemini"
    */
    'provider'   => env('AI_PROVIDER', 'groq'),
    'openai_key' => env('OPENAI_API_KEY'),
    'groq_key'   => env('GROQ_API_KEY'),
    'gemini_key' => env('GEMINI_API_KEY'),
];
