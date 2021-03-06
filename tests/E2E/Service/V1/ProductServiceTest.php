<?php declare(strict_types=1);

namespace Gam6itko\OzonSeller\Tests\E2E\Service\V1;

use Gam6itko\OzonSeller\Exception\AccessDeniedException;
use Gam6itko\OzonSeller\Exception\BadRequestException;
use Gam6itko\OzonSeller\Exception\ProductValidatorException;
use Gam6itko\OzonSeller\Service\V1\ProductService;
use GuzzleHttp\Client as GuzzleClient;
use Http\Adapter\Guzzle6\Client as GuzzleAdapter;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Gam6itko\OzonSeller\Service\V1\ProductService
 * @group  v1
 * @group  e2e
 *
 * @author Alexander Strizhak <gam6itko@gmail.com>
 */
class ProductServiceTest extends TestCase
{
    protected function setUp(): void
    {
        sleep(1); //fix 429 Too Many Requests
    }

    public function getSvc(): ProductService
    {
        $config = [$_SERVER['CLIENT_ID'], $_SERVER['API_KEY'], $_SERVER['API_URL']];
        $adapter = new GuzzleAdapter(new GuzzleClient());

        return new ProductService($config, $adapter);
    }

    /**
     * @covers ::classify
     */
    public function testClassify(): void
    {
        $this->expectException(BadRequestException::class);
        $json = <<<JSON
{
    "products": [
        {
            "offer_id": "147190464",
            "shop_category_full_path": "Электроника/Телефоны и аксессуары/Смартфоны",
            "shop_category": "Смартфоны",
            "shop_category_id": 15502,
            "vendor": "Apple, Inc",
            "model": "iPhone XS 256GB Space Grey",
            "name": "Смартфон Apple iPhone XS 256GB Space Grey",
            "price": "100990",
            "offer_url": "https://www.ozon.ru/context/detail/id/147190464/",
            "img_url": "https://ozon-st.cdn.ngenix.net/multimedia/1024351473.jpg",
            "vendor_code": "apple_inc",
            "barcode": "190198794017"
        }
    ]
}
JSON;

        $this->getSvc()->classify(json_decode($json, true));
    }

    /**
     * @covers ::import
     */
    public function testImport()
    {
        $json = <<<JSON
{
    "items": [
        {
            "category_id": "17036198",
            "description": "Description for item",
            "offer_id": "16209",
            "name": "Наушники Apple AirPods 2 (без беспроводной зарядки чехла)",
            "price": 10110,
            "vat": 0,
            "quantity": "3",
            "vendor_code": "AM016209",
            "height": "55",
            "depth": "22",
            "width": "45",
            "dimension_unit": "mm",
            "weight": "8",
            "weight_unit": "g",
            "images": [
                {
                    "file_name": "https://store.storeimages.cdn-apple.com/4982/as-images.apple.com/is/MRXJ2?wid=1144&hei=1144&fmt=jpeg&qlt=95&op_usm=0.5,0.5&.v=1551489675083",
                    "default": true
                }
            ],
            "attributes": [
                {
                    "id": 8229,
                    "value": "193"
                }
            ]
        }
    ]
}
JSON;

        $result = $this->getSvc()->import(json_decode($json, true), true);
        self::assertIsArray($result);
        self::assertArrayHasKey('task_id', $result);
        self::assertIsInt($result['task_id']);

        return $result['task_id'];
    }

    /**
     * @covers ::import
     * @dataProvider dataImportInvalid
     */
    public function testImportInvalid(string $json): void
    {
        $this->expectException(ProductValidatorException::class);
        $input = json_decode($json, true);
        $result = $this->getSvc()->import($input, true);
        self::assertNotEmpty($result);
        self::assertArrayHasKey('product_id', $result);
        self::assertArrayHasKey('state', $result);
    }

    public function dataImportInvalid()
    {
        $json = <<<JSON
{
    "items": [
        {
            "category_id": "17036198",
            "offer_id": "16209",
            "name": "Наушники Apple AirPods 2 (без беспроводной зарядки чехла)",
            "price": 10110,
            "quantity": "3",
            "vendor_code": "AM016209",
            "description": "",
            "vat": 0,
            "height": 1,
            "images": [
                {
                    "file_name": "http:\/\/allo.market\/upload\/iblock\/4f4\/4f4cc9604b964fdca797519e7e2c0fb1.jpeg",
                    "default": true
                }
            ],
            "attributes": [
                {
                    "id": 8229,
                    "value": "193"
                }
            ]
        }
    ]
}
JSON;

        yield [$json];

        $json = <<<JSON
{
    "category_id": 17029321,
    "name": "uno dos tres cuatrocopter",
    "price": 19000,
    "vat": "0.18",
    "vendor": "Gambitko spy technology",
    "weight": "1500",
    "weight_unit": "g",
    "images": [
        {
            "file_name": "https://images.pexels.com/photos/8769/pen-writing-notes-studying.jpg?auto=compress&cs=tinysrgb&dpr=2&h=750&w=1260",
            "default": true
        }
    ]
}
JSON;

        yield [$json];
    }

