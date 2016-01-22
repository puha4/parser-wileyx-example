<?php

require_once './excel/reader.php';

class CMSClassGlassesParserWileyx extends CMSClassGlassesParser
{
    protected $http;

    private $updData;
    private $countVariation = 0;
    private $countVariationInStock = 0;
    private $countVariationWithUpc = 0;

    const UPC_FILE = "files/wileyx_price_list.xls";
    const URL_BASE = "https://www.wileyx.com";
    const URL_LOGIN = "https://www.wileyx.com/Login";
    const URL_BRAND = 'https://www.wileyx.com/products/series';

    const UPC_CELL = 7;
    const MATCHED_FLAG = 1;
    const COLOR_CODE_CELL = 1;

    /**
     * CMSClassGlassesParserWileyx constructor.
     */
    public function __construct()
    {
        $this->updData = $this->getUpcCodesFormXls();
    }

    /**
     * @return int
     */
    public function getProviderId()
    {
        return CMSLogicProvider::WILEYX;
    }

    public function doLogin()
    {
        $this->http = $this->getHttp();

        $content = $this->doGetAndGetContents(self::URL_LOGIN);

        $post = $this->getPostData($content);

        $this->http->doPost(self::URL_LOGIN, $post);
    }

    /**
     * @param string $content
     * @return array
     */
    private function getPostData($content)
    {
        $dom = str_get_html($content);

        $tssm = $this->getTssnParamFromContent($content);
        $eventTarget = $this->getEventTargetFromDom($dom);
        $eventArgument = $this->getEventArgumentFromDom($dom);
        $viewState = $this->getViewStateFromDom($dom);
        $viewStateGenerator = $this->getViewStateGeneratorFromDom($dom);
        $scrollPosition = $this->getScrollPositionFromDom($dom);
        $eventValidation = $this->getEventValidationFromDom($dom);
        $word = $this->getWordFromDom($dom);
        $loginButton = $this->getLoginBtnValFromDom($dom);

        return [
            'ctl06_TSSM' => $tssm,
            '__EVENTTARGET' => $eventTarget,
            '__EVENTARGUMENT' => $eventArgument,
            '__VIEWSTATE' => $viewState,
            '__VIEWSTATEGENERATOR' => $viewStateGenerator,
            '__SCROLLPOSITIONX' => $scrollPosition['x'],
            '__SCROLLPOSITIONY' => $scrollPosition['y'],
            '__EVENTVALIDATION' => $eventValidation,
            'word' => $word,
            'ctl00$ContentPlaceHolder1$ctl10$Login1$UserName' => $this->getUsername(),
            'ctl00$ContentPlaceHolder1$ctl10$Login1$Password' => $this->getPassword(),
            'ctl00$ContentPlaceHolder1$ctl10$Login1$LoginButton' => $loginButton,
        ];
    }

    /**
     * @param string $content
     * @return string
     */
    private function getTssnParamFromContent($content)
    {
        preg_match("/hf\.value\s*\+=\s*\'(.*)\';/", $content, $matches);

        return $matches[self::MATCHED_FLAG];
    }

    /**
     * @param simplehtmldom $dom
     * @return string
     */
    private function getEventTargetFromDom($dom)
    {
        $eventTarget = current($dom->find('input[name=__EVENTTARGET]'));

        return trim($eventTarget->attr['value']);
    }

    /**
     * @param simplehtmldom $dom
     * @return string
     */
    private function getEventArgumentFromDom($dom)
    {
        $eventArgument = current($dom->find('input[name=__EVENTARGUMENT]'));

        return trim($eventArgument->attr['value']);
    }

    /**
     * @param simplehtmldom $dom
     * @return string
     */
    private function getViewStateFromDom($dom)
    {
        $viewState = current($dom->find('input[name=__VIEWSTATE]'));

        return trim($viewState->attr['value']);
    }

    /**
     * @param simplehtmldom $dom
     * @return string
     */
    private function getViewStateGeneratorFromDom($dom)
    {
        $viewStateGenerator = current($dom->find('input[name=__VIEWSTATEGENERATOR]'));

        return trim($viewStateGenerator->attr['value']);
    }

    /**
     * @param simplehtmldom $dom
     * @return array
     */
    private function getScrollPositionFromDom($dom)
    {
        $scrollPositionX = current($dom->find('input[name=__SCROLLPOSITIONX]'));
        $scrollPositionX = trim($scrollPositionX->attr['value']);

        $scrollPositionY = current($dom->find('input[name=__SCROLLPOSITIONY]'));
        $scrollPositionY = trim($scrollPositionY->attr['value']);

        return [
            'x' => $scrollPositionX,
            'y' => $scrollPositionY,
        ];
    }

