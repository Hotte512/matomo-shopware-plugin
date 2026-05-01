<?php declare(strict_types=1);

namespace Tinect\Matomo;

use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\DirectoryLoader;
use Symfony\Component\DependencyInjection\Loader\GlobFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class TinectMatomo extends Plugin
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $locator = new FileLocator('Resources/config');

        $resolver = new LoaderResolver([
            new YamlFileLoader($container, $locator),
            new GlobFileLoader($container, $locator),
            new DirectoryLoader($container, $locator),
        ]);

        $configLoader = new DelegatingLoader($resolver);

        $confDir = rtrim($this->getPath(), '/') . '/Resources/config';

        $configLoader->load($confDir . '/{packages}/*.yaml', 'glob');
    }

    public function update(UpdateContext $updateContext): void
    {
        parent::update($updateContext);

        $container = $this->container;
        if ($container === null || !$container->has(SystemConfigService::class)) {
            return;
        }

        /** @var SystemConfigService $systemConfigService */
        $systemConfigService = $container->get(SystemConfigService::class);

        $existingMode = $systemConfigService->getString('TinectMatomo.config.trackingMode');
        if ($existingMode !== '') {
            return;
        }

        $legacyProxy = $systemConfigService->get('TinectMatomo.config.activateProxyTracking');
        $mode = ((bool) $legacyProxy) ? 'proxy' : 'client';

        /** @phpstan-ignore-next-line method.deprecated */
        $systemConfigService->set('TinectMatomo.config.trackingMode', $mode);
    }
}
