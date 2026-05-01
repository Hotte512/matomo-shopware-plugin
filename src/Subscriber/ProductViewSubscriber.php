<?php declare(strict_types=1);

namespace Tinect\Matomo\Subscriber;

use Shopware\Storefront\Page\Product\ProductPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Tinect\Matomo\Tracking\ServerSideTracker;
use Tinect\Matomo\Tracking\TrackingPayloadBuilder;
use Tinect\Matomo\Tracking\VisitorIdResolver;

class ProductViewSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ServerSideTracker $tracker,
        private readonly TrackingPayloadBuilder $payloadBuilder,
        private readonly VisitorIdResolver $visitorIdResolver
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductPageLoadedEvent::class => 'onProductPage',
        ];
    }

    public function onProductPage(ProductPageLoadedEvent $event): void
    {
        if (!$this->tracker->isServerMode()) {
            return;
        }

        $product = $event->getPage()->getProduct();
        $request = $event->getRequest();
        $url = $request->getSchemeAndHttpHost() . $request->getRequestUri();

        $productName = $this->resolveProductName($product->getTranslation('name'), $product->getName());
        $sku = $product->getProductNumber();

        $payload = $this->payloadBuilder->productView(
            $url,
            $productName !== '' ? $productName : $sku,
            $this->visitorIdResolver->resolve($event->getSalesChannelContext()),
            $sku,
            $productName,
            $this->resolveCategoryName($event)
        );

        $this->tracker->track($payload);
    }

    private function resolveProductName(mixed $translatedName, ?string $fallback): string
    {
        if (\is_string($translatedName) && $translatedName !== '') {
            return $translatedName;
        }

        return $fallback ?? '';
    }

    private function resolveCategoryName(ProductPageLoadedEvent $event): string
    {
        $categories = $event->getPage()->getProduct()->getCategories();
        if ($categories === null) {
            return '';
        }

        foreach ($categories as $category) {
            $name = $category->getTranslation('name');
            if (\is_string($name) && $name !== '') {
                return $name;
            }

            $fallback = $category->getName();
            if (\is_string($fallback) && $fallback !== '') {
                return $fallback;
            }
        }

        return '';
    }
}
