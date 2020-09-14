<?php
class DataLifeEngineBridge extends BridgeAbstract {
    const NAME = 'DataLife Engine';
    const DESCRIPTION = 'Reads a blog from  the DataLife Engine';
    const MAINTAINER = 'No maintainer';
    const CACHE_TIMEOUT = 3600;

    const PARAMETERS = array(
        array(
            'uri' => array(
                'name' => 'URI',
                'type' => 'text',
                'required' => 'true',
                'title' => 'DLE blog site',
                'exampleValue' => 'https://blog.dle.com'
            ),
            'limit' => array(
                'name' => 'Limit',
                'type' => 'number',
                'required' => 'false',
                'title' => 'Maximum number of posts to fetch'
            )
        )
    );

    private ?string $name = null;

    public function getURI() {
        return $this->getInput('uri');
    }

    public function getName() {
        return $this->name ? : parent::getName();
    }

    public function collectData(){
        $limit = $this->getInput('limit');

        $uri = $this->getURI();
        while (!is_null($uri) && ($limit == 0 || count($this->items) < $limit)) {
            $html = getSimpleHTMLDOM($uri)
                or returnServerError('No contents received!');

            if (is_null($this->name)) {
                $this->name = $html->find('head title', 0)->innertext;
            }

            foreach ($html->find('article') as $article) {
                $item = array();

                $titleAnchor = $article->find('h2[class="title"] a', 0);
                Debug::log('URI is ' . $titleAnchor->innertext);
                Debug::log('HREF is ' . $titleAnchor->href);
                $item['uri'] = $titleAnchor->href;
                $item['title'] = $titleAnchor->innertext;
                $content = $article->find('div[class="post_data"]', 0);
                if (is_null($content)) {
                    //This can be post_data or text
                    $content = $article->find('div[class="text"]', 0);
                }


                foreach ($content->getElementsByTagName('img') as $img) {
                    Debug::log("Found element " . $img);
                    $img->class = null;
                    if (!empty($img->src)) {
                        Debug::log('matched img->src ' . $img->src);
                        $img->src = $this->rel2abs($img->src);
                    } else if (!empty($img->{'data-src'})) {
                        Debug::log('matched img->data-src ' . $img->{'data-src'});
                        $img->src = $this->rel2abs($img->{'data-src'});
                        $img->{'data-src'} = null;
                    }
                }
                $item['content'] = $content->innertext;
                $item['uid'] = $item['uri'];
                $item['categories'] = array_map(function($a) {
                        return $a->innertext;
                    }, $article->find('div[class="category"] a'));

                $this->items[] = $item;
            }

            $next = $html->find('div [class="navigation"] div[class="page"] span[class="next"] a', 0);
            if (is_null($next))
                $uri = null;
            else
                $uri = $next->href;

            //We might have more items than specified
            if (!is_null($limit))
                $this->items = array_slice($this->items, 0, $limit);
        }

    }

    private function rel2abs($href) {
        Debug::log("rel2abs called on " . $href);
        $parseUrl = parse_url($this->getURI());
        $url = $parseUrl['scheme'] . "://" . $parseUrl['host'] . (array_key_exists('port', $parseUrl) ? (":" . $parseUrl['port']) : "");
        return urljoin($url, html_entity_decode($href));
    }


}
