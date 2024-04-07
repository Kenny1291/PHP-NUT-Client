<?php

namespace PhpNutClient;

use PhpNutClient\Exceptions\IOException;
use PhpNutClient\Exceptions\NutException;

require_once 'vendor/autoload.php';

//TODO: Make backwards compatible with old protocol (That used REQ command)
//TODO: Should write a parser to process output (parseconf)

/**
 * PhpNutClient
 * 
 * A NUT (Network UPS Tools) client for PHP.
 * 
 * This class abstracts the connection to the NUT server (upsd).
 * 
 * NUT network protocol docs: <https://networkupstools.org/docs/developer-guide.chunked/ar01s09.html>
 * Should support versions <=2.8.0
 * 
 * @author Raiquen Guidotti <raiquen@guidotti.solutions>
 * @copyright 2023 Raiquen Guidotti 
 * @license GNU GPL
 * You should have received a copy of the GNU GPL License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */
class PhpNutClient
{
    /**
     * The persistent domain socket connection.
     * 
     * @var resource
     */
    private $socketConnection;
    
    /**
     * @var string $host The hostname or IP.
     * @var int $port The port number. Defaults to 3493.
     * @var string $username The username to use to connect to NUT server. Defaults to an empty string.
     * @var string $password The password to use to connect to NUT server. Defaults to an empty string.
     */
    public function __construct(
        private string $host,
        private int $port = 3493,
        private string $username = "",
        private string $password = ""
    ) {}

    public function __destruct()
    {
        $exceptionNotRaised = true;
        try {
            $this->getNutServerVersion();
        } catch (\Throwable) {
            $exceptionNotRaised = false;
        }
        if($exceptionNotRaised) $this->disconnect();
        if($this->socketConnection) {
            $f = fclose($this->socketConnection);
            if(!$f) throw new IOException("Unable to close connection");
        }
    }
    
    /**
     * Writes to the $socketConnection.
     *
     * @param  string $data Data to write.
     * @return true If write is successful.
     * @throws IOException If unable to write to resource.
     */
    private function write(string $data): bool
    {
        $f = fwrite($this->socketConnection, $data."\n"); //Note: "\n" is required (not '\n')
        if(!$f) throw new IOException('Unable to write to resource');
        return true;
    }
    
    /**
     * Reads characters from $socketConnection until a newline character.
     * Note: It allows fgetc() to fail twice.
     * 
     * @return string $chars The characters read discarding the newline character.
     * @throws IOException If unable to read from resource.
     */
    private function readCharsUntilNewLine(): string
    {
        $chars = "";
        $failures = 0;
        do {
            $char = fgetc($this->socketConnection); 
            if(!$char) $failures++;
            if($failures > 2) throw new IOException('Unable to read from resource');
            $chars .= $char;
        } while (addcslashes($char, "\0..\37") !== '\n');
        $chars = substr($chars, 0, -1);
        return $chars;
    }
    
    /**
     * Reads lines from $socketConnection until the delimiter string.
     *
     * @param  string $delimiter The string on which stop reading.
     * @return array $lines The lines read discarding the last one.
     * @throws IOException If unable to read from resource.
     * @throws NutException If a NUT protocol error occurs.
     */
    private function readLinesUntil(string $delimiter): array
    {
        $lines = [];
        do {
            $line = fgets($this->socketConnection);
            if(!$line) throw new IOException('Unable to read from resource');
            $words = explode(" ", $line);
            if($words[0] === 'ERR') throw new NutException($words[1]);
            array_push($lines, $line);
        } while (addcslashes($line, "\0..\37") !== $delimiter);
        array_pop($lines);
        return $lines;
    }
    
    /**
     * Sets the password associated with the connection on the NUT server.
     *
     * @return string $output = "OK" if successful.
     * @throws NutException If a NUT protocol error occurs.
     * @throws IOException If an error occurs in readCharsUntilNewLine().
     */
    private function setPassword(): string
    {
        $this->write('PASSWORD '.$this->password);
        try {
            $output = $this->handleSingleLineOutput();
        } catch(NutException $e) {
            throw new NutException($e->getMessage(), ['password' => $this->password]);
        }
        return $output;
    }
    
