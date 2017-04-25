<?php

namespace BackBeeCloud\Controller;

use BackBeeCloud\UserPreference\UserPreferenceManager;
use BackBee\Renderer\Renderer;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class SearchEngineController
{
    const USER_PREFERENCE_DATA_KEY = 'search-engines';

    /**
     * @var Renderer
     */
    protected $renderer;

    /**
     * @var UserPreferenceManager
     */
    protected $usrPrefMgr;

    public function __construct(Renderer $renderer, UserPreferenceManager $usrPrefMgr)
    {
        $this->renderer = $renderer;
        $this->usrPrefMgr = $usrPrefMgr;
    }

    public function robotsTxt()
    {
        $data = $this->usrPrefMgr->dataOf(self::USER_PREFERENCE_DATA_KEY);
        $content = $this->renderer->partial('common/robots.txt.twig', [
            'do_index' => isset($data['robots_index']) && true == $data['robots_index'],
            'has_sitemap' => $this->renderer->getApplication()->getContainer()->has('bundle.sitemap'),
        ]);

        return new Response($content, Response::HTTP_OK, [
            'Accept-Ranges'  => 'bytes',
            'Content-Type'   => 'text/plain',
            'Content-Length' => strlen($content),
        ]);
    }
}
