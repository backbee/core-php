<?php

namespace BackBeeCloud\Controller;

use Symfony\Component\HttpFoundation\Request;

/**
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class PageByTagController extends AbstractSearchController
{
    /**
     * {@inheritdoc}
     */
    protected function getRedirectionUrlForLang($lang, Request $request)
    {
        return $this->routing->getUrlByRouteName(
            'cloud.search_by_tag_i18n',
            [
                'lang'    => $lang,
                'tagName' => $request->attributes->get('tagName', ''),
            ],
            null,
            false
        );
    }
}