    /**
     * Sets the username associated with the connection on the NUT server.
     *
     * @return string $output = "OK" if successful.
     * @throws NutException If a NUT protocol error occurs.
     * @throws IOException If an error occurs in readCharsUntilNewLine().
     */
    private function setUsername(): string
    {
        $this->write('USERNAME '.$this->username);
        try {
            $output = $this->handleSingleLineOutput();
        } catch(NutException $e) {
            throw new NutException($e->getMessage(), ['username' => $this->username]);
        }
        return $output;
    }

    //TODO: Complete as specified in NUT docs
    //TODO: Revise completely; rename
    private function parser(string $output, int $indexDoubleQuote, bool $calledByHandleMultipleLinesOutput = false): array
    {
        $firstPartOfLine = substr($output, 0, $indexDoubleQuote);
        $secondPartOfLine = substr($output, $indexDoubleQuote);
        $wordsSeparatedBySpaceArr = explode(" ", trim($firstPartOfLine));
        $stringsSeparatedByDoubleQuoteArr = explode('" "', $secondPartOfLine);
        $stringsSeparatedByDoubleQuoteArr[0] = substr($stringsSeparatedByDoubleQuoteArr[0], 1); //remove initial '"' from first word 
        $stringsSeparatedByDoubleQuoteArrLen = count($stringsSeparatedByDoubleQuoteArr); 
        $lastElOfStringSeparatedByDoubleQuoteArr = $stringsSeparatedByDoubleQuoteArr[$stringsSeparatedByDoubleQuoteArrLen - 1];
        if($stringsSeparatedByDoubleQuoteArrLen === 1 and !$calledByHandleMultipleLinesOutput) {
            $stringsSeparatedByDoubleQuoteArr[0] = substr($lastElOfStringSeparatedByDoubleQuoteArr, 0, -1); //remove " if only one word
        } elseif ($stringsSeparatedByDoubleQuoteArrLen === 1 and $calledByHandleMultipleLinesOutput) {
            $stringsSeparatedByDoubleQuoteArr[0] = substr($lastElOfStringSeparatedByDoubleQuoteArr, 0, -2); //remove "\n if only one word
        }
        if($stringsSeparatedByDoubleQuoteArrLen > 1 and !$calledByHandleMultipleLinesOutput) {
            $stringsSeparatedByDoubleQuoteArr[$stringsSeparatedByDoubleQuoteArrLen - 1] = substr($lastElOfStringSeparatedByDoubleQuoteArr, 0, -1); //remove "
        } elseif ($stringsSeparatedByDoubleQuoteArrLen > 1 and $calledByHandleMultipleLinesOutput) {
            $stringsSeparatedByDoubleQuoteArr[$stringsSeparatedByDoubleQuoteArrLen - 1] = substr($lastElOfStringSeparatedByDoubleQuoteArr, 0, -2); //remove "\n
        }
        return array_merge($wordsSeparatedBySpaceArr, $stringsSeparatedByDoubleQuoteArr);
    }
    
    /**
     * Reads output line and returns last word read or 
     * the words starting from the index provided as the parameter.
     *
     * @param  int $fromWordNumber The index of the word from which to start including in the return. Defaults to -1 (only last word).
     * @return string The word/words.
     * @throws NutException If a NUT protocol error occurs.
     * @throws IOException If an error occurs in readCharsUntilNewLine().
     */
    private function handleSingleLineOutput(int $fromWordNumber = -1): string
    {
        $res = $this->readCharsUntilNewLine();

        $indexDoubleQuote = strpos($res, '"');
        if($indexDoubleQuote) {
            $words = $this->parser($res, $indexDoubleQuote);
        } else {
            $words = explode(" ", $res);
        }
        if($words[0] === 'ERR') throw new NutException($words[1]); //Note: NUT Protocol error format: ERR <message> [<extra>] (as of v1.3 only message)
        if($fromWordNumber !== -1) {
            return implode(" ", array_slice($words, $fromWordNumber));
        } else {
            return end($words);
        }
    }

