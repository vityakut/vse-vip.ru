<?php
ini_set('memory_limit', '2048M');
define('ROOT_PATH', realpath(__DIR__."/../"));
define("DOWNLOAD_IMAGE", !in_array("noimage", $argv));
define("FORCE_UPDATE", in_array("force", $argv));
define("DEFAULT_CATEGORY_ID", 0);
define("YML_URL", "http://bizoutmax.ru/price/export/4.yml");

chdir(ROOT_PATH);
require_once('framework.php');
mb_regex_encoding("UTF-8");
mb_internal_encoding("UTF-8");

require_once("Yml.php");
$category_delete = 0;
$category_add = 0;
$category_edit = 0;
$product_delete = 0;
$product_add = 0;
$product_edit = 0;
$mem_start = memory_get_usage();
$start_time = time();


print_r("\r\ninit\r\n");
if (in_array("crone", $argv)){
    $loader->model('catalog/category');
    $loader->model('catalog/product');
    $loader->model('catalog/option');
    $loader->model('catalog/attribute');

    $cat_model = $registry->get('model_catalog_category');
    $prod_model = $registry->get('model_catalog_product');
    $option_model = $registry->get('model_catalog_option');
    $attr_model = $registry->get('model_catalog_attribute');

}else{
    exit("FUCKING FUCK!\r\n");
}

if (in_array("update", $argv)) {
    $yml = new Yml();
    $yml->getCatList();
    $yml->getProdList();
    $cat_count = sizeof($yml->categories);
    $prod_count = sizeof($yml->products);
    echo "Всего категорий " . $cat_count ."\n";
    echo "Всего товаров: " . $prod_count ."\n";
    updateCategories($cat_model, $yml);
    updateAttributes($attr_model, $yml);
    updateOptions($option_model, $yml);
    updateProducts($prod_model, $cat_model, $yml);

    $donemsg = "Добавлено категорий: ". strval($category_add)."\n";
    $donemsg .= "Обновлено категорий: ". strval($category_edit)."\n";
    $donemsg .= "Удалено категорий: ". strval($category_delete)."\n";
    $donemsg .= "Добавлено товаров: ". strval($product_add)."\n";
    $donemsg .= "Обновлено товаров: ". strval($product_edit)."\n";
    $donemsg .= "Удалено товаров: ". strval($product_delete)."\n";
    $donemsg .= "Время выполненения: " . strval(time() - $start_time) . " сек\n";
    $donemsg .= sprintf("\nMemory usage: %s Mb\n", strval(round((memory_get_usage() - $mem_start) / 1048576, 2)));
    print($donemsg);
}

if (in_array("deleteall", $argv)) {
    deleteAllProducts($prod_model);
}

function updateCategories($cat_model, $yml){
    global $category_edit, $category_delete, $category_add;
    $cat_list = $cat_model->getCategories();
    foreach ($cat_list as $cat_item) {
        if (in_array($cat_item['yml_id'], array_keys($yml->categories))){
            $yml->categories[$cat_item['yml_id']]['oc_id'] = $cat_item['category_id'];
        } elseif ($cat_item['yml_id']){
            $cat_model->deleteCategory($cat_item['category_id']);
            $category_delete++;
        }
    }
    foreach ($yml->categories_lvl as $level => $yml_level) {
        foreach ($yml_level as $yml_category) {
            $description = [
                "name" => $yml_category['name'],
                "description" => $yml_category['name'],
                "meta_title" => $yml_category['name'],
                "meta_h1" => $yml_category['name'],
                "meta_description" => $yml_category['name'],
                "meta_keyword" => $yml_category['name']
            ];
            $category = array(
                "parent_id" => isset($yml->categories[$yml_category['parentId']]['oc_id']) ? $yml->categories[$yml_category['parentId']]['oc_id'] : "0",
                "column" => "1",
                "sort_order" => "0",
                "status" => "1",
                "top" => "1",
                "yml_id" => $yml_category['id'],
                "category_store" => ["0"],
//                "category_layout" => ["0" => "0"],
                "category_description"=> [
                    1 => $description,
                    2 => $description
                ],
                "crone" => true
            );
            if ($cid = $yml->categories[$yml_category['id']]['oc_id']){
                printf("Category edit - %s\r\n", $yml_category['name']);
                $cat_model->editCategory($cid, $category);
                $category_edit++;
            } else{
                printf("Category add - %s\r\n", $yml_category['name']);
                $cid = $cat_model->addCategory($category);
                $yml->categories[$yml_category['id']]['oc_id'] = $cid;
                $category_add++;
            }
        }
    }
    unset($csv_category);
}

