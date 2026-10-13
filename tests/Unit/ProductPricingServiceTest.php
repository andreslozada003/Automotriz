<?php

namespace Tests\Unit;

use App\Services\ProductPricingService;
use PHPUnit\Framework\TestCase;

class ProductPricingServiceTest extends TestCase
{
    public function test_it_calculates_purchase_tax_margin_and_sale_tax_in_order(): void
    {
        $pricing = (new ProductPricingService)->calculate(1050, 30);

        $this->assertSame(199.50, $pricing['purchase_tax_amount']);
        $this->assertSame(1249.50, $pricing['cost_with_purchase_tax']);
        $this->assertSame(1624.35, $pricing['sale_price']);
        $this->assertSame(308.63, $pricing['sale_tax_amount']);
        $this->assertSame(1932.98, $pricing['customer_total']);
    }

    public function test_it_accepts_a_decimal_margin(): void
    {
        $pricing = (new ProductPricingService)->calculate(100000, 37.5);

        $this->assertSame(163625.00, $pricing['sale_price']);
        $this->assertSame(194713.75, $pricing['customer_total']);
    }
}
