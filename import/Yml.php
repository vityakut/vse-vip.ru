<?php


class Yml
{
    const YML_FILE = __DIR__ ."/price/4.yml";
    const ADDPRICE = 0;
    public $xml;
    private $xml_cat;
    public $categories = [];
    public $categories_lvl = [];
    private $cat_parent_list = [];
    public $products = [];
    public $attributes = [];
    public $options = [];
    private $bad_vendors = [];

    public function __construct()
    {
        $this->download();
        $this->getPrice();

    }



    /**
     * @return void
     */
    public function getPrice()
    {
        $this->loadXml();
        $tmp = $this->xml->attributes();
        $publishTime = $tmp['date']->__toString();
        printf("price published %s \n", $publishTime);
        unset($tmp);
        $this->xml_cat = $this->xml->shop->categories->category;
    }

    public function getProdList(): array
    {

        $prodList = array();
        $paramList = array();
        foreach ($this->xml->shop->offers->offer as $p) {
            if (in_array(strtolower($p->vendor->__toString()), $this->bad_vendors)){
                continue;
            }
            $pics = array();
            $catId = $p->categoryId->__toString();
//            $rootCat = (sizeof($this->categories[$catId]['parentlist'])) ? $this->categories[$catId]['parentlist'][sizeof($this->categories[$catId]['parentlist']) - 1] : $catId;
            foreach ($p->picture as $pic) {
                $pics[] = $pic->__toString();
            }
            $tmpA = array(
                'model' => $p->model->__toString(),
                'description' => $p->description->__toString(),
                'name' => $p->name->__toString(),
                'price' => intval($p->price->__toString()) + self::ADDPRICE,
                'oldPrice' => 0,
                'code' => $p->vendorCode->__toString(),
                'curId' => $p->currencyId->__toString(),
                'catId' => $catId,
                'vendor' => $p->vendor->__toString(),
                'pictures' => $pics,
            );
            $sale = false;
            if (!(isset($tmpA['attr']['Акционный товар']))){
                $sale = true;
                unset($tmpA['attr']['Акционный товар']);
            }
//            if ($sale && SALE_GOODS){
//                $tmpA['oldPrice'] = round(($tmpA['price'] / (100 - SALE_GOODS)) * 100) ;
//            }
            if ($tmpA['name'] == ""){
                $tmpA['name'] = $tmpA['vendor']. " " .$tmpA['model'];
            }
            foreach ($p->param as $tp){
                $tmp = $tp->attributes();
                $tmp_name = $tmp['name']->__toString();
                if (mb_stripos($tmp_name, 'Размер' )!== false){
                    if (!isset($this->options[$tmp_name])){
                        $this->options[$tmp_name] = [
                            'oc_id' => null,
                            'name' => $tmp_name,
                            'values' => []
                        ];
                    };
                    if (!isset($this->options[$tmp_name]['values'][$tp->__toString()])){
                        $this->options[$tmp_name]['values'][$tp->__toString()] = null;
                    }
                    $tmpA['options'][$tmp_name][] = $tp->__toString();
                }
                if (!(in_array($tmp_name, $this->attributes))){
                    $this->attributes[$tmp_name] = [
                        'oc_id' => null,
                        'name' => $tmp_name
                    ];
                };

                if (!( (mb_strtolower($tmp_name) == "пол") && ($tmpA['catId'] == "3"))){
                    $tmpA['attr'][$tmp_name][] = $tp->__toString();
                }
            }

            foreach ($p->attributes() as $a => $b){
                if ($a == 'group_id')
                    $tmpA[$a] = intval($b);
                else
                    $tmpA[$a] = $b->__toString();

            }

            if (!(isset($tmpA['attr']['Бренд']))){
                $tmpA['attr']['Бренд'] = array($tmpA['vendor']);
            }


            if (!isset($tmpA['group_id'])){
                $tmpA['group_id'] = intval($tmpA['id']);
            }
            $prodList[] = $tmpA;
        }
        foreach ($prodList as $prod){
            if ($prod['available'] === 'true') {
                foreach ($prod as $pkey => $pval) {

                    if (!(isset($this->products[$prod['group_id']][$pkey]))) {
                        $this->products[$prod['group_id']][$pkey] = $prod[$pkey];
                    } else {
                        if (is_array($prod[$pkey])){
                            $this->products[$prod['group_id']][$pkey] = self::array_unique_recursive(array_merge_recursive($this->products[$prod['group_id']][$pkey], $prod[$pkey]));
                        }
                    }
                }
            }
        }
        unset($prodList);
        unset($paramList);
        return $this->products;
    }


