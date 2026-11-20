<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('documents:notify-expiring --due')->everyMinute()->withoutOverlapping();
Schedule::command('birthdays:notify --due')->everyMinute()->withoutOverlapping();
