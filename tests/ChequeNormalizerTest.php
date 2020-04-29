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

    public function cheques()
    {
        $cheque1 = [];
        $cheque1[] = [
            'name' => 'product1',
            'quantity' => 11,
            'price' => 7,
        ];
        $cheque1[] = [
            'name' => 'product2',
            'quantity' => 5,
            'price' => 5,
        ];
        $cheque1[] = [
            'name' => 'gift',
            'quantity' => 13,
            'price' => 0,
        ];
        yield [$cheque1];

        $cheque2 = [];
        $cheque2[] = [
            'name' => 'product1',
            'quantity' => 15,
            'price' => 2,
        ];
        $cheque2[] = [
            'name' => 'gift',
            'quantity' => 13,
            'price' => 0,
        ];
        yield [$cheque2];

        $cheque3 = [];
        $cheque3[] = [
            'name' => 'product1',
            'quantity' => 8,
            'price' => 3,
        ];
        $cheque3[] = [
            'name' => 'gift',
            'quantity' => 13,
            'price' => 0,
        ];
        yield [$cheque3];

        /** @see PWEB-5453 */
        $cheque = [];
        $cheque[] = [
            'name' => 'product1',
            'quantity' => 100,
            'price' => 36,
        ];
        yield [$cheque, 3227];

        /** @see PWEB-5480 */
        $cheque = [];
        $cheque[] = [
            'name' => 'product1',
            'quantity' => 30,
            'price' => 34,
        ];
        yield [$cheque, 899.77];

        /** @see PWEB-5480 */
        $cheque = [];
        $cheque[] = [
            'name' => 'product1',
            'quantity' => 30,
            'price' => 34,
        ];
        yield [$cheque, 900.21];

        /** @see PWEB-5625 */
        $cheque = [];
        $cheque[] = [
            'name' => 'product1',
            'quantity' => 5,
            'price' => 228,
        ];
        yield [$cheque, 1014.12];
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
