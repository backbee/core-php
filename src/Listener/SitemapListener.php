<?php

namespace BackBeePlanet\Listener;

use BackBee\BBApplication;
use BackBee\Config\Config;
use BackBee\Routing\RouteCollection;
use BackBee\Utils\Collection\Collection;
use BackBeePlanet\Sitemap\Decorator\DecoratorInterface;
use BackBeePlanet\Sitemap\Query\BaseCollector;
use BackBeePlanet\Sitemap\SitemapManager;
use Exception;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class SitemapListener
 *
 * @package BackBeePlanet\Listener
 */
class SitemapListener
{
    /**
     * @var BBApplication
     */
    private static $bbApp;

    /**
     * @var SitemapManager
     */
    private static $sitemapManager;

    /**
     * @var Config
     */
    private static $config;

    /**
     * @var string The prefix for route name.
     */
    public static $ROUTE_PREFIX = 'app.sitemap.route.';

    /**
     * @var string The prefix for archive route name.
     */
    public static $ARCHIVE_ROUTE_PREFIX = 'app.sitemap.archive_route.';

    /**
     * @var string The prefix for sitemap decorator service id.
     */
    public static $DECORATOR_PREFIX = 'app.sitemap.decorator.';

    /**
     * @var string The tag for sitemap decorator services.
     */
    public static $DECORATOR_TAG = 'app.sitemap.decorator';

    /**
     * SitemapListener constructor.
     *
     * @param BBApplication  $bbApp
     * @param SitemapManager $sitemapManager
     * @param Config         $config
     */
    public function __construct(BBApplication $bbApp, SitemapManager $sitemapManager, Config $config)
    {
        self::$bbApp = $bbApp;
        self::$sitemapManager = $sitemapManager;
        self::$config = $config;
    }

    /**
     * On Application init.
     *
     * @throws Exception
     */
    public static function onApplicationInit(): void
    {
        self::loadSitemaps();
    }

    /**
     * Adds routes to sitemaps.
     *
     * @throws Exception
     */
    private static function loadSitemaps(): void
    {
        try {
            foreach (self::$config->getSection('sitemaps') as $id => $definition) {
                $active = Collection::get($definition, 'active', true);
                $decorator = Collection::get($definition, 'decorator');
                $collector = Collection::get($definition, 'collector', BaseCollector::class);
                $pattern = Collection::get($definition, 'url_pattern');
                $archivePattern = Collection::get($definition, 'archive_pattern');
                $limits = Collection::get($definition, 'limits', []);

                if (!$active || empty($pattern) || empty($decorator)) {
                    continue;
                }

                $collectorRef = self::getCollectorReference(
                    self::$bbApp->getContainer(),
                    $collector,
                    $id,
                    $pattern,
                    $limits
                );
                self::getDecoratorReference(self::$bbApp->getContainer(), $decorator, $id, $collectorRef);
                self::addRoute(self::$bbApp->getContainer()->get('routing'), $id, $pattern);

                if (null !== $archivePattern) {
                    self::addGenerateArchiveRoute(self::$bbApp->getContainer()->get('routing'), $id, $archivePattern);
                }
            }
        } catch (Exception $exception) {
            self::$bbApp->getLogging()->error(
                sprintf('%s : %s : %s', __CLASS__, __FUNCTION__, $exception->getMessage())
            );
        }
    }

    /**
     * Adds a new route for the sitemap $id to the default action controller.
     *
     * @param RouteCollection $routing The current application route collection.
     * @param string          $id      The sitemap id.
     * @param string          $pattern The sitemap URL pattern.
     */
    private static function addRoute(RouteCollection $routing, string $id, string $pattern): void
    {
        $routing->pushRouteCollection(
            [
                self::$ROUTE_PREFIX . $id => [
                    'pattern' => $pattern,
                    'defaults' => [
                        '_action' => 'indexAction',
                        '_controller' => 'core.sitemap.controller',
                    ],
                ],
            ]
        );
    }

    /**
     * Adds a new route to generate archive for the sitemap $id to the default action controller.
     *
     * @param RouteCollection $routing The current application route collection.
     * @param string          $id      The sitemap id.
     * @param string          $archivePattern
     */
    private static function addGenerateArchiveRoute(RouteCollection $routing, string $id, string $archivePattern): void
    {
        $routing->pushRouteCollection(
            [
                self::$ARCHIVE_ROUTE_PREFIX . $id => [
                    'pattern' => $archivePattern,
                    'defaults' => [
                        '_action' => 'getArchiveAction',
                        '_controller' => 'core.sitemap.controller',
                    ],
                ],
            ]
        );
    }

    /**
     * Returns a reference to a sitemap decorator, builds it if need.
     *
     * @param ContainerBuilder $container    The current application container.
     * @param mixed            $decoratorId  A decorator instance or an id or classname of decorator.
     * @param string           $id           The sitemap id.
     * @param Reference        $collectorRef A reference to collector.
     *
     * @return Reference                      A reference of the decorator.
     * @throws Exception
     */
    private static function getDecoratorReference(
        ContainerBuilder $container,
        $decoratorId,
        string $id,
        Reference $collectorRef
    ): ?Reference {
        if (is_object($decoratorId) && $decoratorId instanceof DecoratorInterface) {
            $decoratorId->setRenderer($container->get('renderer'));
            $decoratorId->setCollector($container->get($collectorRef));

            return null;
        }

        if (0 === strncmp($decoratorId, '@', 1)) {
            $decoratorId = substr($decoratorId, 1);
        } else {
            $class = $decoratorId;
            $decoratorId = self::$DECORATOR_PREFIX . $id;

            $decorator = new Definition();
            $decorator->setClass($class)
                ->addMethodCall('setRenderer', [new Reference('renderer')])
                ->addMethodCall('setCollector', [$collectorRef])
                ->addTag(self::$DECORATOR_TAG);

            $container->setDefinition($decoratorId, $decorator);
        }

        return new Reference($decoratorId);
    }

    /**
     * Returns a reference to a sitemap collector, builds it if need.
     *
     * @param ContainerBuilder $container   The current application container.
     * @param string           $collectorId The collector id or a classname of collector.
     * @param string           $id          The sitemap id.
     * @param string           $pattern     The sitemap URL pattern.
     * @param array            $limits      The limits applied to the collector.
     *
     * @return Reference                     A reference to the sitemap collector.
     */
    private static function getCollectorReference(
        ContainerBuilder $container,
        string $collectorId,
        string $id,
        string $pattern,
        array $limits
    ): Reference {
        if (0 === strncmp($collectorId, '@', 1)) {
            $collectorId = substr($collectorId, 1);
        } else {
            $class = $collectorId;
            $collectorId = sprintf('app.sitemap.collector.%s', $id);

            $collector = new Definition();
            $collector->setClass($class)
                ->addMethodCall('setContainer', [new Reference('service_container')])
                ->addMethodCall('setUrlPattern', [$pattern])
                ->addMethodCall('setLimits', [$limits]);

            $container->setDefinition($collectorId, $collector);
        }

        return new Reference($collectorId);
    }
}
