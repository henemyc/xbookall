<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

// Phase 5 isolated Laravel test base.
abstract class TestCase extends BaseTestCase
{
    // Laravel 11 discovers bootstrap/app.php automatically through CreatesApplication.
}
