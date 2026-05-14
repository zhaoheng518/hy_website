<?php

namespace App\Seo;

use App\Core\SEO;

/**
 * SEO engine façade for the MVC layer: meta, hreflang, JSON-LD, breadcrumbs.
 * Controllers depend on this type; implementation remains App\Core\SEO.
 */
final class SeoEngine
{
    private SEO $driver;

    public function __construct(string $lang)
    {
        $this->driver = new SEO($lang);
    }

    /**
     * Escape hatch for code that still expects App\Core\SEO (e.g. gradual refactors).
     */
    public function driver(): SEO
    {
        return $this->driver;
    }

    /**
     * @param mixed[] $args
     * @return mixed
     */
    public function __call(string $method, array $args)
    {
        if (!method_exists($this->driver, $method)) {
            throw new \BadMethodCallException('Unknown SEO method: ' . $method);
        }

        return $this->driver->$method(...$args);
    }
}
