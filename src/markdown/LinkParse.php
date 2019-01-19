<?php
namespace clomery\markdown;

use Parsedown;

class LinkParse extends Parsedown
{
    /**
     * 图片链接
     *
     * @var array
     */
    protected $images = [];
    /**
     * 网页链接
     *
     * @var array
     */
    protected $links = [];
   
    
    protected function inlineImage($excerpt)
    {
        $image = parent::inlineImage($excerpt);
        $link = $image['element']['attributes']['src'];
        if (strpos($link, 'http') !== 0) {
            $this->images[]=$link;
        }
        return $image;
    }
    protected function inlineLink($excerpt)
    {
        $link = parent::inlineLink($excerpt);
        $href = $link['element']['attributes']['href'];
        if (strpos($href, 'http') !== 0) {
            $this->links[]=$href;
        }
        return $link;
    }

    /**
     * Get 图片链接
     *
     * @return  array
     */
    public function getImages()
    {
        return $this->images;
    }

    /**
     * Get 网页链接
     *
     * @return  array
     */
    public function getLinks()
    {
        return array_diff($this->links, $this->images);
    }

    /**
     * Get 文章附件
     *
     * @return  array
     */
    public function getAttachments()
    {
        $att = array_filter($this->getLinks(), function ($item) {
            return preg_match('/\.md/', $item) !== false;
        });
        sort($att);
        return $att;
    }
}
