<?php
class VBulletinBridge extends BridgeAbstract {
    const NAME = 'VBulletin';
    const DESCRIPTION = 'Reads threads from VBulletin boards';
    const MAINTAINER = 'No maintainer';
    const CACHE_TIMEOUT = 3600;

    const PARAMETERS = array(
        array(
            'uri' => array(
                'name' => 'URI',
                'type' => 'text',
                'required' => 'true',
                'title' => 'Topic URI for vbulletin thread',
                'exampleValue' => 'https://forum.vbulletin.com'
            ),
            'tid' => array(
                'name' => 'Thread Id',
                'type' => 'number',
                'required' => 'true',
                'title' => 'Thread id to fetch'
            ),
            'limit' => array(
                'name' => 'Limit',
                'type' => 'number',
                'required' => 'false',
                'title' => 'Maximum number of posts to fetch'
            )
        )
    );
    //const CACHE_TIMEOUT = 3600; // Can be omitted!

    //since the latest page changes frequently, give it a shorter timeout
    const FIRST_PAGE_CACHE_TIMEOUT = 3600;
    //all other pages change rarely, so give them a maximum timeout
    const OTHER_PAGE_CACHE_TIMEOUT = 3600 * 24;
    
    const LAST_PAGE = 99999;
    const BOLD_PATTERN = '/<b>\s*(\w[^<]*)<\/b>/m';
    const BR_PATTERN = '/\s*(\w[^<>]*)<br\s*(?:\/)?>/m';

    private ?string $name = null;
    

    public function getURI() {
        return $this->getInput('uri');
    }

    private function makeURI($tid, $page) {
        return rtrim($this->getURI() . "/t" . $tid . "-p" . $page . ".html");
    }

    public function getName() {
        return $this->name ?: parent::getName();
    }


    public function collectData() {
        $tid = $this->getInput('tid');

        $limit = $this->getInput('limit');

        //Debug::log("limit is " . $limit);

        $pageUri = $this->makeURI($tid, static::LAST_PAGE);
        $first = true;
        while (!is_null($pageUri)
            && ($limit == 0 || count($this->items) < $limit) ) {
            //Debug::log("fetching " . $pageUri);

            if ($first) {
                $cacheTimeout = static::FIRST_PAGE_CACHE_TIMEOUT;
                $first = false;
            } else {
                $cacheTimeout = static::OTHER_PAGE_CACHE_TIMEOUT;
            }
            //Debug::log("Fetching page with timeout " . $cacheTimeout);
            $html = getSimpleHTMLDOMCached($pageUri, $cacheTimeout)
                or returnServerError("Could not request " . $pageUri);

            if (is_null($this->name)) {
                $this->name = $html->find('table[class="tborder awn-ignore"] td[class="navbar"] strong', 0)->innertext;
            }
            $nextHref = $html->find('a[rel="prev"]', 0);
            if (is_null($nextHref)) {
                //Debug::log("nextHref was null");
                $pageUri = null;
            } else {
                //Debug::log("nextHref was " . $nextHref->href);
                $pageUri = $this->rel2abs($nextHref->href);
            }
            //Debug::log("next uri is " . $pageUri);
            foreach(array_reverse($html->find('table[id^=post]')) as $container) {
                $item = array();
                $uri = $this->rel2abs($container->find('a[id^=postcount]', 0)->href);
                $item['uri'] = $uri;
                $post = $container->find('div[id^=post_message]', 0);
                $postHtml = $post->innertext;
                $stripped = strip_tags($postHtml);
                //Debug::log("Post Html " . $postHtml);
                //First, look at the real title
                if (($realTitle = $container->find('td[id^=td_post] div[class="smallfont"] strong', 0)) != null) {
                    Debug::log("Matched real title " . $realTitle);
                    $title = $realTitle->innertext;
                } else if (preg_match(static::BOLD_PATTERN, $postHtml, $matches)) {
                    //Just find something that's bold in the post

                    //Debug::log("Matched " . implode(', ', $matches));
                    $title = $matches[0];
                } else if (($font = $post->find('font', 0)) != null) {
                    //Try to find the font block
                    Debug::log("Matched font");
                    $title = $font->innertext;
                } else if (preg_match(static::BR_PATTERN, $postHtml, $matches)) {
                    //Try to find something with line breaks.

                    //Debug::log("Matched " . implode(', ', $matches));
                    $title = $matches[0];
                } else {
                    //Okay, just use the whole post.
                    Debug::log("No match");
                    Debug::log($postHtml);
                    Debug::log($stripped);
                    $title = $stripped;
                }
                $item['title'] = $title;
                $item['content'] = $post->innertext;
                $timestamp = strtotime($container->find('a[name^=post]', 0)->parent->plaintext);
                $item['author'] = $container->find('a[class=bigusername]', 0)->innertext;
                $item['uid'] = $container->id;

                $this->items[] = $item;
            }
        }

        //We might have more items than specified
        if (!is_null($limit))
            $this->items = array_slice($this->items, 0, $limit);
    }

    private function rel2abs($href) {
        return urljoin($this->getURI(), html_entity_decode($href));
    }

}


