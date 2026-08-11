<?php

namespace Youandme\Auth\Support;

/**
 * Builds the custom-scheme URLs that carry a person from a browser page back
 * into the mobile app.
 *
 * One place, because the scheme is configuration (MOBILE_DEEP_LINK_SCHEME) and
 * because these strings only ever appear in the handoff views - the mails
 * themselves must stay https, which is the whole reason the handoff exists.
 */
final class DeepLink
{
    /**
     * @param  array<string, string>  $query
     */
    public static function to(string $path, array $query = []): string
    {
        $scheme = (string) config('app.mobile_deep_link_scheme', 'jaity');
        $url = $scheme.'://'.$path;

        return $query === [] ? $url : $url.'?'.http_build_query($query);
    }
}
