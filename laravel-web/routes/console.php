<?php

use Illuminate\Support\Facades\Schedule;

// Cashfree credential commands
use App\Console\Commands\CashfreeSetSandbox;
use App\Console\Commands\CashfreeSetProduction;

// Register Cashfree commands (Laravel 11 safe)
app()->singleton(CashfreeSetSandbox::class);
app()->singleton(CashfreeSetProduction::class);

// Schedule::command('inspire')->hourly();
