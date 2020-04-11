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
        $iPositionsSum = 0;
        foreach ($aProducts as $product) {
            $iPositionsSum += $product['price'] == 0
                ? (int)$product['quantity']
                : (int)$product['quantity']*$product['price'];
        }

        $iDiscountValue = $iPositionsSum - $iTotalSum;

        $fCoefficient = (double)bcdiv($iDiscountValue, $iPositionsSum, 2);
        $iDiscountUsed = 0;
        $iZeroProductsCount = 0;

        foreach ($aProducts as &$aProduct) {
            $iDiscountByProduct = floor($aProduct['price'] * $fCoefficient);
            $iDiscountProductPrice = $aProduct['price'] - $iDiscountByProduct;
            $iDiscountUsed += $iDiscountByProduct * $aProduct['quantity'];

            if ($iDiscountProductPrice <= 0) {
                $iDiscountProductPrice = 1;
                $iZeroProductsCount += $aProduct['quantity'];
            }

            $aProduct['price'] = $iDiscountProductPrice;

            unset($aProduct);
        }

        $iDiscountError = $iDiscountValue - $iDiscountUsed;

        if ($iDiscountError < 0) {
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
        } elseif ($iDiscountError > 0) {
            foreach ($aProducts as &$aProduct) {
                if ($aProduct['price'] <= 1) {
                    continue;
                }

                if ($iDiscountError >= (($aProduct['price'] * $aProduct['quantity']) - $aProduct['quantity'])) {
                    $iDiscountError -= ($aProduct['price'] * $aProduct['quantity']) - $aProduct['quantity'];
                    $aProduct['price'] = 1;
                } else {
                    $iSeparatedProducts = ($iDiscountError / $aProduct['price'] > $aProduct['quantity']) ? $aProduct['quantity'] : $iDiscountError / $aProduct['price'];

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

                    if (($aProduct['price'] - $iDiscountError) >= 1) {
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

                        $iDiscountError = 0;
                    }
                }

                if ($iDiscountError === 0) {
                    break;
                }

                unset($aProduct);
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
