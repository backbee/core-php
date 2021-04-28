<?php

namespace BackBee\Renderer\Helper;

use BackBee\Renderer\AbstractRenderer;
use BackBeeCloud\MultiLang\MultiLangManager;

/**
 * Class getCurrentLang
 *
 * @package BackBee\Renderer\Helper
 *
 * @author Eric Chau <eric.chau@lp-digital.fr>
 * @author Djoudi Bensid <djoudi.bensid@lp-digital.fr>
 */
class getCurrentLang extends AbstractHelper
{
    /**
     * @var MultiLangManager
     */
    public $multiLangManager;

    /**
     * getCurrentLang constructor.
     *
     * @param AbstractRenderer $renderer
     */
    public function __construct(AbstractRenderer $renderer)
    {
        parent::__construct($renderer);

        $this->multiLangManager = $this->_renderer->getApplication()->getContainer()->get('multilang_manager');
    }

    /**
     * @return $this
     */
    public function __invoke(): self
    {
        return $this;
    }

    /**
     * Get code.
     *
     * @return null|string
     */
    public function getCode(): ?string
    {
        return $this->multiLangManager->getCurrentLang() ?? 'fr';
    }

    /**
     * Get label.
     *
     * @param string|null $code
     *
     * @return string
     */
    public function getLabel(?string $code): string
    {
        $lang = $this->multiLangManager->getLang($code);

        return $lang['label'] ?? '';
    }
}