    /**
     * @param simplehtmldom $dom
     * @return string
     */
    private function getEventValidationFromDom($dom)
    {
        $eventValidation = current($dom->find('input[name=__EVENTVALIDATION]'));

        return trim($eventValidation->attr['value']);
    }

    /**
     * @param simplehtmldom $dom
     * @return string
     */
    private function getWordFromDom($dom)
    {
        $word = current($dom->find('input[name=word]'));

        return trim($word->attr['value']);
    }

    /**
     * @param simplehtmldom $dom
     * @return string
     */
    private function getLoginBtnValFromDom($dom)
    {
        $loginButton = current($dom->find('input[name=ctl00$ContentPlaceHolder1$ctl10$Login1$LoginButton]'));

        return trim($loginButton->attr['value']);
    }

    /**
     * @param string $url
     * @return string
     */
    private function doGetAndGetContents($url)
    {
        if (!$this->http->doGet($url)) {
            echo "Get url fail:" . $url . "\n";
        }

        $content = $this->http->getContents(false);

        return $content;
    }

    /**
     * @param string $contents
     * @return bool
     */
    public function isLoggedIn($contents)
    {
        return strpos($contents, '>Logout</a>') !== false;
    }

    /**
     * @throws CMSException
     */
    public function doSyncBrands()
    {
        $content = $this->doGetAndGetContents(self::URL_BRAND);

        $brands = $this->getBrandsFromContent($content);

        if (!$brands) {
            throw new CMSException("Не найдены бренды");
        }

        $issetBrands = CMSLogicBrand::getInstance()->getAll($this->getProvider());

        $brandsWithCodeKey = $this->getNewBrandsWithCodeKey($issetBrands);

        foreach ($brands as $brand) {
            if (!isset($brandsWithCodeKey[$brand['code']])) {
                echo "Create " . $brand['name'] . ", code [" . $brand['code'] . "] new.\n";
                CMSLogicBrand::getInstance()->create($this->getProvider(), $brand['name'], $brand['code'], '');
            } else {
                echo "Brand " . $brand['name'] . ", code [" . $brand['code'] . "] already isset.\n";
            }
        }
    }

    /**
     * Преобразовывает массив существующих брендов таким образом
     * что код бренда становится ключом массива бренда
     *
     * @param array $brands
     * @return array
     */
    private function getNewBrandsWithCodeKey($brands)
    {
        $brandsWithCodeKey = [];

        foreach ($brands as $brand) {
            if ($brand instanceof CMSTableBrand) {
                $brandsWithCodeKey[$brand->getCode()] = $brand;
            }
        }

        return $brandsWithCodeKey;
    }

    /**
     * @param string $content
     * @return array
     */
    private function getBrandsFromContent($content)
    {
        $brands = [];
        $dom = str_get_html($content);
        $brandsDom = $dom->find('ul.sfNavHorizontal a');

        foreach ($brandsDom as $brandDom) {
            $brands[] = [
                'name' => $this->getBrandTitleFromDom($brandDom),
                'code' => $this->getBrandCodeFromDom($brandDom),
            ];
        }

        return $brands;
    }

    /**
     * @param simplehtmldom $brandDom
     * @return string
     */
    private function getBrandTitleFromDom($brandDom)
    {
        return str_replace("™", '', current($brandDom->find("span"))->innertext());
    }

    /**
     * @param simplehtmldom $brandDom
     * @return string
     */
    private function getBrandCodeFromDom($brandDom)
    {
        $href = trim($brandDom->attr["href"]);
        $explodedHref = explode("/", $href);

        return end($explodedHref);
    }

    public function doSyncItems()
    {
        $brands = CMSLogicBrand::getInstance()->getAll($this->getProvider());

        foreach ($brands as $brand) {
            if (!($brand instanceof CMSTableBrand)) {
                throw new Exception("Brand mast be an instance of CMSTableBrand!");
            }

            if ($brand->getValid()) {
                echo get_class($this), ': syncing items of brand: [', $brand->getId(), '] ', $brand->getTitle(), "\n";
            } else {
                echo get_class($this), ': SKIP! syncing items of Disabled brand: [', $brand->getId(), '] ', $brand->getTitle(), "\n";
                continue;
            }

            // Сбрасываем is_valid для моделей бренда - флаг наличия модели у провайдера
            $this->resetModelByBrand($brand);
            // Сбрасываем сток для бренда
            $this->resetStockByBrand($brand);

            $content = $this->doGetAndGetContents(self::URL_BRAND . "/" . $brand->getCode());

            $items = $this->getItemsFromHtml($content);

            $this->syncBrandProducts($items, $brand);
        }

        echo "\n---Count variations {$this->countVariation}\n";
        echo "---Count variations in stock {$this->countVariationInStock}\n";
        echo "---Count variations with upc {$this->countVariationWithUpc}\n";
    }

