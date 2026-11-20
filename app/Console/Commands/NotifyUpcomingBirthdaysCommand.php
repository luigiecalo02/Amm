<?php

namespace App\Console\Commands;

use App\Actions\NotifyUpcomingBirthdaysAction;
use Illuminate\Console\Command;

class NotifyUpcomingBirthdaysCommand extends Command
{
    protected $signature = 'birthdays:notify {--due : Respeta la hora configurada por cada club}';

    protected $description = 'Avisa una vez a la directiva cuando el cumpleaños de un miembro entra en el plazo del club.';

    public function handle(NotifyUpcomingBirthdaysAction $action): int
    {
        $sent = $action->execute((bool) $this->option('due'));
        $this->info("Avisos de cumpleaños enviados: {$sent}");

        return self::SUCCESS;
    }
}
