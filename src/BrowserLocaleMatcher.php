<?php

namespace Noo\CraftLocaleRedirect;

class BrowserLocaleMatcher
{
    /** @param array<int, string> $availableLocales */
    public function match(array $availableLocales, string $acceptLanguageHeader): ?string
    {
        if ($acceptLanguageHeader === '' || $availableLocales === []) {
            return null;
        }

        $preferences = $this->parseAcceptLanguage($acceptLanguageHeader);

        foreach ($preferences as $preferred) {
            foreach ($availableLocales as $locale) {
                if ($locale === $preferred || str_starts_with($preferred, $locale . '-') || str_starts_with($preferred, $locale . '_')) {
                    return $locale;
                }
            }
        }

        return null;
    }

    /** @return array<int, string> */
    private function parseAcceptLanguage(string $header): array
    {
        $entries = [];

        foreach (explode(',', $header) as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            $segments = explode(';', $part);
            $locale = strtolower(trim($segments[0]));
            $quality = 1.0;

            foreach (array_slice($segments, 1) as $segment) {
                $segment = trim($segment);
                if (str_starts_with($segment, 'q=')) {
                    $quality = (float) substr($segment, 2);
                }
            }

            $entries[] = ['locale' => $locale, 'quality' => $quality];
        }

        usort($entries, fn ($a, $b) => $b['quality'] <=> $a['quality']);

        return array_column($entries, 'locale');
    }
}
