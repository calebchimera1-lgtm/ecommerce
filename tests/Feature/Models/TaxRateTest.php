<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\TaxRate;
use Tests\TestCase;

/**
 * Exercises the seeded tax_rates rows from database/kymera_collection.sql
 * (US Standard Sales Tax, California Sales Tax, New York Sales Tax,
 * Kenya VAT, UK VAT) rather than factory-built fixtures - tax_rates is
 * a small reference table with no per-test uniqueness concerns, same
 * as how the app itself only ever reads from it, never writes to it.
 */
final class TaxRateTest extends TestCase
{
    public function test_an_exact_state_match_is_preferred_over_the_country_rate(): void
    {
        $rate = TaxRate::forAddress('United States', 'California');

        $this->assertSame('California Sales Tax', $rate['name']);
        $this->assertSame('8.75', $rate['rate']);
    }

    public function test_state_matching_is_case_and_whitespace_insensitive(): void
    {
        $rate = TaxRate::forAddress('United States', '  california  ');

        $this->assertSame('California Sales Tax', $rate['name']);
    }

    public function test_a_state_with_no_specific_rate_falls_back_to_the_country_rate(): void
    {
        $rate = TaxRate::forAddress('United States', 'Texas');

        $this->assertSame('US Standard Sales Tax', $rate['name']);
        $this->assertNull($rate['state']);
    }

    public function test_no_state_given_falls_back_to_the_country_rate(): void
    {
        $rate = TaxRate::forAddress('United States', null);

        $this->assertSame('US Standard Sales Tax', $rate['name']);
    }

    public function test_a_country_with_no_configured_rate_falls_back_to_the_default(): void
    {
        $rate = TaxRate::forAddress('Nowhereland', null);

        $this->assertNotNull($rate);
        $this->assertSame(TaxRate::defaultRate()['id'], $rate['id']);
    }

    public function test_a_vat_country_without_state_rows_still_matches_on_country_alone(): void
    {
        $rate = TaxRate::forAddress('Kenya', null);

        $this->assertSame('Kenya VAT', $rate['name']);
    }
}
