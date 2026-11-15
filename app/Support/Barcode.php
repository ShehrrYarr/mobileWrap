<?php

namespace App\Support;

use Picqer\Barcode\Renderers\SvgRenderer;
use Picqer\Barcode\Types\TypeCode128;
use Picqer\Barcode\Types\TypeEan13;
use Throwable;

/** Inline SVG barcodes for receipts and product labels. */
class Barcode
{
    /** EAN-13 when the code is a valid 13-digit EAN, otherwise Code 128. */
    public static function svg(?string $code, float $width = 220, float $height = 48): string
    {
        $code = trim((string) $code);
        if ($code === '') {
            return '';
        }

        try {
            $barcode = preg_match('/^\d{13}$/', $code) && self::validEan13($code)
                ? (new TypeEan13)->getBarcode($code)
                : (new TypeCode128)->getBarcode($code);

            return (new SvgRenderer)->render($barcode, $width, $height);
        } catch (Throwable) {
            return '';
        }
    }

    public static function validEan13(string $code): bool
    {
        $sum = 0;
        foreach (str_split(substr($code, 0, 12)) as $i => $d) {
            $sum += (int) $d * ($i % 2 ? 3 : 1);
        }

        return (10 - $sum % 10) % 10 === (int) $code[12];
    }
}
