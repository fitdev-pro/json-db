<?php

namespace FitUnit\Infrastructure\JsonDb;

use FitdevPro\JsonDb\Database;
use FitdevPro\JsonDb\IFileSystem;
use FitdevPro\JsonDb\Table;
use PHPUnit\Framework\TestCase;

final class DatabaseTest extends TestCase
{

    public function testGetTable()
    {
        $fileSystem = $this->prophesize(IFileSystem::class);
        $fileSystem->has('Person/')->shouldBeCalled()->willReturn(true);

        $database = new Database($fileSystem->reveal());

        $this->assertInstanceOf(Table::class, $database->getTable('Person'));
    }

    public function testCreateTable()
    {
        $fileSystem = $this->prophesize(IFileSystem::class);
        $fileSystem->has('Person/')->shouldBeCalled()->willReturn(false);
        $fileSystem->createDir('Person/')->shouldBeCalled()->willReturn(true);

        $database = new Database($fileSystem->reveal());

        $this->assertInstanceOf(Table::class, $database->getTable('Person'));
    }

    /**
     * @expectedException \FitdevPro\JsonDb\Exceptions\WriteException
     */
    public function testCreateTableError()
    {
        $fileSystem = $this->prophesize(IFileSystem::class);
        $fileSystem->has('Person/')->shouldBeCalled()->willReturn(false);
        $fileSystem->createDir('Person/')->shouldBeCalled()->willReturn(false);

        $database = new Database($fileSystem->reveal());

        $database->getTable('Person');
    }

    public function testRead()
    {
        $fileSystem = $this->prophesize(IFileSystem::class);
        $fileSystem->has('Person/1')->shouldBeCalled()->willReturn(true);
        $fileSystem->read('Person/1')->shouldBeCalled()->willReturn(json_encode(['FooBar' => 1]));

        $database = new Database($fileSystem->reveal());

        $this->assertEquals(['FooBar' => 1], $database->read('Person/1'));
    }

    /**
     * @expectedException \FitdevPro\JsonDb\Exceptions\NotFoundException
     */
    public function testReadNotFound()
    {
        $fileSystem = $this->prophesize(IFileSystem::class);
        $fileSystem->has('Person/1')->shouldBeCalled()->willReturn(false);

        $database = new Database($fileSystem->reveal());

        $database->read('Person/1');
    }

    public function testReadAll()
    {
        $fileSystem = $this->prophesize(IFileSystem::class);
        $fileSystem->isDir('Person')->shouldBeCalled()->willReturn(true);
        $fileSystem->dirContent('Person')->shouldBeCalled()->willReturn([
            ['type' => 'dir', 'basename' => 'test1'],
            ['type' => 'file', 'basename' => '.auto'],
            ['type' => 'file', 'basename' => '1'],
            ['type' => 'file', 'basename' => '2'],
            ['type' => 'file', 'basename' => '3'],
        ]);

        $database = new Database($fileSystem->reveal());

        $this->assertEquals([1, 2, 3], $database->readAll('Person'));
    }

    /**
     * @expectedException \FitdevPro\JsonDb\Exceptions\NotFoundException
     */
    public function testReadAllNotFound()
    {
        $fileSystem = $this->prophesize(IFileSystem::class);
        $fileSystem->isDir('Person')->shouldBeCalled()->willReturn(false);

        $database = new Database($fileSystem->reveal());
        $database->readAll('Person');
    }

    public function testGetNextId()
    {
        $fileSystem = $this->prophesize(IFileSystem::class);
        $fileSystem->has('Person/.auto')->shouldBeCalled()->willReturn(true);
        $fileSystem->read('Person/.auto')->shouldBeCalled()->willReturn(2);
        $fileSystem->put('Person/.auto', 3)->shouldBeCalled()->willReturn(true);

        $database = new Database($fileSystem->reveal());
        $this->assertEquals(3, $database->getNextId('Person'));
    }

    public function testGetNewId()
    {
        $fileSystem = $this->prophesize(IFileSystem::class);
        $fileSystem->has('Person/.auto')->shouldBeCalled()->willReturn(false);
        $fileSystem->put('Person/.auto', 1)->shouldBeCalled()->willReturn(true);

        $database = new Database($fileSystem->reveal());
        $this->assertEquals(1, $database->getNextId('Person'));
    }

    /**
     * @expectedException \FitdevPro\JsonDb\Exceptions\WriteException
     */
    public function testGetIdErrorSave()
    {
        $fileSystem = $this->prophesize(IFileSystem::class);
        $fileSystem->has('Person/.auto')->shouldBeCalled()->willReturn(false);
        $fileSystem->put('Person/.auto', 1)->shouldBeCalled()->willReturn(false);

        $database = new Database($fileSystem->reveal());
        $this->assertEquals(1, $database->getNextId('Person'));
    }