function updateProducts($prod_model, $cat_model, $yml){
    global $product_edit, $product_delete, $product_add;
    $prod_list = $prod_model->getProducts();
    foreach ($prod_list as $prod_item) {
        if (in_array($prod_item['sku'], array_keys($yml->products))){
            $yml->products[$prod_item['sku']]['oc_id'] = $prod_item['product_id'];
        } elseif ($prod_item['sku']){
            $prod_model->deleteProduct($prod_item['product_id']);
            $product_delete++;
        }
    }
    foreach ($yml->products as $yml_product) {
        $description = [
            'name' => $yml_product['name'],
            'description' => $yml_product['description'],
            'meta_title' => $yml_product['name'],
            'meta_h1' => $yml_product['name'],
            'meta_description' => $yml_product['name'],
            'meta_keyword' => $yml_product['name'],
            'tag' => '',
        ];
        $product = array(
            "model" => $yml_product['code'],
            "sku" => $yml_product['code'],
            "upc" => "",
            "mpn" => "",
            "ean" => "",
            "jan" => "",
            "isbn" => "",
            "location" => "",
//            "quantity" => $yml_product['quantities'],
            "quantity" => 1000,
            "stock_status_id" => "7",
            "image" => "",
            "status" => "1",
            "shipping" => "1",
            "price" => $yml_product['price'],
            "tax_class_id" => "0",
//            "manufacturer_id" => $yml_product->author['oc_id'],
            "minimum" => "1",
            "subtract" => "0",
            "date_available" => "1",
            "points" => "0",
            "weight" => "0",
            "weight_class_id" => "1",
            "length" => "0",
            "width" => "0",
            "height" => "0",
            "length_class_id" => "1",
            "sort_order" => "1",
            "product_store" => ["0"],
            "product_layout" => ["0" => "0"],
            "main_category_id" => $yml->categories[$yml_product['catId']]['oc_id'],
            "product_category" => [$yml->categories[$yml_product['catId']]['oc_id']],
            "product_description"=> [
                1 => $description,
                2 => $description,
            ],
            "filter" => [],
            "product_attribute" => [],
            "product_option" => [],
            "product_discount" => [],
            "crone" => true
        );
//TODO: Количества
        foreach ($yml_product['attr'] as $attr => $value) {
            $product["product_attribute"][] = array(
                "attribute_id" => $yml->attributes[$attr]['oc_id'],
                "product_attribute_description" => array(
                    1 => array("text" => implode($value, ', ')),
                    2 => array("text" => implode($value, ', '))
                )
            );
        }
        foreach ($yml_product['options'] as $option => $op_values) {
            $product_option = array(
                "option_id" => $yml->options[$option]['oc_id'],
                'type' => 'select',
                'required' => '1',
                "product_option_value" => []
            );
            foreach ($op_values as $op_value) {
                $product_option['product_option_value'][] = [
                  'option_value_id' => $yml->options[$option]['values'][$op_value],
                  'quantity' => 1000,
                  'subtract' => 0,
                  'price_prefix' => '+',
                  'price' => 0,
                  'points_prefix' => '+',
                  'weight_prefix' => '+',
                  'weight' => 0,
                ];
            }
            $product["product_option"][] = $product_option;
        }
        if (sizeof($yml_product['pictures'])){
            $pictures = downloadPhotos($yml, $yml_product['pictures']);
            $product['image'] = $pictures[0];
            $product['product_image'] = [];
            foreach ($pictures as $i => $picture) {
                $product['product_image'][] = [
                    'image' => $picture,
                    'sort_order' => $i
                ];
            }
        }


        if ($pid = $yml->products[$yml_product['code']]['oc_id']){
            printf("Product edit - %s - %s\r\n", $pid, $yml_product['name']);
            $prod_model->editProduct($pid, $product);
            $product_edit++;
        } else{

            $pid = $prod_model->addProduct($product);
            printf("Product add - %s - %s\r\n", $pid, $yml_product['name']);
            $yml->products[$yml_product['code']]['oc_id'] = $pid;
            $product_add++;
        }


    }
}


