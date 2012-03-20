<?php

class TerminationTest extends PHPUnit_Framework_TestCase
{
    /**
     * @covers Artax\Handlers\Termination::__construct
     */
    public function testConstructorInitializesDependencies()
    {
        $dp  = new Artax\Ioc\Provider;
        $med = $this->getMock('Artax\Events\Mediator', NULL, [$dp]);
        $obj = new TerminationTestImplementation($med, TRUE);
        $this->assertTrue($obj->debug);
        $this->assertEquals($med, $obj->mediator);
    }
    
    /**
     * @covers Artax\Handlers\Termination::register
     */
    public function testRegisterReturnsInstanceForChaining()
    {
        $dp = new Artax\Ioc\Provider;
        $med = $this->getMock('Artax\Events\Mediator', NULL, [$dp]);
        $t = $this->getMock('Artax\Handlers\Termination',
            ['exception', 'shutdown', 'setMediator', 'getFatalErrorException', 'lastError', 'defaultHandlerMsg'],
            [$med, FALSE]
        );
        
        $this->assertEquals($t->register(), $t);
        restore_exception_handler();
    }
    
    /**
     * @covers Artax\Handlers\Termination::shutdown
     * @covers Artax\Handlers\Termination::getFatalErrorException
     * @covers Artax\Handlers\Termination::lastError
     */
    public function testShutdownInvokesExHandlerOnFatalError()
    {
        $lastErr = [
            'type'    => 1,
            'message' => 'The black knight always triumphs!',
            'file'    => '/path/to/file.php',
            'line'    => 42
        ];
        
        $dp = new Artax\Ioc\Provider;
        $med = $this->getMock('Artax\Events\Mediator', NULL, [$dp]);
        $stub = $this->getMock(
            'TerminationTestImplementation',
            ['exception', 'lastError', 'defaultHandlerMsg'],
            [$med, TRUE]
        );
        $stub->expects($this->any())
                 ->method('lastError')
                 ->will($this->returnValue($lastErr));
        $stub->shutdown();
    }
    
    /**
     * @covers Artax\Handlers\Termination::defaultHandlerMsg
     */
    public function testDefaultHandlerMsgReturnsExpectedString()
    {
        $dp = new Artax\Ioc\Provider;
        $med = $this->getMock('Artax\Events\Mediator', NULL, [$dp]);
        $obj = new TerminationTestImplementation($med, FALSE);
        ob_start();
        $obj->exception(new \Exception('test'));
        $output = ob_get_contents();
        ob_end_clean();
        $this->assertEquals(NULL, $output);
        
        $obj = new TerminationTestImplementation($med, TRUE);
        ob_start();
        $obj->exception(new \Exception('test'));
        $output = ob_get_contents();
        ob_end_clean();
        
        $this->assertStringStartsWith(
            "exception 'Exception' with message 'test'",
            $output
        );
    }
    
    /**
     * @covers Artax\Handlers\Termination::lastError
     */
    public function testLastErrorReturnsNullOnNoFatalPHPError()
    {
        $dp = new Artax\Ioc\Provider;
        $med = $this->getMock('Artax\Events\Mediator', NULL, [$dp]);
        $obj = new TerminationTestImplementation($med, TRUE);
        $this->assertEquals(NULL, $obj->getFatalErrorException());
    }
    
    /**
     * @covers Artax\Handlers\Termination::shutdown
     */
    public function testShutdownNotifiesListenersIfMediatorExists()
    {
        $medStub = $this->getMock(
            'Artax\Events\Mediator', ['all', 'keys'], [new Artax\Ioc\Provider]
        );
        $obj = $this->getMock('TerminationTestImplementation',
            ['getFatalErrorException'], [$medStub, TRUE]
        );
        $obj->expects($this->once())
            ->method('getFatalErrorException')
            ->will($this->returnValue(NULL));      
        $obj->shutdown();
    }
    
    /**
     * @covers Artax\Handlers\Termination::exception
     */
    public function testExHandlerReturnsQuietlyOnPurposefulScriptHalt()
    {
        $med = $this->getMock('Artax\Events\Mediator', ['notify'],
            [new Artax\Ioc\Provider]
        );
        $obj = new Artax\Handlers\Termination($med, TRUE);
        $this->assertNull($obj->exception(new Artax\Exceptions\ScriptHaltException));
    }
    
    /**
     * @covers Artax\Handlers\Termination::exception
     */
    public function testExHandlerOutputsDefaultMessageIfNoMediatorExists()
    {
        $med = $this->getMock('Artax\Events\Mediator', ['notify'],
            [new Artax\Ioc\Provider]
        );
        $stub = $this->getMock('Artax\Handlers\Termination',
            ['defaultHandlerMsg'], [$med, TRUE]
        );
        $stub->expects($this->once())
             ->method('defaultHandlerMsg')
             ->with($this->equalTo(new Exception))
             ->will($this->returnValue(NULL));
        $stub->exception(new Exception);
    }
    
    /**
     * @covers Artax\Handlers\Termination::exception
     */
    public function testExHandlerNotifiesMediatorOnUncaughtException()
    {
        $med = $this->getMock('Artax\Events\Mediator', ['notify'],
            [new Artax\Ioc\Provider]
        );
        $stub = $this->getMock('Artax\Handlers\Termination',
            NULL, [$med, TRUE]
        );
        $med->expects($this->once())
             ->method('notify')
             ->with(
                $this->equalTo('exception'),
                $this->equalTo(new Exception),
                $this->equalTo(TRUE)
            );
        
        $med->push('exception', function(){});
        $stub->exception(new Exception);
    }
    
