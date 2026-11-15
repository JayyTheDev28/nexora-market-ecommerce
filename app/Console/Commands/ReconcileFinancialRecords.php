<?php

namespace App\Console\Commands;

use App\Services\FinancialReconciliationService;
use Illuminate\Console\Command;

class ReconcileFinancialRecords extends Command
{
    protected $signature = 'finance:reconcile {--company= : Limit check to a single logistics company ID} {--json : Output full JSON report}';

    protected $description = 'Safely audit cross-ledger COD, payout, and historical missing CodCollection records without fabricating financial entries.';

    public function handle(FinancialReconciliationService $reconciliation): int
    {
        $companyId = $this->option('company') ? (int) $this->option('company') : null;
        $report = $reconciliation->runReconciliationReport($companyId);

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return $report['critical_count'] > 0 ? self::FAILURE : self::SUCCESS;
        }

        $this->info('Nexora Financial Reconciliation Report @ ' . $report['checked_at']);
        $this->line(sprintf(
            'Status: %s | Critical: %d | Warnings: %d | Manual Review Required: %d',
            $report['status'],
            $report['critical_count'],
            $report['warning_count'],
            $report['manual_review_count']
        ));

        if (empty($report['issues'])) {
            $this->info('No integrity issues or unreviewed historical exceptions found.');

            return self::SUCCESS;
        }

        $rows = array_map(fn (array $issue) => [
            strtoupper($issue['severity']),
            $issue['check'],
            $issue['reference'],
            $issue['classification'] ?? 'n/a',
            $issue['message'],
        ], $report['issues']);

        $this->table(['Severity', 'Check', 'Reference', 'Classification', 'Details'], $rows);

        return $report['critical_count'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
