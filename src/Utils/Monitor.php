<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
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

class Monitor
{
    public static ?OutputInterface $output = null;

    private static ?ProgressBar $progressBar = null;

    private static bool $enableMonitoring = false;

    private static array $stats = [];

    public static function createProgressBar($nbSteps)
    {
        if (null !== self::$output) {
            static::$progressBar = new ProgressBar(self::$output, $nbSteps);
        }
    }

    public static function advanceProgressBar($step = 1)
    {
        if (null !== static::$progressBar) {
            static::$progressBar->advance($step);
        }
    }

    public static function finishProgressBar()
    {
        if (null !== static::$progressBar) {
            static::$progressBar->finish();
        }
    }

    public static function enableMonitoring($enable)
    {
        self::$enableMonitoring = $enable;
    }

    public static function writeln($message = null)
    {
        if (null !== self::$output) {
            self::$output->writeln($message);
        }
    }

    public static function displayTable(array $datas)
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

    public static function displayStats()
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

    public static function getStats()
    {
        $stats = [];
        foreach (self::$stats as $key => $stat) {
            $stats[$key] = self::getTime($stat);
        }

        return $stats;
    }

    private static function getTime($stat)
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

    public static function formatDuration($microseconds)
    {
        return sprintf('%01.2f ms', $microseconds);
    }

    public static function formatMemory($bytes)
    {
        return round($bytes / 1_000 / 1_000, 2) . ' MB';
    }

    public static function bench($message, callable $function)
    {
        $stopwatch = self::start($message);
        $retour = \call_user_func($function);
        self::stop($message, $stopwatch);

        return $retour;
    }

    public static function start($message): ?Stopwatch
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

    public static function stop($message, ?Stopwatch $stopwatch)
    {
        if (self::$enableMonitoring) {
            $event = $stopwatch->stop($message);

            self::$stats[$message]['time'][] = $event->getDuration();
            self::$stats[$message]['memory'][] = $event->getMemory();
        }
    }
}
