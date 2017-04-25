<?php

namespace BackBeeCloud\Api\Controller;

use BackBee\BBApplication;
use BackBeeCloud\Listener\RequestListener;
use BackBeeCloud\PageType\TypeManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class PageTypeController extends AbstractController
{
    /**
     * @var TypeManager
     */
    protected $mgr;

    public function __construct(BBApplication $app)
    {
        parent::__construct($app);

        $this->mgr = $app->getContainer()->get('cloud.page_type.manager');
    }

    public function getCollection()
    {
        if (null !== $response = $this->getResponseOnAnonymousUser()) {
            return $response;
        }

        return new JsonResponse(
            $all = array_values($this->mgr->all()),
            Response::HTTP_OK,
            [
                'Accept-Range'  => 'pages-types ' . RequestListener::COLLECTION_MAX_ITEM,
                'Content-Range' => 0 === count($all) ? '-/-' : '0-' . (count($all) - 1) . '/' . count($all),
            ]
        );
    }
}
