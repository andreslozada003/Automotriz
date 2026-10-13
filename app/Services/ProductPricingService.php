<?php

namespace App\Services;

class ProductPricingService
{
    public const PURCHASE_TAX_RATE = 19.00;

    public const SALE_TAX_RATE = 19.00;

    public function calculate(float $netCost, float $margin, float $purchaseTaxRate = self::PURCHASE_TAX_RATE): array
    {
        $purchaseTaxAmount = round($netCost * ($purchaseTaxRate / 100), 2);
        $costWithPurchaseTax = round($netCost + $purchaseTaxAmount, 2);
        $salePriceBeforeTax = round($costWithPurchaseTax * (1 + ($margin / 100)), 2);
        $saleTaxAmount = round($salePriceBeforeTax * (self::SALE_TAX_RATE / 100), 2);

        return [
            'purchase_tax_rate' => $purchaseTaxRate,
            'purchase_tax_amount' => $purchaseTaxAmount,
            'cost_with_purchase_tax' => $costWithPurchaseTax,
            'profit_margin' => $margin,
            'sale_price' => $salePriceBeforeTax,
            'tax_rate' => self::SALE_TAX_RATE,
            'sale_tax_amount' => $saleTaxAmount,
            'customer_total' => round($salePriceBeforeTax + $saleTaxAmount, 2),
        ];
    }
}
