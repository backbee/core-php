<?php

namespace BackBeeCloud\Api\Controller;

use BackBee\BBApplication;
use BackBeeCloud\Elasticsearch\ElasticsearchCollection;
use BackBeeCloud\Listener\RequestListener;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class PageController extends AbstractController
{
    /**
     * @var \BackBeeCloud\Entity\PageManager
     */
    protected $pageMgr;

    /**
     * @var \Symfony\Component\HttpFoundation\Request
     */
    protected $request;

    public function __construct(BBApplication $app)
    {
        parent::__construct($app);

        $this->pageMgr = $app->getContainer()->get('cloud.page_manager');
        $this->request = $app->getRequest();
    }

    public function get($uid)
    {
        if (null !== $response = $this->getResponseOnAnonymousUser()) {
            return $response;
        }

        $page = $this->pageMgr->get($uid);
        if (null === $page) {
            return new Response(null, Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($this->pageMgr->format($page, $this->bbtoken));
    }

    public function getCollection($start = 0, $limit = RequestListener::COLLECTION_MAX_ITEM)
    {
        if (null !== $response = $this->getResponseOnAnonymousUser()) {
            return $response;
        }

        $criteria = $this->request->query->all();
        $sort = [];
        if (isset($criteria['sort'])) {
            $desc = explode(',', $this->request->query->get('desc', ''));
            foreach (explode(',', $criteria['sort']) as $attrName) {
                $sort[$attrName] = 'asc';
                if (in_array($attrName, $desc)) {
                    $sort[$attrName] = 'desc';
                }
            }
        }

        unset($criteria['sort'], $criteria['desc']);

        try {
            $pages = $this->pageMgr->getBy($criteria, $start, $limit, $sort);

            $end = null;
            $max = null;
            $count = null;
            if ($pages instanceof Paginator) {
                $max = $pages->count();
                $count = count($pages->getIterator());
            } elseif ($pages instanceof ElasticsearchCollection) {
                $max = $pages->countMax();
                $count = $pages->count();
            }

            $end = $start + $count - 1;
            $end = $end >= 0 ? $end : 0;

            return new JsonResponse(
                $this->pageMgr->formatCollection($pages, $this->bbtoken),
                null !== $max
                    ? ($max > $count ? Response::HTTP_PARTIAL_CONTENT : Response::HTTP_OK)
                    : Response::HTTP_OK,
                [
                    'Accept-Range' => 'pages ' . RequestListener::COLLECTION_MAX_ITEM,
                    'Content-Range' => $max ? "$start-$end/$max" : '-/-',
                ]
            );
        } catch (\Exception $e) {
            return new JsonResponse([
                'error'  => 'bad_request',
                'reason' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    public function post()
    {
        if (null !== $response = $this->getResponseOnAnonymousUser()) {
            return $response;
        }

        try {
            $page = $this->pageMgr->create($this->request->request->all());
        } catch (\Exception $e) {
            return new JsonResponse([
                'error'  => 'bad_request',
                'reason' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }

        return new Response(null, Response::HTTP_CREATED, [
            'Location'   => "/api/pages/{$page->getUid()}",
            'X-Page-URL' => $page->getUrl(),
        ]);
    }

    public function put($uid)
    {
        if (null !== $response = $this->getResponseOnAnonymousUser()) {
            return $response;
        }

        $page = $this->pageMgr->get($uid);
        if (null === $page) {
            return new Response(null, Response::HTTP_NOT_FOUND);
        }

        try {
            $this->pageMgr->update($page, $this->request->request->all());
        } catch (\Exception $e) {
            return new JsonResponse([
                'error'  => 'bad_request',
                'reason' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }

        return new Response(null, Response::HTTP_NO_CONTENT, [
            'X-Page-URL' => $page->getUrl(),
        ]);
    }

    public function delete($uid)
    {
        if (null !== $response = $this->getResponseOnAnonymousUser()) {
            return $response;
        }

        $page = $this->pageMgr->get($uid);
        if (null === $page) {
            return new Response(null, Response::HTTP_NOT_FOUND);
        }

        try {
            $this->pageMgr->delete($page);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error'  => 'bad_request',
                'reason' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    public function duplicate($uid)
    {
        if (null !== $response = $this->getResponseOnAnonymousUser()) {
            return $response;
        }

        $source = $this->pageMgr->get($uid);
        if (null === $source) {
            return new Response(null, Response::HTTP_NOT_FOUND);
        }

        $new = $this->pageMgr->duplicate($source, $this->request->request->all());

        return new Response(null, Response::HTTP_CREATED, [
            'Location'   => "/api/pages/{$new->getUid()}",
            'X-Page-URL' => $new->getUrl(),
        ]);
    }
}
