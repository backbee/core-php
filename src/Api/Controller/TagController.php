<?php

namespace BackBeeCloud\Api\Controller;

use BackBeeCloud\Api\DataFormatter\TagDataFormatter;
use BackBeeCloud\Elasticsearch\ElasticsearchCollection;
use BackBeeCloud\Entity\TagManager;
use BackBeeCloud\Listener\RequestListener;
use BackBeeCloud\Security\UserRightConstants;
use BackBee\NestedNode\KeyWord as Tag;
use BackBee\Security\SecurityContext;
use Doctrine\DBAL\DBALException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\ParameterBag;
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
    protected $tagManager;

    /**
     * @var TagDataFormatter
     */
    protected $dataFormatter;

    public function __construct(SecurityContext $securityContext, TagManager $tagManager, TagDataFormatter $dataFormatter)
    {
        $this->setSecurityContext($securityContext);

        $this->tagManager = $tagManager;
        $this->dataFormatter = $dataFormatter;
    }

    public function getCollection($start = 0, $limit = RequestListener::COLLECTION_MAX_ITEM, Request $request)
    {
        $this->assertIsAuthenticated();

        $tags = $this->tagManager->getBy(
            $request->query->get('term', ''),
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
            array_map(
                [$this->dataFormatter, 'format'],
                $tags->collection()
            ),
            $statusCode,
            [
                'Accept-Range' => 'tags ' . RequestListener::COLLECTION_MAX_ITEM,
                'Content-Range' => $max ? "$start-$end/$max" : '-/-',
            ]
        );
    }

    public function get($uid)
    {
        $this->assertIsAuthenticated();

        if (null === $tag = $this->tagManager->get($uid)) {
            return $this->getTagNotFoundJsonResponse($uid);
        }

        return new JsonResponse(
            $this->dataFormatter->format($tag),
            Response::HTTP_OK
        );
    }

    public function getTreeFirstLevelTags($start = 0, $limit = RequestListener::COLLECTION_MAX_ITEM)
    {
        $this->assertIsAuthenticated();

        $result = $this->tagManager->getTreeFirstLevelTags($start, $limit);

        $max = $result['max_count'];
        $count = count($result['collection']);
        $end = $start + $count - 1;
        $end = $end >= 0 ? $end : 0;
        $statusCode = null !== $max
            ? ($max > $count ? Response::HTTP_PARTIAL_CONTENT : Response::HTTP_OK)
            : Response::HTTP_OK
        ;

        return new JsonResponse(
            array_map(
                [$this->dataFormatter, 'format'],
                $result['collection']
            ),
            $statusCode,
            [
                'Accept-Range' => 'tags ' . RequestListener::COLLECTION_MAX_ITEM,
                'Content-Range' => $max ? "$start-$end/$max" : '-/-',
            ]
        );
    }

    public function getChildren($uid)
    {
        $this->assertIsAuthenticated();

        if (null === $tag = $this->tagManager->get($uid)) {
            return $this->getTagNotFoundJsonResponse($uid);
        }

        return new JsonResponse(
            array_map(
                [$this->dataFormatter, 'format'],
                $tag->getChildren()->toArray()
            ),
            Response::HTTP_OK
        );
    }

    /**
     * Returns an instance of JsonResponse that contains list of pages (id and title)
     * that are linked to the provided tag.
     *
     * @param  string $uid The tag's uid
     * @return JsonResponse
     */
    public function getLinkedPages($uid)
    {
        $this->denyAccessUnlessGranted(
            UserRightConstants::MANAGE_ATTRIBUTE,
            UserRightConstants::TAG_FEATURE
        );

        if (null === $tag = $this->tagManager->get($uid)) {
            return $this->getTagNotFoundJsonResponse($uid);
        }

        return new JsonResponse(
            $this->tagManager->getLinkedPages($tag)
        );
    }

    public function post(Request $request)
    {
        $this->denyAccessUnlessGranted(
            UserRightConstants::MANAGE_ATTRIBUTE,
            UserRightConstants::TAG_FEATURE
        );

        $data = [];
        $tag = null;
        try {
            $data = $this->assertAndExtractPostAndPutRequestData($request->request);

            $tag = $this->tagManager->create(
                $data['name'],
                $data['parent'],
                $data['translations']
            );
        } catch (\Exception $exception) {
            return new JsonResponse(
                [
                    'error' => 'bad_request',
                    'reason' => $exception->getMessage(),
                ],
                Response::HTTP_BAD_REQUEST
            );
        }

        return new JsonResponse(
            $this->dataFormatter->format($tag),
            Response::HTTP_CREATED
        );
    }

    public function put($uid, Request $request)
    {
        $this->denyAccessUnlessGranted(
            UserRightConstants::MANAGE_ATTRIBUTE,
            UserRightConstants::TAG_FEATURE
        );

        if (null === $tag = $this->tagManager->get($uid)) {
            return $this->getTagNotFoundJsonResponse($uid);
        }

        $data = [];
        try {
            $data = $this->assertAndExtractPostAndPutRequestData($request->request);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(
                [
                    'error' => 'bad_request',
                    'reason' => $exception->getMessage(),
                ],
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            $this->tagManager->update(
                $tag,
                $data['name'],
                $data['parent'],
                $data['translations']
            );
        } catch (\RuntimeException $exception) {
            return new JsonResponse(
                [
                    'error' => 'bad_request',
                    'reason' => $exception->getMessage(),
                ],
                Response::HTTP_BAD_REQUEST
            );
        }

        return new JsonResponse('', Response::HTTP_NO_CONTENT);
    }

    public function delete($uid)
    {
        $this->denyAccessUnlessGranted(
            UserRightConstants::MANAGE_ATTRIBUTE,
            UserRightConstants::TAG_FEATURE
        );

        if (null === $tag = $this->tagManager->get($uid)) {
            return $this->getTagNotFoundJsonResponse($uid);
        }

        try {
            $this->tagManager->delete($tag);
        } catch (DBALException $exception) {
            return new JsonResponse([
                'error' => 'bad_request',
                'reason' => 'Cannot delete tag because it has children.',
            ], Response::HTTP_BAD_REQUEST);
        }

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    private function assertAndExtractPostAndPutRequestData(ParameterBag $bag)
    {
        $verifiedData = [];
        if (!$bag->has('name') || false == $bag->get('name')) {
            throw new \InvalidArgumentException(
                '\'name\' parameter is expected but cannot be found in request body.'
            );
        }

        $verifiedData['name'] = $bag->get('name');

        $parent = null;
        if (
            $bag->has('parent_uid')
            && false != $bag->get('parent_uid')
            && null === $parent = $this->tagManager->get($bag->get('parent_uid'))
        ) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Cannot find parent tag with provided uid (:%s).',
                    $bag->get('parent_uid')
                )
            );
        }

        $verifiedData['parent'] = $parent;
        if (
            $bag->has('translations')
            && !is_array($bag->get('translations'))
        ) {
            throw new \InvalidArgumentException(
                '\'translations\' parameter must be type of array.'
            );
        }

        $verifiedData['translations'] = $bag->get('translations', []);

        return $verifiedData;
    }

    private function getTagNotFoundJsonResponse($unknownUid)
    {
        return new JsonResponse(
            [
                'error' => 'not_found',
                'reason' => sprintf(
                    'Cannot find tag with provided uid (:%s)',
                    $unknownUid
                ),
            ],
            Response::HTTP_NOT_FOUND
        );
    }
}