    /**
     * Reads output lines and extrapolates one string from each line or two if parameter specified.
     *
     * @param  string $keyWord The string delimiter to pass to readLinesUntil().
     * @param  bool $includeLastTwoStrings  Defaults to false (only last string). 
     * @return array $linesArr The string/strings extrapolated. Each element (inner array) represent a line read.
     * @throws NutException If a NUT protocol error occurs.
     * @throws IOException If error occurs in readLinesUntil().
     */
    private function handleMultipleLinesOutput(string $keyWord, bool $includeLastTwoStrings = false): array
    {
        $lines = $this->readLinesUntil($keyWord);
        $linesArr = [];
        foreach ($lines as $line) {
            $indexDoubleQuote = strpos($line, '"');
            if($indexDoubleQuote) {
                $words = $this->parser($line, $indexDoubleQuote, true);
            } else {
                $line = substr($line, 0, -1); //remove /n //TODO: this is too janky, I need to cleary define a rule and do this in the parser
                $words = explode(" ", $line);
            }
            $arrLen = count($words);
            if($words[0] === 'ERR') throw new NutException($words[1]);
            if($includeLastTwoStrings) {
                array_push($linesArr, [$words[$arrLen - 2], $words[$arrLen - 1]]);
            } else {
                array_push($linesArr, [$words[$arrLen - 1]]);
            }
        }
        return $linesArr;
    }
    
    /**
     * Converts the inner arrays of a matrix into named keys arrays.
     * Note: Expects the length of the inner arrays to be the same as the length of $keys.
     * 
     * @param  array $inputArr The matrix.
     * @param  array $keys The keys to apply.
     * @return array $namedKeyArr The matrix with the keys applied to the inner arrays.
     */
    private function convertMultipleLinesOutputArrIntoNamedKeysArr(array $inputArr, array $keys): array
    {
        $namedKeyArr = [];
        foreach ($inputArr as $innerArr) {
            array_push($namedKeyArr, array_combine($keys, $innerArr));
        }
        return $namedKeyArr;
    }

    /**
     * Connects to the NUT server, if password or username are specified it sets them.
     *
     * @param int $timeout The seconds it will wait before failing if no response is given. Defaults to 10.
     * @return true If successful.
     * @throws NutException If NUT protocol error occurs.
     * @throws IOException If an error occurs in readCharsUntilNewLine().
     */
    public function connect(int $timeout = 10): bool
    {
        if($this->socketConnection) $this->disconnect();
        $this->socketConnection = pfsockopen($this->host, $this->port, $errorCode, $errorMessage, $timeout);
        if(!$this->socketConnection) throw new IOException("Unable to open connection");
        if($this->username) $this->setUsername();
        if($this->password) $this->setPassword();
        return true;
    }
    
    /**
     * Get the number of clients which have done LOGIN for the UPS.
     * Note: LOGIN differs from an authenticated connection.
     * 
     * @param  string $upsName The UPS.
     * @return string $numberOfLoggedClients The number of logged clients.
     * @throws NutException If NUT protocol error occurs.
     * @throws IOException If an error occurs in write() or readCharsUntilNewLine().
     */
    public function getNumberOfLoggedClients(string $upsName): string 
    {
        $this->write('GET NUMLOGINS '.$upsName);
        try {
            $numberOfLoggedClients = $this->handleSingleLineOutput();
        } catch(NutException $e) {
            throw new NutException($e->getMessage(), ['upsName' => $upsName]);
        }
        return $numberOfLoggedClients;
    }
    
    /**
     * Gets the description of the UPS.
     * Note: If it is not set the NUT server will return "Unavailable".
     * 
     * @param  string $upsName The UPS name.
     * @return string $upsDescription The description.
     * @throws NutException If NUT protocol error occurs.
     * @throws IOException If an error occurs in write() or readCharsUntilNewLine().
     */
    public function getUpsDescription(string $upsName): string
    {
        $this->write('GET UPSDESC '.$upsName);
        try {
            $upsDescription = $this->handleSingleLineOutput();
        } catch(NutException $e) {
            throw new NutException($e->getMessage(), ['upsName' => $upsName]);
        }
        $upsDescription = substr($upsDescription, 1);
        $upsDescription = substr($upsDescription, 0, -1);
        return $upsDescription;
    }
    
