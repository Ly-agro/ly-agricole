<?php

namespace App\Support;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * QR codes en PNG (data URI), lisibles par dompdf. Niveau de correction Q (25 %) :
 * une carte portée dans une poche se salit et se plie.
 */
class CodeQr
{
    public static function pngDataUri(string $contenu, int $echelle = 8): string
    {
        $options = new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'eccLevel' => EccLevel::Q,
            'scale' => $echelle,
            'quietzoneSize' => 2,
            'outputBase64' => true,
        ]);

        return (new QRCode($options))->render($contenu);
    }
}
