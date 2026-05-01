<?php declare(strict_types=1);

namespace Tinect\Matomo\Tracking;

use Shopware\Core\System\SystemConfig\SystemConfigService;

class TrackingPayloadBuilder
{
    public function __construct(
        private readonly SystemConfigService $systemConfigService
    ) {
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    public function pageView(string $url, string $actionName, string $visitorId, array $extra = []): array
    {
        return $this->base($visitorId, [
            'url' => $url,
            'action_name' => $actionName,
        ]) + $extra;
    }

    /**
     * @return array<string, mixed>
     */
    public function productView(
        string $url,
        string $actionName,
        string $visitorId,
        string $productSku,
        string $productName,
        string $categoryName = ''
    ): array {
        return $this->base($visitorId, [
            'url' => $url,
            'action_name' => $actionName,
            '_pks' => $productSku,
            '_pkn' => $productName,
            '_pkc' => $categoryName,
        ]);
    }

    /**
     * @param array<int, array{0: string, 1: string, 2: string, 3: float, 4: int}> $items
     * @return array<string, mixed>
     */
    public function cartUpdate(string $url, string $visitorId, array $items, float $cartTotal): array
    {
        return $this->base($visitorId, [
            'url' => $url,
            'idgoal' => 0,
            'revenue' => $cartTotal,
            'ec_items' => json_encode(array_values($items), \JSON_THROW_ON_ERROR),
        ]);
    }

    /**
     * @param array<int, array{0: string, 1: string, 2: string, 3: float, 4: int}> $items
     * @return array<string, mixed>
     */
    public function ecommerceOrder(
        string $url,
        string $visitorId,
        string $orderNumber,
        float $revenue,
        float $subTotal,
        float $tax,
        float $shipping,
        float $discount,
        array $items
    ): array {
        return $this->base($visitorId, [
            'url' => $url,
            'idgoal' => 0,
            'ec_id' => $orderNumber,
            'revenue' => $revenue,
            'ec_st' => $subTotal,
            'ec_tx' => $tax,
            'ec_sh' => $shipping,
            'ec_dt' => $discount,
            'ec_items' => json_encode(array_values($items), \JSON_THROW_ON_ERROR),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function siteSearch(string $url, string $visitorId, string $keyword, string $category, ?int $count): array
    {
        $payload = $this->base($visitorId, [
            'url' => $url,
            'search' => $keyword,
            'search_cat' => $category,
        ]);

        if ($count !== null) {
            $payload['search_count'] = $count;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function goal(string $url, string $visitorId, int $goalId, ?float $revenue = null): array
    {
        $payload = $this->base($visitorId, [
            'url' => $url,
            'idgoal' => $goalId,
        ]);

        if ($revenue !== null) {
            $payload['revenue'] = $revenue;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function event(
        string $url,
        string $visitorId,
        string $category,
        string $action,
        ?string $name = null,
        int|float|null $value = null
    ): array {
        $payload = $this->base($visitorId, [
            'url' => $url,
            'e_c' => $category,
            'e_a' => $action,
        ]);

        if ($name !== null) {
            $payload['e_n'] = $name;
        }

        if ($value !== null) {
            $payload['e_v'] = $value;
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function base(string $visitorId, array $overrides): array
    {
        return [
            'idsite' => $this->systemConfigService->getString('TinectMatomo.config.matomosite'),
            'rec' => 1,
            'apiv' => 1,
            'send_image' => 0,
            '_id' => $visitorId,
        ] + $overrides;
    }
}
