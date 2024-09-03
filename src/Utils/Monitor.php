<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Utils;

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Stopwatch\Stopwatch;

final class Monitor
{
    public static ?OutputInterface $output = null;

    private static ?ProgressBar $progressBar = null;

    private static bool $enableMonitoring = false;

    private static array $stats = [];

    public static function createProgressBar(int $nbSteps): void
    {
        if (null !== self::$output) {
            self::$progressBar = new ProgressBar(self::$output, $nbSteps);
        }
    }

    public static function advanceProgressBar(int $step = 1): void
    {
        if (null !== self::$progressBar) {
            self::$progressBar->advance($step);
        }
    }

    public static function finishProgressBar(): void
    {
        if (null !== self::$progressBar) {
            self::$progressBar->finish();
        }
    }

    public static function enableMonitoring(bool $enable): void
    {
        self::$enableMonitoring = $enable;
    }

    public static function writeln(?string $message = null): void
    {
        if (null !== self::$output) {
            self::$output->writeln($message);
        }
    }

    public static function displayTable(array $datas): void
    {
        if (!self::$enableMonitoring) {
            return;
        }

        $datas = $datas[0] ?? [$datas];
        $headers = array_keys($datas[0]);

        (new Table(self::$output))
            ->setHeaders($headers)
            ->setRows($datas)
            ->render();
    }

    public static function displayStats(): void
    {
        if (!self::$enableMonitoring) {
            return;
        }

        $table = new Table(self::$output);
        $table
            ->setHeaders([
                [new TableCell('Statistiques détaillées', ['colspan' => 5])],
                ['Nom', 'Nombre', 'Tps Total', 'Tps Moyen', 'Memory Moyen'],
            ]);

        $stats = self::getStats();
        ksort($stats, \SORT_STRING);
        foreach ($stats as $key => $stat) {
            $table->addRow([$key, $stat['nb'], $stat['total'], $stat['avg'], $stat['avg_memory']]);
        }

        $table->render();
        self::$stats = [];
    }

    public static function getStats(): array
    {
        $stats = [];
        foreach (self::$stats as $key => $stat) {
            $stats[$key] = self::getTime($stat);
        }

        return $stats;
    }

    /**
     * @return (int|mixed|null)[]
     *
     * @psalm-return array{nb: int, total: 0|mixed, avg: 0|mixed|null, min?: mixed|null, max?: mixed|null, avg_memory: 0|mixed|null}
     */
    private static function getTime(array $stat): array
    {
        $nbItems = is_countable($stat['time']) ? \count($stat['time']) : 0;

        if (0 === $nbItems) {
            return [
                'nb' => 0,
                'avg' => 0,
                'avg_memory' => 0,
                'total' => 0,
            ];
        }

        $somme = array_sum($stat['time']);
        $sommeMemory = array_sum($stat['memory']);

        return [
            'nb' => $nbItems,
            'total' => self::formatDuration($somme),
            'avg' => $nbItems > 1 ? self::formatDuration($somme / $nbItems) : null,
            'min' => $nbItems > 1 ? self::formatDuration(min($stat['time'])) : null,
            'max' => $nbItems > 1 ? self::formatDuration(max($stat['time'])) : null,
            'avg_memory' => $nbItems > 1 ? self::formatMemory($sommeMemory / $nbItems) : null,
        ];
    }

    public static function formatDuration(int|float $microseconds): string
    {
        return \sprintf('%01.2f ms', $microseconds);
    }

    public static function formatMemory(float|int $bytes): string
    {
        return round($bytes / 1_000 / 1_000, 2) . ' MB';
    }

    public static function bench(string $message, callable $function): mixed
    {
        $stopwatch = self::start($message);
        $retour = \call_user_func($function);
        self::stop($message, $stopwatch);

        return $retour;
    }

    public static function start(?string $message): ?Stopwatch
    {
        $stopwatch = null;
        if (self::$enableMonitoring) {
            if (!isset(self::$stats[$message])) {
                self::$stats[$message] = [
                    'time' => [],
                    'memory' => [],
                ];
            }

            $stopwatch = new Stopwatch();
            $stopwatch->start($message);
        }

        return $stopwatch;
    }

    public static function stop(?string $message, ?Stopwatch $stopwatch): void
    {
        if (self::$enableMonitoring) {
            $event = $stopwatch->stop($message);

            self::$stats[$message]['time'][] = $event->getDuration();
            self::$stats[$message]['memory'][] = $event->getMemory();
        }
    }
}
