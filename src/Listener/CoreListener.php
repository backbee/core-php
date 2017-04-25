<?php

namespace BackBeeCloud\Listener;

use BackBee\Bundle\Registry;
use BackBee\ClassContent\AbstractClassContent;
use BackBee\Event\Event;
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
        $container = $app->getContainer();
        $entyMgr = $app->getEntityManager();

        $baseDir = $app->getBundle('core')->getBaseDirectory();
        $resDir = realpath($baseDir . '/../res');
        $app->getRenderer()->addLayoutDir($resDir . DIRECTORY_SEPARATOR . 'Layout');
        $app->getRenderer()->addScriptDir($resDir . DIRECTORY_SEPARATOR . 'views');
        $app->getRenderer()->addHelperDir($resDir . DIRECTORY_SEPARATOR . 'helpers');

        if ($app->isRestored()) {
            if ($container->hasParameter('all_classcontents_classnames')) {
                $metadata = $entyMgr->getClassMetadata(AbstractClassContent::class);
                foreach ($container->getParameter('all_classcontents_classnames') as $short => $full) {
                    $metadata->addDiscriminatorMapClass($short, $full);
                }
            }

            return;
        }

        $app->unshiftClassContentDir($resDir . DIRECTORY_SEPARATOR . 'ClassContent');

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
}
