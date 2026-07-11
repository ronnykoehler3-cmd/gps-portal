<?php

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/vendor/tecnickcom/tcpdf/tcpdf.php';

$pdf = new TCPDF();

$pdf->AddPage();

$pdf->SetFont('helvetica', '', 12);

$pdf->Write(
    0,
    'TCPDF funktioniert'
);

$pdf->Output(
    'test.pdf',
    'I'
);
