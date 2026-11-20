<?php

namespace App\Console\Commands;

use App\Actions\NotifyExpiringDocumentsAction;
use Illuminate\Console\Command;

class NotifyExpiringDocumentsCommand extends Command
{
    protected $signature = 'documents:notify-expiring {--due : Respeta la hora configurada por cada club}';

    protected $description = 'Avisa una vez cuando un documento entra en el plazo configurado por el club.';

    public function handle(NotifyExpiringDocumentsAction $action): int
    {
        $sent = $action->execute((bool) $this->option('due'));
        $this->info("Avisos enviados: {$sent}");

        return self::SUCCESS;
    }
}
