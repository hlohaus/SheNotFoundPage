<?php declare(strict_types=1);

namespace She\NotFoundPage\Components;

use Shopware\Core\Framework\Seo\SeoResolver as CoreSeoResolver;
use Shopware\Storefront\Framework\Seo\SeoResolver as StorefrontSeoResolver;

if (!class_exists(CoreSeoResolver::class)) {
    class_alias(StorefrontSeoResolver::class, CoreSeoResolver::class);
}

class SeoResolver extends CoreSeoResolver
{

}