<?php

namespace BackBeeCloud\Search;

use BackBee\ClassContent\Article\ArticleAbstract;
use BackBee\ClassContent\Basic\BaseSearchResult;
use BackBee\ClassContent\Media\Image;
use BackBee\ClassContent\Revision;
use BackBee\ClassContent\Text\Paragraph;
use BackBee\Renderer\Renderer;
use BackBee\Security\Token\BBUserToken;
use Doctrine\ORM\EntityManager;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class ResultItemHtmlFormatter
{
    /**
     * @var EntityManager
     */
    protected $entyMgr;

    /**
     * @var Renderer
     */
    protected $renderer;

    /**
     * @var null|BBUserToken
     */
    protected $bbtoken;

    /**
     * Constructor.
     *
     * @param EntityManager    $entyMgr
     * @param Renderer         $renderer
     * @param BBUserToken|null $bbtoken
     */
    public function __construct(EntityManager $entyMgr, Renderer $renderer, BBUserToken $bbtoken = null)
    {
        $this->entyMgr = $entyMgr;
        $this->renderer = $renderer;
        $this->bbtoken = $bbtoken;
    }

    public function renderItemFromRawData(BaseSearchResult $content, array $pageRawData)
    {
        $abstract = null;
        if (false != $abstractUid = $pageRawData['_source']['abstract_uid']) {
            $abstract = $this->getContentWithDraft(ArticleAbstract::class, $abstractUid);
            if (null !== $abstract) {
                $abstract = $this->getContentWithDraft(Paragraph::class, $abstractUid);
            }

            if (null !== $abstract) {
                $abstract = trim(preg_replace(
                    '#\s\s+#',
                    ' ',
                    preg_replace('#<[^>]+>#', ' ', $abstract->value)
                ));
            }
        }

        $imageData = [];
        if (false != $imageUid = $pageRawData['_source']['image_uid']) {
            $image = $this->getContentWithDraft(Image::class, $imageUid);
            if (null !== $image) {
                $imageData = [
                    'uid'    => $image->getUid(),
                    'url'    => $image->path,
                    'title'  => $image->getParamValue('title'),
                    'legend' => $image->getParamValue('description'),
                    'stat'   => $image->getParamValue('stat'),
                ];
            }
        }

        return $this->renderer->partial('SearchResult/page_item.html.twig', [
            'title'                => $pageRawData['_source']['title'],
            'abstract'             => (string) $abstract,
            'url'                  => $pageRawData['_source']['url'],
            'is_online'            => $pageRawData['_source']['is_online'],
            'image'                => $imageData,
            'publishing'           => $pageRawData['_source']['published_at']
                ? new \DateTime($pageRawData['_source']['published_at'])
                : null
            ,
            'show_image'           => $content->getParamValue('show_image'),
            'show_abstract'        => $content->getParamValue('show_abstract'),
            'show_published_at'    => $content->getParamValue('show_published_at'),
        ]);
    }

    protected function getContentWithDraft($classname, $uid)
    {
        $content = $this->entyMgr->find($classname, $uid);
        if (null !== $content && null !== $this->bbtoken) {
            $draft = $this->entyMgr
                ->getRepository(Revision::class)
                ->getDraft($content, $this->bbtoken, false)
            ;
            $content->setDraft($draft);
        }

        return $content;
    }
}
