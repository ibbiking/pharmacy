<?php
namespace App\Http\Controllers;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Intervention\Image\Facades\Image;

class GenerateQrWithLogoController extends Controller
{
    public function generateQrWithLogo()
{
    // Step 1: Generate QR Code without saving to file
    $qrCode = QrCode::format('png')
        ->size(300)
        ->errorCorrection('H') // High error correction for logo
        ->generate('https://ee.kobotoolbox.org/x/JYF9lANG');

    // Step 2: Create an Intervention Image from the QR
    $qrImage = Image::make($qrCode);

    // Step 3: Load the logo
    $logo = Image::make(public_path('packages.png'))->resize(60, 60);

    // Step 4: Insert the logo into the center of the QR
    $qrImage->insert($logo, 'center');

    return $qrImage->response('png');
}
}
