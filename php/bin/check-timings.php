<?php

declare(strict_types=1);

/**
 * Per-test time budget: fails if any test takes longer than the allowed wall time.
 *
 * Reads a PHPUnit JUnit log. Run it against an UNINSTRUMENTED run (`phpunit --no-coverage`):
 * Xdebug's branch/path collection adds a fixed ~170ms of post-processing to every test, which
 * measures the profiler rather than the test. The coverage gate is a separate step for exactly
 * that reason — see bin/check-coverage.php.
 *
 * Usage: php bin/check-timings.php [junit.xml] [budget-seconds]
 */

$logPath = $argv[1] ?? __DIR__ . '/../build/junit.xml';
$budget = (float) ($argv[2] ?? 0.2);

if (!is_file($logPath)) {
    fwrite(STDERR, "JUnit log not found: {$logPath}\n");
    exit(1);
}

$xml = simplexml_load_file($logPath);
if ($xml === false) {
    fwrite(STDERR, "Could not parse JUnit log: {$logPath}\n");
    exit(1);
}

$slow = [];
$count = 0;
$slowest = 0.0;

foreach ($xml->xpath('//testcase') ?: [] as $testcase) {
    $time = (float) ($testcase['time'] ?? 0);
    $count++;
    $slowest = max($slowest, $time);
    if ($time > $budget) {
        $slow[] = sprintf('%s::%s (%.1f ms)', (string) $testcase['classname'], (string) $testcase['name'], $time * 1000);
    }
}

printf("Checked %d tests; slowest %.1f ms (budget %.0f ms)\n", $count, $slowest * 1000, $budget * 1000);

if ($slow !== []) {
    echo "FAIL: tests over the per-test budget:\n";
    foreach ($slow as $entry) {
        echo "  {$entry}\n";
    }
    exit(1);
}

if ($count === 0) {
    fwrite(STDERR, "FAIL: the JUnit log contained no tests\n");
    exit(1);
}

echo "All tests within the per-test time budget\n";
