<?php declare(strict_types=1);

namespace Tinect\Matomo\Subscriber;

use Shopware\Core\Checkout\Cart\Event\BeforeLineItemAddedEvent;
use Shopware\Core\Checkout\Cart\Event\BeforeLineItemRemovedEvent;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Tinect\Matomo\Tracking\ServerSideTracker;
use Tinect\Matomo\Tracking\TrackingPayloadBuilder;
use Tinect\Matomo\Tracking\VisitorIdResolver;

class CartSubscriber implements EventSubscriberInterface
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
            BeforeLineItemAddedEvent::class => 'onAdd',
            BeforeLineItemRemovedEvent::class => 'onRemove',
        ];
    }

    public function onAdd(BeforeLineItemAddedEvent $event): void
    {
        $this->trackCartEvent('add', $event->getLineItem(), $event->getSalesChannelContext());
    }

    public function onRemove(BeforeLineItemRemovedEvent $event): void
    {
        $this->trackCartEvent('remove', $event->getLineItem(), $event->getSalesChannelContext());
    }

    private function trackCartEvent(string $action, LineItem $lineItem, SalesChannelContext $context): void
    {
        if (!$this->tracker->isServerOrHybridMode()) {
            return;
        }

        $payload = $lineItem->getPayload();
        $productNumber = $payload['productNumber'] ?? null;

        if (\is_string($productNumber) && $productNumber !== '') {
            $sku = $productNumber;
        } else {
            $referencedId = $lineItem->getReferencedId();
            $sku = ($referencedId !== null && $referencedId !== '') ? $referencedId : $lineItem->getId();
        }

        $this->tracker->track($this->payloadBuilder->event(
            '',
            $this->visitorIdResolver->resolve($context),
            'Cart',
            $action,
            $sku,
            $lineItem->getQuantity()
        ));
    }
}