    /**
     * Gets the value of a variable from the UPS.
     *
     * @param  string $upsName The UPS name.
     * @param  string $varName The variable name.
     * @return string The value of the variable.
     * @throws NutException If NUT protocol error occurs.
     * @throws IOException If an error occurs in write() or readCharsUntilNewLine().
     */
    public function getUpsVar(string $upsName, string $varName): string
    {
        $this->write('GET VAR '.$upsName.' '.$varName);
        try {
            $variableValue = $this->handleSingleLineOutput();
        } catch(NutException $e) {
            throw new NutException($e->getMessage(), ['upsName' => $upsName, 'variableName' => $varName]);
        }
        return $variableValue;
    }

    /**
     * Gets the type of the variable.
     *
     * @param  string $upsName The UPS name.
     * @param  string $varName The variable name.
     * @return string The variable type/types.
     * @throws NutException If NUT protocol error occurs. 
     * @throws IOException If a NUT protocol error occurs in handleSingleLineOutput().
     * 
     */
    public function getVarType(string $upsName, string $varName): string
    {
        $this->write('GET TYPE '.$upsName.' '.$varName);
        try {
            $variableType = $this->handleSingleLineOutput(3);
        } catch(NutException $e) {
            throw new NutException($e->getMessage(), ['upsName' => $upsName, 'variableName' => $varName]);
        }
        return $variableType;
    }
    
    /**
     * Gets the description of the variable.
     * Note: May return "Unavailable" if the file which provides the description is not installed.
     *
     * @param  string $upsName The UPS name.
     * @param  string $varName The variable name.
     * @return string The description of the variable.
     * @throws NutException If NUT protocol error occurs. 
     * @throws IOException If an error occurs in write() or readCharsUntilNewLine().
     */
    public function getVarDescription(string $upsName, string $varName): string
    {   
        $this->write('GET DESC '.$upsName.' '.$varName);
        try {
            return $this->handleSingleLineOutput();
        } catch(NutException $e) {
            throw new NutException($e->getMessage(), ['upsName' => $upsName, 'variableName' => $varName]);
        }
        // return $variableDescription;
    }
    
    /**
     * Gets the command description.
     *
     * @param  string $upsName The UPS name.
     * @param  string $commandName The command name.
     * @return string The command description.
     * @throws NutException If NUT protocol error occurs. 
     * @throws IOException If an error occurs in write() or readCharsUntilNewLine().
     */
    public function getCommandDescription(string $upsName, string $commandName): string
    {
        $this->write('GET CMDDESC '.$upsName.' '.$commandName);
        try {
            $commandDescription = $this->handleSingleLineOutput();
        } catch(NutException $e) {
            throw new NutException($e->getMessage(), ['upsName' => $upsName, 'commandName' => $commandName]);
        }   
        return $commandDescription;
    }

    //TODO: I am not sure if I should supply extra args for these NutExceptions
    /**
     * Gets the status of the tracking feature.
     *
     * @return string "ON" or "OFF"
     * @throws NutException If a NUT protocol error occurs.
     * @throws IOException If an error occurs in write() or readCharsUntilNewLine().
     */
    public function getTrackingStatus(): string
    {
        $this->write('GET TRACKING');
        $res = $this->readCharsUntilNewLine();
        $validResponse = match ($res) {
            'ON', 'OFF'=> true,
            default => false,
        };
        if($validResponse) {
            return $res;
        } else {
            throw new NutException(explode(" ", $res)[1]);
        }
    }

    //TODO: I am not sure if I should supply extra args for these NutExceptions
    /**
     * Gets the execution status of a setvar or a command.
     *
     * @param  string $id The variable or command id. Defaults to an empty string.
     * @return string "PENDING" or "SUCCESS"
     * @throws NutException If a NUT protocol error occurs.
     * @throws IOException If an error occurs in write() or readCharsUntilNewLine().
     */
    public function getSetVarOrCommandStatus(string $id = ""): string
    {
        $this->write('GET TRACKING '.$id);
        $res = $this->readCharsUntilNewLine();
        $validResponse = match ($res) {
            'PENDING', 'SUCCESS' => true,
            default => false,
        };
        if($validResponse) {
            return $res;
        } else {
            throw new NutException(explode(" ", $res)[1]);
        }
    }
    
