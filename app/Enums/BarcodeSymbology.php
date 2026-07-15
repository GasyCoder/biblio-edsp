<?php

namespace App\Enums;

enum BarcodeSymbology: string
{
    case Qr = 'qr';
    case Code128 = 'code128';
}
