<?php

namespace App\Controller;
use Endroid\QrCode\Builder\BuilderInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;


class QRCodeController extends AbstractController
{
public function __construct(BuilderInterface $customQrCodeBuilder)
{
    $result = $customQrCodeBuilder->build(
        size: 400,
        margin: 20
    );
}

}