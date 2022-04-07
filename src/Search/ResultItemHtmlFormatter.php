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

namespace BackBeeCloud\Search;

use BackBee\ClassContent\Article\ArticleAbstract;
use BackBee\ClassContent\Basic\Image;
use BackBee\ClassContent\Revision;
use BackBee\ClassContent\Text\Paragraph;
use BackBee\Renderer\Exception\RendererException;
use BackBee\Renderer\Renderer;
use BackBee\Security\Token\BBUserToken;
use DateTime;
use Doctrine\ORM\EntityManager;
use Exception;
use Psr\Log\LoggerInterface;

/**
 * Class ResultItemHtmlFormatter
 *
 * @package BackBeeCloud\Search
 *
 * @author  Eric Chau <eric.chau@lp-digital.fr>
 */
class ResultItemHtmlFormatter
{
    /**
     * @var EntityManager
     */
    protected $entityMgr;

    /**
     * @var Renderer
     */
    protected $renderer;

    /**
     * @var null|BBUserToken
     */
    protected $bbToken;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * ResultItemHtmlFormatter constructor.
     *
     * @param EntityManager    $entityMgr
     * @param Renderer         $renderer
     * @param LoggerInterface  $logger
     * @param BBUserToken|null $bbToken
     */
    public function __construct(
        EntityManager $entityMgr,
        Renderer $renderer,
        LoggerInterface $logger,
        BBUserToken $bbToken = null
    ) {
        $this->entityMgr = $entityMgr;
        $this->renderer = $renderer;
        $this->logger = $logger;
        $this->bbToken = $bbToken;
    }

    /**
     * Render item from raw data.
     *
     * @param array $pageRawData
     * @param array $extraParams
     *
     * @return string|void
     * @throws RendererException
     */
    public function renderItemFromRawData(array $pageRawData, array $extraParams = [])
    {
        $params = $pageRawData['_source'];

        try {
            $params['publishing'] = $params['published_at'] ? new DateTime($params['published_at']) : null;
        } catch (Exception $exception) {
            $this->logger->error(
                sprintf(
                    '%s : %s :%s',
                    __CLASS__,
                    __FUNCTION__,
                    $exception->getMessage()
                )
            );
        }

        if (null !== $abstractUid = $pageRawData['_source']['abstract_uid'] ?? null) {
            $abstract = $this->getContentWithDraft(ArticleAbstract::class, $abstractUid);
            if (null === $abstract) {
                $abstract = $this->getContentWithDraft(Paragraph::class, $abstractUid);
            }

            if (null !== $abstract) {
                $params['abstract'] = trim(
                    preg_replace(
                        '#\s\s+#',
                        ' ',
                        preg_replace('#<[^>]+>#', ' ', $abstract->value)
                    )
                );
            }
        }

        if (null !== $imageUid = $pageRawData['_source']['image_uid'] ?? null) {
            $image = $this->getContentWithDraft(Image::class, $imageUid);
            if (null !== $image) {
                $params['image'] = [
                    'uid' => $image->getUid(),
                    'url' => $image->image->path,
                    'title' => $image->getParamValue('title'),
                    'legend' => $image->getParamValue('description'),
                    'alt' => $this->renderer->getImageAlternativeText($image, $params['title']),
                    'stat' => $image->image->getParamValue('stat'),
                ];
            }
        }

        unset(
            $params['published_at'],
            $params['abstract_uid'],
            $params['image_uid']
        );

        return $this->renderer->reset()->partial(
            'SearchResult/page_item.html.twig',
            array_merge(
                $params,
                $extraParams
            )
        );
    }

    /**
     * Get content with draft.
     *
     * @param $classname
     * @param $uid
     *
     * @return object|null
     */
    protected function getContentWithDraft($classname, $uid)
    {
        $content = null;

        try {
            $content = $this->entityMgr->find($classname, $uid);
            if (null !== $content && null !== $this->bbToken) {
                $draft = $this->entityMgr
                    ->getRepository(Revision::class)
                    ->getDraft($content, $this->bbToken);
                $content->setDraft($draft);
            }
        } catch (Exception $exception) {
            $this->logger->error(
                sprintf(
                    '%s : %s :%s',
                    __CLASS__,
                    __FUNCTION__,
                    $exception->getMessage()
                )
            );
        }

        return $content;
    }
}
