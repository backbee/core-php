<?php

namespace BackBee\Renderer\Helper;

use BackBee\Renderer\AbstractRenderer;
use BackBee\Renderer\Helper\AbstractHelper;

use BackBee\Bundle\Registry;

/**
 * @author Florian Kroockmann <florian.kroockmann@lp-digital.fr>
 */
class getPageErrors extends AbstractHelper
{
    const DESCRIPTION = [
        '404' => 'Page not found. The page you are looking for was moved, removed, renamed or might never existed.',
        '500' => '',
    ];

    const BUTTON_TITLE = 'BACK TO HOMEPAGE';

    public function __invoke($code = '')
    {
        $app = $this->_renderer->getApplication();
        $container = $app->getContainer();

        $userPrefMgr = $container->get('user_preference.manager');
        $result = $userPrefMgr->dataOf('error_page_' . $code);

        $data = [
            'title'        => 'Error ' . $code,
            'description'  => self::DESCRIPTION[$code],
            'button_title' => self::BUTTON_TITLE,
        ];

        if (false !== $result) {
            if (isset($result['title']) && false !== $result['title']) {
                $data['title'] = $result['title'];
            }

            if (isset($result['description']) && false !== $result['description']) {
                $data['description'] = $result['description'];
            }

            if (isset($result['button_title']) && false !== $result['button_title']) {
                $data['button_title'] = $result['button_title'];
            }
        }

        return $data;
    }
}
