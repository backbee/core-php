<?php

namespace BackBeeCloud\Controller;

use BackBee\Renderer\Exception\RendererException;
use BackBee\Renderer\Renderer;
use BackBeeCloud\SearchEngine\SearchEngineManager;
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
    /**
     * User preference data key const.
     */
    public const USER_PREFERENCE_DATA_KEY = 'search-engines';

    /**
     * @var Renderer
     */
    protected $renderer;

    /**
     * @var SearchEngineManager
     */
    protected $searchEngineManager;

    /**
     * SearchEngineController constructor.
     *
     * @param Renderer            $renderer
     * @param SearchEngineManager $searchEngineManager
     */
    public function __construct(Renderer $renderer, SearchEngineManager $searchEngineManager)
    {
        $this->renderer = $renderer;
        $this->searchEngineManager = $searchEngineManager;
    }

    /**
     * @return Response
     * @throws RendererException
     */
    public function robotsTxt(): Response
    {
        $content = $this->renderer->partial('common/robots.txt.twig', [
            'do_index' => $this->searchEngineManager->googleSearchEngineIsActivated()
        ]);

        return new Response($content, Response::HTTP_OK, [
            'Accept-Ranges'  => 'bytes',
            'Content-Type'   => 'text/plain',
            'Content-Length' => strlen($content),
        ]);
    }
}
