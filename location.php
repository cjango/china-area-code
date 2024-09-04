<?php

class Location
{
    protected string $baseUrl = 'https://www.stats.gov.cn/sj/tjbz/tjyqhdmhcxhfdm/2023/';

    protected int $level = 5;

    protected int $errors = 0;

    protected string $province = '';

    protected string $city = '';

    protected string $county = '';

    protected string $town = '';

    protected string $village = '';

    protected array $provinceArray = [
        11 => '北京市',
        12 => '天津市',
        13 => '河北省',
        14 => '山西省',
        15 => '内蒙古自治区',
        21 => '辽宁省',
        22 => '吉林省',
        23 => '黑龙江省',
        31 => '上海市',
        32 => '江苏省',
        33 => '浙江省',
        34 => '安徽省',
        35 => '福建省',
        36 => '江西省',
        37 => '山东省',
        41 => '河南省',
        42 => '湖北省',
        43 => '湖南省',
        44 => '广东省',
        45 => '广西壮族自治区',
        46 => '海南省',
        50 => '重庆市',
        51 => '四川省',
        52 => '贵州省',
        53 => '云南省',
        54 => '西藏自治区',
        61 => '陕西省',
        62 => '甘肃省',
        63 => '青海省',
        64 => '宁夏回族自治区',
        65 => '新疆维吾尔自治区',
    ];

    function handle(int $level = 3)
    {
        $this->level = $level;

        $current = $this->baseUrl . 'index.html';
        $html = file_get_contents($current);

        $doc = new DOMDocument();
        $doc->loadHTML($html);
        $xpath = new DOMXPath($doc);
        $elements = $xpath->query("//tr[contains(@class, 'tr')]//a");

        $list = [];

        foreach ($elements as $element) {
            $nextUrl = $element->attributes['href']->value;
            $provinceId = str_replace('.html', '', $nextUrl);
            $list[] = $this->getProvince($provinceId);
        }

        file_put_contents('location_' . $this->level . '.json', json_encode($list, JSON_UNESCAPED_UNICODE));
        var_dump('TOTAL ERROR:' . $this->errors);
    }

    function getProvince($provinceId): array
    {
        $this->province = $this->provinceArray[$provinceId];
        printf("采集省份：%s\r\n", $this->province);
        $children = $this->getCity($this->baseUrl . $provinceId . '.html', 2);

        $data = [
            'id' => $provinceId,
            'name' => $this->province,
            'level' => 1,
            'children_count' => count($children),
            'children' => $children,
        ];
        file_put_contents($provinceId . '_' . $this->level . '.json', json_encode($data, JSON_UNESCAPED_UNICODE));

        var_dump('ERROR:' . $this->errors);

        return $data;
    }

    /**
     * 采集列表内容
     *
     * @param  string  $url
     * @return array
     */
    protected function getCity(string $url): array
    {
        $elements = $this->initData($url);

        $list = [];

        foreach ($elements as $element) {
            $children = [];

            $this->city = $element->lastChild->nodeValue;
            printf("地市：%s %s\r\n", $this->province, $this->city);

            if ($element->firstChild->firstChild->hasChildNodes() && $element->firstChild->firstChild->hasAttributes()) {
                $children = $this->getCounty($this->buildUrl(
                    $url,
                    $element->firstChild->firstChild->attributes['href']->value
                ));
            }

            $data = [
                'id' => $element->firstChild->nodeValue,
                'name' => $element->lastChild->nodeValue,
                'level' => 2,
                'children_count' => count($children),
                'children' => $children,
            ];
            $list[] = $data;
        }

        return $list;
    }

    function getCounty(string $url): array
    {
        $elements = $this->initData($url);

        $list = [];
        foreach ($elements as $element) {
            $children = [];

            $this->county = $element->lastChild->nodeValue;
            printf("区县：%s %s %s\r\n", $this->province, $this->city, $this->county);

            if ($this->level == 5 && $element->firstChild->firstChild->hasChildNodes() && $element->firstChild->firstChild->hasAttributes()) {
                $children = $this->getTown($this->buildUrl(
                    $url,
                    $element->firstChild->firstChild->attributes['href']->value
                ));
            }

            if (!$element->lastChild->lastChild->hasAttributes() && $element->lastChild->nodeValue == '市辖区') {
                continue;
            }

            $data = [
                'id' => $element->firstChild->nodeValue,
                'name' => $element->lastChild->nodeValue,
                'level' => 3,
            ];

            if ($this->level == 5) {
                $data = array_merge($data, [
                    'children_count' => count($children),
                    'children' => $children,
                ]);
            }
            $list[] = $data;
        }

        return $list;
    }

    function getTown(string $url): array
    {
        $elements = $this->initData($url);
        $list = [];
        foreach ($elements as $element) {
            $this->town = $element->lastChild->nodeValue;
            printf("乡镇街道：%s %s %s %s\r\n", $this->province, $this->city, $this->county, $this->town);

            $children = [];
            if ($element->firstChild->firstChild->hasChildNodes() && $element->firstChild->firstChild->hasAttributes()) {
                $children = $this->getVillage($this->buildUrl(
                    $url,
                    $element->firstChild->firstChild->attributes['href']->value
                ));
            }

            $data = [
                'id' => $element->firstChild->nodeValue,
                'name' => $element->lastChild->nodeValue,
                'level' => 4,
                'children_count' => count($children),
                'children' => $children,
            ];
            $list[] = $data;
        }

        return $list;
    }

    function getVillage($url): ?array
    {
        $elements = $this->initData($url);
        $list = [];
        foreach ($elements as $element) {
            $this->village = $element->lastChild->nodeValue;
            printf(
                "村屯居委会：%s %s %s %s %s\r\n",
                $this->province,
                $this->city,
                $this->county,
                $this->town,
                $this->village
            );
            $data = [
                'id' => $element->firstChild->nodeValue,
                'name' => $element->lastChild->nodeValue,
                'level' => 5,
            ];
            $list[] = $data;
        }

        usleep(100000);
        return $list;
    }

    protected function initData(string $url): ?DOMNodeList
    {
        try {
            $html = file_get_contents($url);
            $doc = new DOMDocument();
            $doc->loadHTML($html);
            $xpath = new DOMXPath($doc);

            return $xpath->query("//tr[contains(@class, 'tr')]");
        } catch (Exception $e) {
            $this->errors++;

            return null;
        }
    }

    function buildUrl($current, $uri)
    {
        $pos = strrpos($current, '/');
        $current = substr($current, 0, $pos + 1);

        return $current . $uri;
    }
}
