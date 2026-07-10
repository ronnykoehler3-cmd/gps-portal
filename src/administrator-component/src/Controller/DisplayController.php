<?php

namespace TKKundendienst\Component\Gpsportal\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use TKKundendienst\Component\Gpsportal\Administrator\Model\SettingsModel;
use TKKundendienst\Component\Gpsportal\Administrator\Model\TraccarModel;
use TKKundendienst\Component\Gpsportal\Administrator\Model\TodoModel;
use TKKundendienst\Component\Gpsportal\Administrator\Model\SystemModel;
use TKKundendienst\Component\Gpsportal\Administrator\Model\TripGroupModel;

class DisplayController extends BaseController
{
    public function execute($task)
    {
        $app = Factory::getApplication();

        /*
         * Sammelfahrt löschen
         */
        if (
            $app->input->getCmd('action') === 'deletegroup'
        )
        {
            $groupId =
                $app->input->getInt(
                    'group_id'
                );

$model =
    new TripGroupModel();
            $model =
                new TripGroupModel();

            $model->deleteGroup(
                $groupId
            );

            $app->enqueueMessage(
                'Sammelfahrt gelöscht',
                'success'
            );

            $app->redirect(
                'index.php?option=com_gpsportal&view=logbook'
            );

            return;
        }

        /*
        * Fahrt speichern
         */
        if (
            $app->input->getCmd('action') === 'savetrip'
        )
        {
            $model =
                new TripGroupModel();

            $model->saveTripItem(
                $app->input->getInt('id'),
                $app->input->getString(
                    'trip_type'
                ),
                $app->input->post->get(
                    'note',
                    '',
                    'raw'
                ),
                $app->input->post->get(
                    'customer',
                    '',
                    'string'
                ),
                $app->input->post->get(
                    'order_number',
                    '',
                    'string'
                )
            );

            $app->enqueueMessage(
                'Fahrt gespeichert',
                'success'
            );

            $app->redirect(
                'index.php?option=com_gpsportal&view=logbook'
            );

            return;
        }

        /*
         * PDF Export
         */
        if (
            $app->input->getCmd('action') === 'pdf'
        )
        {
            require_once JPATH_ROOT . '/vendor/autoload.php';

            $groupId =
                $app->input->getInt(
                    'group_id'
                );

            $model =
                new TripGroupModel();

            $group =
                $model->getGroup(
                    $groupId
                );

            $items =
                $model->getGroupItems(
                    $groupId
                );

            $html =
                '<h1>GPS Portal Fahrtenbuch</h1>';

            $html .=
                '<h3>Sammelfahrt #'
                . $groupId
                . '</h3>';

            $html .=
                '<table border="1" cellpadding="5" width="100%">';

            $html .=
                '<tr>
                    <th>Kunde</th>
                    <th>Auftrag</th>
                    <th>Typ</th>
                    <th>KM</th>
                  </tr>';

            foreach ($items as $item)
            {
                $html .=
                    '<tr>
                        <td>'
                        . htmlspecialchars(
                            $item['customer'] ?? ''
                        )
                        . '</td>
                        <td>'
                        . htmlspecialchars(
                            $item['order_number'] ?? ''
                        )
                        . '</td>
                        <td>'
                        . htmlspecialchars(
                            $item['trip_type'] ?? ''
                        )
                        . '</td>
                        <td>'
                        . round(
                            ($item['distance'] ?? 0)
                            / 1000,
                            2
                        )
                        . '</td>
                    </tr>';
            }

            $html .= '</table>';

$pdf =
    new \Mpdf\Mpdf([
        'tempDir' =>
            JPATH_ROOT . '/tmp/mpdf'
    ]);
            $pdf->WriteHTML(
                $html
            );

            $pdf->Output(
                'Sammelfahrt_' .
                $groupId .
                '.pdf',
                'D'
            );

            exit;
        }

        /*
         * Systemeinstellungen speichern
         */
	 if (
            $this->input->getCmd('view') === 'system'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
        )
        {
            $model = new SystemModel();

            $model->saveSetting(
                'live_refresh',
                $app->input->getInt('live_refresh', 0)
            );

            $model->saveSetting(
                'refresh_interval',
                $app->input->getInt('refresh_interval', 5)
            );

            $model->saveSetting(
                'vehicle_icons',
                $app->input->getInt('vehicle_icons', 0)
            );

            $model->saveSetting(
                'history',
                $app->input->getInt('history', 0)
            );

            $model->saveSetting(
                'geofencing',
                $app->input->getInt('geofencing', 0)
            );

            $model->saveSetting(
                'alarms',
                $app->input->getInt('alarms', 0)
            );

            $app->enqueueMessage(
                'Systemeinstellungen gespeichert',
                'success'
            );
        }

        /*
         * ToDo Status umschalten
         */
        if ($task === 'todo.toggle')
        {
            $id = $app->input->getInt('id');

            $model = new TodoModel();

            $model->toggle($id);

            $app->redirect(
                'index.php?option=com_gpsportal&view=todo'
            );

            return;
        }

        /*
         * Einstellungen speichern
         */
        if (
            $this->input->getCmd('view') === 'settings'
            && $_SERVER['REQUEST_METHOD'] === 'POST'
        )
        {
            $url =
                $app->input->getString(
                    'traccar_url'
                );

            $user =
                $app->input->getString(
                    'traccar_user'
                );

            $password =
                $app->input->getString(
                    'traccar_password'
                );

            $model =
                new SettingsModel();

            $model->saveSetting(
                'traccar_url',
                $url
            );

            $model->saveSetting(
                'traccar_user',
                $user
            );

            $model->saveSetting(
                'traccar_password',
                $password
            );

            if (
                $app->input->getCmd('action')
                === 'test'
            )
            {
                $traccar =
                    new TraccarModel();

                if (
                    $traccar->testConnection(
                        $url,
                        $user,
                        $password
                    )
                )
                {
                    $app->enqueueMessage(
                        'Traccar Verbindung erfolgreich',
                        'success'
                    );
                }
                else
                {
                    $app->enqueueMessage(
                        'Traccar Verbindung fehlgeschlagen',
                        'error'
                    );
                }

                $this->input->set(
                    'view',
                    'settings'
                );

                return parent::display();
            }

            $app->enqueueMessage(
                'Einstellungen gespeichert',
                'success'
            );
        }

        $view =
            $this->input->getCmd(
                'view',
                'dashboard'
            );

        $this->input->set(
            'view',
            $view
        );

        return parent::display();
    }
}
