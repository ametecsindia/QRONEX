<?php

namespace App\Console\Commands;

use App\Services\Preflight as Checks;
use Illuminate\Console\Command;

class Preflight extends Command
{
    protected $signature = 'qronex:preflight';

    protected $description = 'Go-live checklist (exit code 1 if anything fails)';

    public function handle(): int
    {
        $rows = Checks::run();
        $this->table(['Area', 'Check', 'Status', 'Detail'], array_map(fn ($r) => [$r['area'], $r['check'], ['ok' => '<info>OK</info>', 'warn' => '<comment>WARN</comment>', 'fail' => '<error>FAIL</error>'][$r['status']], $r['detail']], $rows));
        $fails = count(array_filter($rows, fn ($r) => $r['status'] === 'fail'));
        $warns = count(array_filter($rows, fn ($r) => $r['status'] === 'warn'));
        $fails ? $this->error("$fails failing, $warns warnings — not ready to go live.") : $this->info("No failures ($warns warnings).");

        return $fails ? self::FAILURE : self::SUCCESS;
    }
}
