<?php

namespace TKKundendienst\Component\Gpsportal\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

class TraccarModel
{
    private function request($endpoint)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select(['setting_key', 'setting_value'])
            ->from('#__gpsportal_settings');

        $db->setQuery($query);

        $rows = $db->loadAssocList(
            'setting_key',
            'setting_value'
        );

        $ch = curl_init();

        curl_setopt_array($ch, [

            CURLOPT_URL =>
                rtrim(
                    $rows['traccar_url'],
                    '/'
                ) . $endpoint,

            CURLOPT_RETURNTRANSFER => true,

            CURLOPT_USERPWD =>
                $rows['traccar_user']
                . ':'
                . $rows['traccar_password'],

            CURLOPT_SSL_VERIFYPEER => false,

            CURLOPT_TIMEOUT => 15
        ]);

        $response = curl_exec($ch);

        curl_close($ch);

        return json_decode(
            $response,
            true
        );
    }

    public function getDevices()
    {
        return $this->request(
            '/api/devices'
        );
    }

    public function getPositions()
    {
        return $this->request(
            '/api/positions'
        );
    }

    public function getEvents(
        $deviceId,
        $from,
        $to
    )
    {
        return $this->request(
            '/api/reports/events'
            . '?deviceId='
            . (int)$deviceId
            . '&from='
            . urlencode($from)
            . '&to='
            . urlencode($to)
        );
    }

    public function getTrips(
        $deviceId,
        $from,
        $to
    )
    {
        $db = Factory::getContainer()
            ->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select([
                'setting_key',
                'setting_value'
            ])
            ->from('#__gpsportal_settings');

        $db->setQuery($query);

        $rows =
            $db->loadAssocList(
                'setting_key',
                'setting_value'
            );

        $url =
            rtrim(
                $rows['traccar_url'],
                '/'
            )
            . '/api/reports/trips'
            . '?deviceId=' . $deviceId
            . '&from=' . urlencode($from)
            . '&to=' . urlencode($to);

        $ch = curl_init();

        curl_setopt_array($ch, [

            CURLOPT_URL => $url,

            CURLOPT_RETURNTRANSFER => true,

            CURLOPT_USERPWD =>
                $rows['traccar_user']
                . ':'
                . $rows['traccar_password'],

            CURLOPT_HTTPHEADER => [
                'Accept: application/json'
            ],

            CURLOPT_SSL_VERIFYPEER => false,

            CURLOPT_TIMEOUT => 30
        ]);

        $response =
            curl_exec($ch);

        curl_close($ch);

        $trips =
            json_decode(
                $response,
                true
            );

        if (is_array($trips))
        {
            foreach ($trips as &$trip)
            {
                if (
                    empty($trip['endAddress'])
                    && !empty($trip['endPositionId'])
                )
                {
                    $trip['endAddress'] =
                        $this->getCachedAddress(
                            $trip['endPositionId'],
                            $trip['endLat'] ?? null,
                            $trip['endLon'] ?? null
                        );
                }

                if (
                    empty($trip['startAddress'])
                    && !empty($trip['startPositionId'])
                )
                {
                    $trip['startAddress'] =
                        $this->getCachedAddress(
                            $trip['startPositionId'],
                            $trip['startLat'] ?? null,
                            $trip['startLon'] ?? null
                        );
                }
            }
        }

        return $trips;
    }

    private function getCachedAddress(
        $positionId,
        $lat,
        $lon
    )
    {
        if (
            empty($positionId)
            || empty($lat)
            || empty($lon)
        )
        {
            return null;
        }

        $db = Factory::getContainer()
            ->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select('end_address')
            ->from('eusdi_gpsportal_trip_cache')
            ->where(
                'end_position_id='
                . (int)$positionId
            );

        $db->setQuery($query);

        $cached =
            $db->loadResult();

        if (!empty($cached))
        {
            return $cached;
        }

        $url =
            'https://nominatim.openstreetmap.org/reverse'
            . '?format=jsonv2'
            . '&lat=' . urlencode($lat)
            . '&lon=' . urlencode($lon);

        $ch = curl_init();

        curl_setopt_array($ch, [

            CURLOPT_URL => $url,

            CURLOPT_RETURNTRANSFER => true,

            CURLOPT_USERAGENT => 'GPSPortal/1.0',

            CURLOPT_TIMEOUT => 15
        ]);

        $response =
            curl_exec($ch);

        curl_close($ch);

        $json =
            json_decode(
                $response,
                true
            );

        $address =
            $json['display_name']
            ?? null;

        if (!empty($address))
        {
            try
            {
                $query = $db->getQuery(true)
                    ->insert(
                        'eusdi_gpsportal_trip_cache'
                    )
                    ->columns([
                        'end_position_id',
                        'end_address'
                    ])
                    ->values(
                        (int)$positionId
                        . ','
                        . $db->quote($address)
                    );

                $db->setQuery($query);
                $db->execute();
            }
            catch (\Exception $e)
            {
            }
        }

        return $address;
    }

    public function testConnection()
    {
        return is_array(
            $this->getDevices()
        );
    }
}
