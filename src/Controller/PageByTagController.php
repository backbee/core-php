<?php

namespace BackBeeCloud\Controller;

use Symfony\Component\HttpFoundation\Request;

/**
 * Class PageByTagController
 *
 * @package BackBeeCloud\Controller
 *
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class PageByTagController extends AbstractSearchController
{
    /**
     * Get redirection url for lang.
     *
     * @param         $lang
     * @param Request $request
     *
     * @return string
     */
    protected function getRedirectionUrlForLang($lang, Request $request): string
    {
        return $this->routing->getUrlByRouteName(
            'cloud.search_by_tag_i18n',
            [
                'lang' => $lang,
                'tagName' => $request->attributes->get('tagName', ''),
            ],
            null,
            false
        );
    }
}

