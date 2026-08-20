<?php

declare(strict_types=1);

/**
 * Project-level coverage gate: fails unless line AND branch coverage both reach 90%.
 *
 * Reads the raw php-code-coverage object dumped by PHPUnit (--coverage-php) and computes
 * real line and branch percentages from Xdebug data (the Clover writer omits branches).
 *
 * Usage: php bin/check-coverage.php [coverage.php] [threshold]
 */

use SebastianBergmann\CodeCoverage\CodeCoverage;

$coveragePath = $argv[1] ?? __DIR__ . '/../build/coverage/coverage.php';
$threshold = (float) ($argv[2] ?? 90.0);

if (!is_file($coveragePath)) {
    fwrite(STDERR, "Coverage dump not found: {$coveragePath}\n");
    exit(1);
}

require __DIR__ . '/../vendor/autoload.php';

/** @var CodeCoverage $coverage */
$coverage = require $coveragePath;
$report = $coverage->getReport();

$executableLines = $report->numberOfExecutableLines();
$executedLines = $report->numberOfExecutedLines();
$executableBranches = $report->numberOfExecutableBranches();
$executedBranches = $report->numberOfExecutedBranches();

$linePct = $executableLines > 0 ? ($executedLines / $executableLines) * 100 : 100.0;
$branchPct = $executableBranches > 0 ? ($executedBranches / $executableBranches) * 100 : 100.0;

printf("Lines:    %.2f%% (%d/%d)\n", $linePct, $executedLines, $executableLines);
printf("Branches: %.2f%% (%d/%d)\n", $branchPct, $executedBranches, $executableBranches);

$fail = false;
if ($linePct < $threshold) {
    printf("FAIL: line coverage %.2f%% is below %.0f%%\n", $linePct, $threshold);
    $fail = true;
}
if ($branchPct < $threshold) {
    printf("FAIL: branch coverage %.2f%% is below %.0f%%\n", $branchPct, $threshold);
    $fail = true;
}

exit($fail ? 1 : 0);
