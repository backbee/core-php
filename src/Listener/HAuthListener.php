<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace BackBeeCloud\Listener;

use BackBee\Bundle\Event\BundleStartEvent;
use BackBee\Bundle\Registry;
use BackBee\Event\Event;
use BackBee\Security\User;
use BackBeePlanet\GlobalSettings;
use Doctrine\DBAL\DBALException;
use Doctrine\ORM\Tools\SchemaTool;
use LpDigital\Bundle\HAuthBundle\Entity\SocialSignIn;
use LpDigital\Bundle\HAuthBundle\Entity\UserProfile;
use LpDigital\Bundle\HAuthBundle\HAuth;
use Symfony\Component\Security\Acl\Domain\UserSecurityIdentity;

/**
 * Description of HAuthListener
 *
 * @copyright    ©2017 - Lp digital
 * @author       Charles Rouillon <charles.rouillon@lp-digital.fr>
 */
class HAuthListener
{

    /**
     * On application start, checks for hauth tables and pending social signin in registries.
     *
     * @param Event $event
     */
    public static function onApplicationStart(Event $event)
    {
        $em = $event->getApplication()->getEntityManager();

        try {
            $em->getRepository(SocialSignIn::class)->findBy(['networkId' => '']);
        } catch (DBALException $ex) {
            $metadata = [
                $em->getClassMetadata(SocialSignIn::class),
                $em->getClassMetadata(UserProfile::class),
            ];

            $schema = new SchemaTool($em);
            $schema->updateSchema($metadata, true);
        }

        $registries = $em->getRepository(Registry::class)->findBy(['scope' => 'HAUTH_SIGNIN']);
        foreach ($registries as $registry) {
            $em->remove($registry);

            $value = json_decode($registry->getValue(), true);
            if (!is_array($value) || !isset($value['network']) || !isset($value['userId'])) {
                continue;
            }

            $user = $em->getRepository(User::class)->findOneBy(['_login' => $registry->getKey()]);
            if (null === $user) {
                continue;
            }

            $signin = new SocialSignIn(
                $event->getApplication()->getSite(),
                UserSecurityIdentity::fromAccount($user),
                $value['network'],
                $value['userId']
            );

            $em->merge($signin);
        }
        $em->flush();
    }

    /**
     * On bundle start, override bundle config by GlobalSettings parameters.
     *
     * @param BundleStartEvent $event
     */
    public static function onBundleStart(BundleStartEvent $event)
    {
        $bundle = $event->getBundle();
        if ($bundle instanceof HAuth) {
            $bundle->getConfig()
                    ->setSection('hybridauth', (new GlobalSettings())->hybridAuth());
        }
    }
}