    /**
     * @covers ::import
     */
    public function testImportException(): void
    {
        try {
            $this->getSvc()->import([], false);
        } catch (BadRequestException $exc) {
            self::assertEmpty($exc->getData()); //todo-ozon-support нет никаких данных
            self::assertEquals('Invalid JSON payload', $exc->getMessage());
        }
    }

    /**
     * @covers ::import
     * @dataProvider dataImportFail
     */
    public function testImportFail(string $json): void
    {
        try {
            $input = json_decode($json, true);
            $this->getSvc()->import($input, false);
        } catch (BadRequestException $exc) {
            self::assertEmpty($exc->getData()); //todo-ozon-support нет никаких данных
            self::assertEquals('Invalid JSON payload', $exc->getMessage());
        }
    }

    public function dataImportFail()
    {
        $json = <<<JSON
{
    "items": [
        {
            "category_id": "17036198",
            "offer_id": "16209",
            "name": "Наушники Apple AirPods 2 (без беспроводной зарядки чехла)",
            "price": 10110,
            "quantity": "3",
            "vendor_code": "AM016209",
            "description": "",
            "vat": 0,
            "height": 1,
            "images": [
                {
                    "file_name": "http:\/\/allo.market\/upload\/iblock\/4f4\/4f4cc9604b964fdca797519e7e2c0fb1.jpeg",
                    "default": true
                }
            ],
            "attributes": [
                {
                    "id": 8229,
                    "value": "193"
                }
            ]
        }
    ]
}
JSON;

        yield [$json];

        $json = <<<JSON
{
    "category_id": 17029321,
    "name": "uno dos tres cuatrocopter",
    "price": 19000,
    "vat": "0.18",
    "vendor": "Gambitko spy technology",
    "weight": "1500",
    "weight_unit": "g",
    "images": [
        {
            "file_name": "https://images.pexels.com/photos/8769/pen-writing-notes-studying.jpg?auto=compress&cs=tinysrgb&dpr=2&h=750&w=1260",
            "default": true
        }
    ]
}
JSON;

        yield [$json];
    }

    /**
     * @covers ::createBySku
     */
    public function testImportBySku(): void
    {
        $json = <<<JSON
{
    "items": [
        {
            "sku": 1445625485,
            "name": "Nice boots 1",
            "offer_id": "RED-SHOES-MODEL-1-38-39",
            "price": "7999",
            "old_price": "8999",
            "premium_price": "7555",
            "vat": "0"
        },
        {
            "sku": 1445625485,
            "name": "Nice boots 2",
            "offer_id": "RED-SHOES-MODEL-1-38-39",
            "price": "7999",
            "old_price": "8999",
            "premium_price": "7555",
            "vat": "0"
        }
    ]
}
JSON;

        $result = $this->getSvc()->importBySku(json_decode($json, true));
        self::assertIsArray($result);
        self::assertArrayHasKey('task_id', $result);
    }

    /**
     * @covers ::importInfo
     * @depends testImport
     */
    public function testCreationStatus(int $taskId): void
    {
        $status = $this->getSvc()->importInfo($taskId);
        self::assertNotEmpty($status);
        self::assertArrayHasKey('total', $status);
        self::assertArrayHasKey('items', $status);
        self::assertCount(1, $status['items']);
        self::assertArrayHasKey('offer_id', $status['items'][0]);
        self::assertArrayHasKey('product_id', $status['items'][0]);
        self::assertArrayHasKey('status', $status['items'][0]);
    }

    /**
     * @covers ::infoStocks
     */
    public function testInfoStock(): void
    {
        $status = $this->getSvc()->infoStocks();
        self::assertNotEmpty($status);
        self::assertArrayHasKey('total', $status);
        self::assertArrayHasKey('items', $status);
    }

    /**
     * @covers ::infoPrices
     */
    public function testInfoPrices(): void
    {
        $status = $this->getSvc()->infoPrices();
        self::assertNotEmpty($status);
        self::assertArrayHasKey('total', $status);
        self::assertArrayHasKey('items', $status);
    }

    /**
     * @covers ::list
     */
    public function testList(): int
    {
        $result = $this->getSvc()->list();
        self::assertNotEmpty($result);
        self::assertCount(2, $result);
        self::assertArrayHasKey('items', $result);
        self::assertArrayHasKey('total', $result);
        $items = $result['items'];
        self::assertCount(10, $items);
        self::assertArrayHasKey('product_id', $items[0]);
        self::assertArrayHasKey('offer_id', $items[0]);

        return $items[0]['product_id'];
    }

