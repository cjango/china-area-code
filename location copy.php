<?php

class Location
{
    protected string $baseUrl = 'https://www.stats.gov.cn/sj/tjbz/tjyqhdmhcxhfdm/2023/';

    protected array $list = [];

    protected int $level = 5;

    function handle() {
        $current = $this->baseUrl . 'index.html';
        $html = file_get_contents($current);

        $doc = new DOMDocument();
        $doc->loadHTML($html);
        $xpath = new DOMXPath($doc);
        $elements = $xpath->query("//tr[contains(@class, 'tr')]//a");

        foreach ($elements as $element) {
            $nextUrl = $element->attributes['href']->value;
            $pId = str_replace('.html', '', $nextUrl);
            var_dump($pId . ' --- ' .  $element->nodeValue);
            var_dump($element->attributes['href']->value);
        }
    }

    function getProvince()
    {
        $current = $this->baseUrl . 'index.html';
        $html = file_get_contents($current);

        $doc = new DOMDocument();
        $doc->loadHTML($html);
        $xpath = new DOMXPath($doc);
        $elements = $xpath->query("//tr[@class='provincetr']//a");

        foreach ($elements as $element) {
            $nextUrl = $element->attributes['href']->value;
            $pId = str_replace('.html', '', $nextUrl);
            var_dump('省份：：' . $pId . ' --- ' .  $element->nodeValue);

            $children = $this->getCity($this->buildUrl($current, $nextUrl), 1);

            $data = [
                'id' => $pId,
                'name' => $element->nodeValue,
                'level' => 1,
                'children' => $children
            ];
            $list[] = $data;

            file_put_contents($pId . '.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        file_put_contents('list.json', json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    function getCity($uri, $level)
    {
        if ($level >= $this->level) {
            return;
        }
        // $current = $this->baseUrl . $uri;
        $html = file_get_contents($uri);
        $docNext = new DOMDocument();
        $docNext->loadHTML($html);
        $xpathNext = new DOMXPath($docNext);
        // $elementsNext = $xpathNext->query("//tr[@class]");
        $elementsNext = $xpathNext->query("//tr[contains(@class, 'tr')]");
        $layer = $level + 1;

        $list = [];
        // var_dump('Children: ' . $elementsNext->length);
        foreach ($elementsNext as $n) {
            $children = [];
            if ($n->firstChild->hasChildNodes() && $n->firstChild->firstChild->nodeName == 'a') {
                $children = $this->getCity($this->buildUrl($uri, $n->firstChild->firstChild->attributes['href']->value),  $layer);
            }

            if ($n->firstChild->firstChild->nodeName != 'a' && $n->lastChild->nodeValue == '市辖区') {
                break;
            }
            var_dump($n->firstChild->nodeValue . '>>>>>>>>' .  $n->lastChild->nodeValue);

            $data = [
                'id' => $n->firstChild->nodeValue,
                'level' => $layer,
                'name' => $n->lastChild->nodeValue,
                'children' => $children
            ];

            if ($layer == $this->level) {
                unset($data['children']);
            }
            $list[] = $data;
        }

        return $list;
    }

    function buildUrl($current, $uri)
    {
        $pos = strrpos($current, '/');
        $current = substr($current, 0, $pos + 1);
        return $current . $uri;
    }
}
