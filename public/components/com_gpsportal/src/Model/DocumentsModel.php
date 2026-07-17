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
    private function getStoragePath(): string
    {
        $projectRoot = JPATH_ROOT;

        /*
         * Auf dem Produktionsserver liegt Joomla unter:
         * /var/www/gps/public
         *
         * Der gesch?tzte Speicher liegt daneben unter:
         * /var/www/gps/storage
         */
        if (
            strtolower(
                basename(
                    str_replace('\\', '/', JPATH_ROOT)
                )
            ) === 'public'
        ) {
            $projectRoot = dirname(JPATH_ROOT);
        }

        $storagePath = $projectRoot
            . DIRECTORY_SEPARATOR
            . 'storage'
            . DIRECTORY_SEPARATOR
            . 'vehicles';

        if (
            !is_dir($storagePath)
            && !mkdir($storagePath, 0770, true)
            && !is_dir($storagePath)
        ) {
            $this->lastError =
                'Der Dokumentenordner konnte nicht angelegt werden.';

            return '';
        }

        return $storagePath
            . DIRECTORY_SEPARATOR;
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
            'Bitte zuerst eine Datei auswählen.';

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

    $storagePath = $this->getStoragePath();

    if (
        $storagePath === ''
        || !is_dir($storagePath)
        || !is_writable($storagePath)
    ) {
        $this->lastError =
            'Der Dokumentenordner ist nicht beschreibbar.';

        return false;
    }

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
        'Ungültiger Dateityp erkannt.';

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

        ->from(
            $db->quoteName(
                '#__gpsportal_vehicle_documents',
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
            . $db->quoteName('d.vehicle_id')
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
        $this->getStoragePath()
            . basename((string) $document->filename);

    if (!file_exists($file))
    {
        return false;
    }

    while (ob_get_level())
    {
        ob_end_clean();
    }

    header('Content-Description: File Transfer');
    $extension = strtolower(
    pathinfo(
        $document->original_name,
        PATHINFO_EXTENSION
    )
);

$mimeTypes = [
    'pdf'  => 'application/pdf',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png'
];

header(
    'Content-Type: '
    . ($mimeTypes[$extension]
        ?? 'application/octet-stream')
);
    header(
        'Content-Disposition: attachment; filename="'
        . basename($document->original_name)
        . '"'
    );
    header('Content-Length: ' . filesize($file));

    readfile($file);

    exit;
}
public function previewDocument(int $id)
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
        $this->getStoragePath()
        . basename((string) $document->filename);

    if (!file_exists($file))
    {
        return false;
    }

    $extension = strtolower(
        pathinfo(
            $document->original_name,
            PATHINFO_EXTENSION
        )
    );

    $mimeTypes = [
        'pdf'  => 'application/pdf',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png'
    ];

    $mime =
        $mimeTypes[$extension]
        ?? 'application/octet-stream';

    while (ob_get_level())
    {
        ob_end_clean();
    }

    header('Content-Type: ' . $mime);
    header(
        'Content-Disposition: inline; filename="'
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
            $this->getStoragePath()
            . basename((string) $document->filename);

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

