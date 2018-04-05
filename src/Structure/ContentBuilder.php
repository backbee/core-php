<?php

namespace BackBeeCloud\Structure;

use BackBee\ClassContent\AbstractClassContent;
use BackBee\ClassContent\Basic\Title;
use BackBee\ClassContent\CloudContentSet;
use BackBee\ClassContent\ColContentSet;
use BackBee\ClassContent\ContentAutoblock;
use BackBee\ClassContent\Revision;
use BackBee\DependencyInjection\Container;
use BackBee\NestedNode\Page;
use BackBee\Security\Token\BBUserToken;
use Doctrine\ORM\EntityManager;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class ContentBuilder
{
    use ClassContentHelperTrait;

    const CONTENT_HANDLER_SERVICE_TAG = 'structure.content_handler';

    /**
     * @var EntityManager
     */
    protected $entyMgr;

    /**
     * @var array
     */
    protected $contentHandlers = [];

    public function __construct(Container $dic, EntityManager $entyMgr)
    {
        $this->entyMgr = $entyMgr;
        foreach ($dic->findTaggedServiceIds(self::CONTENT_HANDLER_SERVICE_TAG) as $id => $data) {
            $this->contentHandlers[] = $dic->get($id);
        }
    }

    public function getContentHandlers()
    {
        return $this->contentHandlers;
    }

    /**
     * Loops into all content handlers to find the handler which can handle the
     * hydratation of provided content with provided data.
     *
     * @param  AbstractClassContent $content
     * @param  array                $data
     */
    public function hydrateContent(AbstractClassContent $content, array $data)
    {
        foreach ($this->contentHandlers as $handler) {
            if ($handler->supports($content)) {
                $handler->handle($content, $data);
            }
        }
    }

    /**
     * Loops into contents data to inject them into page contentset.
     *
     * @param  Page   $page
     * @param  array  $contents
     */
    public function hydrateContents(Page $page, array $contents, BBUserToken $token = null)
    {
        $this
            ->putContentOnline($page->getContentSet())
            ->putContentOnline($page->getContentSet()->first())
        ;

        $mainContainer = $page->getContentSet()->first();
        $mainContainer->clear();

        foreach ($contents as $data) {
            $cloudContentSet = $this->createContent(CloudContentSet::class, $token);
            $this->hydrateContent($cloudContentSet, isset($data['data']) ? $data['data'] : []);

            $mainContainer->push($cloudContentSet);

            foreach ($data['columns'] as $items) {
                $colContentSet = $this->createContent(ColContentSet::class, $token);
                $cloudContentSet->push($colContentSet);

                foreach ($items as $item) {
                    $content = $this->createContent($item['type'], $token, isset($item['uid']) ? $item['uid'] : null);
                    $itemData = isset($item['data']) ? $item['data'] : [];
                    if ($content instanceof Title) {
                        $itemData['text'] = str_replace(
                            strip_tags($itemData['text']),
                            $page->getTitle(),
                            $itemData['text']
                        );
                    }

                    $this->hydrateContent($content, $itemData);
                    $colContentSet->push($content);
                }
            }
        }
    }

    /**
     * Creates a new content according to provided name (could be classcontent type or classname)
     * and updates it to be visible online.
     *
     * @param  string $name
     * @return AbstractClassContent
     */
    protected function createContent($type, BBUserToken $token = null, $uid = null)
    {
        $classname = $this->getClassnameFromType($type);
        if ($uid && $content = $this->entyMgr->find($classname, $uid)) {
            return $content;
        }

        $content = new $classname($uid);
        if (null === $token) {
            $this->putContentOnline($content);
        }

        $this->entyMgr->persist($content);

        return $content;
    }

    /**
     * Hydrates a draft to the given content if an instance of BBUserToken is provided.
     *
     * This method will checkout a new draft if no draft is found.
     *
     * @param  AbstractClassContent $content
     * @param  BBUserToken|null     $token
     * @return AbstractClassContent
     */
    public function hydrateDraft(AbstractClassContent $content, BBUserToken $token = null)
    {
        if (null !== $token) {
            $draft = $this->entyMgr->getRepository(Revision::class)->checkout($content, $token);
            $content->setDraft($draft);
        }

        return $content;
    }
}
