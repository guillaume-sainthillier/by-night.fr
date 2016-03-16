<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 02/03/2016
 * Time: 20:51
 */

namespace TBN\MajDataBundle\Utils;


use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Output\OutputInterface;

class Monitor
{
    /**
     * @var OutputInterface
     */
    public static $output;
    public static $log;
    private static $stats = [];

    public static function getStats()
    {
        $stats = [];
        foreach (self::$stats as $key => $stat) {
            $stats[$key] = self::getTime($stat);
        }

        return $stats;
    }

    private static function convertMemory($size)
    {
        if($size == 0) {
            return '0 b';
        }
        $unit=array('b','kb','mb','gb','tb','pb');
        return @round($size/pow(1024,($i=floor(log($size,1024)))),2).' '.$unit[$i];
    }

    private static function getTime($stat)
    {
        $nbItems = count($stat['time']);

        if($nbItems === 0) {
            return [
                'avg' => 0,
                'min' => 0,
                'max' => 0,
                'nb' => 0,
                'memory' => 0,
                'avg_memory' => 0,
                'min_memory' => 0,
                'max_memory' => 0,
                'total' => 0
            ];
        }
        $somme = array_sum($stat['time']);
        $sommeMemory = array_sum($stat['memory']);
        return [
            'avg' => sprintf('%01.2f ms', ($somme / $nbItems) * 1000),
            'min' => sprintf('%01.2f ms', min($stat['time']) * 1000),
            'max' => sprintf('%01.2f ms', max($stat['time']) * 1000),
            'nb' => $nbItems,
            'memory' => self::convertMemory($sommeMemory),
            'avg_memory' => self::convertMemory($sommeMemory / $nbItems),
            'min_memory' => self::convertMemory(min($stat['memory'])),
            'max_memory' => self::convertMemory(max($stat['memory'])),
            'total' => sprintf('%01.2f ms', $somme * 1000)
        ];
    }

    public static function write($message = null) {
        if(self::$output) {
            self::$output->write($message);
        }
    }

    public static function writeln($message = null) {
        if(self::$output) {
            self::$output->writeln($message);
        }
    }

    public static function displayStats()
    {
        $table = new Table(self::$output);
        $table
            ->setHeaders(array(
                array(new TableCell('Statistiques détaillées', array('colspan' => 6))),
                array('Nom', 'Nombre', 'Tps Total', 'Tps Moyen', 'Tps Min', 'Tps Max', 'Memory Total', 'Memory Moyen', 'Memory Min', 'Memory Max')
            ));

        $stats = self::getStats();
        ksort($stats, SORT_STRING);
        foreach ($stats as $key => $stat) {
            $table->addRow([$key, $stat['nb'], $stat['total'], $stat['avg'], $stat['min'], $stat['max'], $stat['memory'], $stat['avg_memory'], $stat['min_memory'], $stat['max_memory']]);
        }
        $table->render();
    }

    public static function bench($message, callable $function, $display = false)
    {
        if (!isset(self::$stats[$message])) {
            self::$stats[$message] = [
                'time' => [],
                'memory' => []
            ];
        }
        $start = microtime(true);
        $memoryStart = memory_get_usage();
        $retour = call_user_func($function);
        $memoryEnd = memory_get_usage();
        $end = microtime(true);
        $time = ($end - $start);
        if (($display === true || self::$log === true) && self::$output) {
            self::$output->writeln(sprintf('%s : <info>%01.2f ms</info>',
                $message,
                $time * 1000.0
            ));
        }

        self::$stats[$message]['time'][] = $time;
        self::$stats[$message]['memory'][] = $memoryEnd - $memoryStart;

        return $retour;
    }
}