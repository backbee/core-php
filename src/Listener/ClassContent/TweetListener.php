<?php

namespace BackBeeCloud\Listener\ClassContent;

use BackBee\Renderer\Event\RendererEvent;

use GuzzleHttp\Client;

/**
 * @author Florian Kroockmann <florian.kroockmann@lp-digital.fr>
 */
class TweetListener
{
    /**
     * Tweet onRender event
     *
     * @param RendererEvent $event
     * @return void
     */
    public static function onRender(RendererEvent $event)
    {
        $renderer = $event->getRenderer();
        $content = $event->getTarget();

        $url = $content->getParamValue('url');
        $limit = (int) $content->getParamValue('limit');

        if (false == $url) {
            return;
        }

        $urlData = parse_url($url);

        if ($urlData['host'] === 'twitter.com') {
            try {
                $res = (new Client())->request('GET', 'https://publish.twitter.com/oembed?url=' . urlencode($url) . '&limit=' . $limit . '', [
                    'headers' => [
                        'Accept'     => 'application/json',
                    ]
                ]);

                $data = json_decode((string) $res->getBody());

                $renderer->assign('html', $data->html);
            } catch (\Exception $e) {
                // nothing to do
            }
        }
    }
}