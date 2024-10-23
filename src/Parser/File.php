<?php

namespace MagicTest\MagicTest\Parser;

use Illuminate\Support\Collection;
use MagicTest\MagicTest\Exceptions\InvalidFileException;
use MagicTest\MagicTest\Parser\Printer\PrettyPrinter;
use MagicTest\MagicTest\Parser\Visitors\GrammarBuilderVisitor;
use MagicTest\MagicTest\Parser\Visitors\MagicRemoverVisitor;
use PhpParser\Lexer;
use PhpParser\Node;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\CloningVisitor;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\Parser;
use PhpParser\ParserFactory;

class File
{
    protected Parser $parser;

    protected Lexer $lexer;

    protected array $ast;

    protected array $initialStatements;

    protected array $newStatements;

    protected ?Closure $closure;

    public function __construct(string $content, string $method)
    {
        $this->lexer = new \PhpParser\Lexer\Emulative();

        $this->parser = (new ParserFactory)->createForNewestSupportedVersion();
        $this->ast = (array) $this->parser->parse($content)[0];
        $this->initialStatements = $this->ast['stmts'];
        $this->newStatements = $this->getNewStatements();
        $this->closure = $this->getClosure($method);
    }

    public static function fromContent(string $content, string $method)
    {
        return new static($content, $method);
    }

    public function addMethods(Collection $grammar): string
    {
        $traverser = new NodeTraverser;
        $traverser->addVisitor(new ParentConnectingVisitor);
        $traverser->addVisitor(new GrammarBuilderVisitor($grammar));
        $traverser->traverse($this->closure->stmts);

        return $this->print();
    }

    public function finish(): string
    {
        $traverser = new NodeTraverser;
        $traverser->addVisitor(new ParentConnectingVisitor);
        $traverser->addVisitor(new MagicRemoverVisitor);
        $traverser->traverse($this->closure->stmts);

        return $this->print();
    }

    protected function print(): string
    {
        return (new PrettyPrinter)->printFormatPreserving(
            $this->newStatements,
            $this->initialStatements,
            $this->parser->getTokens(),
        );
    }

    /**
     * Clone the statements to leave the starting ones untouched so they can be diffed by the printer later.
     *
     * @return array
     */
    protected function getNewStatements(): array
    {
        $traverser = new NodeTraverser;
        $traverser->addVisitor(new CloningVisitor);

        return $traverser->traverse($this->initialStatements);
    }

    protected function getClassMethod(string $method): ?ClassMethod
    {
        return (new NodeFinder)->findFirst(
            $this->newStatements,
            fn(Node $node) => $node instanceof ClassMethod && $node->name->__toString() === $method
        );
    }

    /**
     * Finds the first valid method call inside a class method.
     * A valid method call is one that is both a MethodCall instance and
     * that also has a node that is a Identifier and ahs the name magic.
     *
     * @param \PhpParser\Node\Stmt\ClassMethod $classMethod
     * @return \PhpParser\Node\Expr\MethodCall|null
     */
    protected function getMethodCall(ClassMethod $classMethod): ?MethodCall
    {
        return (new NodeFinder)->findFirst($classMethod->stmts, function (Node $node) {
            return $node instanceof MethodCall &&
                (new NodeFinder)->find(
                    $node->args,
                    fn(Node $node) => $node instanceof Identifier && $node->name === 'magic'
                );
        });
    }

    /**
     * Get the closure object
     *
     * @param string $method
     * @throws \MagicTest\MagicTest\Exceptions\InvalidFileException
     * @return Closure
     */
    protected function getClosure(string $method): ?Closure
    {
        $classMethod = $this->getClassMethod($method);
        throw_if(!$classMethod, new InvalidFileException("Could not find method {$method} on file."));

        $methodCall = $this->getMethodCall($classMethod);
        throw_if(!$methodCall, new InvalidFileException("Could not find the browse call on file."));

        $closure = (new NodeFinder)->findFirst($methodCall->args, fn(Node $node) => $node instanceof Closure);
        throw_if(!$closure, new InvalidFileException("Could not find the closure on file."));

        return $closure;
    }
}
