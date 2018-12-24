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

use Monolog\Test\TestCase;
use Monolog\Logger;
use Monolog\Formatter\LineFormatter;

class InfluxDBHandlerTest extends TestCase
{
    /**
     * @expectedException InvalidArgumentException
     */
    public function testConstructorShouldThrowExceptionForInvalidRedis()
    {
        new InfluxDBHandler(new \stdClass(), 'measurement');
    }

    public function testConstructorShouldCheckInfluxDB()
    {
        $influxdb = $this->createMock('InfluxDB\Database');
        $this->assertInstanceof('Monolog\Handler\InfluxDBHandler', new InfluxDBHandler($influxdb, 'stuff'));
    }

    public function testInfluxDBHandle()
    {
        $influxdb = $this->createPartialMock('InfluxDB\Database', ['writePoints']);

        // InfluxDB\Client uses writePoints
        $influxdb->expects($this->once())
            ->method('writePoints')
            ->with($this->containsOnlyInstancesOf('\InfluxDB\Point'), 'u', null);

        $record = $this->getRecord(Logger::WARNING, 'test', ['data' => new \stdClass, 'foo' => 34]);

        $handler = new InfluxDBHandler($influxdb, 'stuff');
        $handler->setFormatter(new LineFormatter("%message%"));
        $handler->handle($record);
    }

    public function testInfluxDBHandleWithData()
    {
        $influxdb = $this->createPartialMock('InfluxDB\Database', ['writePoints']);

        $record = $this->getRecord(Logger::WARNING, 'test', ['data' => new \stdClass, 'foo' => 34]);

        // InfluxDB\Client uses writePoints
        $influxdb->expects($this->once())
                 ->method('writePoints')
                 ->with($this->callback(function ($points) use ($record) {
                     $point = $points[0];
                     $this->assertSame('stuff', $point->getMeasurement());
                     $this->assertSame(['hello' => 'world'], $point->getTags());
                     $this->assertSame($record['datetime']->getTimestamp(), $point->getTimestamp());
                     $this->assertSame([
                         'message' => '"test"',
                         'level'   => '300i',
                         'channel' => '"test"',
                     ], $point->getFields());
                     return true;
                 }), 'u', null);

        $handler = new InfluxDBHandler($influxdb, 'stuff', Logger::DEBUG, true, ['hello' => 'world'], null);
        $handler->setFormatter(new LineFormatter("%message%"));
        $handler->handle($record);
    }
}
