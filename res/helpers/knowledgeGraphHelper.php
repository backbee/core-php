<?php

namespace BackBee\Renderer\Helper;

use BackBee\Exception\BBException;
use BackBee\NestedNode\Page;
use BackBeePlanet\GlobalSettings;
use BackBee\KnowledgeGraph\KnowledgeGraphManager;
use BackBee\KnowledgeGraph\SeoMetadataManager;
use Exception;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class knowledgeGraphHelper
 *
 * @package BackBee\Renderer\Helper
 *
 * @author Michel Baptista <michel.baptista@lp-digital.fr>
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
     * Is knowledge graph enabled.
     *
     * @return bool
     */
    public function isKnowledgeGraphEnabled(): bool
    {
        return (new GlobalSettings())->isKnowledgeGraphEnabled();
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
     */
    public function getMetaGoogleSiteVerification(): ?string
    {
        return $this->getKgManager()->getMetaGoogleSiteVerification();
    }

    /**
     * @return Request
     * @throws BBException
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

        if (
            $multiLangManager->isActive() &&
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
