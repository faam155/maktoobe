<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('notifications:dispatch')->everyMinute()->withoutOverlapping();
Schedule::command('audit:configuration')->hourly()->withoutOverlapping();
