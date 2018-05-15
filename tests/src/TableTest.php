<?php

namespace FitUnit\Infrastructure\JsonDb;

use FitdevPro\JsonDb\Database;
use FitdevPro\JsonDb\Table;
use PHPUnit\Framework\TestCase;

final class TableTest extends TestCase
{
    public function testCreateObject()
    {
        $db = $this->prophesize(Database::class);
        $db->createTableIfNotExists('Person/')->shouldBeCalled()->willReturn(true);

        new Table($db->reveal(), 'Person');
    }

    public function testFindById()
    {
        $db = $this->getDbMock();

        $table = new Table($db->reveal(), 'Person');
        $out = $table->find(3);

        $this->assertEquals([3=>['id' => 3, 'zupa' => 'rosol']], $out);
    }

    private function getDbMock()
    {
        $db = $this->prophesize(Database::class);
        $db->createTableIfNotExists('Person/')->shouldBeCalled()->willReturn(true);
        $db->readAll('Person/')->willReturn([1, 2, 3, 4]);
        $db->read('Person/1')->willReturn(['id' => 1, 'zupa' => 'pomidorowa']);
        $db->read('Person/2')->willReturn(['id' => 2, 'zupa' => 'ogorkowa']);
        $db->read('Person/3')->willReturn(['id' => 3, 'zupa' => 'rosol']);
        $db->read('Person/4')->willReturn(['id' => 4, 'zupa' => 'pomidorowa']);

        return $db;
    }

    public function testFindByData()
    {
        $db = $this->getDbMock();

        $table = new Table($db->reveal(), 'Person');
        $out = $table->find(['zupa' => 'pomidorowa']);

        $this->assertArrayHasKey(1, $out);
        $this->assertArrayHasKey(4, $out);
        $this->assertEquals(['id' => 1, 'zupa' => 'pomidorowa'], $out[1]);
        $this->assertEquals(['id' => 4, 'zupa' => 'pomidorowa'], $out[4]);
    }

    public function testFindByDataCallable()
    {
        $db = $this->getDbMock();

        $table = new Table($db->reveal(), 'Person');
        $out1 = $table->find(function ($row) {
            return $row['id'] > 2;
        }, true);

        $outAll = $table->find(function ($row) {
            return $row['id'] > 2;
        });

        $this->assertEquals(['id' => 3, 'zupa' => 'rosol'], $out1);

        $this->assertArrayHasKey(3, $outAll);
        $this->assertArrayHasKey(4, $outAll);
        $this->assertEquals(['id' => 3, 'zupa' => 'rosol'], $outAll[3]);
        $this->assertEquals(['id' => 4, 'zupa' => 'pomidorowa'], $outAll[4]);
    }

    public function testFindFirstByData()
    {
        $db = $this->getDbMock();

        $table = new Table($db->reveal(), 'Person');
        $out = $table->findFirst(['zupa' => 'pomidorowa']);

        $this->assertEquals(['id' => 1, 'zupa' => 'pomidorowa'], $out);
    }

    public function testFindByCallable()
    {
        $db = $this->getDbMock();

        $table = new Table($db->reveal(), 'Person');

        $out = $table->find(function ($row) {
            return $row['zupa'] === 'pomidorowa';
        });

        $this->assertArrayHasKey(1, $out);
        $this->assertArrayHasKey(4, $out);
        $this->assertEquals(['id' => 1, 'zupa' => 'pomidorowa'], $out[1]);
        $this->assertEquals(['id' => 4, 'zupa' => 'pomidorowa'], $out[4]);
    }

    /**
     * @expectedException  \FitdevPro\JsonDb\Exceptions\NotFoundException
     */
    public function testNotFound()
    {
        $db = $this->getDbMock();

        $table = new Table($db->reveal(), 'Person');
        $out = $table->find(['zupa' => 'krupnik']);

        $this->assertEquals([], $out);

        $out = $table->findFirst(['zupa' => 'krupnik']);
    }

    public function testSave()
    {
        $db = $this->getDbMock();
        $db->save('Person/3', json_encode(['id' => 3, 'zupa' => 'pomidorowa']))->shouldBeCalled()->willReturn(true);

        $table = new Table($db->reveal(), 'Person');

        $out = $table->save(['id' => 3, 'zupa' => 'pomidorowa',]);

        $this->assertTrue($out);
    }

    public function testCreateNew()
    {
        $db = $this->getDbMock();
        $db->getNextId('Person/')->shouldBeCalled()->willReturn(5);
        $db->save('Person/5', json_encode(['zupa' => 'rosol', 'id' => 5]))->shouldBeCalled()->willReturn(true);

        $table = new Table($db->reveal(), 'Person');

        $out = $table->save(['zupa' => 'rosol',]);

        $this->assertTrue($out);
    }

    public function testDeleteById()
    {
        $db = $this->getDbMock();
        $db->delete('Person/4')->shouldBeCalled()->willReturn(false);

        $table = new Table($db->reveal(), 'Person');
        $out = $table->delete(4);

        $this->assertEquals([4 => false], $out);
    }

    public function testDelete()
    {
        $db = $this->getDbMock();
        $db->delete('Person/1')->shouldBeCalled()->willReturn(true);
        $db->delete('Person/4')->shouldBeCalled()->willReturn(false);

        $table = new Table($db->reveal(), 'Person');
        $out = $table->delete(['zupa' => 'pomidorowa']);

        $this->assertEquals([1 => true, 4 => false], $out);
    }

    public function testRepair()
    {
        $db = $this->getDbMock();

        $table = new Table($db->reveal(), 'Person');
        $data = [];
        $table->repair(function ($table, $row) use (&$data) {
            $this->assertInstanceOf(Table::class, $table);
            $data[$row['id']] = $row['zupa'];
        });

        $this->assertEquals([1 => 'pomidorowa', 2 => 'ogorkowa', 3 => 'rosol', 4 => 'pomidorowa'], $data);
    }
}
