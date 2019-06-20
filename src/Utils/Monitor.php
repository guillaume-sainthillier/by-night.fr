<?php


namespace App\Utils;

use Exception;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Stopwatch\Stopwatch;

class Monitor
{
    /**
     * @var OutputInterface
     */
    public static $output;

    /**
     * @var ProgressBar
     */
    private static $progressBar;

    private static $enableMonitoring;

    private static $stats = [];

    public static function createProgressBar($nbSteps)
    {
        if (self::$output) {
            static::$progressBar = new ProgressBar(self::$output, $nbSteps);
        }
    }

    public static function advanceProgressBar($step = 1)
    {
        if (static::$progressBar) {
            static::$progressBar->advance($step);
        }
    }

    public static function finishProgressBar()
    {
        if (static::$progressBar) {
            static::$progressBar->finish();
        }
    }

    public static function getStats()
    {
        $stats = [];
        foreach (self::$stats as $key => $stat) {
            $stats[$key] = self::getTime($stat);
        }

        return $stats;
    }

    public static function enableMonitoring($enable)
    {
        self::$enableMonitoring = $enable;
    }

    public static function formatMemory($bytes)
    {
        return round($bytes / 1000 / 1000, 2) . ' MB';
    }

    public static function formatDuration($microseconds)
    {
        return sprintf('%01.2f ms', $microseconds);
    }

    private static function getTime($stat)
    {
        $nbItems = \count($stat['time']);

        if (0 === $nbItems) {
            return [
                'avg' => 0,
                'min' => 0,
                'max' => 0,
                'nb' => 0,
                'memory' => 0,
                'avg_memory' => 0,
                'min_memory' => 0,
                'max_memory' => 0,
                'total' => 0,
            ];
        }
        $somme = \array_sum($stat['time']);
        $sommeMemory = \array_sum($stat['memory']);

        return [
            'total' => self::formatDuration($somme),
            'avg' => $nbItems > 1 ? self::formatDuration($somme / $nbItems) : null,
            'min' => $nbItems > 1 ? self::formatDuration(\min($stat['time'])) : null,
            'max' => $nbItems > 1 ? self::formatDuration(\max($stat['time'])) : null,
            'nb' => $nbItems,
            'memory' => self::formatMemory($sommeMemory),
            'avg_memory' => $nbItems > 1 ? self::formatMemory($sommeMemory / $nbItems) : null,
            'min_memory' => $nbItems > 1 ? self::formatMemory(\min($stat['memory'])) : null,
            'max_memory' => $nbItems > 1 ? self::formatMemory(\max($stat['memory'])) : null,
        ];
    }

    public static function write($message = null)
    {
        if (self::$output) {
            self::$output->write($message);
        }
    }

    public static function writeException(Exception $e)
    {
        self::writeln(\sprintf(
            '<error>%s at %s(%d)</error> <info>%s</info>',
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        ));
    }

    public static function writeln($message = null)
    {
        if (self::$output) {
            self::$output->writeln($message);
        }
    }

    public static function displayTable(array $datas)
    {
        $datas = isset($datas[0]) ? $datas[0] : [$datas];
        $headers = \array_keys($datas[0]);

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
                [new TableCell('Statistiques détaillées', ['colspan' => 10])],
                ['Nom', 'Nombre', 'Tps Total', 'Tps Moyen', 'Tps Min', 'Tps Max', 'Memory Total', 'Memory Moyen', 'Memory Min', 'Memory Max'],
            ]);

        $stats = self::getStats();
        \ksort($stats, \SORT_STRING);
        foreach ($stats as $key => $stat) {
            $table->addRow([$key, $stat['nb'], $stat['total'], $stat['avg'], $stat['min'], $stat['max'], $stat['memory'], $stat['avg_memory'], $stat['min_memory'], $stat['max_memory']]);
        }
        $table->render();
        self::$stats = [];
    }

    public static function bench($message, callable $function)
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

        $retour = \call_user_func($function);

        if (self::$enableMonitoring) {
            $event = $stopwatch->stop($message);

            self::$stats[$message]['time'][] = $event->getDuration();
            self::$stats[$message]['memory'][] = $event->getMemory();
        }

        return $retour;
    }
}
