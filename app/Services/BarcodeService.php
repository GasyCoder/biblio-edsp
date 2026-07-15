<?php

namespace App\Services;

use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;
use Picqer\Barcode\BarcodeGeneratorSVG;

class BarcodeService
{
    public function qrSvg(string $value, int $size = 260): string
    {
        $qrCode = new QrCode(
            data: $value,
            encoding: new Encoding('ISO-8859-1'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: $size,
            margin: 10,
        );

        return (new SvgWriter)->write($qrCode)->getString();
    }

    public function qrPngDataUri(string $value, int $size = 260): string
    {
        $qrCode = new QrCode(
            data: $value,
            encoding: new Encoding('ISO-8859-1'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: $size,
            margin: 10,
        );

        return (new PngWriter)->write($qrCode)->getDataUri();
    }

    public function code128Svg(string $value, int $height = 56): string
    {
        return (new BarcodeGeneratorSVG)->getBarcode($value, BarcodeGeneratorSVG::TYPE_CODE_128, 2, $height);
    }
}
