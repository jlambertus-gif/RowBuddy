<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * ADR-027 Decision 5: the default `single` log channel now writes
 * structured JSON (Monolog's own JsonFormatter) rather than plain text —
 * configuration only, no new dependency. Proven by writing a uniquely
 * marked message and confirming its own log line round-trips through
 * json_decode, rather than merely asserting the config array's shape.
 */
it('writes a JSON-decodable line to the single log channel', function () {
    $marker = 'structured-logging-test-'.Str::uuid();

    Log::channel('single')->info($marker, ['probe' => true]);

    $path = storage_path('logs/laravel.log');
    expect(file_exists($path))->toBeTrue();

    $lines = array_filter(explode("\n", file_get_contents($path)));
    $matching = array_values(array_filter($lines, fn (string $line): bool => str_contains($line, $marker)));

    expect($matching)->not->toBeEmpty();

    $decoded = json_decode($matching[array_key_last($matching)], true);

    expect($decoded)->not->toBeNull()
        ->and($decoded['message'])->toBe($marker)
        ->and($decoded['context']['probe'])->toBeTrue();
});