    /**
     * @param $itemsDom array simplehtmldom
     * @param $brand CMSTableBrand
     */
    private function syncBrandProducts($items, CMSTableBrand $brand)
    {
        foreach ($items as $item) {
            $this->parseItem($item['name'], $item['href'], $brand);
        }
    }

    /**
     * @param string $content
     * @return array
     */
    private function getItemsFromHtml($content)
    {
        $items = [];
        $dom = str_get_html($content);
        $selector = "#ContentPlaceHolder1_TA088B38C003_Col00 .itemListing";
        $itemsDom = $dom->find($selector);

        foreach ($itemsDom as $itemDom) {
            $items[] = [
                'name' => $this->getItemNameFromDom($itemDom),
                'href' => $this->getItemHrefFromDom($itemDom),
            ];
        }

        return $items;
    }

    /**
     * @param simplehtmldom $itemDom
     * @return string
     */
    private function getItemNameFromDom($itemDom)
    {
        return trim(current($itemDom->find(".itemNumber"))->innertext());
    }

    /**
     * @param simplehtmldom $itemDom
     * @return string
     */
    private function getItemHrefFromDom($itemDom)
    {
        return trim(current($itemDom->find(".itemPicture a"))->attr["href"]);
    }

    private function parseItem($itemName, $itemHref, CMSTableBrand $brand)
    {
        $result = [];
        echo "--Parse item - " . $itemName . "\n";

        $content = $this->doGetAndGetContents(self::URL_BASE . $itemHref);

        $vatiationsUrls = $this->getVariationsUrlsFromItemHtml($content);

        $variations = $this->getVariations($vatiationsUrls);

        $externalId = $this->getGeneretedExtId($itemName);

        foreach ($variations as $variation) {
            $this->countVariation++;

            if ($variation['upc']) {
                $this->countVariationWithUpc++;
            }

            if (!$variation['stock']) {
                echo "\n----------variation {$variation['color']} ({$variation['colorCode']}) not in stock. (not parse!)\n";
                echo "--------------------------------------------\n";
                continue;
            }

            $this->countVariationInStock++;

            echo "\n";
            echo "----------brand         - {$brand->getTitle()}\n";
            echo "----------model_name    - {$itemName}\n";
            echo "----------external_id   - {$externalId}\n";
            echo "----------color_title   - {$variation['color']}\n";
            echo "----------color_code    - {$variation['colorCode']}\n";
            echo "----------size 1        - {$variation['sizes']['one']}\n";
            echo "----------size 2        - {$variation['sizes']['two']}\n";
            echo "----------size 3        - {$variation['sizes']['three']}\n";
            echo "----------image         - {$variation['img']}\n";
            echo "----------price         - {$variation['price']}\n";
            echo "----------type          - sun\n";
            echo "----------upc           - {$variation['upc']}\n";
            echo "----------stock         - {$variation['stock']}\n\n";
            echo "--------------------------------------------\n";

            // создаем обьект модели и синхронизируем
            $item = new CMSClassGlassesParserItem();
            $item->setBrand($brand);
            $item->setTitle($itemName);
            $item->setExternalId($externalId);
            $item->setColor($variation['color']);
            $item->setColorCode($variation['colorCode']);
            $item->setSize($variation['sizes']['one']);
            $item->setSize2($variation['sizes']['two']);
            $item->setSize3($variation['sizes']['three']);
            $item->setPrice($variation['price']);
            $item->setType(CMSLogicGlassesItemType::getInstance()->getSun());
            $item->setStockCount($variation['stock']);
            $item->setImg($variation['img']);
            $item->setIsValid(1);

            if ($variation['upc']) {
                $item->setUpc($variation['upc']);
            }

            $result[] = $item;
        }

        if (empty($result))
            return;

        echo "\n=============================================================================================\n";
        $this->syncingResult($result);
        echo "\n=============================================================================================\n";
    }

    /**
     * @param array $result
     */
    private function syncingResult($result)
    {
        foreach ($result as $res) {
            $res->sync();
        }
    }

    private function getGeneretedExtId($itemName)
    {
        $externalId = strtolower($itemName) . "_wileyx";

        return str_replace([" ", "-"], "_", $externalId);
    }