    public function testSave()
    {
        $fileSystem = $this->prophesize(IFileSystem::class);
        $fileSystem->put('Person/2', json_encode(['FooBar' => 2]))->shouldBeCalled()->willReturn(true);

        $database = new Database($fileSystem->reveal());

        $this->assertTrue($database->save('Person/2', ['FooBar' => 2]));
    }

    /**
     * @expectedException \FitdevPro\JsonDb\Exceptions\WriteException
     */
    public function testSaveError()
    {
        $fileSystem = $this->prophesize(IFileSystem::class);
        $fileSystem->put('Person/2', json_encode(['FooBar' => 2]))->shouldBeCalled()->willReturn(false);

        $database = new Database($fileSystem->reveal());

        $database->save('Person/2', ['FooBar' => 2]);
    }

    public function testDelete()
    {
        $fileSystem = $this->prophesize(IFileSystem::class);
        $fileSystem->delete('Person/1')->shouldBeCalled()->willReturn(true);

        $database = new Database($fileSystem->reveal());
        $database->delete('Person/1');
    }

    /**
     * @expectedException \FitdevPro\JsonDb\Exceptions\WriteException
     */
    public function testDeleteError()
    {
        $fileSystem = $this->prophesize(IFileSystem::class);
        $fileSystem->delete('Person/1')->shouldBeCalled()->willReturn(false);

        $database = new Database($fileSystem->reveal());
        $database->delete('Person/1');
    }

    public function testTransactionRead()
    {
        $fileSystem = $this->prophesize(IFileSystem::class);

        $database = new Database($fileSystem->reveal());
        $database->begin();
        $database->save('Person/1', ['FooBar' => 2]);

        $this->assertEquals(['FooBar' => 2], $database->read('Person/1'));
    }

    public function testTransactionRolback()
    {
        $fileSystem = $this->prophesize(IFileSystem::class);
        $fileSystem->has('Person/1')->shouldBeCalled()->willReturn(true);
        $fileSystem->read('Person/1')->shouldBeCalled()->willReturn(json_encode(['FooBar' => 1]));

        $database = new Database($fileSystem->reveal());
        $database->begin();
        $database->save('Person/1', ['FooBar' => 2]);
        $database->rollback();

        $this->assertEquals(['FooBar' => 1], $database->read('Person/1'));
    }

    public function testTransactionCommit()
    {
        $fileSystem = $this->prophesize(IFileSystem::class);
        $fileSystem->put('Person/1', json_encode(['FooBar' => 2]))->shouldBeCalled()->willReturn(true);
        $fileSystem->has('Person/1')->shouldBeCalled()->willReturn(true);
        $fileSystem->read('Person/1')->shouldBeCalled()->willReturn(json_encode(['FooBar' => 2]));

        $database = new Database($fileSystem->reveal());
        $database->begin();
        $database->save('Person/1', ['FooBar' => 2]);
        $database->commit();

        $this->assertEquals(['FooBar' => 2], $database->read('Person/1'));
    }

    /**
     * @expectedException \FitdevPro\JsonDb\Exceptions\NotFoundException
     */
    public function testDeleteTransactionRead()
    {
        $fileSystem = $this->prophesize(IFileSystem::class);

        $database = new Database($fileSystem->reveal());
        $database->begin();
        $database->delete('Person/1');

        $database->read('Person/1');
    }

    public function testDeleteTransactionCommit()
    {
        $fileSystem = $this->prophesize(IFileSystem::class);
        $fileSystem->delete('Person/1')->shouldBeCalled()->willReturn(true);

        $database = new Database($fileSystem->reveal());
        $database->begin();
        $database->delete('Person/1');
        $database->commit();
    }

    public function testDeleteTransactionRollback()
    {
        $fileSystem = $this->prophesize(IFileSystem::class);
        $fileSystem->has('Person/1')->shouldBeCalled()->willReturn(true);
        $fileSystem->read('Person/1')->shouldBeCalled()->willReturn(json_encode(['FooBar' => 2]));

        $database = new Database($fileSystem->reveal());
        $database->begin();
        $database->delete('Person/1');
        $database->rollback();

        $this->assertEquals(['FooBar' => 2], $database->read('Person/1'));
    }

    public function testNestedTransaction()
    {
        $fileSystem = $this->prophesize(IFileSystem::class);
        $fileSystem->has('Person/1')->shouldBeCalled()->willReturn(true);
        $fileSystem->read('Person/1')->shouldBeCalled()->willReturn(json_encode(['FooBar' => 1]));

        $database = new Database($fileSystem->reveal());
        $database->begin();
        $database->begin();
        $database->save('Person/1', ['FooBar' => 2]);
        $database->commit();

        $this->assertEquals(['FooBar' => 2], $database->read('Person/1'));
        $database->rollback();
        $this->assertEquals(['FooBar' => 1], $database->read('Person/1'));
    }
}
