<?php

namespace PhpNutClient\Tests;

require_once 'vendor/autoload.php';

use PhpNutClient\PhpNutClient;
use PHPUnit\Framework\TestCase;
use PhpNutClient\Exceptions\IOException;
use PhpNutClient\Exceptions\NutException;
use function PhpNutClient\Tests\getPrivateMethod;

class PhpNutClientWithSharedClientTest extends TestCase
{
    private static $client;
    private static $upsName = 'dummy';

    public static function setUpBeforeClass(): void
    {
        self::$client = new PhpNutClient('172.18.89.2', username: 'primaryUserName', password: 'primaryUserPassword');
    }

    private function getPrivateMethodForPhpNutClient(string $method): \ReflectionMethod
    {
        return getPrivateMethod($method, self::$client);
    }

    private function getWriteMethod(): \ReflectionMethod
    {
        return $this->getPrivateMethodForPhpNutClient('write');
    }

    /**
     * @test 
     */ 
    public function canConnect(): void
    {
        $this->assertTrue(self::$client->connect());
    }

    /**
     * @test
     */
    public function throwsExceptionWithSpecificMessageWhenTryingToSetPasswordIfAlreadySet(): void
    {
        $setPasswordMethod = $this->getPrivateMethodForPhpNutClient('setPassword');
        $this->expectException(NutException::class);
        $this->expectExceptionMessage('ALREADY-SET-PASSWORD: PASSWORD already set and another cannot be set.');
        $setPasswordMethod->invoke(self::$client);
    }

    /**
     * @test
     */
    public function throwsExceptionWithSpecificMessageWhenTryingToSetUsernameIfAlreadySet(): void
    {
        $setUsernameMethod = $this->getPrivateMethodForPhpNutClient('setUsername');
        $this->expectException(NutException::class);
        $this->expectExceptionMessage('ALREADY-SET-USERNAME: USERNAME already set and another cannot be set.');
        $setUsernameMethod->invoke(self::$client);
    }

    /**
     * @test
     */
    public function handleSingleLineOutputReturnsOnlyLastWordWhenCalledWithoutFromWordNumberParameter(): void
    {
        $writeMethod = $this->getWriteMethod();
        $writeMethod->invokeArgs(self::$client, ['HELP']);
        $handleSingleLineOutputMethod = $this->getPrivateMethodForPhpNutClient('handleSingleLineOutput');
        $output = $handleSingleLineOutputMethod->invoke(self::$client);
        $this->assertStringNotContainsString(' ', $output);
        $this->assertEquals('STARTTLS', $output);
    }

    /**
     * @test
     */
    public function handleSingleLineOutputReturnsWholeResponseWhenNumberOfWordsParameterIsZero(): void
    {
        $writeMethod = $this->getWriteMethod();
        $writeMethod->invokeArgs(self::$client, ['HELP']);
        $handleSingleLineOutputMethod = $this->getPrivateMethodForPhpNutClient('handleSingleLineOutput');
        $output = $handleSingleLineOutputMethod->invokeArgs(self::$client, [0]);
        $words = explode(" ", $output);
        $this->assertEquals(12, count($words));
    }

    /**
     * @test
     */
    public function handleSingleLineOutputReturnsCorrectNumberOfWordsWhenFromWordNumberParameterIsSpecified(): void
    {
        $writeMethod = $this->getWriteMethod();
        $writeMethod->invokeArgs(self::$client, ['HELP']);
        $handleSingleLineOutputMethod = $this->getPrivateMethodForPhpNutClient('handleSingleLineOutput');
        $wholeOutput = $handleSingleLineOutputMethod->invokeArgs(self::$client, [0]);
        $writeMethod->invokeArgs(self::$client, ['HELP']);
        $fromWordIndex = 2;
        $partialOutput = $handleSingleLineOutputMethod->invokeArgs(self::$client, [$fromWordIndex]);
        $nrOfWordsTotal = count(explode(" ", $wholeOutput));
        $nrOfWordsPartial = count(explode(" ", $partialOutput));
        $this->assertEquals( $nrOfWordsTotal - $fromWordIndex, $nrOfWordsPartial);
    }

