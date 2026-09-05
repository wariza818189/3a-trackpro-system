<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_landing_page_displays_the_application_identity(): void
    {
        $this->withoutVite();

        $this->get('/')
            ->assertOk()
            ->assertSee('3A TrackPro')
            ->assertSee('Hardware Store Sales and Inventory Management System');
    }

    public function test_business_clock_uses_manila_time(): void
    {
        $this->assertSame('Asia/Manila', config('app.timezone'));
        $this->assertSame('Asia/Manila', date_default_timezone_get());
        $this->assertSame('+08:00', now()->format('P'));
    }
}
