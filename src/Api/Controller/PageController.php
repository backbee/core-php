<?php

namespace BackBeeCloud\Api\Controller;

use BackBee\Exception\InvalidArgumentException;
use BackBeeCloud\Elasticsearch\ElasticsearchCollection;
use BackBeeCloud\Entity\PageManager;
use BackBeeCloud\Listener\RequestListener;
use BackBeeCloud\PageCategory\PageCategoryManager;
use BackBeeCloud\PageType\TypeManager;
use BackBeeCloud\Security\Authorization\Voter\UserRightPageAttribute;
use BackBeeCloud\Security\UserRightConstants;
use BackBee\BBApplication;
use BackBee\NestedNode\Page;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\Query\QueryException;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\ORM\TransactionRequiredException;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 * @author Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class PageController extends AbstractController
{
    /**
     * @var PageManager
     */
    protected $pageManager;

    /**
     * @var TypeManager
     */
    protected $pageTypeManager;

    /**
     * @var PageCategoryManager
     */
    protected $pageCategoryManager;

    /**
     * PageController constructor.
     *
     * @param PageManager         $pageManager
     * @param TypeManager         $pageTypeManager
     * @param PageCategoryManager $pageCategoryManager
     * @param BBApplication       $app
     */
    public function __construct(
        PageManager $pageManager,
        TypeManager $pageTypeManager,
        PageCategoryManager $pageCategoryManager,
        BBApplication $app
    ) {
        parent::__construct($app);

        $this->pageManager = $pageManager;
        $this->pageTypeManager = $pageTypeManager;
        $this->pageCategoryManager = $pageCategoryManager;
    }

    /**
     * Get.
     *
     * @param $uid
     *
     * @return JsonResponse|Response
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     */
    public function get($uid)
    {
        $this->assertIsAuthenticated();

        $page = $this->pageManager->get($uid);
        if (null === $page) {
            return new Response(null, Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($this->pageManager->format($page));
    }

    /**
     * Get collection.
     *
     * @param int     $start
     * @param int     $limit
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function getCollection(
        $start = 0,
        $limit = RequestListener::COLLECTION_MAX_ITEM,
        Request $request
    ): ?JsonResponse {
        $this->assertIsAuthenticated();

        $criteria = $request->query->all();
        $sort = [];
        if (isset($criteria['sort'])) {
            foreach (explode(',', $criteria['sort']) as $attrName) {
                $attrName = explode(':', $attrName);
                if (count($attrName) === 2) {
                    $sort[$attrName[0]] = $attrName[1];
                }
            }
        }

        unset($criteria['sort'], $criteria['desc']);

        $criteria['lang'] = $criteria['lang'] ?? 'all';

        try {
            $pages = $this->pageManager->getBy($criteria, $start, $limit, $sort);

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
                $this->pageManager->formatCollection($pages, true),
                null !== $max
                    ? ($max > $count ? Response::HTTP_PARTIAL_CONTENT : Response::HTTP_OK)
                    : Response::HTTP_OK,
                [
                    'Accept-Range' => 'pages ' . RequestListener::COLLECTION_MAX_ITEM,
                    'Content-Range' => $max ? "$start-$end/$max" : '-/-',
                ]
            );
        } catch (Exception $exception) {
            return new JsonResponse(
                ['error'  => 'bad_request', 'reason' => $exception->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        }
    }

    /**
     * Post.
     *
     * @param Request $request
     *
     * @return JsonResponse|Response
     */
    public function post(Request $request)
    {
        $pageData = $request->request->all();

        $this->denyAccessUnlessGranted(
            new UserRightPageAttribute(
                UserRightConstants::CREATE_ATTRIBUTE,
                $pageData['type'],
                $pageData['category'] ?? null
            ),
            UserRightConstants::OFFLINE_PAGE
        );

        try {
            $page = $this->pageManager->create($pageData);
        } catch (Exception $exception) {
            return new JsonResponse([
                'error'  => 'bad_request',
                'reason' => $exception->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }

        return new Response(null, Response::HTTP_CREATED, [
            'Location'   => "/api/pages/{$page->getUid()}",
            'X-Page-URL' => $page->getUrl(),
        ]);
    }

    /**
     * Put.
     *
     * @param         $uid
     * @param Request $request
     *
     * @return JsonResponse|Response
     */
    public function put($uid, Request $request)
    {
        $page = $this->pageManager->get($uid);
        if (null === $page) {
            return new Response(null, Response::HTTP_NOT_FOUND);
        }

        $this->denyAccessUnlessGrantedByPage($page, UserRightConstants::EDIT_ATTRIBUTE);

        try {
            $this->pageManager->update($page, $request->request->all());
        } catch (Exception $e) {
            return new JsonResponse([
                'error'  => 'bad_request',
                'reason' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }

        return new Response(null, Response::HTTP_NO_CONTENT, [
            'X-Page-URL' => $page->getUrl(),
        ]);
    }

    /**
     * Delete.
     *
     * @param $uid
     *
     * @return JsonResponse|Response
     */
    public function delete($uid)
    {
        $page = $this->pageManager->get($uid);
        if (null === $page) {
            return new Response(null, Response::HTTP_NOT_FOUND);
        }

        $this->denyAccessUnlessGrantedByPage($page, UserRightConstants::DELETE_ATTRIBUTE);

        try {
            $this->pageManager->delete($page);
        } catch (Exception $e) {
            return new JsonResponse([
                'error'  => 'bad_request',
                'reason' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Duplicate.
     *
     * @param         $uid
     * @param Request $request
     *
     * @return Response
     * @throws InvalidArgumentException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws QueryException
     * @throws TransactionRequiredException
     */
    public function duplicate($uid, Request $request): Response
    {
        $source = $this->pageManager->get($uid);
        if (null === $source) {
            return new Response(null, Response::HTTP_NOT_FOUND);
        }

        $this->denyAccessUnlessGranted(
            new UserRightPageAttribute(
                UserRightConstants::CREATE_ATTRIBUTE,
                $this->pageTypeManager->findByPage($source)->uniqueName()
            ),
            UserRightConstants::OFFLINE_PAGE
        );

        $new = $this->pageManager->duplicate($source, $request->request->all());

        return new Response(null, Response::HTTP_CREATED, [
            'Location'   => "/api/pages/{$new->getUid()}",
            'X-Page-URL' => $new->getUrl(),
        ]);
    }

    /**
     * Deny access unless granted by page.
     *
     * @param Page $page
     * @param      $attribute
     */
    private function denyAccessUnlessGrantedByPage(Page $page, $attribute): void
    {
        $subject = $page->isOnline(true) ? UserRightConstants::ONLINE_PAGE : UserRightConstants::OFFLINE_PAGE;
        $this->denyAccessUnlessGranted(
            new UserRightPageAttribute(
                $attribute,
                $this->pageTypeManager->findByPage($page)->uniqueName(),
                $this->pageCategoryManager->getCategoryByPage($page)
            ),
            $subject
        );
    }
}