    /**
     * @test
     */
    public function handleSingleLineOutputThrowsNutExceptionWhenNutProtocolErrorOccurs(): void
    {
        $writeMethod = $this->getWriteMethod();
        $writeMethod->invokeArgs(self::$client, ['TEST']);
        $handleSingleLineOutputMethod = $this->getPrivateMethodForPhpNutClient('handleSingleLineOutput');
        $this->expectException(NutException::class);
        $handleSingleLineOutputMethod->invoke(self::$client);
    }

    /**
     * @test 
     */    
    public function handleMultipleLinesOutputReturnsOnlyLastStringOfEachLineWhenCalledWithoutIncludeLastTwoStrings(): void 
    {
        $writeMethod = $this->getWriteMethod();
        $writeMethod->invokeArgs(self::$client, ['LIST UPS']);
        $handleMultipleLinesOutputMethod = $this->getPrivateMethodForPhpNutClient('handleMultipleLinesOutput');
        $output = $handleMultipleLinesOutputMethod->invokeArgs(self::$client, ['END LIST UPS\n']);
        foreach ($output as $lineArr) {
            $this->assertEquals(1, count($lineArr));
            foreach ($lineArr as $lineEl) {
                $this->assertNotEquals(self::$upsName, $lineEl);
            }
        }
    }

    /**
     * @test
     */
    public function handleMultipleLinesOutputReturnsLastTwoStringsWhenCalledWithIncludeLastTwoStrings(): void
    {
        $writeMethod = $this->getWriteMethod();
        $writeMethod->invokeArgs(self::$client, ['LIST UPS']);
        $handleMultipleLinesOutputMethod = $this->getPrivateMethodForPhpNutClient('handleMultipleLinesOutput');
        $output = $handleMultipleLinesOutputMethod->invokeArgs(self::$client, ['END LIST UPS\n', true]);
        foreach ($output as $lineArr) {
            $this->assertEquals(2, count($lineArr));
        }
    }

    /**
     * @test 
     */
    public function handleMultipleLinesOutputThrowsExceptionWhenNutProtocolErrorOccurs(): void
    {
        $writeMethod = $this->getWriteMethod();
        $writeMethod->invokeArgs(self::$client, ['TEST']);
        $handleMultipleLinesOutputMethod = $this->getPrivateMethodForPhpNutClient('handleMultipleLinesOutput');
        $this->expectException(NutException::class);
        $handleMultipleLinesOutputMethod->invokeArgs(self::$client, ['TEST']);
    }

    /**
     * @test
     */
    public function getNumberOfLoggedClientsReturnsANumber(): void
    {
        $output = self::$client->getNumberOfLoggedClients(self::$upsName);
        $this->assertIsNumeric($output);
    }

    /**
     * @test
     */
    public function getUpsDescription(): void
    {
        $output = self::$client->getUpsDescription(self::$upsName);
        $this->assertIsString($output);
    }

    /**
     * @test
     */
    public function getUpsVar(): void
    {
        $variableName = self::$client->getUpsVars(self::$upsName)[0]['variableName'];
        $output = self::$client->getUpsVar(self::$upsName, $variableName);
        $this->assertIsString($output);
    }

    /**
     * @test
     */
    public function getVarType(): void 
    {
        $variableName = self::$client->getUpsVars(self::$upsName)[0]['variableName'];
        $output = self::$client->getVarType(self::$upsName, $variableName);
        $this->assertIsString($output);
        $this->assertThat(
            $output, $this->logicalOr(
                $this->stringContains('RW'),
                $this->stringContains('ENUM'),
                $this->stringContains('STRING'),
                $this->stringContains('RANGE'),
                $this->stringContains('NUMBER'),
            )
        );
    }

    /**
     * @test
     */
    public function getVarDescription(): void 
    {
        $variableName = self::$client->getUpsVars(self::$upsName)[0]['variableName'];
        $output = self::$client->getVarDescription(self::$upsName, $variableName);
        $this->assertIsString($output);
    }