    public function getCatList(): void
    {
        foreach ($this->xml_cat as $c) {
            $tmpA = array('name' => $c->__toString());
            foreach ($c->attributes() as $a => $b){
                $tmp = self::decodeXml($b);
                $tmpA[$a] = $tmp[0];
            }
            $this->categories[intval($tmpA['id'])] = $tmpA;
        }
        foreach ($this->categories as $cat){
            $this->cat_parent_list = [];
            $this->getCatParent($cat['id']);
            array_shift($this->cat_parent_list);
            $cat['parentlist'] = $this->cat_parent_list;
            $cat['level'] = sizeof($this->cat_parent_list);
            $this->categories[intval($cat['id'])] = $cat;
            $this->categories_lvl[intval($cat['level'])][intval($cat['id'])] = $cat;
        }
        ksort($this->categories_lvl);
        unset($this->cat_parent_list);
    }

    private function getCatParent($catid): void
    {
        if (isset($this->categories[$catid])){
            $this->cat_parent_list[] = $catid;
            if (isset($this->categories[$catid]['parentId'])){
                $this->getCatParent($this->categories[$catid]['parentId']);
            }
        } else {
            return;
        }
    }

    public function loadXml(){
        try {
            $this->xml = simplexml_load_file(self::YML_FILE);
            return $this->xml;
        }
        catch (Exception $e){
            print ("Error: " . $e);
            exit(0);
        }
    }

    public function download(): bool
    {
        if (file_exists(self::YML_FILE)){
            if (!self::needDownloads()){
                return false;
            }
            unlink(self::YML_FILE);
        }
        echo "start download price to " .self::YML_FILE. "\n";
        return self::curl_download(YML_URL, self::YML_FILE);
    }

    function needDownloads(): bool
    {
        if (!file_exists(self::YML_FILE)){
            return false;
        } else {
            $xml = self::loadXml();
            if ($xml){
                $tmp = $xml->attributes();
                $publishTime = $tmp['date']->__toString();
                $now = date_create();
                unset($tmp);
                unset($xml);
                $last = date_create_from_format("Y-m-d H:i", $publishTime);
                $diff = date_diff($now, $last);
                $diffHour = ($diff->y * 365.25 + $diff->m * 30 + $diff->d) * 24 + $diff->h;
                return boolval($diffHour);
            } else {
                return true;
            }
        }
    }

    static function curl_download($url, $file): bool
    {
        $uploads_dir = dirname($file);
        if (!is_dir($uploads_dir)){
            mkdir($uploads_dir, 0755, true);
        }
        printf("start download ". $url."\n");
        $dest_file = @fopen($file, "a");
        $resource = curl_init();
        curl_setopt($resource, CURLOPT_URL, $url);
        curl_setopt($resource, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($resource, CURLOPT_MAXREDIRS, 10);
        curl_setopt($resource, CURLOPT_TIMEOUT, 120);
        curl_setopt($resource, CURLOPT_FILE, $dest_file);
        curl_setopt($resource, CURLOPT_HEADER, 0);
        curl_exec($resource);
        curl_close($resource);
        fclose($dest_file);
        return true;
    }

    static function curl_get_file_size($url){
        $result = -1;
        $curl = curl_init( $url );
        curl_setopt( $curl, CURLOPT_NOBODY, true );
        curl_setopt( $curl, CURLOPT_HEADER, true );
        curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $curl, CURLOPT_FOLLOWLOCATION, true );
        curl_setopt( $curl, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13" );
        $data = curl_exec( $curl );
        curl_close( $curl );
        if( $data ) {
            $content_length = false;
            $status = false;
            if( preg_match( "/^HTTP\/1\.[01] (\d\d\d)/", $data, $matches ) ) {
                $status = (int)$matches[1];
            }
            if( preg_match( "/Content-Length: (\d+)/", $data, $matches ) ) {
                $content_length = (int)$matches[1];
            }
            if( $status == 200 || ($status > 300 && $status <= 308) ) {
                $result = $content_length;
            }
        }
        return $result;
    }

    static function decodeXml($xmlObject){
        return json_decode(json_encode($xmlObject), TRUE);
    }
    static function array_unique_recursive($array){
        $result = array_map("unserialize", array_unique(array_map("serialize", $array)));
        foreach ($result as $key => $value){
            if (is_array($value)){
                $result[$key] = self::array_unique_recursive($value);
            }
        }
        return $result;
    }
}