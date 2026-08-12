<?php

namespace Tests\Feature;

use App\Support\SatTaxonomy;
use Tests\TestCase;

class SatTaxonomyTest extends TestCase
{
    /**
     * Both teacher-facing surfaces are generated from config/sat_taxonomy.php.
     * They were hand-maintained copies before, which is how the guide ended up
     * naming skills the importer had never heard of.
     *
     * @return array<string, array{0: string}>
     */
    public static function taxonomyDrivenViewProvider(): array
    {
        return [
            'AI conversion prompt' => ['components.admin.test-builder.questions.ai-conversion-prompt'],
            'in-app import guide' => ['components.admin.test-builder.questions.import-guide-modal'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('taxonomyDrivenViewProvider')]
    public function test_it_lists_every_allowed_skill(string $view): void
    {
        $rendered = view($view)->render();

        foreach (SatTaxonomy::all() as $domains) {
            foreach ($domains as $domain => $subdomains) {
                $this->assertStringContainsString($domain, $rendered, "{$view} is missing domain {$domain}");

                foreach ($subdomains as $subdomain) {
                    $this->assertStringContainsString($subdomain, $rendered, "{$view} is missing subdomain {$subdomain}");
                }
            }
        }
    }

    public function test_every_subdomain_belongs_to_exactly_one_domain(): void
    {
        $seen = [];

        foreach (SatTaxonomy::all() as $domains) {
            foreach ($domains as $domain => $subdomains) {
                foreach ($subdomains as $subdomain) {
                    $this->assertArrayNotHasKey(
                        $subdomain,
                        $seen,
                        "Subdomain {$subdomain} appears under both {$domain} and ".($seen[$subdomain] ?? '?')
                    );
                    $seen[$subdomain] = $domain;

                    $this->assertSame($domain, SatTaxonomy::domainForSubdomain($subdomain));
                    $this->assertTrue(SatTaxonomy::isValidSubdomain($domain, $subdomain));
                }
            }
        }
    }

    public function test_domains_are_scoped_by_section(): void
    {
        $this->assertContains('algebra', SatTaxonomy::domains('math'));
        $this->assertNotContains('algebra', SatTaxonomy::domains('reading_writing'));
        $this->assertContains('algebra', SatTaxonomy::domains());

        $this->assertFalse(SatTaxonomy::isValidDomain('algebra', 'reading_writing'));
        $this->assertTrue(SatTaxonomy::isValidDomain('algebra', 'math'));
        $this->assertFalse(SatTaxonomy::isValidDomain('made_up_domain'));
    }
}
