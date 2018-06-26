<?php

namespace BackBee\Renderer\Helper;

use BackBee\Renderer\Helper\AbstractHelper;
use BackBee\Renderer\Renderer;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class csrf_token extends AbstractHelper
{
    /**
     * @var \Symfony\Component\Security\Csrf\CsrfTokenManager
     */
    protected $csrfTokenManager;

    public function __construct(Renderer $renderer)
    {
        $this->setRenderer($renderer);

        $this->csrfTokenManager = $renderer->getApplication()->getContainer()->get('app.csrf_token.manager');
    }

    public function __invoke($id)
    {
        return $this->csrfTokenManager->getToken($id)->getValue();
    }
}