    /**
     * @covers Artax\Handlers\Termination::exception
     */
    public function testExHandlerFallsBackToDefaultMessageOnNotifyException()
    {
        $med = $this->getMock('Artax\Events\Mediator', ['notify'],
            [new Artax\Ioc\Provider]
        );
        $med->expects($this->once())
             ->method('notify')
             ->will($this->throwException(new Exception));
        $stub = $this->getMock('Artax\Handlers\Termination',
            ['notify', 'defaultHandlerMsg'],
            [$med, TRUE]
        );
        
        $stub->expects($this->once())
             ->method('defaultHandlerMsg')
             ->with($this->equalTo(new Exception))
             ->will($this->returnValue(NULL));
        
        $med->push('exception', function(){});
        $stub->exception(new Exception);
    }
    
    /**
     * @covers Artax\Handlers\Termination::exception
     */
    public function testExHandlerReturnsImmediatelyOnNotifyScriptHaltException()
    {
        $e = new Artax\Exceptions\ScriptHaltException;
        
        $med = $this->getMock('Artax\Events\Mediator', ['notify'],
            [new Artax\Ioc\Provider]
        );
        $stub = $this->getMock('Artax\Handlers\Termination',
            ['notify'], [$med, TRUE]
        );
        $med->expects($this->once())
             ->method('notify')
             ->will($this->throwException($e));
        
        // We have to push a listener otherwise notify won't be called
        $med->push('exception', function(){});
        $stub->exception(new Exception);
    }
    
    /**
     * @covers Artax\Handlers\Termination::shutdown
     */
    public function testShutdownReturnsImmediatelyOnNotifyScriptHaltException()
    {
        $med = $this->getMock('Artax\Events\Mediator', ['notify'],
            [new Artax\Ioc\Provider]
        );
        $stub = $this->getMock('Artax\Handlers\Termination', NULL, [$med, TRUE]);
        $med->expects($this->once())
             ->method('notify')
             ->will($this->throwException(new Artax\Exceptions\ScriptHaltException));
        
        // We have to push a listener otherwise notify won't be called
        $med->push('exception', function(){});
        $this->assertNull($stub->shutdown());
    }
    
    /**
     * @covers Artax\Handlers\Termination::shutdown
     */
    public function testShutdownFallsBackToDefaultOnNotifyException()
    {
        $med = $this->getMock('Artax\Events\Mediator', ['notify'],
            [new Artax\Ioc\Provider]
        );
        $stub = $this->getMock('Artax\Handlers\Termination',
            ['defaultHandlerMsg'], [$med, TRUE]
        );
        $med->expects($this->once())
             ->method('notify')
             ->will($this->throwException(new Exception));
        $stub->expects($this->once())
             ->method('defaultHandlerMsg')
             ->will($this->returnValue('test exception output'));
        
        // We have to push a listener otherwise notify won't be called
        $med->push('exception', function(){});
        
        $this->expectOutputString('test exception output');
        $stub->shutdown();
    }
    
    /**
     * @covers Artax\Handlers\Termination::exception
     */
    public function testExHandlerNotifiesShutdownEventOnFatalRuntimeError()
    {
        $med = $this->getMock('Artax\Events\Mediator', ['notify'],
            [new Artax\Ioc\Provider]
        );
        $stub = $this->getMock('Artax\Handlers\Termination',
            NULL, [$med, TRUE]
        );
        $med->expects($this->exactly(2))
             ->method('notify')
             ->will($this->returnValue(NULL));
             
        // We have to push listeners otherwise notify won't be called
        $med->push('exception', function(){});
        $med->push('shutdown', function(){});
        $stub->exception(new Artax\Exceptions\FatalErrorException);
    }
    
    /**
     * @covers Artax\Handlers\Termination::exception
     */
    public function testExHandlerFallsBackToDefaultOnNotifyException()
    {
        $med = $this->getMock('Artax\Events\Mediator', ['notify'],
            [new Artax\Ioc\Provider]
        );
        $stub = $this->getMock('Artax\Handlers\Termination',
            ['defaultHandlerMsg'], [$med, TRUE]
        );
        $med->expects($this->atLeastOnce())
             ->method('notify')
             ->will($this->throwException(new Exception('test exception output')));
        $stub->expects($this->once())
             ->method('defaultHandlerMsg')
             ->will($this->returnValue('test exception output'));
             
        // We have to push listeners otherwise notify won't be called
        $med->push('exception', function(){});
        $med->push('shutdown', function(){});
        
        
        $this->expectOutputString('test exception output');
        $stub->exception(new Artax\Exceptions\FatalErrorException);
    }
    
    /**
     * @covers Artax\Handlers\Termination::exception
     */
    public function testExHandlerExitsOnNotifyScriptHaltException()
    {
        $med = $this->getMock('Artax\Events\Mediator', ['notify'], 
            [new Artax\Ioc\Provider]
        );
        $stub = $this->getMock('Artax\Handlers\Termination',
            NULL, [$med, TRUE]
        );
        $med->expects($this->atLeastOnce())
             ->method('notify')
             ->will($this->throwException(new Artax\Exceptions\ScriptHaltException));
             
        // We have to push listeners otherwise notify won't be called
        $med->push('exception', function(){});
        $med->push('shutdown', function(){});
        $this->assertNull($stub->exception(new Artax\Exceptions\FatalErrorException));
    }
}



class TerminationTestImplementation extends Artax\Handlers\Termination
{
    use MagicTestGetTrait;
}