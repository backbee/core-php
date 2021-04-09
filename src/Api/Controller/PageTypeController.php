<?php

namespace BackBeeCloud\Api\Controller;

use BackBee\BBApplication;
use BackBeeCloud\Listener\RequestListener;
use BackBeeCloud\PageType\TypeManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use function count;

/**
 * Class PageTypeController
 *
 * @package BackBeeCloud\Api\Controller
 *
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class PageTypeController extends AbstractController
{
    /**
     * @var TypeManager
     */
    protected $pageTypeManager;

    /**
     * PageTypeController constructor.
     *
     * @param TypeManager   $pageTypeManager
     * @param BBApplication $app
     */
    public function __construct(TypeManager $pageTypeManager, BBApplication $app)
    {
        parent::__construct($app);

        $this->pageTypeManager = $pageTypeManager;
    }

    /**
     * Get collection.
     *
     * @return JsonResponse
     */
    public function getCollection(): JsonResponse
    {
        $this->assertIsAuthenticated();

        return new JsonResponse(
            $all = array_values($this->pageTypeManager->all(true)),
            Response::HTTP_OK,
            [
                'Accept-Range' => 'pages-types ' . RequestListener::COLLECTION_MAX_ITEM,
                'Content-Range' => 0 === count($all) ? '-/-' : '0-' . (count($all) - 1) . '/' . count($all),
            ]
        );
    }
}
