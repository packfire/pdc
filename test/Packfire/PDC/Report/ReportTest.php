<?php
namespace Packfire\PDC\Report;

/**
 * Generated by PHPUnit_SkeletonGenerator 1.2.0 on 2013-01-02 at 22:38:28.
 */
class ReportTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Report
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        $this->object = new Report;
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {
    }
    
    protected function getValue($object, $property)
    {
        $prop = new \ReflectionProperty(get_class($object), $property);
        $prop->setAccessible(true);
        return $prop->getValue($object);
    }

    /**
     * @covers Packfire\PDC\Report\Report::add
     */
    public function testAdd()
    {
        $mock = $this->getMock('\Packfire\PDC\Report\Index', array(), array('mismatches'));
        $this->object->add('test', $mock);
        $indexes = $this->getValue($this->object, 'indexes');
        $this->assertArrayHasKey('test', $indexes);
        $this->assertEquals($mock, $indexes['test']);
    }
    
    /**
     * @covers Packfire\PDC\Report\Report::add
     * @expectedException \Exception
     */
    public function testBadAdd()
    {
        $this->object->add('test', 'anyhow');
    }

    /**
     * @covers Packfire\PDC\Report\Report::processFile
     */
    public function testProcessFile()
    {
        $path = 'Test/Example/ClassA.php';
        $this->object->processFile($path);
        $currentFile = $this->getValue($this->object, 'currentFile');
        $files = $this->getValue($this->object, 'files');
        $this->assertEquals($path, $currentFile);
        $this->assertArrayHasKey($path, $files);
        $this->assertEquals(array(), $files[$path]);
    }

    /**
     * @covers Packfire\PDC\Report\Report::increment
     */
    public function testIncrement()
    {
        $mock = new Index('test');
        $this->object->add('test', $mock);
        $this->assertEquals(0, $mock->count());
        $this->object->increment('test');
        $this->assertEquals(1, $mock->count());
    }

    /**
     * @covers Packfire\PDC\Report\Report::report
     */
    public function testReport()
    {
        $this->assertInternalType('string', $this->object->report());
    }
}
