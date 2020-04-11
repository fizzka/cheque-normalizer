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

        $normalizer = new ChequeNormalizer();
        $aSmudgeProducts = $normalizer->normalize($this->getProductionArray(), $iSum);
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

            $normalizer = new ChequeNormalizer();
            $aSmudgeProducts = $normalizer->normalize($aProductsArray, $iSum);
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
}
