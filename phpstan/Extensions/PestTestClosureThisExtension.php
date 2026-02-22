<?php

declare(strict_types=1);

namespace Signals\PHPStan;

use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Reflection\ParameterReflection;
use PHPStan\Type\FunctionParameterClosureThisExtension;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

/**
 * Resolves $this inside Pest test closures to PestTestCase.
 *
 * Pest binds closures passed to test(), it(), beforeEach(), etc.
 * to the TestCase configured via pest()->extend(), with the closure
 * scope set to the TestCase class — making protected methods (mock,
 * partialMock, spy) callable as if public. This extension returns
 * a PestTestCase that extends Tests\TestCase with those methods
 * exposed as public.
 */
final class PestTestClosureThisExtension implements FunctionParameterClosureThisExtension
{
    private const PEST_FUNCTIONS = [
        'test',
        'it',
        'beforeEach',
        'afterEach',
        'beforeAll',
        'afterAll',
    ];

    public function isFunctionSupported(FunctionReflection $functionReflection, ParameterReflection $parameter): bool
    {
        return in_array($functionReflection->getName(), self::PEST_FUNCTIONS, true);
    }

    public function getClosureThisTypeFromFunctionCall(
        FunctionReflection $functionReflection,
        FuncCall $functionCall,
        ParameterReflection $parameter,
        Scope $scope,
    ): Type {
        return new ObjectType(PestTestCase::class);
    }
}
