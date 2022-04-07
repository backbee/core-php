<?php

/*
 * Copyright (c) 2022 Obione
 *
 * This file is part of BackBee Standalone.
 *
 * BackBee is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with BackBee Standalone. If not, see <https://www.gnu.org/licenses/>.
 */

namespace BackBeeCloud\Api\Controller;

use BackBee\Security\SecurityContext;
use BackBeeCloud\Api\DataFormatter\TagDataFormatter;
use BackBeeCloud\Elasticsearch\ElasticsearchCollection;
use BackBeeCloud\Listener\RequestListener;
use BackBeeCloud\Security\UserRightConstants;
use BackBeeCloud\Tag\TagManager;
use Doctrine\DBAL\DBALException;
use Exception;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use function is_array;

/**
 * Tag controller.
 *
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

    /**
     * @param \BackBee\Security\SecurityContext                $securityContext
     * @param \BackBeeCloud\Tag\TagManager                     $tagManager
     * @param \BackBeeCloud\Api\DataFormatter\TagDataFormatter $dataFormatter
     */
    public function __construct(
        SecurityContext $securityContext,
        TagManager $tagManager,
        TagDataFormatter $dataFormatter
    ) {
        $this->setSecurityContext($securityContext);

        $this->tagManager = $tagManager;
        $this->dataFormatter = $dataFormatter;

        parent::__construct($securityContext->getApplication());
    }

    /**
     * Get collection.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @param int                                       $start
     * @param int                                       $limit
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function getCollection(
        Request $request,
        int $start = 0,
        int $limit = RequestListener::COLLECTION_MAX_ITEM
    ): JsonResponse {
        $this->assertIsAuthenticated();

        $tags = $this->tagManager->getBy(
            $request->query->get('term', ''),
            $request->query->get('context', ''),
            $start,
            $limit
        );

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
            : Response::HTTP_OK;

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

    /**
     * Get tag.
     *
     * @param $uid
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function get($uid): JsonResponse
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

    /**
     * Get tree first level tags.
     *
     * @param int $start
     * @param int $limit
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function getTreeFirstLevelTags(
        int $start = 0,
        int $limit = RequestListener::COLLECTION_MAX_ITEM
    ): JsonResponse {
        $this->assertIsAuthenticated();

        $result = $this->tagManager->getTreeFirstLevelTags($start, $limit);

        $max = $result['max_count'];
        $count = count($result['collection']);
        $end = $start + $count - 1;
        $end = $end >= 0 ? $end : 0;
        $statusCode = null !== $max
            ? ($max > $count ? Response::HTTP_PARTIAL_CONTENT : Response::HTTP_OK)
            : Response::HTTP_OK;

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

    /**
     * Get children.
     *
     * @param $uid
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function getChildren($uid): JsonResponse
    {
        $this->assertIsAuthenticated();

        if (null === $tag = $this->tagManager->get($uid)) {
            return $this->getTagNotFoundJsonResponse($uid);
        }

        $children = array_map(
            [$this->dataFormatter, 'format'],
            $tag->getChildren()->toArray()
        );

        usort($children, static function ($first, $second) {
            return strtolower($first['keyword']) <=> strtolower($second['keyword']);
        });

        return new JsonResponse(
            $children,
            Response::HTTP_OK
        );
    }

    /**
     * Returns an instance of JsonResponse that contains list of pages (id and title)
     * that are linked to the provided tag.
     *
     * @param string $uid The tag's uid
     *
     * @return JsonResponse
     */
    public function getLinkedPages(string $uid): JsonResponse
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

    /**
     * Post tag.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function post(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(
            UserRightConstants::MANAGE_ATTRIBUTE,
            UserRightConstants::TAG_FEATURE
        );

        try {
            $data = $this->assertAndExtractPostAndPutRequestData($request->request);

            $tag = $this->tagManager->create(
                $data['name'],
                $data['parent'],
                $data['translations']
            );
        } catch (Exception $exception) {
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

    /**
     * Update tag.
     *
     * @param string                                    $uid
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function put(string $uid, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(
            UserRightConstants::MANAGE_ATTRIBUTE,
            UserRightConstants::TAG_FEATURE
        );

        if (null === $tag = $this->tagManager->get($uid)) {
            return $this->getTagNotFoundJsonResponse($uid);
        }

        try {
            $data = $this->assertAndExtractPostAndPutRequestData($request->request);
        } catch (InvalidArgumentException $exception) {
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
        } catch (RuntimeException $exception) {
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

    /**
     * Delete tag.
     *
     * @param string $uid
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function delete(string $uid): JsonResponse
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

        return new JsonResponse('', Response::HTTP_NO_CONTENT);
    }

    /**
     * Assert and extract post and put request data.
     *
     * @param \Symfony\Component\HttpFoundation\ParameterBag $bag
     *
     * @return array
     */
    private function assertAndExtractPostAndPutRequestData(ParameterBag $bag): array
    {
        $verifiedData = [];
        if (!$bag->has('name') || false === $bag->get('name')) {
            throw new InvalidArgumentException(
                '\'name\' parameter is expected but cannot be found in request body.'
            );
        }

        $verifiedData['name'] = $bag->get('name');

        $parent = null;
        if (
            $bag->has('parent_uid')
            && false !== $bag->get('parent_uid')
            && !empty($bag->get('parent_uid'))
            && null === $parent = $this->tagManager->get($bag->get('parent_uid'))
        ) {
            throw new InvalidArgumentException(
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
            throw new InvalidArgumentException(
                '\'translations\' parameter must be type of array.'
            );
        }

        $verifiedData['translations'] = $bag->get('translations', []);

        return $verifiedData;
    }

    /**
     * Get tag not found json response.
     *
     * @param $unknownUid
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    private function getTagNotFoundJsonResponse($unknownUid): JsonResponse
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