    /**
     * Gets the UPSes connected to the NUT server.
     *
     * @return array The UPSes name and description.
     * @throws IOException If an error occurs in write() or readLinesUntil().
     * @throws NutException If a NUT protocol error occurs in handleMultipleLineOutput().
     */
    public function getUpsList(): array
    {
        $this->write('LIST UPS');
        $this->readLinesUntil('BEGIN LIST UPS\n');
        $linesArr = $this->handleMultipleLinesOutput('END LIST UPS\n', true);
        return $this->convertMultipleLinesOutputArrIntoNamedKeysArr($linesArr, ['upsName', 'upsDescription']);
    }
    
    /**
     * Gets the variables of the UPS.
     *
     * @param  string $upsName The UPS name.
     * @return array The variables name and value.
     * @throws NutException If a NUT protocol error occurs.
     * @throws IOException If an error occurs in write() or readLinesUntil().
     */
    public function getUpsVars(string $upsName): array
    {
        $this->write('LIST VAR '.$upsName);
        try {
            $this->readLinesUntil('BEGIN LIST VAR '.$upsName.'\n');
            $linesArr = $this->handleMultipleLinesOutput('END LIST VAR '.$upsName.'\n', true);
        } catch(NutException $e) {
            throw new NutException($e->getMessage(), ['upsName' => $upsName]);
        }
        return $this->convertMultipleLinesOutputArrIntoNamedKeysArr($linesArr, ['variableName', 'variableValue']);
    }
    
    /**
     * Gets writable variables of the UPS.
     *
     * @param  string $upsName The UPS name.
     * @return array The variables name and value.
     * @throws NutException If a NUT protocol error occurs.
     * @throws IOException If an error occurs in write() or readLinesUntil().
     */
    public function getUpsRwVars(string $upsName): array 
    {
        $this->write('LIST RW '.$upsName);
        try {
            $this->readLinesUntil('BEGIN LIST RW '.$upsName.'\n');
            $linesArr = $this->handleMultipleLinesOutput('END LIST RW '.$upsName.'\n', true);
        } catch(NutException $e) {
            throw new NutException($e->getMessage(), ['upsName' => $upsName]);
        }
        return $this->convertMultipleLinesOutputArrIntoNamedKeysArr($linesArr, ['variableName', 'variableValue']);
    }
    
    /**
     * Gets the commands of the UPS.
     *
     * @param  string $upsName The UPS name.
     * @return array The commands.
     * @throws NutException If a NUT protocol error occurs.
     * @throws IOException If an error occurs in write() or readLinesUntil().
     */
    public function getUpsCommands(string $upsName): array
    {
        $this->write('LIST CMD '.$upsName);
        try {
            $this->readLinesUntil('BEGIN LIST CMD '.$upsName.'\n');
            $linesArr = $this->handleMultipleLinesOutput('END LIST CMD '.$upsName.'\n');
        } catch(NutException $e) {
            throw new NutException($e->getMessage(), ['upsName' => $upsName]);
            
        }
        return $this->convertMultipleLinesOutputArrIntoNamedKeysArr($linesArr, ['commandName']);
    }
    
    /**
     * Gets the enum of a variable.
     *
     * @param  string $upsName The UPS name.
     * @param  string $varName The variable name.
     * @return array The variable enums.
     * @throws NutException If a NUT protocol error occurs.
     * @throws IOException If an error occurs in write() or readLinesUntil().
     */
    public function getVarEnums(string $upsName, string $varName): array
    {
        $this->write('LIST ENUM '.$upsName.' '.$varName);
        try {
            $this->readLinesUntil('BEGIN LIST ENUM '.$upsName.' '.$varName.'\n');
            $linesArr = $this->handleMultipleLinesOutput('END LIST ENUM '.$upsName.' '.$varName.'\n');
        } catch(NutException $e) {
            throw new NutException($e->getMessage(), ['upsName' => $upsName, 'variableName' => $varName]);
        }
        return $this->convertMultipleLinesOutputArrIntoNamedKeysArr($linesArr, ['variableEnum']);
    }
    
