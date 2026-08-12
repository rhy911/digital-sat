<?php

namespace App\Support;

/**
 * Read access to config/sat_taxonomy.php.
 *
 * Kept as a class rather than raw config() calls so the importer, the question
 * edit form, and the generated documentation all agree on what "valid" means.
 */
class SatTaxonomy
{
    /**
     * @return array<string, array<string, list<string>>>
     */
    public static function all(): array
    {
        return config('sat_taxonomy', []);
    }

    /**
     * Domains allowed for a section, or every domain when the section is
     * unknown (a module can be previewed before its section is resolved).
     *
     * @return list<string>
     */
    public static function domains(?string $sectionType = null): array
    {
        $taxonomy = self::all();

        if ($sectionType !== null && isset($taxonomy[$sectionType])) {
            return array_keys($taxonomy[$sectionType]);
        }

        return array_values(array_unique(array_merge(...array_map(
            'array_keys',
            array_values($taxonomy)
        ))));
    }

    /**
     * @return list<string>
     */
    public static function subdomains(string $domain): array
    {
        foreach (self::all() as $domains) {
            if (isset($domains[$domain])) {
                return $domains[$domain];
            }
        }

        return [];
    }

    public static function isValidDomain(string $domain, ?string $sectionType = null): bool
    {
        return in_array($domain, self::domains($sectionType), true);
    }

    public static function isValidSubdomain(string $domain, string $subdomain): bool
    {
        return in_array($subdomain, self::subdomains($domain), true);
    }

    /**
     * The domain a subdomain belongs to, or null when it belongs to none —
     * used to tell "wrong domain" apart from "invented skill" in error
     * messages.
     */
    public static function domainForSubdomain(string $subdomain): ?string
    {
        foreach (self::all() as $domains) {
            foreach ($domains as $domain => $subdomains) {
                if (in_array($subdomain, $subdomains, true)) {
                    return $domain;
                }
            }
        }

        return null;
    }
}
