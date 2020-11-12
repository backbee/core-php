<?php

namespace BackBeeCloud\Search;

use BackBee\ClassContent\Article\ArticleAbstract;
use BackBee\ClassContent\Basic\Image;
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

    public function renderItemFromRawData(array $pageRawData, array $extraParams = [])
    {
        $params = $pageRawData['_source'];

        $params['publishing'] = $params['published_at']
            ? new \DateTime($params['published_at'])
            : null;

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

    protected function getContentWithDraft($classname, $uid)
    {
        $content = $this->entyMgr->find($classname, $uid);
        if (null !== $content && null !== $this->bbtoken) {
            $draft = $this->entyMgr
                ->getRepository(Revision::class)
                ->getDraft($content, $this->bbtoken, false);
            $content->setDraft($draft);
        }

        return $content;
    }
}
