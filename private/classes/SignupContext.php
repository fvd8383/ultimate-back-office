<?php

final class SignupContext
{
    private const SUPPORTED_PRODUCTS = ['247sp'];

    public static function fromRequest(array $post, array $query): ?string
    {
        return self::normalize($post['product'] ?? $query['product'] ?? null);
    }

    public static function normalize($product): ?string
    {
        $normalized = strtolower(trim((string) $product));

        return in_array($normalized, self::SUPPORTED_PRODUCTS, true) ? $normalized : null;
    }

    public static function query(?string $product, array $extra = []): string
    {
        $parameters = $extra;
        $normalized = self::normalize($product);

        if ($normalized !== null) {
            $parameters['product'] = $normalized;
        }

        return $parameters === [] ? '' : '?' . http_build_query($parameters);
    }

    public static function destination(?string $product): string
    {
        return self::normalize($product) === '247sp'
            ? 'business-create.php?product=247sp'
            : 'dashboard.php';
    }

    public static function is247sp(?string $product): bool
    {
        return self::normalize($product) === '247sp';
    }
}
