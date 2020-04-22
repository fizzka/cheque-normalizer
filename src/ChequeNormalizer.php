<?php

namespace Petcorp\Fiscal;

class ChequeNormalizer
{
    private $rounder = 'floor';
    private $checker;

    public function __construct()
    {
        $this->checker = $this->defaultChecker();
    }

    public function normalize(array $aProducts, $iTotalSum)
    {
        $positionCount = $this->totalCount($aProducts);

        if ($positionCount > $iTotalSum) {
            throw new \Exception("Can't normalize cheque: items={$positionCount}, sum={$iTotalSum}");
        }

        $iPositionsSum = 0;
        foreach ($aProducts as $product) {
            $iPositionsSum += $product['price'] == 0
                ? (int)$product['quantity']
                : (int)$product['quantity'] * $product['price'];
        }

        $iDiscountValue = $iPositionsSum - $iTotalSum;

        $fCoefficient = (double)bcdiv($iDiscountValue, $iPositionsSum, 2);
        $iDiscountUsed = 0;

        foreach ($aProducts as &$aProduct) {
            $iDiscountByProduct = floor($aProduct['price'] * $fCoefficient);
            $iDiscountProductPrice = $aProduct['price'] - $iDiscountByProduct;
            $iDiscountUsed += $iDiscountByProduct * $aProduct['quantity'];

            if ($iDiscountProductPrice <= 0) {
                $iDiscountProductPrice = 1;
            }

            $aProduct['price'] = (int)$iDiscountProductPrice;

            unset($aProduct);
        }

        $iDiscountError = $iDiscountValue - (int)$iDiscountUsed;
        //todo
        //$iDiscountError = $this->totalSum($aProducts) - $iTotalSum);

        $prevDiff = 2 * $iDiscountError;

        while (abs($iDiscountError) >= 1 && abs($prevDiff) > abs($iDiscountError)) {
            if ($iDiscountError > 0) {
                $aProducts = $this->positiveCorrection($aProducts, $iDiscountError);
            }

            if ($iDiscountError < 0) {
                $aProducts = $this->negativeCorrection($aProducts, $iDiscountError);
            }

            $prevDiff = $iDiscountError;
            $iDiscountError = $this->totalSum($aProducts) - $iTotalSum;
        }

        if (abs($iDiscountError) >= 1) {
            // throw new \Exception('Normalization failed');
            return [];
        }

        $iDiscountError = round($iDiscountError, 2);
        if ($iDiscountError !== 0) {
            $aProducts = $this->fixCops($aProducts, $iDiscountError);
        }

        return $aProducts;
    }

    public static function totalSum(array $cheque): float
    {
        return collect($cheque)->sum(function(array $pos): float {
            return $pos['price'] * $pos['quantity'];
        });
    }

    public static function totalCount(array $cheque): int
    {
        return collect($cheque)->sum('quantity');
    }

    private function fixCops(array $cheque, float $iDiscountError): array
    {
        // tmp fix PWEB-5480
        $lastPos = array_pop($cheque);
        if ($lastPos['quantity'] == 1) {
            $lastPos['price'] -= $iDiscountError;
        }

        array_push($cheque, $lastPos);
        return $cheque;
    }

    private function negativeCorrection($aProducts, $iDiscountError)
    {
        $aFirstProduct = &$aProducts[0];

        if ($aFirstProduct['quantity'] > 1) {
            $aFirstProduct['quantity'] -= 1;
            $aProducts[] = [
                'name' => $aFirstProduct['name'] ?? '',
                'quantity' => 1,
                'price' => $aFirstProduct['price'] - $iDiscountError,
            ];
        } else {
            $aFirstProduct['price'] -= $iDiscountError;
        }

        return $aProducts;
    }

    private function positiveCorrection($aProducts, $iDiscountError)
    {
        foreach ($aProducts as &$aProduct) {
            if ($iDiscountError === 0) {
                break;
            }

            if ($aProduct['price'] <= 1) {
                continue;
            }

            if ($iDiscountError >= ($aProduct['price'] - 1) * $aProduct['quantity']) {
                $iDiscountError -= ($aProduct['price'] - 1) * $aProduct['quantity'];
                $aProduct['price'] = 1;
                continue;
            }

            if ($iDiscountError <= $aProduct['price'] - 1) {
                if ($aProduct['quantity'] > 1) {
                    $aProduct['quantity'] -= 1;

                    $aProducts[] = [
                        'name' => $aProduct['name'] ?? '',
                        'quantity' => 1,
                        'price' => $aProduct['price'] - $iDiscountError,
                    ];
                } else {
                    $aProduct['price'] -= $iDiscountError;
                }
                break;
            }

            $iSeparatedProducts = min($aProduct['quantity'], $iDiscountError / ($aProduct['price'] - 1));
            $iSeparatedProducts = $this->round($iSeparatedProducts);

            if ($this->check($iSeparatedProducts, $aProduct['price'], $iDiscountError)) {
                $aProduct['quantity'] -= $iSeparatedProducts;
                $aProducts[] = [
                    'name' => $aProduct['name'] ?? '',
                    'quantity' => $iSeparatedProducts,
                    'price' => 1,
                ];

                $iDiscountError -= ($aProduct['price'] - 1) * $iSeparatedProducts;
            }
        }

        return $aProducts;
    }

    private static function defaultChecker()
    {
        return function ($number) {
            return $number > 0;
        };
    }

    public function check(int $number, float $price, int $error): bool
    {
        return ($this->checker)($number, $price, $error);
    }

    public function round($number): int
    {
        return ($this->rounder)($number);
    }

    public function setRounder(callable $fn): self
    {
        $this->rounder = $fn;
        return $this;
    }

    public function setChecker(callable $fn): self
    {
        $this->condition = $fn;
        return $this;
    }
}