    /**
     * @covers ::update
     */
    public function testUpdateException(): void
    {
        try {
            $this->getSvc()->update([], false);
        } catch (BadRequestException $exc) {
            self::assertEmpty($exc->getData()); //todo-ozon-support нет никаких данных
            self::assertEquals('Invalid JSON payload', $exc->getMessage());
        }
    }

    /**
     * @covers ::info
     * @depends testImport
     */
    public function testInfo(int $taskId): void
    {
        $productInfo = $this->getSvc()->info($taskId);
        self::assertNotEmpty($productInfo);
        self::assertArrayHasKey('name', $productInfo);
    }

    /**
     * @covers ::update
     */
    public function testUpdate(): void
    {
        $this->expectException(AccessDeniedException::class);
        $arr = [
            'product_id' => 507735,
            'images'     => [
                [
                    'file_name' => 'https://images.freeimages.com/images/large-previews/4ad/snare-drum-second-take-1-1564542.jpg',
                    'default'   => true,
                ],
            ],
        ];
        $result = $this->getSvc()->update($arr, false);
        self::assertNotEmpty($result);
        self::assertArrayHasKey('updated', $result);
        self::assertTrue($result['updated']);
    }

    /**
     * @covers ::deactivate
     * @depends testList
     */
    public function testDeactivate(int $productId): void
    {
        $this->expectException(BadRequestException::class);
        $result = $this->getSvc()->deactivate($productId);
        self::assertTrue($result);
    }

    /**
     * @covers ::activate
     * @depends testList
     */
    public function testActivate(int $productId): void
    {
        $this->expectException(BadRequestException::class);
        $result = $this->getSvc()->activate($productId);
        self::assertTrue($result);
    }

    /**
     * @covers ::delete
     */
    public function testDelete(): void
    {
        $this->expectException(BadRequestException::class);
        $status = $this->getSvc()->delete(123);
        self::assertNotEmpty($status);
    }

    public function testUpdatePricesNotFound(): void
    {
        $expectedJson = <<<JSON
[
    {
        "product_id": 120000,
        "offer_id": "offer_1",
        "updated": false,
        "errors": [
            {
                "code": "NOT_FOUND_ERROR",
                "message": "Product not found"
            }
        ]
    },
    {
        "product_id": 124100,
        "offer_id": "offer_2",
        "updated": false,
        "errors": [
            {
                "code": "NOT_FOUND_ERROR",
                "message": "Product not found"
            }
        ]
    }
]
JSON;
        $arr = [
            [
                'product_id'    => 120000,
                'offer_id'      => 'offer_1',
                'price'         => '79990',
                'old_price'     => '89990',
                'premium_price' => '69990',
                'vat'           => '0.1',
            ],
            [
                'product_id'    => 124100,
                'offer_id'      => 'offer_2',
                'price'         => '79990',
                'old_price'     => '89990',
                'premium_price' => '69990',
                'vat'           => '0.1',
            ],
        ];
        $result = $this->getSvc()->importPrices($arr);
        self::assertNotEmpty($result);
        self::assertJsonStringEqualsJsonString($expectedJson, \GuzzleHttp\json_encode($result));
    }

    /**
     * @covers ::importPrices
     */
    public function testUpdatePrices(): void
    {
        $expectedJson = <<<JSON
[
    {
        "product_id": 508756,
         "offer_id": "PRD-1",
        "updated": false,
        "errors": [
            {
                "code": "NOT_FOUND_ERROR",
                "message": "Product not found"
            }
        ]
    }
]
JSON;

        $arr = [
            [
                'product_id'    => 508756,
                'offer_id'      => 'PRD-1',
                'price'         => '45000',
                'old_price'     => '40000',
                'premium_price' => '35000',
                'vat'           => '0.2',
            ],
        ];
        $result = $this->getSvc()->importPrices($arr);
        self::assertNotEmpty($result);
        self::assertJsonStringEqualsJsonString($expectedJson, \GuzzleHttp\json_encode($result));
    }

    /**
     * @covers ::importStocks
     */
    public function testUpdateStocks(): void
    {
        $expectedJson = <<<JSON
[
    {
        "errors": [
            {
                "code": "NOT_FOUND",
                "message": "Product not found"
            }
        ],
        "offer_id": "",
        "product_id": 507735,
        "updated": false
    }
]
JSON;

        $arr = [
            [
                'product_id' => 507735,
                'stock'      => 20,
            ],
        ];
        $result = $this->getSvc()->importStocks($arr);
        self::assertNotEmpty($result);
        self::assertJsonStringEqualsJsonString($expectedJson, \GuzzleHttp\json_encode($result));
    }

    public function testPrice(): void
    {
        $result = $this->getSvc()->price([], ['page' => 1, 'page_size' => 10]);
        self::assertNotEmpty($result);
    }
}
