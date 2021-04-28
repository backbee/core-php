<?php

namespace BackBee\Renderer\Helper;

use BackBee\Renderer\Renderer;
use Symfony\Component\Translation\Translator;

/**
 * Class trans
 *
 * @package BackBee\Renderer\Helper
 *
 * @author  Eric Chau <eric.chau@lp-digital.fr>
 */
class trans extends AbstractHelper
{
    /**
     * @var Translator
     */
    protected $translator;

    /**
     * @var string|null
     */
    protected $currentLang;

    /**
     * trans constructor.
     *
     * @param Renderer $renderer
     */
    public function __construct(Renderer $renderer)
    {
        $this->setRenderer($renderer);

        $this->translator = $renderer->getApplication()->getContainer()->get('translator');
        $this->currentLang = $renderer
                ->getApplication()
                ->getContainer()
                ->get('multilang_manager')
                ->getCurrentLang() ?? 'fr';

        parent::__construct($renderer);
    }

    /**
     * Invoke.
     *
     * @param       $id
     * @param array $parameters
     * @param null  $locale
     *
     * @return string
     */
    public function __invoke($id, array $parameters = [], $locale = null): string
    {
        return $this->translator->trans($id, $parameters, null, $locale ?: $this->currentLang);
    }
}
