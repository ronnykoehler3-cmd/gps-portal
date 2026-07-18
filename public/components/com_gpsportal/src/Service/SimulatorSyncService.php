<?php

declare(strict_types=1);

namespace TKKundendienst\Component\Gpsportal\Site\Service;

defined('_JEXEC') or die;

final class SimulatorSyncService
{
    private const QUEUE_DIRECTORY = '/var/lib/tk-gps-simulator/queue';

    public function enqueueUpsert(array $vehicle): bool
    {
        if (!is_dir(self::QUEUE_DIRECTORY) || !is_writable(self::QUEUE_DIRECTORY)) {
            return false;
        }

        $payload = [
            'schema_version' => 1,
            'operation' => 'upsert',
            'created_at' => gmdate('c'),
            'vehicle' => $vehicle,
        ];

        $name = sprintf(
            '%s-%s-%s.json',
            gmdate('Ymd-His'),
            preg_replace('/[^A-Za-z0-9_.-]/', '-', (string) ($vehicle['unique_id'] ?? 'vehicle')),
            bin2hex(random_bytes(6))
        );
        $temporary = self::QUEUE_DIRECTORY . '/.' . $name . '.tmp';
        $target = self::QUEUE_DIRECTORY . '/' . $name;
        $json = json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );

        if (file_put_contents($temporary, $json . PHP_EOL, LOCK_EX) === false) {
            throw new \RuntimeException('Der Simulatorauftrag konnte nicht geschrieben werden.');
        }

        if (!rename($temporary, $target)) {
            @unlink($temporary);
            throw new \RuntimeException('Der Simulatorauftrag konnte nicht freigegeben werden.');
        }

        return true;
    }

    public function enqueueDelete(string $uniqueId): bool
    {
        if (!is_dir(self::QUEUE_DIRECTORY) || !is_writable(self::QUEUE_DIRECTORY)) {
            return false;
        }

        $payload = [
            'schema_version' => 1,
            'operation' => 'delete',
            'created_at' => gmdate('c'),
            'vehicle' => ['unique_id' => $uniqueId],
        ];
        $name = sprintf(
            '%s-%s-%s.json',
            gmdate('Ymd-His'),
            preg_replace('/[^A-Za-z0-9_.-]/', '-', $uniqueId),
            bin2hex(random_bytes(6))
        );
        $temporary = self::QUEUE_DIRECTORY . '/.' . $name . '.tmp';
        $target = self::QUEUE_DIRECTORY . '/' . $name;
        $json = json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );

        if (file_put_contents($temporary, $json . PHP_EOL, LOCK_EX) === false
            || !rename($temporary, $target)) {
            @unlink($temporary);
            throw new \RuntimeException('Der Löschauftrag für den Simulator konnte nicht gespeichert werden.');
        }

        return true;
    }
}
