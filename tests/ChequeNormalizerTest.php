<?php

namespace Tests\Petshop\Assistance;

use Petcorp\Fiscal\ChequeNormalizer;
use PHPUnit\Framework\TestCase;

class ChequeNormalizerTest extends TestCase
{
    private function getProductionArray(): array
    {
        return [
            ["quantity" => 2, "price" => 138900],
            ["quantity" => 100, "price" => 169],
            ["quantity" => 33, "price" => 0],
            ["quantity" => 5000, "price" => 3000],
            ["quantity" => 165, "price" => 365],
        ];
    }

    public function percentSum(): array
    {
        return [
            [0.7],
        ];
    }

    public function randomCoeficent(): array
    {
        return [
            [0.3, 3, 5000, 10, 100, 1000, 1, 300],
        ];
    }

    private function createProduct(int $price, $quantity = 1, string $name = null): array
    {
        // thieft from illuminate/support Str::random
        $random = function ($length = 16): string {
            $string = '';

            while (($len = strlen($string)) < $length) {
                $size = $length - $len;

                $bytes = random_bytes($size);

                $string .= substr(str_replace(['/', '+', '='], '', base64_encode($bytes)), 0, $size);
            }

            return $string;
        };

        return [
            'name' => $name ?? $random(4),
            'quantity' => $quantity,
            'price' => $price,
        ];
    }

    public function cheques()
    {
        $cheque = [
            $this->createProduct(7, 11),
            $this->createProduct(5, 5),
            $this->createProduct(0, 13),
        ];
        yield [$cheque];

        $cheque = [
            $this->createProduct(2, 15),
            $this->createProduct(0, 13),
        ];
        yield [$cheque];

        $cheque = [
            $this->createProduct(3, 8),
            $this->createProduct(0, 13),
        ];
        yield [$cheque];

        /** @see PWEB-5453 */
        $cheque = [
            $this->createProduct(36, 100),
        ];
        yield [$cheque, 3227];

        /** @see PWEB-5480 */
        $cheque = [
            $this->createProduct(34, 30),
        ];
        yield [$cheque, 899.77];
        yield [$cheque, 900.21];

        /** @see PWEB-5625 */
        $cheque = [
            $this->createProduct(228, 5),
        ];
        yield [$cheque, 1014.12];

        /** @see PWEB-5626 */
        $cheque = [
            $this->createProduct(30, 2),
            $this->createProduct(30, 2),
            $this->createProduct(30, 2),
            $this->createProduct(30, 2),
            $this->createProduct(30, 2),
            $this->createProduct(30, 2),
            $this->createProduct(30, 2),
            $this->createProduct(30, 2),
            $this->createProduct(2134, 1),
            $this->createProduct(30, 2),
            $this->createProduct(1919, 1),
        ];
        yield [$cheque, 2729.78];
    }

    /**
     * @dataProvider cheques
     */
    public function testSmudgeOrders(array $cheque, float $sum = null)
    {
        $normalizer = $this->createSut();
        if (!$sum) {
            $sum = $normalizer->totalSum($cheque);
        }

        $chequeNormalized = $normalizer->normalize($cheque, $sum);

        $this->assertEquals($sum, $normalizer->totalSum($chequeNormalized));
        $this->assertEquals($normalizer->totalCount($cheque), $normalizer->totalCount($chequeNormalized));

        $zeroPrices = collect($chequeNormalized)->filter(function ($pos) {
            return $pos['price'] <= 0;
        });
        $this->assertEmpty($zeroPrices);
    }

    /**
     * @dataProvider percentSum
     */
    public function testSmudgeOrdersPositionsDetailFunction($iPercentSum)
    {
        $iSum = 0;
        foreach ($this->getProductionArray() as $aProductData) {
            $iSum += $aProductData["quantity"] * $aProductData["price"];
        }
        $iSum *= $iPercentSum;

        $aSmudgeProducts = $this->createSut()->normalize($this->getProductionArray(), $iSum);
        $this->assertNotEmpty($aSmudgeProducts);

        $iSmurgeSum = 0;
        foreach ($aSmudgeProducts as $aSmudgeProduct) {
            $iSmurgeSum += $aSmudgeProduct['price'] * $aSmudgeProduct['quantity'];
            // проверка на отсутствие позиций с нулевой ценой
            $this->assertNotEquals(0, $aSmudgeProduct['price'], "Price is 0");
        }
        // проверка на эквивалентность конечной суммы требуемой
        $this->assertEquals($iSum, $iSmurgeSum, "Sum not equals");
    }

    /**
     * @dataProvider randomCoeficent
     */
    public function testSmudgeOrdersPositionsDetailFunctionRandom(
        $iPercentSum,
        $iCountOrder,
        $iCountProduct,
        $iCountZeroProduct,
        $iMinPrice,
        $iMaxPrice,
        $iMinQuantity,
        $iMaxQuantity
    ) {

        for ($i = 0; $i < $iCountOrder; $i++) {
            $iSum = 0;

            // Добавление позиций с нулевой ценой
            $aProductsArray = array();
            for ($j = 0; $j < $iCountZeroProduct; $j++) {
                $aRandProduct = ["quantity" => rand($iMinQuantity, $iMaxQuantity), "price" => 0];
                $aProductsArray[] = $aRandProduct;
            }
            // Добавление случайных позиций
            for ($j = 0; $j < $iCountProduct; $j++) {
                $aRandProduct = [
                    "quantity" => rand($iMinQuantity, $iMaxQuantity),
                    "price" => rand($iMinPrice, $iMaxPrice),
                ];
                $iSum += $aRandProduct["quantity"] * $aRandProduct["price"];
                $aProductsArray[] = $aRandProduct;
            }
            $iSum = round($iSum * $iPercentSum, 2);

            $aSmudgeProducts = $this->createSut()->normalize($aProductsArray, $iSum);
            $this->assertNotEmpty($aSmudgeProducts);
            $iSmurgeSum = 0;
            foreach ($aSmudgeProducts as $aSmudgeProduct) {
                $iSmurgeSum += $aSmudgeProduct['price'] * $aSmudgeProduct['quantity'];
                // проверка на отсутствие позиций с нулевой ценой
                $this->assertNotEquals(0, $aSmudgeProduct['price'], "Price is 0");
            }
            // проверка на эквивалентность конечной суммы требуемой
            $this->assertEqualsWithDelta($iSum, $iSmurgeSum, 0.001, "Sum not equals");
        }
    }

    private function createSut()
    {
        return new ChequeNormalizer();
    }
}