    /**
     * @test
     */
    public function getCommandDescription(): void 
    {
        $commandName = self::$client->getUpsCommands(self::$upsName)[0]['commandName'];  
        $output = self::$client->getCommandDescription(self::$upsName, $commandName);
        $this->assertIsString($output);
    }

    /**
     * @test
     */
    public function getTrackingStatusReturnsOnOrOff(): void 
    {
        $output = self::$client->getTrackingStatus();
        $this->assertThat(
            $output, $this->logicalOr(
                $this->equalTo('ON'),
                $this->equalTo('OFF')
            )
        );
    }

    //I am not calling getTrackingStatus method
    /**
     * @test
     */
    public function getTrackingStatusThrowsExceptionWhenNutProtocolErrorsOccurs(): void
    {
        $writeMethod = $this->getWriteMethod();
        $writeMethod->invokeArgs(self::$client, ['GET TRACKING TEST']);
        $handleSingleLineOutputMethod = $this->getPrivateMethodForPhpNutClient('handleSingleLineOutput');
        $this->expectException(NutException::class);
        $handleSingleLineOutputMethod->invoke(self::$client);
    }

    //TODO: Sometimes getSetVarOrCommandStatus() fails, NutException: UNKNOWN
    /**
     * @test
     */
    public function getSetVarOrCommandStatus() 
    {
        $trackingStatus = self::$client->getTrackingStatus();
        if($trackingStatus === 'OFF') {
            self::$client->enableTracking();
        }
        $commandName = self::$client->getUpsCommands(self::$upsName)[0]['commandName'];  
        $commandId = explode(" ", self::$client->runUpsCommand(self::$upsName, $commandName))[2];
        $output = self::$client->getSetVarOrCommandStatus($commandId);
        $this->assertThat(
            $output, $this->logicalOr(
                $this->equalTo('PENDING'),
                $this->equalTo('SUCCESS')
            )
        );
    }

    /**
     * @test
     */
    public function getUpsList(): void
    {
        $output = self::$client->getUpsList();
        foreach ($output as $lineArr) {
            $this->assertEquals(self::$upsName, $lineArr['upsName']);
            $this->assertIsString($lineArr['upsDescription']);
        }
    }

    /**
     * @test
     */
    public function getUpsVars(): void
    {
        $output = self::$client->getUpsVars(self::$upsName);
        foreach ($output as $lineArr) {
            $this->assertIsString($lineArr['variableName']);
            //I could test pattern called.like.this
            $this->assertIsString($lineArr['variableValue']);
        }
    }
    
    /**
     * @test
     */
    public function getUpsRwVars(): void 
    {
        $output = self::$client->getUpsRwVars(self::$upsName);
        foreach ($output as $lineArr) {
            $this->assertIsString($lineArr['variableName']);
            $this->assertIsString($lineArr['variableValue']);
        }
    }

    /**
     * @test
     */
    public function getUpsCommands(): void 
    {
        $output = self::$client->getUpsCommands(self::$upsName);
        foreach ($output as $lineArr) {
            $this->assertIsString($lineArr['commandName']);
        }
    }

    //phpuniterror
    //not every var has enums
    /**
     * @test
     */
    // public function getUpsVarEnums(): void 
    // {
    //     $upsVariables = self::$client->getUpsVars(self::$upsName); //[[]]
    //     $variableName = "";
    //     foreach ($upsVariables as $variableDetails) {
    //         $variableType = self::$client->getVarType(self::$upsName, $variableDetails['variableName']);
    //         $isEnum = strpos($variableType, 'ENUM');
    //         if($isEnum) {
    //             $variableName = $variableDetails['variableName'];
    //             break;
    //         }
    //     }
    //     if($variableName) $output = self::$client->getUpsVarEnums(self::$upsName, $variableName);
    //     foreach ($output as $lineArr) {
    //         $this->assertIsString($lineArr['variableEnum']);
    //     }
    //     $this->assertEquals([[]], $output); //debug output
    // }

