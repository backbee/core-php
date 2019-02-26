<?php

namespace BackBeeCloud\Api\Controller;

use BackBeeCloud\Elasticsearch\ElasticsearchCollection;
use BackBeeCloud\Entity\TagManager;
use BackBeeCloud\Listener\RequestListener;
use BackBeeCloud\Security\UserRightConstants;
use BackBee\BBApplication;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class TagController extends AbstractController
{
    /**
     * @var TagManager
     */
    protected $tagMgr;

    /**
     * @var Request
     */
    protected $request;

    public function __construct(BBApplication $app)
    {
        parent::__construct($app);

        $this->tagMgr = $app->getContainer()->get('cloud.tag_manager');
        $this->request = $app->getRequest();
    }

    public function getCollection($start = 0, $limit = RequestListener::COLLECTION_MAX_ITEM)
    {
        $this->assertIsAuthenticated();

        $tags = $this->tagMgr->getBy(
            $this->request->query->get('term', ''),
            $start,
            $limit
        );

        $end = null;
        $max = null;
        $count = null;
        if ($tags instanceof ElasticsearchCollection) {
            $max = $tags->countMax();
            $count = $tags->count();
        }

        $end = $start + $count - 1;
        $end = $end >= 0 ? $end : 0;
        $statusCode = null !== $max
            ? ($max > $count ? Response::HTTP_PARTIAL_CONTENT : Response::HTTP_OK)
            : Response::HTTP_OK
        ;

        return new JsonResponse(
            $tags->collection(),
            $statusCode,
            [
                'Accept-Range' => 'tags ' . RequestListener::COLLECTION_MAX_ITEM,
                'Content-Range' => $max ? "$start-$end/$max" : '-/-',
            ]
        );
    }

    /**
     * Returns an instance of JsonResponse that contains list of pages (id and title)
     * that are linked to the provided tag.
     *
     * @param  string $uid The tag's uid
     * @return JsonReponse
     */
    public function getLinkedPages($uid)
    {
        $this->denyAccessUnlessGranted(
            UserRightConstants::MANAGE_ATTRIBUTE,
            UserRightConstants::TAG_FEATURE
        );

        if (null === $tag = $this->tagMgr->get($uid)) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($this->tagMgr->getLinkedPages($tag));
    }

    public function post()
    {
        $this->denyAccessUnlessGranted(
            UserRightConstants::MANAGE_ATTRIBUTE,
            UserRightConstants::TAG_FEATURE
        );

        if (!$this->request->request->has('name')) {
            return new JsonResponse([
                'error'  => 'bad_request',
                'reason' => "'name' parameter is expected but cannot be found in request body.",
            ], Response::HTTP_BAD_REQUEST);
        }

        $this->tagMgr->create($this->request->request->get('name'));

        return new JsonResponse('', Response::HTTP_CREATED);
    }

    public function put($uid)
    {
        $this->denyAccessUnlessGranted(
            UserRightConstants::MANAGE_ATTRIBUTE,
            UserRightConstants::TAG_FEATURE
        );

        if (!$this->request->request->has('name')) {
            return new JsonResponse([
                'error'  => 'bad_request',
                'reason' => "'name' parameter is expected but cannot be found in request body.",
            ], Response::HTTP_BAD_REQUEST);
        }

        $this->tagMgr->update($this->tagMgr->get($uid), $this->request->request->get('name'));

        return new JsonResponse('', Response::HTTP_NO_CONTENT);
    }

    public function delete($uid)
    {
        $this->denyAccessUnlessGranted(
            UserRightConstants::MANAGE_ATTRIBUTE,
            UserRightConstants::TAG_FEATURE
        );

        $this->tagMgr->delete($this->tagMgr->get($uid));

        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
