<?php

namespace TKKundendienst\Component\Gpsportal\Site\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use TCPDF;
use TKKundendienst\Component\Gpsportal\Site\Model\TraccarModel;
class LogbookController extends BaseController
{
    public function save()
    {
        $app = Factory::getApplication();
$signaturePlace =
    $app->input->getString(
        'signature_place',
        ''
    );

$signatureDate =
    $app->input->getString(
        'signature_date',
        ''
    );

$signatureDriver =
    $app->input->getString(
        'signature_driver',
        ''
    );	
$tripTypes = $app->input->post->get('trip_type', [], 'array');
        $tripReasons = $app->input->post->get('trip_reason', [], 'array');

        $tripStarts = $app->input->post->get('trip_start', [], 'array');
        $tripEnds = $app->input->post->get('trip_end', [], 'array');

        $startKms = $app->input->post->get('start_km', [], 'array');
        $endKms = $app->input->post->get('end_km', [], 'array');
$startLats = $app->input->post->get('start_lat', [], 'array');
$startLons = $app->input->post->get('start_lon', [], 'array');

$endLats = $app->input->post->get('end_lat', [], 'array');
$endLons = $app->input->post->get('end_lon', [], 'array');
        $distanceKms = $app->input->post->get('distance_km', [], 'array');
        $durationMinutes = $app->input->post->get('duration_minutes', [], 'array');

        $vehicleId = (int) $app->input->post->getInt('vehicle');

        $user = $app->getIdentity();

        $db = Factory::getContainer()
            ->get('DatabaseDriver');

        foreach ($tripTypes as $tripKey => $tripType)
        {
            $tripReason =
                $tripReasons[$tripKey]
                ?? '';

            $query = $db->getQuery(true)
                ->select('id')
                ->from('#__gpsportal_logbook')
                ->where(
                    'trip_key = ' .
                    $db->quote($tripKey)
                );

            $db->setQuery($query);

            $existingId = (int) $db->loadResult();

            if ($existingId)
            {
                $entry = (object) [

                    'id' => $existingId,

                    'trip_type' =>
                        $tripType,

                    'trip_reason' =>
                        $tripReason,
		    'signature_place' =>
		        $signaturePlace,

		    'signature_date' =>
    			$signatureDate,

		    'signature_driver' =>
    			$signatureDriver,
                    'trip_start' =>
                        $tripStarts[$tripKey] ?? null,

                    'trip_end' =>
                        $tripEnds[$tripKey] ?? null,

                    'start_km' =>
                        (float)($startKms[$tripKey] ?? 0),

                    'end_km' =>
                        (float)($endKms[$tripKey] ?? 0),

                    'distance_km' =>
                        (float)($distanceKms[$tripKey] ?? 0),

                    'duration_minutes' =>
                        (int)($durationMinutes[$tripKey] ?? 0)
                ];

$result = $db->updateObject(
    '#__gpsportal_logbook',
    $entry,
    'id'
);

                continue;
            }

            $entry = (object) [

                'user_id' =>
                    (int)$user->id,

                'vehicle_id' =>
                    $vehicleId,

                'trip_key' =>
                    $tripKey,

                'trip_type' =>
                    $tripType,

                'trip_reason' =>
                    $tripReason,
		'signature_place' =>
		    $signaturePlace,

		'signature_date' =>
		    $signatureDate,

		'signature_driver' =>
		    $signatureDriver,
                'trip_start' =>
                    $tripStarts[$tripKey] ?? null,

                'trip_end' =>
                    $tripEnds[$tripKey] ?? null,

                'start_km' =>
                    (float)($startKms[$tripKey] ?? 0),

                'end_km' =>
                    (float)($endKms[$tripKey] ?? 0),

                'distance_km' =>
                    (float)($distanceKms[$tripKey] ?? 0),
'duration_minutes' =>
    (int)($durationMinutes[$tripKey] ?? 0),
'start_lat' =>
    (float)($startLats[$tripKey] ?? 0),

'start_lon' =>
    (float)($startLons[$tripKey] ?? 0),

'end_lat' =>
    (float)($endLats[$tripKey] ?? 0),

'end_lon' =>
    (float)($endLons[$tripKey] ?? 0),

'created' =>
    date('Y-m-d H:i:s')
            ];

            $db->insertObject(
                '#__gpsportal_logbook',
                $entry
            );
        }

        $app->enqueueMessage(
            'Fahrtenbuch gespeichert.'
        );

        $app->redirect(
            $_SERVER['HTTP_REFERER']
        );
    }
public function pdf()
{
    require_once JPATH_ROOT . '/vendor/autoload.php';
    require_once JPATH_ROOT . '/vendor/tecnickcom/tcpdf/tcpdf.php';

    $app = Factory::getApplication();

$vehicleId = (int) $app->input->getInt('vehicle');
$signaturePlace =
    $app->input->getString(
        'signature_place',
        ''
    );

$signatureDate =
    $app->input->getString(
        'signature_date',
        ''
    );

$signatureDriver =
    $app->input->getString(
        'signature_driver',
        ''
    );

    $db = Factory::getContainer()
        ->get('DatabaseDriver');

    $query = $db->getQuery(true)
        ->select('*')
        ->from('#__gpsportal_logbook')
        ->where('vehicle_id = ' . (int) $vehicleId)
        ->order('trip_start ASC');

    $db->setQuery($query);

    $rows = $db->loadAssocList();
if (!empty($rows))
{
    foreach (array_reverse($rows) as $row)
    {
        if (
            !empty($row['signature_place'])
            ||
            !empty($row['signature_driver'])
        )
        {
            $signaturePlace =
                $row['signature_place'] ?? '';

            $signatureDate =
                $row['signature_date'] ?? '';

            $signatureDriver =
                $row['signature_driver'] ?? '';

            break;
        }
    }
}
$deviceQuery = $db->getQuery(true)
    ->select('*')
    ->from('#__gpsportal_devices')
    ->where(
        'traccar_device_id = '
        . (int) $vehicleId
    );
$db->setQuery($deviceQuery);

$device = $db->loadAssoc();
    $totalKm = 0;
    $totalMinutes = 0;
$fromDate = '';
$toDate = '';

if (!empty($rows))
{
    $fromDate = date(
        'd.m.Y',
        strtotime($rows[0]['trip_start'])
    );

    $lastRow = end($rows);

    $toDate = date(
        'd.m.Y',
        strtotime($lastRow['trip_end'])
    );
}
foreach ($rows as $row)
{
    $totalKm += (float) $row['distance_km'];
    $totalMinutes += (int) $row['duration_minutes'];
}

$traccarModel = new TraccarModel();
    $pdf = new TCPDF(
        'L',
        PDF_UNIT,
        'A4',
        true,
        'UTF-8',
        false
    );

    $pdf->SetCreator('GPS Portal');
    $pdf->SetAuthor('TK Kundendienst');
    $pdf->SetTitle('Fahrtenbuch');

    $pdf->SetMargins(8, 10, 8);

    $pdf->AddPage();
$pdf->SetAutoPageBreak(false);
$pdf->Image(
    JPATH_ROOT . '/images/gpsportal/logo.png',
    190,
    4,
    90
);

$pdf->Ln(0);
$pdf->Ln(12);
$pdf->SetFont('helvetica', 'B', 16);

$pdf->Cell(
    0,
    10,
    'Fahrtenbuch',
    0,
    1
);

$pdf->Ln(2);

$pdf->SetFont('helvetica', '', 10);

$pdf->Cell(
    90,
    6,
    'Fahrzeug: ' .
    ($device['name'] ?? '-'),
    0,
    0
);

$pdf->Cell(
    90,
    6,
    'Kennzeichen: ' .
    (!empty($device['license_plate'])
        ? $device['license_plate']
        : '-'),
    0,
    1
);
$pdf->Cell(
    90,
    6,
    'Gesamt-KM: ' .
    round($totalKm, 1),
    0,
    0
);

$pdf->Cell(
    90,
    6,
    'Gesamt-Fahrzeit: ' .
    floor($totalMinutes / 60)
    . ' h '
    . ($totalMinutes % 60)
    . ' min',
    0,
    1
);
$pdf->Cell(
    0,
    6,
    'Zeitraum: '
    . $fromDate
    . ' - '
    . $toDate,
    0,
    1
);
$pdf->Ln(5);


    $pdf->Ln(2);
$traccarModel = new TraccarModel();
$html = '
<table border="1" cellpadding="3">
<tr style="font-weight:bold;">
<th width="70">Datum</th>
<th width="35">Start</th>
<th width="140">Startadresse</th>
<th width="35">Ende</th>
<th width="140">Zieladresse</th>
<th width="40">Start KM</th>
<th width="40">End KM</th>
<th width="40">KM</th>
<th width="45">Dauer</th>
<th width="80">Art</th>
<th width="125">Fahrtgrund</th>
</tr>';
    foreach ($rows as $row)
    {
$startAddress =
    $traccarModel->getAddress(
        (float)$row['start_lat'],
        (float)$row['start_lon']
    );

$endAddress =
    $traccarModel->getAddress(
        (float)$row['end_lat'],
        (float)$row['end_lon']
    );
        $html .= '

<tr>

<td>'
. date('d.m.Y', strtotime($row['trip_start']))
. '</td>

<td>'
. date('H:i', strtotime($row['trip_start']))
. '</td>

<td>'
. htmlspecialchars(
    mb_strimwidth(
        $startAddress,
        0,
        45,
        '...'
    )
)
. '</td>
<td>'
. date('H:i', strtotime($row['trip_end']))
. '</td>

<td>'
. htmlspecialchars(
    mb_strimwidth(
        $endAddress,
        0,
        45,
        '...'
    )
)
. '</td>
            <td>'
            . number_format($row['start_km'], 1, ',', '.')
            . '</td>

            <td>'
            . number_format($row['end_km'], 1, ',', '.')
            . '</td>

            <td>'
            . number_format($row['distance_km'], 1, ',', '.')
            . '</td>

            <td>'
            . floor($row['duration_minutes'] / 60)
            . 'h '
            . ($row['duration_minutes'] % 60)
            . 'm</td>

            <td>'
            . htmlspecialchars($row['trip_type'])
            . '</td>

            <td>'
            . htmlspecialchars($row['trip_reason'])
            . '</td>
        </tr>';
    }

    $html .= '
        <tr style="font-weight:bold;">
            <td colspan="5">Gesamt</td>
            <td>'
            . number_format($totalKm, 1, ',', '.')
            . '</td>
            <td>'
            . floor($totalMinutes / 60)
            . 'h '
            . ($totalMinutes % 60)
            . 'm</td>
            <td colspan="2"></td>
        </tr>
    </table>';

    $pdf->writeHTML(
        $html,
        true,
        false,
        true,
        false,
        ''
    );

$pdf->Ln(2);

$pdf->Ln(4);

$pdf->SetFont('helvetica', '', 9);

$pdf->Ln(2);

$pdf->Cell(
    120,
    6,
    'Ort / Datum: '
    . $signaturePlace
    . (!empty($signatureDate)
        ? ' - ' . date('d.m.Y', strtotime($signatureDate))
        : ''),
    0,
    0
);

$pdf->Cell(
    120,
    6,
    'Fahrer: '
    . $signatureDriver,
    0,
    1
);
    $pdf->Output(
        'fahrtenbuch.pdf',
        'I'
    );

    Factory::getApplication()->close();
}
}