    //phpuniterror
    //not every var has ranges
    /**
     * @test
     */
    // public function getVarRanges(): void 
    // {
    //     $output = self::$client->getVarRanges(self::$upsName, 'battery.charge');
    //     foreach ($output as $lineArr) {
    //         $this->assertIsString($lineArr['variableMinValue']);
    //         $this->assertIsString($lineArr['variableMaxValue']);
    //     }
    // }

    //phpuniterror
    /**
     * @test
     */
    // public function getUpsClients(): void 
    // {
    //     $output = self::$client->getUpsClients(self::$upsName);
    //     foreach ($output as $lineArr) {
    //         $this->assertIsString($lineArr['clientIPAddress']);
    //     }
    // }

    // all the set commands have to be testes on a dummy ups
    //setUpsVars

    /**
     * @test
     */
    public function canEnableTracking(): void 
    {
        $output = self::$client->enableTracking();
        $this->assertEquals('OK', $output);
    }

    /**
     * @test
     */
    public function canDisableTracking(): void 
    {
        $output = self::$client->disableTracking();
        $this->assertEquals('OK', $output);
    }

    /**
     * @test
     */
    public function canRunUpsCommand(): void
    {
        $command = self::$client->getUpsCommands(self::$upsName)[0]['commandName'];
        $output = self::$client->runUpsCommand(self::$upsName, $command);
        $this->assertEquals('OK', $output);
    }

    /**
     * @test
     */
    // public function canRunUpsCommandWithParameter(): void
    // {

    // }

    /**
     * @test
     */
    public function canCheckForPrimaryPrivileges(): void 
    {
        $output = self::$client->checkIfPrimaryPrivileges(self::$upsName);
        $nutProtocolVersion = self::$client->getNetworkProtocolVersion();
        if((float)$nutProtocolVersion >= 1.3) {
            $this->assertEquals('OK PRIMARY-GRANTED', $output);
        }else {
            $this->assertEquals('OK MASTER-GRANTED', $output);
        }
    }

    /**
     * @test
     */
    public function canSendFSDCommand(): void 
    {
        $output = self::$client->FSD(self::$upsName);
        $this->assertEquals('OK FSD-SET', $output);
    }

    /**
     * @test
     */
    public function help(): void 
    {
        $output = self::$client->help();
        $this->assertEquals('Commands: HELP VER GET LIST SET INSTCMD LOGIN LOGOUT USERNAME PASSWORD STARTTLS', $output);
    }

    //"Network UPS Tools upsd [version number] - <http://www.networkupstools.org/>"
    /** 
     * @test
     */
    public function canGetNutServerVersion(): void 
    {
        $output = self::$client->getNutServerVersion();
        $this->assertIsString($output);
    }

    /**
     * @test
     */
    public function canGetNetworkProtocolVersion(): void 
    {
        $output = self::$client->getNetworkProtocolVersion();
        $this->assertIsString($output);
        $this->assertStringNotContainsString(' ', $output);
    }

    /**
     * @test
     */
    public function canLogin(): void 
    {
        $output = self::$client->login(self::$upsName);
        $this->assertEquals('OK', $output);
    }

    /**
     * @test
     */
    public function throwsExceptionWithSpecificMessageWhenTryingToEnableTlsAndFeatureIsNotConfiguredOnNutServer(): void
    {
        $this->expectException(NutException::class);
        $this->expectExceptionMessage('FEATURE-NOT-CONFIGURED: This instance of upsd has not been configured properly to allow the requested feature to operate.');
        self::$client->setTlsOnNutServer();
    }

    //phpuniterror
    /**
     * @test
     */
    // public function canEnableTlsOnNutServerIfFeatureIsEnabled(): void 
    // {
    //     $exceptionNotRaised = true;
    //     try {
    //         $output = self::$client->setTlsOnNutServer();
    //     } catch (\Throwable $e) {
    //         $exceptionNotRaised = false;
    //     }
    //     if($exceptionNotRaised) $this->assertEquals('OK STARTTLS', $output);
    // }
}
