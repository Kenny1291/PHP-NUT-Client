<?php

namespace PhpNutClient\Tests;

function getPrivateMethod(string $method, $classInstance): \ReflectionMethod
{
    $reflector = new \ReflectionClass($classInstance);
    $method = $reflector->getMethod($method);
    $method->setAccessible(true);
    return $method;
}

function getPrivateOrProtectedPropertyValue(string $property, $classInstance)
{
    $reflector = new \ReflectionClass($classInstance);
    $property = $reflector->getProperty($property);
    $property->setAccessible(true);
    return $property->getValue($classInstance);
}