<?php

namespace TKKundendienst\Component\Gpsportal\Site\View\Updates;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Session\Session;
use TKKundendienst\Component\Gpsportal\Site\Service\UpdateBackupService;
use TKKundendienst\Component\Gpsportal\Site\Service\UpdateService;

class HtmlView extends BaseHtmlView
{
    public array $versionInfo = [];
    public array $systemStatus = [];
    public ?array $updateCheck = null;
    public ?array $backupResult = null;

    public function display($tpl = null)
    {
        $app = Factory::getApplication();
        $user = $app->getIdentity();

        $isUpdateAdministrator =
            $user
            && $user->id
            && (
                $user->authorise(
                    'core.admin'
                )
                || $user->authorise(
                    'core.manage',
                    'com_gpsportal'
                )
            );

        if (!$isUpdateAdministrator) {
            throw new \RuntimeException(
                'Sie besitzen keine Berechtigung fuer den Updatebereich.',
                403
            );
        }

        $defaults = [
            'project' => 'GPS-Portal',
            'version' => 'Unbekannt',
            'channel' => 'Unbekannt',
            'build' => 'Unbekannt',
            'released_at' => 'Unbekannt',
            'update_server' => '',
        ];

        $versionFile =
            JPATH_SITE
            . '/components/com_gpsportal/version.php';

        if (is_file($versionFile)) {
            $loaded = require $versionFile;

            if (is_array($loaded)) {
                $this->versionInfo =
                    array_merge(
                        $defaults,
                        $loaded
                    );
            }
        }

        if (empty($this->versionInfo)) {
            $this->versionInfo = $defaults;
        }

        $database = Factory::getContainer()
            ->get('DatabaseDriver');

        $databaseOk = false;

        try {
            $database->setQuery(
                'SELECT 1'
            );

            $databaseOk =
                (int) $database->loadResult()
                === 1;
        } catch (\Throwable $exception) {
            $databaseOk = false;
        }

        $componentPath =
            JPATH_SITE
            . '/components/com_gpsportal';

        $this->systemStatus = [
            'php_version' => PHP_VERSION,

            'php_supported' =>
                version_compare(
                    PHP_VERSION,
                    '8.2.0',
                    '>='
                ),

            'database' => $databaseOk,

            'component_writable' =>
                is_dir($componentPath)
                && is_writable($componentPath),

            'backup_directory' =>
                is_dir(
                    JPATH_SITE
                    . '/storage'
                ),

            'update_server_configured' =>
                trim(
                    (string) (
                        $this->versionInfo[
                            'update_server'
                        ] ?? ''
                    )
                ) !== '',
        ];

        $requestMethod =
            strtoupper(
                $app->input->getMethod()
            );

        $updateAction =
            $app->input->post->getCmd(
                'update_action',
                ''
            );

        if (
            $requestMethod === 'POST'
            && $updateAction === 'backup'
        ) {
            if (!Session::checkToken('post')) {
                throw new \RuntimeException(
                    'Die Sicherheitspruefung ist fehlgeschlagen.',
                    403
                );
            }

            try {
                $backupService =
                    new UpdateBackupService();

                $this->backupResult =
                    $backupService
                        ->createBackup();
            } catch (\Throwable $exception) {
                $this->backupResult = [
                    'success' => false,
                    'error' =>
                        $exception->getMessage(),
                ];
            }
        }

        $checkRequested =
            $app->input->getInt(
                'check',
                0
            ) === 1;

        if ($checkRequested) {
            $updateService =
                new UpdateService(
                    $this->versionInfo
                );

            $manifestFile =
                JPATH_SITE
                . '/components/com_gpsportal/update/manifest.json';

            $this->updateCheck =
                $updateService
                    ->checkLocalManifest(
                        $manifestFile
                    );
        }

        ob_start();

        parent::display($tpl);

        $content = ob_get_clean();

        require JPATH_SITE
            . '/components/com_gpsportal/layouts/app.php';
    }
}