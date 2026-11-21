<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_application_structure_is_ready(): void
    {
        assert(file_exists(dirname(__DIR__, 2) . '/public/index.php'));
    }
}
