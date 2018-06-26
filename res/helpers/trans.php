<?php

namespace BackBee\Renderer\Helper;

use BackBee\Renderer\Helper\AbstractHelper;
use BackBee\Renderer\Renderer;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class trans extends AbstractHelper
{
    /**
     * @var \Symfony\Component\Translation\Translator
     */
    protected $translator;

    /**
     * @var string|null
     */
    protected $currentLang;

    public function __construct(Renderer $renderer)
    {
        $this->setRenderer($renderer);

        $this->translator = $renderer->getApplication()->getContainer()->get('translator');
        $this->currentLang = $renderer->getCurrentLang();
    }

    public function __invoke($id, array $parameters = [], $locale = null)
    {
        return $this->translator->trans($id, $parameters, null, $locale ?: $this->currentLang);
    }
}
