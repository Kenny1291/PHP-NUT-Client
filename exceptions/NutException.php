<?php

namespace PhpNutClient\Exceptions;

class NutException extends \Exception
{
    private array $descriptions = [
        'ACCESS-DENIED' => 'The host and/or authentication details (username, password) are not sufficient to execute the requested command.',
        'UNKNOWN-UPS' => 'The UPS: {upsName} in the request is not known to upsd. This usually means that it did not match anything in ups.conf.',
        'VAR-NOT-SUPPORTED' => 'The UPS: {upsName}, does not support the variable: {variableName}',
        'CMD-NOT-SUPPORTED' => 'The UPS: {upsName} does not support the command: {commandName}.',
        'INVALID-ARGUMENT' => 'The argument: {commandParam} is not recognized for the command: {commandName} or is otherwise invalid in this context.',
        'INSTCMD-FAILED' => 'upsd failed to deliver the command: {commandName} request to the driver. This typically indicates a dead or broken driver.',
        //this could be more specific, but SET request can be very different
        'SET-FAILED' => 'upsd failed to deliver the set request to the driver. This typically indicates a dead or broken driver.', 
        //could be more clear
        'READONLY' => 'The requested variable: {variableName} is not writable.',
        //could add command name
        'TOO-LONG' => 'The requested value: {value} in a SET command is too long',
        'FEATURE-NOT-SUPPORTED' => 'This instance of upsd does not support the requested feature.',
        'FEATURE-NOT-CONFIGURED' => 'This instance of upsd has not been configured properly to allow the requested feature to operate.',
        'ALREADY-SSL-MODE' => 'TLS/SSL mode is already enabled on this connection, so upsd cannot start it again.',
        'DRIVER-NOT-CONNECTED' => 'upsd cannot perform the requested command: {commandName}, since the driver for UPS: {upsName} is not connected. This usually means that the driver is not running, or if it is, the ups.conf is misconfigured.',
        'DATA-STALE' => 'upsd is connected to the driver for UPS: {upsName}, but that driver is not providing regular updates or has specifically marked the data as stale. upsd refuses to provide variables on stale units to avoid false readings.',
        'ALREADY-LOGGED-IN' => 'A LOGIN has already been sent for UPS: {upsName}. There is a limit of one LOGIN record per connection.',
        'INVALID-PASSWORD' => 'The PASSWORD: {password} it is invalid.',
        'ALREADY-SET-PASSWORD' => 'PASSWORD already set and another cannot be set.',
        'INVALID-USERNAME' => 'The USERNAME: {username} it is invalid.',
        'ALREADY-SET-USERNAME' => 'USERNAME already set and another cannot be set.',
        'USERNAME-REQUIRED' => 'The requested command requires a username for authentication, but it is not set.',
        'PASSWORD-REQUIRED' => 'The requested command requires a passname for authentication, but it is not set.',
        'UNKNOWN-COMMAND' => 'upsd does not recognize the requested command: {commandName}.',
        'INVALID-VALUE' => 'The value: {value} specified in the request is not valid.',
    ];

    public function __construct(string $message, array $extraArgs = [])
    {
        $description = $this->descriptions[$message] ?? null;
        if($description) {
            foreach ($extraArgs as $extraArgKey => $extraArgValue) {
                $description = str_replace("{".$extraArgKey."}", $extraArgValue, $description);
            }
            $message = "$message: $description";
        }
        parent::__construct($message);
    }
}