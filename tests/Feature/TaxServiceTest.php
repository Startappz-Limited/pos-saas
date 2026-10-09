<?php

use App\Enums\TaxClass;
use App\Models\Shop;
use App\Services\TaxService;

beforeEach(function () {
    config([
        'tax.enabled' => true,
        'tax.rates.standard' => 16.0,
        'tax.rates.reduced' => 8.0,
        'tax.default_class' => 'standard',
        'tax.prices_include_tax' => true,
        'tax.fees_taxable' => false,
    ]);

    $this->tax = new TaxService;
});

describe('rate resolution', function () {
    it('uses the configured Kenyan standard rate of 16%', function () {
        expect($this->tax->rateFor(TaxClass::Standard))->toBe(16.0);
    });

    it('charges nothing on zero rated and exempt supplies', function () {
        expect($this->tax->rateFor(TaxClass::ZeroRated))->toBe(0.0)
            ->and($this->tax->rateFor(TaxClass::Exempt))->toBe(0.0);
    });

    it('lets a shop override a rate in its settings', function () {
        $shop = new Shop(['settings' => ['tax' => ['rates' => ['standard' => 14.0]]]]);

        expect($this->tax->rateFor(TaxClass::Standard, $shop))->toBe(14.0);
    });
});

describe('inclusive pricing', function () {
    it('extracts VAT from a tax-inclusive shelf price', function () {
        // KSh 1,160 inclusive of 16% = KSh 1,000 net + KSh 160 VAT.
        $split = $this->tax->split(1160.00, TaxClass::Standard, inclusive: true);

        expect($split['tax'])->toBe(160.0)
            ->and($split['taxable'])->toBe(1000.0);
    });

    it('never adds tax on top of an inclusive price', function () {
        $result = $this->tax->calculate(
            lines: [['amount' => 1160.00, 'tax_class' => TaxClass::Standard]],
            inclusive: true,
        );

        expect($result->taxAmount + $result->taxableAmount)->toBe(1160.0);
    });
});

describe('exclusive pricing', function () {
    it('adds VAT on top of a net price', function () {
        $split = $this->tax->split(1000.00, TaxClass::Standard, inclusive: false);

        expect($split['tax'])->toBe(160.0)
            ->and($split['taxable'])->toBe(1000.0);
    });
});

describe('tax classes', function () {
    it('charges no VAT on an exempt line but still records its net value', function () {
        $result = $this->tax->calculate(
            lines: [['amount' => 500.00, 'tax_class' => TaxClass::Exempt]],
            inclusive: true,
        );

        expect($result->taxAmount)->toBe(0.0)
            ->and($result->taxableAmount)->toBe(500.0);
    });

    it('separates zero rated from exempt turnover, which the VAT return requires', function () {
        $result = $this->tax->calculate(
            lines: [
                ['amount' => 400.00, 'tax_class' => TaxClass::ZeroRated],
                ['amount' => 600.00, 'tax_class' => TaxClass::Exempt],
            ],
            inclusive: true,
        );

        expect($result->taxableSupplyAmount())->toBe(400.0)
            ->and($result->exemptSupplyAmount())->toBe(600.0);
    });

    it('mixes rates across lines in one sale', function () {
        $result = $this->tax->calculate(
            lines: [
                ['amount' => 1160.00, 'tax_class' => TaxClass::Standard],  // 160 VAT
                ['amount' => 500.00, 'tax_class' => TaxClass::ZeroRated],  // 0 VAT
                ['amount' => 300.00, 'tax_class' => TaxClass::Exempt],     // 0 VAT
            ],
            inclusive: true,
        );

        expect($result->taxAmount)->toBe(160.0)
            ->and($result->breakdown())->toHaveCount(3);
    });

    it('falls back to the default class when a product does not set one', function () {
        $result = $this->tax->calculate(
            lines: [['amount' => 1160.00, 'tax_class' => null]],
            inclusive: true,
        );

        expect($result->taxAmount)->toBe(160.0);
    });
});

describe('discounts', function () {
    it('charges VAT on the discounted consideration, not the list price', function () {
        // 1160 list less 160 discount = 1000 paid, of which 137.93 is VAT.
        $result = $this->tax->calculate(
            lines: [['amount' => 1160.00, 'tax_class' => TaxClass::Standard]],
            discount: 160.00,
            inclusive: true,
        );

        expect($result->taxAmount)->toBe(137.93)
            ->and($result->taxableAmount)->toBe(862.07);
    });

    it('apportions a sale-level discount across lines pro-rata', function () {
        $result = $this->tax->calculate(
            lines: [
                ['amount' => 750.00, 'tax_class' => TaxClass::Standard],
                ['amount' => 250.00, 'tax_class' => TaxClass::Standard],
            ],
            discount: 100.00,
            inclusive: true,
        );

        expect($result->lineFor(0)->discountShare)->toBe(75.0)
            ->and($result->lineFor(1)->discountShare)->toBe(25.0);
    });

    it('gives the rounding residue to the last line so the shares sum exactly', function () {
        $result = $this->tax->calculate(
            lines: [
                ['amount' => 100.00, 'tax_class' => TaxClass::Standard],
                ['amount' => 100.00, 'tax_class' => TaxClass::Standard],
                ['amount' => 100.00, 'tax_class' => TaxClass::Standard],
            ],
            discount: 10.00,
            inclusive: true,
        );

        $shares = array_map(fn ($line) => $line->discountShare, $result->lines);

        expect(round(array_sum($shares), 2))->toBe(10.0);
    });

    it('never lets a discount larger than the sale invert the lines', function () {
        $result = $this->tax->calculate(
            lines: [['amount' => 100.00, 'tax_class' => TaxClass::Standard]],
            discount: 500.00,
            inclusive: true,
        );

        expect($result->taxAmount)->toBe(0.0)
            ->and($result->taxableAmount)->toBe(0.0);
    });
});

describe('fees', function () {
    it('leaves recharged fees untaxed by default', function () {
        $result = $this->tax->calculate(
            lines: [['amount' => 1160.00, 'tax_class' => TaxClass::Standard]],
            fees: 500.00,
            inclusive: true,
        );

        expect($result->feeTaxAmount)->toBe(0.0)
            ->and($result->taxAmount)->toBe(160.0);
    });

    it('taxes recharged fees when the business opts in', function () {
        config(['tax.fees_taxable' => true, 'tax.fees_tax_class' => 'standard']);

        $result = $this->tax->calculate(
            lines: [['amount' => 1160.00, 'tax_class' => TaxClass::Standard]],
            fees: 580.00,
            inclusive: true,
        );

        expect($result->feeTaxAmount)->toBe(80.0)
            ->and($result->taxAmount)->toBe(240.0);
    });
});

describe('the shop gate', function () {
    it('stays off for a shop that is not VAT registered', function () {
        $shop = new Shop(['vat_registered' => false]);

        expect($this->tax->enabledFor($shop))->toBeFalse();
    });

    it('engages for a VAT registered shop', function () {
        $shop = new Shop(['vat_registered' => true]);

        expect($this->tax->enabledFor($shop))->toBeTrue();
    });

    it('stays off everywhere when the engine is disabled globally', function () {
        config(['tax.enabled' => false]);
        $shop = new Shop(['vat_registered' => true]);

        expect($this->tax->enabledFor($shop))->toBeFalse();
    });
});
