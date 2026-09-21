<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Http;

use GoSuccess\UptimeRobot\Exception\TransportException;

/**
 * Minimal HTTP transport abstraction.
 *
 * The library ships with {@see CurlHttpClient} (no Composer dependencies). Provide
 * your own implementation to route requests through an existing HTTP stack, e.g.
 * Guzzle, without the library depending on it.
 */
interface HttpClient
{
    /**
     * Send a request and return the response.
     *
     * Implementations MUST:
     * - throw a {@see TransportException} for transport-level failures (connection
     *   errors, timeouts, …), but never for HTTP error status codes, which are
     *   returned as a normal {@see Response};
     * - return the response headers with lower-cased names;
     * - not follow redirects, so the API key is never sent to another host.
     *
     * @throws TransportException
     */
    public function send(Request $request): Response;
}
