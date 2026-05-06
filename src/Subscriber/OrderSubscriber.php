<?php declare(strict_types=1);

namespace Tinect\Matomo\Subscriber;

use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Order\OrderEntity;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Tinect\Matomo\Tracking\ServerSideTracker;
use Tinect\Matomo\Tracking\TrackingPayloadBuilder;
use Tinect\Matomo\Tracking\VisitorIdResolver;

class OrderSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ServerSideTracker $tracker,
        private readonly TrackingPayloadBuilder $payloadBuilder,
        private readonly VisitorIdResolver $visitorIdResolver,
        private readonly RequestStack $requestStack
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutOrderPlacedEvent::class => 'onOrderPlaced',
        ];
    }

    public function onOrderPlaced(CheckoutOrderPlacedEvent $event): void
    {
        if (!$this->tracker->isServerOrHybridMode()) {
            return;
        }

        $order = $event->getOrder();
        $url = $this->resolveUrl();

        $customerId = $order->getOrderCustomer()?->getCustomerId();
        $visitorId = $customerId !== null
            ? $this->visitorIdResolver->resolveForCustomerId($customerId)
            : $this->visitorIdResolver->resolve(null);

        $payload = $this->payloadBuilder->ecommerceOrder(
            $url,
            $visitorId,
            $order->getOrderNumber() ?? $order->getId(),
            $order->getAmountTotal(),
            $order->getPositionPrice(),
            $order->getAmountTotal() - $order->getAmountNet(),
            $order->getShippingTotal(),
            0.0,
            $this->buildItems($order)
        );

        $this->tracker->track($payload);
    }

    /**
     * @return array<int, array{0: string, 1: string, 2: string, 3: float, 4: int}>
     */
    private function buildItems(OrderEntity $order): array
    {
        $items = [];
        $lineItems = $order->getLineItems();
        if ($lineItems === null) {
            return $items;
        }

        foreach ($lineItems as $lineItem) {
            $payload = $lineItem->getPayload() ?? [];
            $productNumber = $payload['productNumber'] ?? null;
            $sku = \is_string($productNumber) && $productNumber !== ''
                ? $productNumber
                : ($lineItem->getReferencedId() ?? $lineItem->getId());

            $items[] = [
                $sku,
                (string) $lineItem->getLabel(),
                '',
                (float) $lineItem->getUnitPrice(),
                (int) $lineItem->getQuantity(),
            ];
        }

        return $items;
    }

    private function resolveUrl(): string
    {
        $request = $this->requestStack->getMainRequest();
        if ($request === null) {
            return '';
        }

        return $request->getSchemeAndHttpHost() . $request->getRequestUri();
    }
}
