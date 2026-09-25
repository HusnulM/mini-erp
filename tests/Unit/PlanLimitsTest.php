<?php

namespace Tests\Unit;

use App\Support\Modules\PlanLimits;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PlanLimitsTest extends TestCase
{
    #[Test]
    public function null_means_unlimited_and_limits_are_exclusive(): void
    {
        $limits = new PlanLimits(users: 5, companies: null);

        $this->assertTrue($limits->allows('users', 4));
        $this->assertFalse($limits->allows('users', 5));
        $this->assertTrue($limits->allows('companies', 1000));
    }

    #[Test]
    public function unknown_limits_are_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new PlanLimits)->allows('warehouses', 1);
    }
}
