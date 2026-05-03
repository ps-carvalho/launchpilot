<?php

declare(strict_types=1);

namespace App\Dashboard\Http;

use Marko\Routing\Http\Request;

/**
 * Parses request bodies that may be sent as either form-encoded or JSON.
 *
 * This is a workaround for Marko's Request class, which only populates
 * $_POST for form-encoded submissions and does not parse JSON bodies
 * for POST requests (only PUT/PATCH/DELETE).
 */
class RequestBodyParser
{
    public function get(Request $request, string $key, mixed $default = null): mixed
    {
        $value = $request->post($key);
        if ($value !== null) {
            return $value;
        }

        $body = json_decode($request->body(), true);
        if (is_array($body) && array_key_exists($key, $body)) {
            return $body[$key];
        }

        return $default;
    }

    public function all(Request $request): array
    {
        $post = $request->post();
        if (!empty($post)) {
            return $post;
        }

        $body = json_decode($request->body(), true);
        return is_array($body) ? $body : [];
    }
}
