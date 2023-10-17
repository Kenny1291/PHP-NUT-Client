<?php

namespace PhpNutClient\Tests;

require_once 'vendor/autoload.php';

use PhpNutClient\PhpNutClient;
use PHPUnit\Framework\TestCase;
use PhpNutClient\Exceptions\IOException;
use PhpNutClient\Exceptions\NutException;
use function PhpNutClient\Tests\getPrivateMethod;
use function PhpNutClient\Tests\getPrivateOrProtectedPropertyValue;

class PhpNutClientTest extends TestCase
{
    private string $ip = '172.18.89.2';

    /**
     * @test
     */
    public function canSetPassword(): void
    {
        $client = new PhpNutClient($this->ip);
        $client->connect();
        $client->password = 'testPassword';
        $setPasswordMethod = getPrivateMethod('setPassword', $client);
        $output = $setPasswordMethod->invoke($client);
        $this->assertEquals('OK', $output);
    }
    

    /**
     * @test
     */
    public function canSetUsername(): void
    {
        $client = new PhpNutClient($this->ip);
        $client->connect();
        $client->username = 'testUsername';
        $setUsernameMethod = getPrivateMethod('setUsername', $client);
        $output = $setUsernameMethod->invoke($client);
        $this->assertEquals('OK', $output);
    }

    /**
     * @test
     */
    public function canDisconnect(): void 
    {
        $client = new PhpNutClient($this->ip);
        $client->connect();
        $output = $client->disconnect();
        $this->assertThat(
            $output, $this->logicalOr(
                $this->equalTo('OK Goodbye'), //recent versions
                $this->equalTo('Goodbye...') //older versions
            )
        );
    }

    /**
     * @test
     */
    public function canCloseConnection(): void 
    {
        $client = new class($this->ip) extends PhpNutClient {
            public function __destruct() {}
        };
        $client->connect();
        $socketConnection = getPrivateOrProtectedPropertyValue('socketConnection', $client);
        $this->assertTrue(fclose($socketConnection));
    }

    /**
     * @test
     */
    public function throwsExceptionWithSpecificMessageIfCannotConnect(): void
    {
        $client = new PhpNutClient('testHost');
        $this->expectException(IOException::class);
        $this->expectExceptionMessage('Unable to open connection');
        $client->connect(timeout: 3);
    }

    // /**
    //  * @test
    //  */
    // public function throwsExceptionWithSpecificMessageIfTryingToSetAnInvalidPassword(): void
    // {
    //     $client = new PhpNutClient$ip);
    //     $client->connect();
    //     $client->password = ' '; //I have to find an invalid password
    //     $setPasswordMethod = getPrivateMethod('setPassword', $client);
    //     $setPasswordMethod->invoke($client);
    //     $this->expectException(NutException::class);
    //     $this->expectExceptionMessage('INVALID-PASSWORD');
    // }
}