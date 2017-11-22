<?php

namespace BackBeeCloud\Listener;

use BackBeePlanet\GlobalSettings;
use BackBee\Bundle\Registry;
use BackBee\ClassContent\AbstractClassContent;
use BackBee\Event\Event;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class CoreListener
{
    /**
     * Called on `bbapplication.init` event.
     *
     * @param  Event  $event
     */
    public static function onApplicationInit(Event $event)
    {
        $app = $event->getTarget();

        $baseDir = $app->getBundle('core')->getBaseDirectory();
        $resDir = realpath($baseDir . '/../res');
        $app->getRenderer()->addLayoutDir($resDir . DIRECTORY_SEPARATOR . 'Layout');
        $app->getRenderer()->addScriptDir($resDir . DIRECTORY_SEPARATOR . 'views');
        $app->getRenderer()->addHelperDir($resDir . DIRECTORY_SEPARATOR . 'helpers');

        if ($app->isRestored()) {
            return;
        }

        $app->unshiftClassContentDir($resDir . DIRECTORY_SEPARATOR . 'ClassContent');
    }

    /**
     * Called on `bbapplication.init` event to force load of ClassContent class
     * metadata into EntityManager.
     *
     * It **MUST** occur before service container dump.
     *
     * @param  Event  $event
     */
    public static function forceClassContentLoadOnApplicationInit(Event $event)
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
        } catch (\Exception $e) {
            return;
        }

        $entyMgr->getProxyFactory()->generateProxyClasses($metadata);
        $container->setParameter('all_classcontents_classnames', $classnames);
    }

    public static function onApplicationStart(Event $event)
    {
        $entyMgr = $event->getApplication()->getEntityManager();
        $isSuspended = null !== $entyMgr->getRepository(Registry::class)->findOneBy([
            'scope' => 'GLOBAL',
            'type'  => 'site',
            'key'   => 'status',
            'value' => 'suspended',
        ]);
        if ($isSuspended) {
            throw new SiteSuspendedException();
        }
    }

    public static function onAuthenticationException(GetResponseForExceptionEvent $event)
    {
        if (!($event->getException() instanceof UsernameNotFoundException)) {
            return;
        }

        $response = new Response();
        $response->setStatusCode(Response::HTTP_UNAUTHORIZED, 'Invalid authentication informations');
        $event->setResponse($response);
    }

    public static function onKernelException(GetResponseForExceptionEvent $event)
    {
        $exception = $event->getException();

        $request = Request::createFromGlobals();
        if ($request->headers->get('x-debug-token') === sha1(date('Y-m-d') . '-backbee')) {
            $whoops = new \Whoops\Run();
            $whoops->pushHandler(new \Whoops\Handler\PrettyPageHandler());
            $whoops->register();

            throw $exception;
        }

        error_log(sprintf('[%s] %s', get_class($exception), $exception->getMessage()));
    }
}
