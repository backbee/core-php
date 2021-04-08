<?php

namespace BackBeeCloud\Listener;

use BackBee\BBApplication;
use BackBee\Bundle\Registry;
use BackBee\ClassContent\AbstractClassContent;
use BackBee\Event\Event;
use BackBee\Routing\Route;
use Exception;
use LogicException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run;
use function dirname;

/**
 * Class CoreListener
 *
 * @package BackBeeCloud\Listener
 *
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class CoreListener
{
    /**
     * @var BBApplication
     */
    private static $bbApp;

    /**
     * CoreListener constructor.
     *
     * @param BBApplication $bbApp
     */
    public function __construct(BBApplication $bbApp)
    {
        self::$bbApp = $bbApp;
    }

    /**
     * Called on `bbapplication.init` event.
     *
     * @param Event $event
     */
    public static function onApplicationInit(Event $event): void
    {
        $app = $event->getTarget();
        if ($app->isRestored()) {
            return;
        }

        $baseDir = $app->getBundle('core')->getBaseDirectory();
        $resDir = dirname($baseDir) . '/res';
        $app->getRenderer()->addLayoutDir($resDir . DIRECTORY_SEPARATOR . 'Layout');
        $app->getRenderer()->addScriptDir($resDir . DIRECTORY_SEPARATOR . 'views');
        $app->getRenderer()->addHelperDir($resDir . DIRECTORY_SEPARATOR . 'helpers');
        $app->unshiftClassContentDir($resDir . DIRECTORY_SEPARATOR . 'ClassContent');
    }

    /**
     * Called on `bbapplication.init` event to force load of ClassContent class
     * metadata into EntityManager.
     *
     * It **MUST** occur before service container dump.
     *
     * @param Event $event
     */
    public static function forceClassContentLoadOnApplicationInit(Event $event): void
    {
        $app = $event->getTarget();
        $container = $app->getContainer();
        $entyMgr = $app->getEntityManager();
        if ($app->isRestored()) {
            if ($container->hasParameter('all_classcontents_classnames')) {
                $metadata = $entyMgr->getClassMetadata(AbstractClassContent::class);
                foreach ($container->getParameter('all_classcontents_classnames') as $short => $full) {
                    $metadata->addDiscriminatorMapClass($short, $full);
                }
            }

            return;
        }

        $metadata = [];
        $classnames = [];

        try {
            foreach ($container->get('classcontent.manager')->getAllClassContentClassnames() as $classname) {
                class_exists($classname);
                $metadata[] = $entyMgr->getClassMetadata($classname);
                $classnames[AbstractClassContent::getShortClassname($classname)] = $classname;
            }
        } catch (Exception $e) {
            return;
        }

        $entyMgr->getProxyFactory()->generateProxyClasses($metadata);
        $container->setParameter('all_classcontents_classnames', $classnames);
    }

    /**
     * On application start.
     *
     * @param Event $event
     */
    public static function onApplicationStart(Event $event): void
    {
        $app = $event->getApplication();

        // Checks that site is not suspended
        $isSuspended = null !== $app->getEntityManager()->getRepository(Registry::class)->findOneBy([
            'scope' => 'GLOBAL',
            'type' => 'site',
            'key' => 'status',
            'value' => 'suspended',
        ]);

        if ($isSuspended) {
            throw new SiteSuspendedException();
        }

        // Checks that no update is in progress
        $request = $app->getRequest();
        $route = $app->getRouting()->get('api.site.work_progress');
        if ($route instanceof Route && $route->getPath() === $request->getPathInfo()) {
            return;
        }

        try {
            $app->getContainer()->get('site_status.manager')->getLockProgress();
        } catch (LogicException $e) {
            return;
        }

        throw new WorkInProgressException();
    }

    /**
     * This listener listen to 'kernel.controller' event to prevent \BackBee\Controller\FrontController::defaultAction
     * sending response by itself.
     *
     * @param FilterControllerEvent $event
     */
    public static function onKernelController(FilterControllerEvent $event): void
    {
        $event->getRequest()->attributes->set('sendResponse', false);
    }

    /**
     * On authentication exception.
     *
     * @param GetResponseForExceptionEvent $event
     */
    public static function onAuthenticationException(GetResponseForExceptionEvent $event): void
    {
        if (!($event->getException() instanceof UsernameNotFoundException)) {
            return;
        }

        $response = new Response();
        $response->setStatusCode(Response::HTTP_UNAUTHORIZED, 'Invalid authentication information');
        $event->setResponse($response);
    }

    /**
     * On kernel exception.
     *
     * @param GetResponseForExceptionEvent $event
     *
     * @throws Exception
     */
    public static function onKernelException(GetResponseForExceptionEvent $event): void
    {
        $exception = $event->getException();

        $request = Request::createFromGlobals();
        $isDevMode = self::$bbApp->getAppParameter('dev_mode');
        if ($isDevMode || $request->headers->get('x-debug-token') === sha1(date('Y-m-d') . '-backbee')) {
            $whoops = new Run();
            $whoops->pushHandler(new PrettyPageHandler());
            $whoops->register();

            throw $exception;
        }
    }
}
