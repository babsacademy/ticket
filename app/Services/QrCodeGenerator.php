<?php

namespace App\Services;

use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

class QrCodeGenerator
{
    /**
     * Render the given data as a PNG QR code and return the raw image bytes.
     */
    public function toPng(string $data): string
    {
        $qrCode = new QrCode(
            data: $data,
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 400,
            margin: 16,
        );

        return (new PngWriter)->write($qrCode)->getString();
    }
}
