<?php

namespace BackBeeCloud\Controller;

use BackBeeCloud\ThemeColor\ColorPanelCssGenerator;
use BackBeeCloud\UserAgentHelper;
use BackBeePlanet\Redis\RedisManager;
use BackBee\Site\Site;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Sachan Nilleti <sachan.nilleti@lp-digital.fr>
 */
class ColorPanelController
{
    /**
     * @var ColorPanelCssGenerator
     */
    protected $cssGenerator;

    /**
     * @var \BackBee\Site\Site
     */
    protected $site;

    public function __construct(ColorPanelCssGenerator $cssGenerator, EntityManagerInterface $entityManager)
    {
        $this->cssGenerator = $cssGenerator;
        $this->site = $entityManager->getRepository(Site::class)->findOneBy([]);
    }

    public function getColorPanelCssAction($hash, Request $request)
    {
        $currentHash = $this->cssGenerator->getCurrentHash();
        if ($hash !== $currentHash) {
            return new RedirectResponse(
                str_replace($hash, $currentHash, $request->getPathInfo()),
                Response::HTTP_PERMANENTLY_REDIRECT
            );
        }

        $cssContent = null;
        $redisClient = RedisManager::getClient();
        $redisCacheKey = sprintf(
            '%s:%s[%s]',
            $this->site->getLabel(),
            $request->getPathInfo(),
            UserAgentHelper::getDeviceType()
        );
        if (null === $redisClient || null === $cssContent = $redisClient->get($redisCacheKey)) {
            $cssContent = $this->cssGenerator->generate();
            if (null !== $redisClient) {
                $redisClient->set($redisCacheKey, $cssContent);
            }
        }

        return new Response($cssContent, Response::HTTP_OK, [
            'Content-Type' => 'text/css',
        ]);
    }
}
