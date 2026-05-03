<?php

declare(strict_types=1);

namespace App\Dashboard\Helper;

use Marko\Routing\Http\Request;

class JsonInput
{
    public static function get(Request $request, string $key, mixed $default = null): mixed
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

    public static function all(Request $request): array
    {
        $post = $request->post();
        if (!empty($post)) {
            return $post;
        }

        $body = json_decode($request->body(), true);
        return is_array($body) ? $body : [];
    }
}
