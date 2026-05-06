<?php declare(strict_types=1);

namespace Tinect\Matomo\Subscriber;

use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Page\Checkout\Cart\CheckoutCartPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Tinect\Matomo\Tracking\ServerSideTracker;
use Tinect\Matomo\Tracking\TrackingPayloadBuilder;
use Tinect\Matomo\Tracking\VisitorIdResolver;

class CheckoutFunnelSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ServerSideTracker $tracker,
        private readonly TrackingPayloadBuilder $payloadBuilder,
        private readonly VisitorIdResolver $visitorIdResolver,
        private readonly SystemConfigService $systemConfigService
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutCartPageLoadedEvent::class => 'onCart',
            CheckoutConfirmPageLoadedEvent::class => 'onConfirm',
        ];
    }

    public function onCart(CheckoutCartPageLoadedEvent $event): void
    {
        if (!$this->tracker->isServerOrHybridMode()) {
            return;
        }

        $request = $event->getRequest();
        $url = $request->getSchemeAndHttpHost() . $request->getRequestUri();
        $visitorId = $this->visitorIdResolver->resolve($event->getSalesChannelContext());
        $cart = $event->getPage()->getCart();

        $this->tracker->track($this->payloadBuilder->pageView(
            $url,
            'checkout/cart',
            $visitorId
        ));

        $goalId = $this->systemConfigService->getInt('TinectMatomo.config.goalIdCartView');
        if ($goalId > 0) {
            $this->tracker->track($this->payloadBuilder->goal(
                $url,
                $visitorId,
                $goalId,
                $cart->getPrice()->getTotalPrice()
            ));
        }
    }

    public function onConfirm(CheckoutConfirmPageLoadedEvent $event): void
    {
        if (!$this->tracker->isServerOrHybridMode()) {
            return;
        }

        $request = $event->getRequest();
        $url = $request->getSchemeAndHttpHost() . $request->getRequestUri();
        $visitorId = $this->visitorIdResolver->resolve($event->getSalesChannelContext());

        $this->tracker->track($this->payloadBuilder->pageView(
            $url,
            'checkout/confirm',
            $visitorId
        ));

        $goalId = $this->systemConfigService->getInt('TinectMatomo.config.goalIdCheckoutConfirm');
        if ($goalId > 0) {
            $this->tracker->track($this->payloadBuilder->goal(
                $url,
                $visitorId,
                $goalId
            ));
        }
    }
}
