<?php

namespace TKKundendienst\Component\Gpsportal\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

class DocumentsModel extends BaseDatabaseModel
{
private string $lastError = '';

public function getLastError(): string
{
    return $this->lastError;
}
    public function getVehicles(): array
    {
        $user = Factory::getApplication()
            ->getIdentity();

        $userId = (int) ($user->id ?? 0);

        if ($userId <= 0) {
            return [];
        }

        $db = Factory::getContainer()
            ->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select([
                'd.*',
                $db->quoteName(
                    'ud.display_name',
                    'customer_display_name'
                )
            ])
            ->from(
                $db->quoteName(
                    '#__gpsportal_devices',
                    'd'
                )
            )
            ->join(
                'INNER',
                $db->quoteName(
                    '#__gpsportal_user_devices',
                    'ud'
                )
                . ' ON '
                . $db->quoteName('ud.device_id')
                . ' = '
                . $db->quoteName('d.id')
            )
            ->where(
                $db->quoteName('ud.user_id')
                . ' = '
                . $userId
            )
            ->order(
                $db->quoteName('ud.display_name')
                . ' ASC'
            );

        $db->setQuery($query);

        $vehicles = $db->loadObjectList();

        foreach ($vehicles as $vehicle) {
            $customerName = trim(
                (string) (
                    $vehicle->customer_display_name
                    ?? ''
                )
            );

            if ($customerName !== '') {
                $vehicle->name = $customerName;
            }
        }

        return $vehicles;
    }

    public function getDocuments(int $vehicleId)
    {
        $db = Factory::getContainer()
            ->get('DatabaseDriver');

        $query = $db->getQuery(true)

            ->select('*')

            ->from(
                '#__gpsportal_vehicle_documents'
            )

            ->where(
                'vehicle_id=' . (int) $vehicleId
            )

            ->order(
                'created DESC'
            );

        $db->setQuery($query);

        return $db->loadObjectList();
    }
public function saveDocument(
    array $file,
    array $data
)
{
    if (
        empty($file['tmp_name'])
        || empty($file['name'])
    )
    {
        $this->lastError =
            'Bitte zuerst eine Datei auswÃ¤hlen.';

        return false;
    }

    if (
        !empty($file['error'])
        && $file['error'] !== UPLOAD_ERR_OK
    )
    {
        $this->lastError =
            'Fehler beim Hochladen der Datei.';

        return false;
    }

    $user = Factory::getApplication()->getIdentity();

    $storagePath =
        '/var/www/gps/storage/vehicles/';

    $extension =
        strtolower(
            pathinfo(
                $file['name'],
                PATHINFO_EXTENSION
            )
        );
$allowedExtensions = [
    'pdf',
    'jpg',
    'jpeg',
    'png'
];
if (!in_array($extension, $allowedExtensions))
{
    $this->lastError =
        'Erlaubt sind nur PDF, JPG und PNG Dateien.';

    return false;
}
$maxSize = 20 * 1024 * 1024;
if (($file['size'] ?? 0) > $maxSize)
{
    $this->lastError =
        'Die Datei darf maximal 20 MB groÃŸ sein.';

    return false;
}
$finfo = finfo_open(FILEINFO_MIME_TYPE);

$mimeType = finfo_file(
    $finfo,
    $file['tmp_name']
);

finfo_close($finfo);

$allowedMimeTypes = [
    'application/pdf',
    'image/jpeg',
    'image/png'
];
if (!in_array($mimeType, $allowedMimeTypes))
{
    $this->lastError =
        'UngÃ¼ltiger Dateityp erkannt.';

    return false;
}
    $filename =
        uniqid('doc_')
        . '.'
        . $extension;
if (
    !move_uploaded_file(
        $file['tmp_name'],
        $storagePath . $filename
    )
)
{
    $this->lastError =
        'Datei konnte nicht gespeichert werden.';

    return false;
}
    $db = Factory::getContainer()
        ->get('DatabaseDriver');

    $document = (object) [

        'vehicle_id'
            => (int) $data['vehicle_id'],

        'user_id'
            => (int) $user->id,

        'filename'
            => $filename,

        'original_name'
            => $file['name'],

        'file_size'
            => (int) $file['size'],

        'document_type'
            => $data['document_type'],

'valid_until'
    => !empty($data['valid_until'])
        ? $data['valid_until']
        : null,

        'reminder_days'
            => (int) $data['reminder_days'],

        'notes'
            => $data['notes'],

        'created'
            => date('Y-m-d H:i:s')
    ];

    $db->insertObject(
        '#__gpsportal_vehicle_documents',
        $document
    );

    return true;
}
private function userCanAccessDocument(int $documentId): bool
{
    $user = Factory::getApplication()->getIdentity();

    $db = Factory::getContainer()
        ->get('DatabaseDriver');

    $query = $db->getQuery(true)

        ->select('COUNT(*)')

        ->from('#__gpsportal_vehicle_documents', 'd')

        ->join(
            'INNER',
            '#__gpsportal_user_devices ud ON ud.device_id = d.vehicle_id'
        )

        ->where('d.id = ' . (int) $documentId)

        ->where('ud.user_id = ' . (int) $user->id);

    $db->setQuery($query);

    return (int) $db->loadResult() > 0;
}
public function getDocument(int $id)
{
if (!$this->userCanAccessDocument($id))
{
    return null;
}
    $db = Factory::getContainer()
        ->get('DatabaseDriver');

    $query = $db->getQuery(true)

        ->select('*')

        ->from(
            '#__gpsportal_vehicle_documents'
        )

        ->where(
            'id=' . (int) $id
        );

    $db->setQuery($query);

    return $db->loadObject();
}

public function downloadDocument(int $id)
{
if (!$this->userCanAccessDocument($id))
{
    return false;
}
    $document = $this->getDocument($id);

    if (!$document)
    {
        return false;
    }

    $file =
        '/var/www/gps/storage/vehicles/'
        . $document->filename;

    if (!file_exists($file))
    {
        return false;
    }

    while (ob_get_level())
    {
        ob_end_clean();
    }

    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header(
        'Content-Disposition: attachment; filename="'
        . basename($document->original_name)
        . '"'
    );
    header('Content-Length: ' . filesize($file));

    readfile($file);

    exit;
}
public function deleteDocument(int $id)
{
if (!$this->userCanAccessDocument($id))
{
    return false;
}
    $document = $this->getDocument($id);

    if ($document)
    {
        $file =
            '/var/www/gps/storage/vehicles/'
            . $document->filename;

        if (file_exists($file))
        {
            unlink($file);
        }
    }

    $db = Factory::getContainer()
        ->get('DatabaseDriver');

    $query = $db->getQuery(true)

        ->delete(
            '#__gpsportal_vehicle_documents'
        )

        ->where(
            'id=' . (int) $id
        );

    $db->setQuery($query);

    $db->execute();

    return true;
}
}