function updateAttributes($attr_model, $yml){
    $attr_list = $attr_model->getAttributes();
    foreach ($yml->attributes as &$attribute) {
        if (($oc_i = array_search($attribute['name'], array_column($attr_list, 'name')) )!== false ){
            $attribute['oc_id'] = $attr_list[$oc_i]['attribute_id'];
        } else{
            $data = [
                'attribute_group_id' => 7,
                'sort_order' => 0,
                'attribute_description' => [
                    1 => ['name' => $attribute['name']],
                    2 => ['name' => $attribute['name']]
                ]
            ];
            if ($oc_id = $attr_model->addAttribute($data)){
                $attribute['oc_id'] = $oc_id;
            }
        }
    }
}

function updateOptions($option_model, $yml){
    $option_list = $option_model->getOptions();
    foreach ($yml->options as &$option) {
        if (($oc_i = array_search($option['name'], array_column($option_list, 'name'))) !== false){
            $option['oc_id'] = $option_list[$oc_i]['option_id'];
        } else{
            $data = [
                'type' => 'select',
                'sort_order' => 0,
                'option_description' => [
                    1 => ['name' => $option['name']],
                    2 => ['name' => $option['name']]
                ],
            ];
            if ($oc_id = $option_model->addOption($data)){
                $option['oc_id'] = $oc_id;
            }
        }
    }
    foreach ($yml->options as &$option) {
        $opt_values = $option_model->getOptionValues($option['oc_id']);
        foreach ($option['values'] as $value => &$oc_id) {
            if (($oc_i = array_search($value, array_column($opt_values, 'name'))) !== false){
                $oc_id = $opt_values[$oc_i]['option_value_id'];

            } else{
                $option_value = [
                    'image' => '',
                    'sort_order' => intval($value),
                    'option_value_description' => [
                        1 => ['name' => $value],
                        2 => ['name' => $value]
                    ]
                ];
                $oc_id = $option_model->addOptionValue($option['oc_id'], $option_value);
            }
        }
    }

}

/**
 * @param $yml Yml
 * @param $picList
 * @return mixed
 */
function downloadPhotos($yml, $picList){
    foreach ($picList as $i => &$pic) {
        $url = $pic;
        if (DOWNLOAD_IMAGE) {
            $pic = (str_replace("http://bizoutmax.ru/image/data/products/", "", $pic));
//        $pic = (str_replace("/", "-", $pic));

            $filepath = "catalog/products/" . $pic;
            $pic = $filepath;
            if (!(file_exists(DIR_IMAGE . $filepath))) {
                if ($yml::curl_get_file_size($url) > 1000) {
                    $yml::curl_download($url, DIR_IMAGE . $filepath);
                } else {
                    unset($picList[$i]);
                }
            }
        }
    }
    return $picList;
}

function deleteAllProducts($prod_model){
    global $product_delete;
    printf("Start delete all products");
    $prodlist = $prod_model->getProducts();
    foreach ($prodlist as $prod) {
        $prod_model->deleteProduct($prod['product_id']);
        $product_delete++;
    }
}