    /**
     * Gets the range of a variable.
     *
     * @param  string $upsName The UPS name.
     * @param  string $varName The variable name.
     * @return array The variable minimum and maximum value.
     * @throws NutException If a NUT protocol error occurs.
     * @throws IOException If an error occurs in write() or readLinesUntil().
     */
    public function getVarRanges(string $upsName, string $varName): array
    {
        $this->write('LIST RANGE '.$upsName.' '.$varName);
        try {
            $this->readLinesUntil('BEGIN LIST RANGE '.$upsName.' '.$varName.'\n');
            $linesArr = $this->handleMultipleLinesOutput('END LIST RANGE '.$upsName.' '.$varName.'\n', true);
        } catch(NutException $e) {
            throw new NutException($e->getMessage(), ['upsName' => $upsName, 'variableName' => $varName]);
        }
        return $this->convertMultipleLinesOutputArrIntoNamedKeysArr($linesArr, ['variableMinValue', 'variableMaxValue']);
    }

    //not sure why in nut docs the arg is device_name and not upsname    
    /**
     * Gets the clients connected to the UPS.
     *
     * @param  string $upsName The UPS name.
     * @return array The IP addresses of the clients.
     * @throws NutException If a NUT protocol error occurs.
     * @throws IOException If an error occurs in write() or readLinesUntil().
     */
    public function getUpsClients(string $upsName): array 
    {
        $this->write('LIST CLIENT '.$upsName);
        try {
            $this->readLinesUntil('BEGIN LIST CLIENT '.$upsName.'\n');
            $linesArr = $this->handleMultipleLinesOutput('END LIST CLIENT '.$upsName.'\n');
        } catch(NutException $e) {
            throw new NutException($e->getMessage(), ['upsName' => $upsName]);
        }
        return $this->convertMultipleLinesOutputArrIntoNamedKeysArr($linesArr, ['clientIPAddress']);
    }
    
    /**
     * Sets a variable.
     *
     * @param  string $upsName The UPS name.
     * @param  string $varName The variable name.
     * @param  string|int|float $value The value to set.
     * @return string "OK" or "OK TRACKING [id]" if successful.
     * @throws NutException If a NUT protocol error occurs.
     * @throws IOException If an error occurs in write() or readCharsUntilNewLine().
     */
    public function setUpsVar(string $upsName, string $varName, string|int|float $value): string
    {
        $this->write('SET VAR '.$upsName.' '.$varName.' '.$value);
        try {
            $output = $this->handleSingleLineOutput(0);
        } catch(NutException $e) {
            throw new NutException($e->getMessage(), ['upsName' => $upsName, 'variableName' => $varName, 'value' => $value]);
        }
        return $output;
    }
    
    /**
     * Enables tracking of setvar or commands execution status.
     *
     * @return string "OK" if successful.
     * @throws IOException If an error occurs in write() or readCharsUntilNewLine().
     * @throws NutException If a NUT protocol error occurs in handleSingleLineOutput().
     */
    public function enableTracking(): string
    {
        $this->write('SET TRACKING ON');
        return $this->handleSingleLineOutput();
    }
        
    /**
     * Disables tracking of setvar or commands execution status.
     *
     * @return string "OK" if successful.
     * @throws IOException If an error occurs in write() or readCharsUntilNewLine().
     * @throws NutException If a NUT protocol error occurs in handleSingleLineOutput().
     */
    public function disableTracking(): string
    {
        $this->write('SET TRACKING OFF');
        return $this->handleSingleLineOutput();
    }
    
    /**
     * Sends a command to an UPS.
     *
     * @param  string $upsName The UPS name.
     * @param  string $commandName The command name.
     * @param  string|int|float $commandParam The command parameter. Defaults to an empty string.
     * @return string "OK" or "OK TRACKING [id]" if successful.
     * @throws NutException If a NUT protocol error occurs.
     * @throws IOException If an error occurs in write() or readCharsUntilNewLine().
     */
    public function runUpsCommand(string $upsName, string $commandName, string|int|float $commandParam = ""): string
    {
        if($commandParam) {
            $this->write('INSTCMD '.$upsName.' '.$commandName.' '.$commandParam);
        }else {
            $this->write('INSTCMD '.$upsName.' '.$commandName);
        }
        try {
            $output = $this->handleSingleLineOutput(0);
        } catch(NutException $e) {
            throw new NutException($e->getMessage(), ['upsName' => $upsName, 'commandName' => $commandName, 'commandParam' => $commandParam]);
        }
        return $output;
    }

