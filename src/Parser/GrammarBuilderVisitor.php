<?php 
namespace MagicTest\MagicTest\Parser;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\BuilderFactory;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Node\Expr\MethodCall;

class GrammarBuilderVisitor extends NodeVisitorAbstract
{
    public function __construct($grammar)
    {
        $this->grammar = $grammar;
    }

    public function leaveNode(Node $node)
    {
        if ($node instanceof MethodCall && $node->name->__toString() === 'magic') {
            $this->buildNodes($node);
        }  
    }

    public function buildNodes($node)
    {
        $grammar = $this->grammar
                        ->map(function($grammar) {
                            return [$grammar, $grammar->pause()];
                        })->flatten()->filter();

        foreach ($grammar as $gram) {
            $arguments = $this->buildArguments($gram->arguments());
            $previousNode = clone $node;
            $node->var = $call = new MethodCall(
                $previousNode->var,
                $gram->nameForParser(),
                $arguments
            );
        }
    }

    public function buildArguments($arguments)
    {
        return collect($arguments)
                ->map(fn($argument) => 
                    new Arg($argument)
                )
                ->toArray();
    }
}