    /**
     * @param array $urls
     * @return array
     */
    private function getVariations($urls)
    {
        $variations = [];

        foreach ($urls as $url) {
            $content = $this->doGetAndGetContents(self::URL_BASE . $url);
            $dom = str_get_html($content);

            $img = $this->getVariationImageFromDom($dom);
            $color = $this->getVariationColorFromDom($dom);
            $colorCode = $this->getVariationColorCodeFromDom($dom);
            $stock = $this->getStockFromDom($dom);
            $price = $this->getPriceFromDom($dom);
            $sizes = $this->getVariationSizesFromDom($content);
            $upc = $this->getUpcFromArray($colorCode);

            $variations[] = [
                'img' => $img,
                'color' => $color,
                'colorCode' => $colorCode,
                'stock' => $stock,
                'price' => $price,
                'sizes' => $sizes,
                'upc' => $upc,
            ];
        }

        return $variations;
    }

    /**
     * @param $dom
     * @return string
     */
    private function getVariationImageFromDom($dom)
    {
        $parsedImgHref = trim(current($dom->find(".DetailImage .itemPicture .large"))->attr["src"]);

        $img = self::URL_BASE . $parsedImgHref;

        return $img;
    }

    /**
     * @param $dom
     * @return string
     */
    private function getVariationColorFromDom($dom)
    {
        return trim(current($dom->find("#ContentPlaceHolder1_C015_divClickable .itemPictureDescription span"))->innertext());
    }

    /**
     * @param $dom
     * @return string
     */
    private function getVariationColorCodeFromDom($dom)
    {
        $colorCodeStr = current($dom->find(".itemNumber"))->innertext();
        $colorCode = str_replace(["Product", "Number", ":"], "", $colorCodeStr);

        return trim($colorCode);
    }

    /**
     * @param $dom
     * @return string
     */
    private function getStockFromDom($dom)
    {
        $stockStr = current($dom->find("#ContentPlaceHolder1_C016_Col00 .availableStatus"))->innertext();
        $stock = str_replace(["Availability", ":", " "], "", $stockStr);
        $stock = trim(strtolower($stock)) == "instock" ? 1 : 0;

        return $stock;
    }

    /**
     * @param $dom
     * @return string
     */
    private function getPriceFromDom($dom)
    {
        $priceStr = current($dom->find("#ContentPlaceHolder1_C016_Col00 .regularPrice"))->innertext();
        $price = str_replace(["List", "Price", ":", "US", "$"], "", $priceStr);

        return trim($price);
    }

    /**
     * @param string $content
     * @return array
     */
    private function getVariationSizesFromDom($content)
    {
        preg_match("/b>\s*eye\s+size\s*:?\s*<\/b>\s*(\d*)\s*\|\s*\w*:?\s*(\d*)\s*\|\s*\w*:?\s*(\d*)\s*<\//i", $content, $matches);

        $sizeOne = isset($matches[1]) && !empty($matches[1]) ? $matches[1] : 0;
        $sizeTwo = isset($matches[2]) && !empty($matches[2]) ? $matches[2] : 0;
        $sizeThree = isset($matches[3]) && !empty($matches[3]) ? $matches[3] : 0;

        return [
            'one' => $sizeOne,
            'two' => $sizeTwo,
            'three' => $sizeThree,
        ];
    }

    /**
     * @param string $content
     * @return array
     */
    private function getVariationsUrlsFromItemHtml($content)
    {
        $links = [];
        $dom = str_get_html($content);
        $variationsDom = $dom->find(".prodImages .itemList .itemPicture a");

        foreach ($variationsDom as $variationDom) {
            $links[] = trim($variationDom->attr['href']);
        }

        return $links;
    }

    /**
     * @param $colorCode
     * @return string
     */
    private function getUpcFromArray($colorCode)
    {
        return isset($this->updData[$colorCode]) ? $this->updData[$colorCode] : '';
    }

    /**
     * Возвращает все upc кода без повторей из файла
     * @return array
     */
    private function getUpcCodesFormXls()
    {
        $upcData = [];

        if (fopen(CMSGlobal::getBase() . self::UPC_FILE, "r") == FALSE) {
            die("Cant read upc file!");
        }

        $data = new Spreadsheet_Excel_Reader();
        $data->read(CMSGlobal::getBase() . self::UPC_FILE);

        foreach ($data->sheets[0]['cells'] as $cell) {
            if (
                isset($cell[self::COLOR_CODE_CELL])
                && !empty($cell[self::COLOR_CODE_CELL])
                && isset($cell[self::UPC_CELL])
                && !empty($cell[self::UPC_CELL])
            ) {
                $colorCodeCell = trim($cell[self::COLOR_CODE_CELL]);
                $upcData[$colorCodeCell] = trim($cell[self::UPC_CELL]);
            }
        }

        return $upcData;
    }
}