    /**
     * Establishes a login session with the UPS.
     * Note: This is like upsmon. Requires "upsmon secondary" or "upsmon primary" in upsd.users.
     * 
     * @param  string $upsName The UPS name.
     * @return string "OK" if successful.
     * @throws NutException If a NUT protocol error occurs.
     * @throws IOException If an error occurs in write() or readCharsUntilNewLine().
     */
    public function login(string $upsName): string
    {
        $this->write('LOGIN '.$upsName);
        try {
            $output = $this->handleSingleLineOutput();
        } catch(NutException $e) {
            throw new NutException($e->getMessage(), ['upsName' => $upsName]);
        }
        return $output;
    }

    /**
     * Disconnects from the NUT server.
     *
     * @return string "OK Goodbye" or "Goodbye..." if successful.
     * @throws IOException If an error occurs in write() or readCharsUntilNewLine().
     * @throws NutException If a NUT protocol error occurs in handleSingleLineOutput().
     */
    public function disconnect(): string
    {
        $this->write('LOGOUT');
        return $this->handleSingleLineOutput(0);
    }
    
    /**
     * Checks if current user has access to primary-mode functions.
     *
     * @param  string $upsName The UPS name.
     * @return string "OK PRIMARY-GRANTED" or "OK MASTER-GRANTED" if successful.
     * @throws NutException If a NUT protocol error occurs.
     * @throws IOException If an error occurs in write() or readCharsUntilNewLine().
     */
    public function checkIfPrimaryPrivileges(string $upsName): string
    {
        $nutProtocolVersion = $this->getNetworkProtocolVersion();
        if((float)$nutProtocolVersion >= 1.3) {
            $this->write('PRIMARY '.$upsName);
        } else {
            $this->write('MASTER '.$upsName);
        }
        try {
            return $this->handleSingleLineOutput(0);
        } catch(NutException $e) {
            throw new NutException($e->getMessage(), ['upsName' => $upsName]);
        }
    }
    
    /**
     * Sets the UPS in FSD mode.
     * Note: This requires "upsmon primary" in upsd.users, or "FSD" action granted in upsd.users
     * 
     * @param  string $upsName The UPS name.
     * @return string "OK FSD-SET" if successful.
     * @throws NutException If a NUT protocol error occurs.
     * @throws IOException If an error occurs in write() or readCharsUntilNewLine().
     */
    public function FSD(string $upsName): string
    {
        $this->write('FSD '.$upsName);
        try {
            return $this->handleSingleLineOutput(0);
        } catch(NutException $e) {
            throw new NutException($e->getMessage(), ['upsName' => $upsName]);
        }
    }
    
    /**
     * Sets the NUT server to TLS mode.
     * Note: You must also change to TLS mode the client or the connection will be useless.
     * 
     * @return string "OK STARTTLS" if successful.
     * @throws IOException If an error occurs in write() or readCharsUntilNewLine().
     * @throws NutException If a NUT protocol error occurs in handleSingleLineOutput().
     */
    public function setTlsOnNutServer(): string
    {
        $this->write('STARTTLS');
        return $this->handleSingleLineOutput(0);
        //TODO: evaluate At this point a new connection with tls should be opened by the client
    }
    
    /**
     * Gets the commands supported by this NUT server.
     *
     * @return string The commands.
     * @throws IOException If an error occurs in write() or readCharsUntilNewLine().
     * @throws NutException If a NUT protocol error occurs in handleSingleLineOutput().
     */
    public function help(): string
    {
        $this->write('HELP');
        return $this->handleSingleLineOutput(0);
    }
    
    /**
     * Gets the version of the NUT server currently in use.
     *
     * @return string "Network UPS Tools upsd [version number] - <http://www.networkupstools.org/>"
     * @throws IOException If an error occurs in write() or readCharsUntilNewLine().
     * @throws NutException If a NUT protocol error occurs in handleSingleLineOutput().
     */
    public function getNutServerVersion(): string
    {
        $this->write('VER');
        return $this->handleSingleLineOutput(0);
    }
    
    /**
     * Gets the version of the network protocol currently in use.
     *
     * @return string The version number.
     * @throws IOException If an error occurs in write() or readCharsUntilNewLine().
     * @throws NutException If a NUT protocol error occurs in handleSingleLineOutput().
     */
    public function getNetworkProtocolVersion(): string
    {
        $this->write('NETVER');
        return $this->handleSingleLineOutput(0);
    }
}   
