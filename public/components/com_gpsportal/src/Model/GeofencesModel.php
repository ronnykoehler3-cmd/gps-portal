<?php

declare(strict_types=1);

namespace TKKundendienst\Component\Gpsportal\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use TKKundendienst\Component\Gpsportal\Site\Service\GeocodingService;

final class GeofencesModel extends BaseDatabaseModel
{
    private const COLORS = ['green', 'yellow', 'red'];
    private const COUNTRIES = [
        'de' => 'Deutschland', 'pl' => 'Polen', 'cz' => 'Tschechien',
        'at' => 'Österreich', 'ch' => 'Schweiz', 'fr' => 'Frankreich',
        'be' => 'Belgien', 'nl' => 'Niederlande', 'dk' => 'Dänemark',
        'lu' => 'Luxemburg', 'it' => 'Italien', 'es' => 'Spanien',
        'pt' => 'Portugal', 'se' => 'Schweden', 'no' => 'Norwegen',
        'fi' => 'Finnland', 'sk' => 'Slowakei', 'hu' => 'Ungarn',
        'si' => 'Slowenien', 'hr' => 'Kroatien', 'ro' => 'Rumänien',
        'bg' => 'Bulgarien', 'lt' => 'Litauen', 'lv' => 'Lettland',
        'ee' => 'Estland', 'gb' => 'Großbritannien', 'ie' => 'Irland',
    ];

