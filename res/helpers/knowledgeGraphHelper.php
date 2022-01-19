<?php

/*
 * Copyright (c) 2011-2021 Lp Digital
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

namespace BackBee\Renderer\Helper;

use BackBee\KnowledgeGraph\KnowledgeGraphManager;
use BackBee\KnowledgeGraph\SeoMetadataManager;
use BackBee\NestedNode\Page;
use BackBee\Renderer\Exception\RendererException;
use Exception;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class knowledgeGraphHelper
 *
 * @package BackBee\Renderer\Helper
 *
 * @author  Michel Baptista <michel.baptista@lp-digital.fr>
 */
class knowledgeGraphHelper extends AbstractHelper
{
    /**
     * @var KnowledgeGraphManager
     */
    protected $knowledgeGraphManager;

    /**
     * @var SeoMetadataManager
     */
    protected $seoMetadataManager;

    /**
     * @var Request
     */
    protected $request;

    /**
     * Direct invocation does nothing
     *
     * @return knowledgeGraphHelper
     */
    public function __invoke(): knowledgeGraphHelper
    {
        return $this;
    }

    /**
     * @param Page $page
     *
     * @return string
     */
    public function getSeoMetadata(Page $page): string
    {
        return $this->getKgManager()->getSeoMetadata($page);
    }

    /**
     * @param Page $page
     *
     * @return string|null
     * @throws Exception
     */
    public function getGraph(Page $page): ?string
    {
        return $this->getKgManager()->getGraph($page);
    }

    /**
     * @return string|null
     * @throws RendererException
     */
    public function getMetaGoogleSiteVerification(): ?string
    {
        return $this->getKgManager()->getMetaGoogleSiteVerification();
    }

    /**
     * @return Request
     */
    protected function getRequest(): Request
    {
        return ($this->request ?: $this->getRenderer()->getApplication()->getRequest());
    }

    /**
     * Get default page by lang.
     *
     * @param Page $page
     *
     * @return Page|null
     */
    public function getDefaultPageByLang(Page $page): ?Page
    {
        $multiLangManager = $this->_renderer->getApplication()->getContainer()->get('multilang_manager');
        $pageAssociationMgr = $this->_renderer->getApplication()->getContainer()->get(
            'cloud.multilang.page_association.manager'
        );

        if ($multiLangManager->isActive() &&
            null !== ($defaultLang = $multiLangManager->getDefaultLang()) &&
            null !== ($defaultPage = $pageAssociationMgr->getAssociatedPage($page, $defaultLang['id']))
        ) {
            return $defaultPage;
        }

        return $page;
    }

    /**
     * @return KnowledgeGraphManager
     */
    protected function getKgManager(): KnowledgeGraphManager
    {
        return (
        $this->knowledgeGraphManager ?:
            $this->knowledgeGraphManager = $this
                ->getRenderer()
                ->getApplication()
                ->getContainer()
                ->get('core.knowledge_graph.manager')
        );
    }

    /**
     * @return SeoMetadataManager
     */
    protected function getSeoMetadataManager(): SeoMetadataManager
    {
        return (
        $this->seoMetadataManager ?:
            $this->seoMetadataManager = $this
                ->getRenderer()
                ->getApplication()
                ->getContainer()
                ->get('core.knowledge_graph.seo_metadata.manager')
        );
    }
}
