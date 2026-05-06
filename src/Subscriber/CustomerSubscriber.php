<?php declare(strict_types=1);

namespace Tinect\Matomo\Subscriber;

use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerRegisterEvent;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Tinect\Matomo\Tracking\ServerSideTracker;
use Tinect\Matomo\Tracking\TrackingPayloadBuilder;
use Tinect\Matomo\Tracking\VisitorIdResolver;

class CustomerSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ServerSideTracker $tracker,
        private readonly TrackingPayloadBuilder $payloadBuilder,
        private readonly VisitorIdResolver $visitorIdResolver,
        private readonly SystemConfigService $systemConfigService,
        private readonly RequestStack $requestStack
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CustomerRegisterEvent::class => 'onRegister',
            CustomerLoginEvent::class => 'onLogin',
        ];
    }

    public function onRegister(CustomerRegisterEvent $event): void
    {
        if (!$this->tracker->isServerOrHybridMode()) {
            return;
        }

        $visitorId = $this->visitorIdResolver->resolveForCustomerId($event->getCustomer()->getId());
        $url = $this->resolveUrl();

        $goalId = $this->systemConfigService->getInt('TinectMatomo.config.goalIdRegister');
        if ($goalId > 0) {
            $this->tracker->track($this->payloadBuilder->goal($url, $visitorId, $goalId));

            return;
        }

        $this->tracker->track($this->payloadBuilder->event(
            $url,
            $visitorId,
            'Account',
            'register'
        ));
    }

    public function onLogin(CustomerLoginEvent $event): void
    {
        if (!$this->tracker->isServerOrHybridMode()) {
            return;
        }

        $visitorId = $this->visitorIdResolver->resolveForCustomerId($event->getCustomer()->getId());

        $this->tracker->track($this->payloadBuilder->event(
            $this->resolveUrl(),
            $visitorId,
            'Account',
            'login'
        ));
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
