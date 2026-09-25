<?php

use Illuminate\Support\Facades\Schedule;

// TDD §7: registrations not verified within 7 days are deleted.
Schedule::command('erp:registrations:purge')->dailyAt('02:15')->onOneServer()->withoutOverlapping();
