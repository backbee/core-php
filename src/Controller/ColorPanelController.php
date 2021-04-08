<?php

namespace BackBeeCloud\Controller;

use BackBee\Cache\RedisManager;
use BackBee\Site\Site;
use BackBeeCloud\ThemeColor\ColorPanelCssGenerator;
use BackBee\HttpClient\UserAgent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class ColorPanelController
 *
 * @package BackBeeCloud\Controller
 *
 * @author  Sachan Nilleti <sachan.nilleti@lp-digital.fr>
 */
class ColorPanelController
{
    /**
     * @var ColorPanelCssGenerator
     */
    private $cssGenerator;

    /**
     * @var Site
     */
    private $site;

    /**
     * @var RedisManager
     */
    private $redisManager;

    /**
     * ColorPanelController constructor.
     *
     * @param ColorPanelCssGenerator $cssGenerator
     * @param EntityManagerInterface $entityManager
     * @param RedisManager  $redisManager
     */
    public function __construct(
        ColorPanelCssGenerator $cssGenerator,
        EntityManagerInterface $entityManager,
        RedisManager $redisManager
    ) {
        $this->cssGenerator = $cssGenerator;
        $this->site = $entityManager->getRepository(Site::class)->findOneBy([]);
        $this->redisManager = $redisManager;
    }

    /**
     * Get color panel css.
     *
     * @param         $hash
     * @param Request $request
     *
     * @return RedirectResponse|Response
     */
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
        $redisClient = $this->redisManager->getClient();
        $redisCacheKey = sprintf(
            '%s:%s[%s]',
            $this->site->getLabel(),
            $request->getPathInfo(),
            UserAgent::getDeviceType()
        );
        if (null === $redisClient || null === $cssContent = $redisClient->get($redisCacheKey)) {
            $cssContent = $this->cssGenerator->generate();
            if (null !== $redisClient) {
                $redisClient->set($redisCacheKey, $cssContent);
            }
        }

        return new Response(
            $cssContent, Response::HTTP_OK, [
                'Content-Type' => 'text/css',
            ]
        );
    }
}
