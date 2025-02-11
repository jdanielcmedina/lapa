<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Lapa\Lapa;

class LapaTest extends TestCase
{
    public function test_lapa_can_be_instantiated()
    {
        $app = new Lapa();
        $this->assertInstanceOf(Lapa::class, $app);
    }
} 