<?php declare(strict_types=1);

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Handler;

use Monolog\Formatter\LineFormatter;
use Monolog\Formatter\FormatterInterface;
use Monolog\Logger;

/**
 * Logs to a InfluxDB server.
 *
 * usage example:
 *
 *   $log = new Logger('application');
 *   $influxdb = new InfluxDBHandler(new InfluxDB\Client('localhost'), 'php_logs', '');
 *   $log->pushHandler($influxdb);
 *
 * @author Sean Molenaar <sean@m2mobi.com>
 */
class InfluxDBHandler extends AbstractProcessingHandler
{
    private $database;
    private $measurement;
    private $tags;
    private $policy;

    /**
     * @param \InfluxDB\Database                 $database         The database to push records to
     * @param string                             $measurement      The measurement to push records to
     * @param string|integer                     $level            The minimum logging level at which this handler will be triggered
     * @param boolean                            $bubble           Whether the messages that are handled can bubble up the stack or not
     * @param array                              $tags             Tags to add to measurements
     * @param \InfluxDB\Database\RetentionPolicy $retention_policy Retention policy to apply
     */
    public function __construct($database,
                                string $measurement,
                                $level = Logger::DEBUG,
                                bool $bubble = true,
                                array $tags = [],
                                \InfluxDB\Database\RetentionPolicy $retention_policy = null)
    {
        if (!($database instanceof \InfluxDB\Database)) {
            throw new \InvalidArgumentException(' \InfluxDB\Database instance required');
        }

        $this->database    = $database;
        $this->measurement = $measurement;
        $this->tags        = $tags;
        $this->policy      = $retention_policy;
        parent::__construct($level, $bubble);
    }

    /**
     * {@inheritDoc}
     */
    protected function write(array $record): void
    {
        $point = $this->getPoint();
        $point->setTimestamp($record['datetime']->getTimestamp());
        $point->setFields([
            'message' => $record['formatted'],
            'level'   => $record['level'],
            'channel' => $record['channel'],
        ]);
        $point->setTags(array_merge($record['extra'], $this->tags));
        $this->database->writePoints([$point], \InfluxDB\Database::PRECISION_MICROSECONDS, $this->policy);
    }

    /**
     * Get an influxDB measurement point.
     *
     * @return \InfluxDB\Point
     */
    protected function getPoint(): \InfluxDB\Point {
        return new \InfluxDB\Point($this->measurement);
    }

    /**
     * {@inheritDoc}
     */
    protected function getDefaultFormatter(): FormatterInterface
    {
        return new LineFormatter();
    }
}
