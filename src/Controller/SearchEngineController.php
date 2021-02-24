<?php

namespace BackBeeCloud\Controller;

use BackBee\Renderer\Exception\RendererException;
use BackBeeCloud\UserPreference\UserPreferenceManager;
use BackBee\Renderer\Renderer;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class SearchEngineController
 *
 * @package BackBeeCloud\Controller
 *
 * @author Eric Chau <eric.chau@lp-digital.fr>
 * @author Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class SearchEngineController
{
    public const USER_PREFERENCE_DATA_KEY = 'search-engines';

    /**
     * @var Renderer
     */
    protected $renderer;

    /**
     * @var UserPreferenceManager
     */
    protected $usrPrefMgr;

    /**
     * SearchEngineController constructor.
     *
     * @param Renderer              $renderer
     * @param UserPreferenceManager $usrPrefMgr
     */
    public function __construct(Renderer $renderer, UserPreferenceManager $usrPrefMgr)
    {
        $this->renderer = $renderer;
        $this->usrPrefMgr = $usrPrefMgr;
    }

    /**
     * @return Response
     * @throws RendererException
     */
    public function robotsTxt(): Response
    {
        $data = $this->usrPrefMgr->dataOf(self::USER_PREFERENCE_DATA_KEY);
        $content = $this->renderer->partial('common/robots.txt.twig', [
            'do_index' => isset($data['robots_index']) && true === $data['robots_index'],
        ]);

        return new Response($content, Response::HTTP_OK, [
            'Accept-Ranges'  => 'bytes',
            'Content-Type'   => 'text/plain',
            'Content-Length' => strlen($content),
        ]);
    }
}