    public function getGeofences(): array
    {
        $user = Factory::getApplication()->getIdentity();
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__gpsportal_geofences'))
            ->where($db->quoteName('user_id') . ' = ' . (int) $user->id)
            ->order($db->quoteName('name') . ' ASC');
        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    public function getGeofence(int $id): ?object
    {
        if ($id <= 0) {
            return null;
        }

        $user = Factory::getApplication()->getIdentity();
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__gpsportal_geofences'))
            ->where($db->quoteName('id') . ' = ' . $id)
            ->where($db->quoteName('user_id') . ' = ' . (int) $user->id);
        $db->setQuery($query);

        return $db->loadObject() ?: null;
    }

    public function saveGeofence(array $data): void
    {
        $user = Factory::getApplication()->getIdentity();
        $id = max(0, (int) ($data['id'] ?? 0));
        $existing = $id > 0 ? $this->getGeofence($id) : null;
        $name = trim((string) ($data['name'] ?? ''));
        $zoneType = (string) ($data['zone_type'] ?? 'address');
        $color = (string) ($data['status_color'] ?? 'green');

        if ($zoneType === 'country' && $name === '') {
            $selectedCountryCode = strtolower(
                trim((string) ($data['country_code'] ?? ''))
            );
            $name = self::COUNTRIES[$selectedCountryCode] ?? '';
        }

        if ($name === '') {
            throw new \RuntimeException(
                'Bitte einen Namen für die Geozone eingeben oder ein Land auswählen.'
            );
        }
        if (!in_array($zoneType, ['address', 'country'], true)) {
            throw new \RuntimeException('Der Geozonentyp ist ungültig.');
        }
        if (!in_array($color, self::COLORS, true)) {
            throw new \RuntimeException('Die ausgewählte Statusfarbe ist ungültig.');
        }
        if ($id > 0 && !$existing) {
            throw new \RuntimeException('Die Geozone wurde nicht gefunden oder gehört nicht zu diesem Benutzer.');
        }

        $geocoder = new GeocodingService();
        $geometryJson = null;
        $countryCode = null;
        $warningBuffer = 0;

        if ($zoneType === 'address') {
            $submittedAddress = trim((string) ($data['address'] ?? ''));

            if ($submittedAddress === '') {
                throw new \RuntimeException('Eine Adresse fehlt.');
            }

            if (
                $existing
                && (string) $existing->zone_type === 'address'
                && $submittedAddress === trim((string) $existing->address)
            ) {
                $address = (string) $existing->address;
                $latitude = (float) $existing->latitude;
                $longitude = (float) $existing->longitude;
            } else {
                $resolved = $geocoder->resolve($submittedAddress);
                $address = $resolved['address'];
                $latitude = (float) $resolved['latitude'];
                $longitude = (float) $resolved['longitude'];
            }

            $radius = max(10, min(100000, (int) ($data['radius'] ?? 100)));
        } else {
            $countryCode = strtolower(trim((string) ($data['country_code'] ?? '')));
            $countryName = self::COUNTRIES[$countryCode] ?? '';

            if ($countryName === '') {
                throw new \RuntimeException('Bitte ein gültiges Land auswählen.');
            }

            if (
                $existing
                && (string) $existing->zone_type === 'country'
                && $countryCode === strtolower((string) $existing->country_code)
                && trim((string) $existing->geometry_json) !== ''
            ) {
                $address = (string) $existing->address;
                $latitude = (float) $existing->latitude;
                $longitude = (float) $existing->longitude;
                $geometryJson = (string) $existing->geometry_json;
            } else {
                $resolved = $geocoder->resolveCountry($countryCode, $countryName);
                $address = $resolved['address'];
                $latitude = (float) $resolved['latitude'];
                $longitude = (float) $resolved['longitude'];
                $geometryJson = json_encode($resolved['geometry'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            }

            $radius = 0;
            $warningBuffer = max(0, min(100, (int) ($data['warning_buffer_km'] ?? 0)));
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $row = (object) [
            'user_id' => (int) $user->id,
            'name' => $name,
            'zone_type' => $zoneType,
            'address' => $address,
            'country_code' => $countryCode,
            'status_color' => $color,
            'warning_buffer_km' => $warningBuffer,
            'geometry_json' => $geometryJson,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'radius' => $radius,
            'modified' => date('Y-m-d H:i:s'),
        ];

        if ($id > 0) {
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__gpsportal_geofences'))
                ->set($db->quoteName('name') . ' = ' . $db->quote($name))
                ->set($db->quoteName('zone_type') . ' = ' . $db->quote($zoneType))
                ->set($db->quoteName('address') . ' = ' . $db->quote($address))
                ->set(
                    $db->quoteName('country_code') . ' = '
                    . ($countryCode === null ? 'NULL' : $db->quote($countryCode))
                )
                ->set($db->quoteName('status_color') . ' = ' . $db->quote($color))
                ->set($db->quoteName('warning_buffer_km') . ' = ' . $warningBuffer)
                ->set(
                    $db->quoteName('geometry_json') . ' = '
                    . ($geometryJson === null ? 'NULL' : $db->quote($geometryJson))
                )
                ->set($db->quoteName('latitude') . ' = ' . $db->quote((string) $latitude))
                ->set($db->quoteName('longitude') . ' = ' . $db->quote((string) $longitude))
                ->set($db->quoteName('radius') . ' = ' . $radius)
                ->set($db->quoteName('modified') . ' = ' . $db->quote(date('Y-m-d H:i:s')))
                ->where($db->quoteName('id') . ' = ' . $id)
                ->where($db->quoteName('user_id') . ' = ' . (int) $user->id);

            $db->setQuery($query)->execute();

            $saved = $this->getGeofence($id);

            if (
                !$saved
                || (string) $saved->name !== $name
                || (string) $saved->zone_type !== $zoneType
                || (string) $saved->status_color !== $color
                || (int) $saved->warning_buffer_km !== $warningBuffer
                || (int) $saved->radius !== $radius
            ) {
                throw new \RuntimeException(
                    'Die Änderungen konnten nicht vollständig in der Datenbank gespeichert werden.'
                );
            }

            return;
        }

        $row->created = date('Y-m-d H:i:s');
        $db->insertObject('#__gpsportal_geofences', $row);
    }

    public function deleteGeofence(int $id): void
    {
        $zone = $this->getGeofence($id);
        if (!$zone) {
            throw new \RuntimeException('Die Geozone wurde nicht gefunden.');
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        foreach (['#__gpsportal_geofence_events', '#__gpsportal_geofence_status'] as $table) {
            $query = $db->getQuery(true)
                ->delete($db->quoteName($table))
                ->where($db->quoteName('geofence_id') . ' = ' . $id);
            $db->setQuery($query)->execute();
        }
        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__gpsportal_geofences'))
            ->where($db->quoteName('id') . ' = ' . $id)
            ->where($db->quoteName('user_id') . ' = ' . (int) Factory::getApplication()->getIdentity()->id);
        $db->setQuery($query)->execute();
    }
}
