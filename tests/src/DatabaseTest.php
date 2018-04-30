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
        $fileSystem->createDir('Person/')->shouldBeCalled();

        $database = new Database($fileSystem->reveal());

        $this->assertInstanceOf(Table::class, $database->getTable('Person'));
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
     * @expectedException \FitdevPro\JsonDb\Exceptions\NotFoundException;
     */
    public function testReadNotFound()
    {
        $fileSystem = $this->prophesize(IFileSystem::class);
        $fileSystem->has('Person/1')->shouldBeCalled()->willReturn(false);

        $database = new Database($fileSystem->reveal());

        $database->read('Person/1');
    }

    public function testSave()
    {
        $fileSystem = $this->prophesize(IFileSystem::class);
        $fileSystem->put('Person/2', json_encode(['FooBar' => 2]))->shouldBeCalled()->willReturn(true);

        $database = new Database($fileSystem->reveal());

        $database->save('Person/2', ['FooBar' => 2]);
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

    public function testDelete()
    {
        $fileSystem = $this->prophesize(IFileSystem::class);
        $fileSystem->delete('Person/1')->shouldBeCalled()->willReturn(true);

        $database = new Database($fileSystem->reveal());
        $database->delete('Person/1');
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
        $fileSystem->read('Person/1')->shouldBeCalled()->willReturn(json_encode(['FooBar' => 2]));

        $database = new Database($fileSystem->reveal());
        $database->begin();
        $database->delete('Person/1');
        $database->rollback();

        $this->assertEquals(['FooBar' => 2], $database->read('Person/1'));
    }
}
